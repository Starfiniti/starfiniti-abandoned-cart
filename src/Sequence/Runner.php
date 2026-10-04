<?php
/**
 * Sends due e-mails and cleans up.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Sequence;

use RuntimeException;
use Starfiniti\AbandonedCart\Coupons\CouponService;
use Starfiniti\AbandonedCart\Email\Context;
use Starfiniti\AbandonedCart\Email\Mailer;
use Starfiniti\AbandonedCart\Email\Renderer;
use Starfiniti\AbandonedCart\Settings;
use Starfiniti\AbandonedCart\Store\Carts;
use Starfiniti\AbandonedCart\Store\Contacts;
use Starfiniti\AbandonedCart\Store\Events;
use Starfiniti\AbandonedCart\Support\Emails;
use Starfiniti\AbandonedCart\Support\Logger;
use Starfiniti\AbandonedCart\Support\Secret;
use Throwable;

/**
 * Processes the recurring tick.
 */
final class Runner {

	/**
	 * Transient used as a lock against overlapping ticks.
	 */
	private const LOCK = 'sfac_tick_lock';

	/**
	 * Maximum carts per tick.
	 */
	private const BATCH = 25;

	/**
	 * Run one tick.
	 *
	 * @param int|null $now Current Unix timestamp; tests pass a fixed time.
	 */
	public static function tick( ?int $now = null ): void {
		if ( get_transient( self::LOCK ) ) {
			return;
		}

		set_transient( self::LOCK, 1, 240 );
		$now = $now ?? time();

		try {
			self::cleanup( $now );

			if ( Settings::MODE_OFF !== Settings::mode() ) {
				foreach ( Carts::due( $now, self::BATCH ) as $row ) {
					self::process_safely( $row, $now );
				}

				self::payment_checks( $now );
			}
		} catch ( Throwable $error ) {
			Logger::exception( 'Abandoned cart tick failed.', $error );
		} finally {
			delete_transient( self::LOCK );
		}
	}

	/**
	 * Process one cart and record failures without stopping the tick.
	 *
	 * @param array<string, mixed> $row Cart row.
	 * @param int                  $now Current Unix timestamp.
	 */
	private static function process_safely( array $row, int $now ): void {
		try {
			self::process( $row, $now );
		} catch ( Throwable $error ) {
			Logger::exception( 'An abandoned cart e-mail could not be sent.', $error );
			$attempts = (int) $row['attempts'] + 1;
			$failed   = $attempts >= 3;

			Carts::update(
				(int) $row['id'],
				array(
					'attempts'     => $attempts,
					'status'       => $failed ? 'error' : Carts::STATUS_ACTIVE,
					'next_send_at' => $failed ? null : Carts::now( $now + 1800 ),
				)
			);
			Events::add( (int) $row['id'], 'error', (int) $row['step'] + 1, (string) $row['variant'] );
		}
	}

	/**
	 * Send the next e-mail of one cart or finish it.
	 *
	 * @param array<string, mixed> $row Cart row.
	 * @param int                  $now Current Unix timestamp.
	 * @throws RuntimeException When the mail system rejects the e-mail.
	 */
	public static function process( array $row, int $now ): void {
		$id    = (int) $row['id'];
		$email = (string) $row['email'];
		$hash  = (string) $row['email_hash'];

		if ( ! Settings::allows_email( $email ) || Contacts::is_opted_out( $hash ) ) {
			self::finish( $id, 'stopped' );
			return;
		}

		$order_id = self::ordered_since( $email, (string) $row['created_at'] );

		if ( $order_id > 0 ) {
			Carts::update(
				$id,
				array(
					'status'       => (int) $row['step'] > 0 ? 'recovered' : 'ordered',
					'order_id'     => $order_id,
					'next_send_at' => null,
				)
			);
			return;
		}

		$settings = Settings::all();
		$timezone = wp_timezone();
		$shifted  = Schedule::outside_quiet_hours( $now, $timezone, (int) $settings['quiet_start'], (int) $settings['quiet_end'], wp_rand( 0, 900 ) );

		if ( $shifted > $now ) {
			Carts::update( $id, array( 'next_send_at' => Carts::now( $shifted ) ) );
			return;
		}

		$segment = (string) $row['segment'];
		$step    = (int) $row['step'] + 1;
		$def     = Sequences::step( $segment, $step );

		if ( null === $def ) {
			self::finish( $id, 'done' );
			return;
		}

		$context = json_decode( (string) $row['context'], true );
		$context = is_array( $context ) ? $context : array();

		if ( ! empty( $def['skip_if_date_passed'] ) && Schedule::is_past_date( (string) ( $context['delivery_date'] ?? '' ), $timezone, $now ) ) {
			self::finish( $id, 'done' );
			return;
		}

		if ( ! empty( $def['coupon'] ) && '' === (string) $row['coupon_code'] ) {
			$coupon = CouponService::create( $row, $now );

			if ( null !== $coupon ) {
				$coupon_data = array(
					'coupon_id'         => $coupon['id'],
					'coupon_code'       => $coupon['code'],
					'coupon_expires_at' => Carts::now( $coupon['expires'] ),
				);
				$row         = array_merge( $row, $coupon_data );
				Carts::update( $id, $coupon_data );
				Events::add( $id, 'coupon_created', $step, (string) $row['variant'] );
			}
		}

		if ( ! empty( $def['requires_coupon'] ) && ( '' === (string) $row['coupon_code'] || ! CouponService::is_usable( (string) $row['coupon_code'] ) ) ) {
			self::finish( $id, 'done' );
			return;
		}

		$message = Renderer::render( Context::for_cart( $row, $step, $def ) );

		if ( ! Mailer::send( $email, $message, $row ) ) {
			throw new RuntimeException( 'wp_mail() did not accept the reminder.' );
		}

		Events::add( $id, 'sent', $step, (string) $row['variant'] );

		if ( 1 === $step ) {
			Contacts::touch( $hash, 'last_sequence_at' );
		}

		$next = self::next_time( $segment, $step + 1, $row, $now );

		Carts::update(
			$id,
			array(
				'step'         => $step,
				'attempts'     => 0,
				'status'       => null === $next ? 'done' : Carts::STATUS_ACTIVE,
				'next_send_at' => null === $next ? null : Carts::now( $next ),
			)
		);
	}

	/**
	 * Return when a step should be sent, or null when the sequence ends.
	 *
	 * @param string               $segment Segment name.
	 * @param int                  $step    Step number.
	 * @param array<string, mixed> $row     Cart row.
	 * @param int                  $now     Current Unix timestamp.
	 */
	public static function next_time( string $segment, int $step, array $row, int $now ): ?int {
		$def = Sequences::step( $segment, $step );

		if ( null === $def ) {
			return null;
		}

		if ( 'coupon_last_day' === ( $def['delay'] ?? '' ) ) {
			$expires = strtotime( (string) ( $row['coupon_expires_at'] ?? '' ) . ' UTC' );

			if ( '' === (string) ( $row['coupon_code'] ?? '' ) || false === $expires ) {
				return null;
			}

			return Schedule::last_day( $expires, wp_timezone(), (int) ( $def['hour'] ?? 10 ), $now );
		}

		$activity = strtotime( (string) $row['last_activity_at'] . ' UTC' );

		return max( $now + 600, Schedule::after_activity( false === $activity ? $now : $activity, (int) ( $def['minutes'] ?? 60 ) ) );
	}

	/**
	 * Return the id of an order placed with this address after the cart was captured.
	 *
	 * @param string $email      Address.
	 * @param string $created_at Cart creation time (UTC).
	 */
	private static function ordered_since( string $email, string $created_at ): int {
		$created = strtotime( $created_at . ' UTC' );

		if ( false === $created || ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'date_created'  => '>' . $created,
				'status'        => array( 'pending', 'processing', 'on-hold', 'completed' ),
				'limit'         => 1,
				'return'        => 'ids',
			)
		);

		if ( ! is_array( $orders ) || array() === $orders ) {
			return 0;
		}

		$first = reset( $orders );

		return $first instanceof \WC_Abstract_Order ? $first->get_id() : ( is_numeric( $first ) ? (int) $first : 0 );
	}

	/**
	 * Remove expired coupons and old data.
	 *
	 * @param int $now Current Unix timestamp.
	 */
	private static function cleanup( int $now ): void {
		CouponService::janitor( $now );

		$cutoff = $now - (int) Settings::get( 'retention_days' ) * DAY_IN_SECONDS;
		Carts::delete_older_than( $cutoff );
		Events::delete_older_than( $cutoff );
	}

	/**
	 * Send one "payment failed" e-mail for orders that stayed unpaid.
	 *
	 * @param int $now Current Unix timestamp.
	 */
	private static function payment_checks( int $now ): void {
		$settings = Settings::all();

		if ( empty( $settings['payment_failed_enabled'] ) || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$timezone = wp_timezone();

		if ( Schedule::outside_quiet_hours( $now, $timezone, (int) $settings['quiet_start'], (int) $settings['quiet_end'] ) > $now ) {
			return;
		}

		foreach ( Carts::payment_checks( $now - (int) $settings['payment_failed_minutes'] * 60, $now - DAY_IN_SECONDS ) as $row ) {
			$checked = 1;

			try {
				$order = wc_get_order( (int) $row['order_id'] );

				if ( $order instanceof \WC_Order && self::needs_payment_reminder( $order ) ) {
					$email = Emails::normalize( (string) $order->get_billing_email() );

					if ( '' !== $email && Settings::allows_email( $email ) && ! Contacts::is_opted_out( Secret::email_hash( $email ) ) ) {
						$message = Renderer::render( Context::for_payment_failed( $row, $order ) );

						if ( Mailer::send( $email, $message, $row ) ) {
							Events::add( (int) $row['id'], 'payment_failed_sent', 0, (string) $row['variant'] );
							$checked = 2;
						}
					}
				}
			} catch ( Throwable $error ) {
				Logger::exception( 'The payment reminder could not be processed.', $error );
			}

			Carts::update( (int) $row['id'], array( 'payment_check' => $checked ) );
		}
	}

	/**
	 * Check whether an order is still waiting for an online payment.
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function needs_payment_reminder( \WC_Order $order ): bool {
		if ( $order->is_paid() || ! in_array( $order->get_status(), array( 'pending', 'failed' ), true ) ) {
			return false;
		}

		/**
		 * Filters payment methods that never get a "payment failed" e-mail (offline methods).
		 *
		 * @param list<string> $methods Payment method ids.
		 */
		$offline = apply_filters( 'sfac_offline_payment_methods', array( 'bacs', 'cod', 'cheque' ) );

		return ! in_array( $order->get_payment_method(), is_array( $offline ) ? $offline : array(), true );
	}

	/**
	 * End a sequence.
	 *
	 * @param int    $id     Cart row identifier.
	 * @param string $status Final status.
	 */
	private static function finish( int $id, string $status ): void {
		Carts::update(
			$id,
			array(
				'status'       => $status,
				'next_send_at' => null,
			)
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
