<?php
/**
 * Cart capture.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Capture;

use Starfiniti\AbandonedCart\Cart\Snapshot;
use Starfiniti\AbandonedCart\Integration\Multilingual;
use Starfiniti\AbandonedCart\Sequence\Schedule;
use Starfiniti\AbandonedCart\Sequence\Sequences;
use Starfiniti\AbandonedCart\Settings;
use Starfiniti\AbandonedCart\Store\Carts;
use Starfiniti\AbandonedCart\Store\Contacts;
use Starfiniti\AbandonedCart\Store\Events;
use Starfiniti\AbandonedCart\Support\Emails;
use Starfiniti\AbandonedCart\Support\Names;
use Starfiniti\AbandonedCart\Support\Secret;

/**
 * Saves the shopper's cart and address, keeps it in sync and stops it on order.
 */
final class CaptureService {

	/**
	 * Session key with the captured cart row id.
	 */
	public const SESSION_ID = 'sfac_cart_id';

	/**
	 * Session key with the last stored cart hash.
	 */
	public const SESSION_HASH = 'sfac_cart_hash';

	/**
	 * Session key set when the shopper ticked the consent checkbox.
	 */
	public const SESSION_CONSENT = 'sfac_consent';

	/**
	 * Days after a sequence during which an order still counts as recovered.
	 */
	public const ATTRIBUTION_DAYS = 7;

	/**
	 * Capture or update the current session cart for an address.
	 *
	 * @param string $email    Address typed by the shopper.
	 * @param string $language Shopper language.
	 * @param string $source   Capture path: store_api, classic, rest, account.
	 * @param bool   $consent  Whether the consent checkbox was ticked (checkbox mode).
	 * @return int Cart row id, or 0 when nothing was captured.
	 */
	public static function capture( string $email, string $language, string $source, bool $consent = false ): int {
		if ( Settings::MODE_OFF === Settings::mode() || ! function_exists( 'WC' ) ) {
			return 0;
		}

		$email = Emails::normalize( $email );

		if ( '' === $email || ! Settings::allows_email( $email ) ) {
			return 0;
		}

		$cart    = WC()->cart;
		$session = WC()->session;

		if ( ! $cart instanceof \WC_Cart || $cart->is_empty() ) {
			return 0;
		}

		if ( 'checkbox' === Settings::get( 'consent' ) ) {
			if ( $consent && is_object( $session ) ) {
				$session->set( self::SESSION_CONSENT, 1 );
			}

			if ( ! $consent && ! ( is_object( $session ) && $session->get( self::SESSION_CONSENT ) ) ) {
				return 0;
			}
		}

		$email_hash = Secret::email_hash( $email );

		if ( Contacts::is_opted_out( $email_hash ) ) {
			return 0;
		}

		$snapshot = Snapshot::from_cart( $cart );

		if ( array() === $snapshot['items'] ) {
			return 0;
		}

		$customer    = WC()->customer;
		$context     = self::context( $cart, $customer, $session );
		$session_key = is_object( $session ) && method_exists( $session, 'get_customer_id' ) ? (string) $session->get_customer_id() : '';
		$now         = time();
		$existing    = self::existing_row( $session, $session_key );
		$first_delay = (int) Settings::get( 'first_delay_minutes' );
		$language    = Multilingual::normalize( $language );

		$data = array(
			'session_key'      => substr( $session_key, 0, 64 ),
			'user_id'          => get_current_user_id(),
			'email'            => $email,
			'email_hash'       => $email_hash,
			'locale'           => $language,
			'currency'         => $snapshot['currency'],
			'first_name'       => $customer instanceof \WC_Customer ? Names::first( (string) $customer->get_billing_first_name() ) : '',
			'context'          => wp_json_encode( $context ),
			'cart'             => wp_json_encode( array( 'items' => $snapshot['items'] ) ),
			'cart_hash'        => $snapshot['hash'],
			'items_total'      => $snapshot['items_total'],
			'shipping_total'   => $snapshot['shipping_total'],
			'segment'          => self::segment( $snapshot, $context ),
			'last_activity_at' => Carts::now( $now ),
		);

		if ( null !== $existing ) {
			$cart_id = (int) $existing['id'];

			if ( 'rest' !== $source && '' !== (string) $existing['locale'] ) {
				$data['locale'] = (string) $existing['locale'];
			}

			if ( 0 === (int) $existing['step'] ) {
				$data['next_send_at'] = Carts::now( Schedule::after_activity( $now, $first_delay ) );
			}

			Carts::update_if_active( $cart_id, $data );
		} else {
			if ( Contacts::within( $email_hash, 'last_sequence_at', (int) Settings::get( 'sequence_cooldown_days' ), $now ) ) {
				return 0;
			}

			$data['variant']      = 0 === wp_rand( 0, 1 ) ? 'A' : 'B';
			$data['status']       = Carts::STATUS_ACTIVE;
			$data['step']         = 0;
			$data['next_send_at'] = Carts::now( Schedule::after_activity( $now, $first_delay ) );
			$cart_id              = Carts::insert( $data );

			if ( $cart_id <= 0 ) {
				return 0;
			}

			Events::add( $cart_id, 'captured', 0, (string) $data['variant'], array( 'source' => $source ) );
		}

		Carts::replace_others( $email_hash, $cart_id );

		if ( is_object( $session ) ) {
			$session->set( self::SESSION_ID, $cart_id );
			$session->set( self::SESSION_HASH, $snapshot['hash'] );
		}

		return $cart_id;
	}

	/**
	 * Refresh the stored cart after the shopper changed it.
	 */
	public static function sync_cart(): void {
		static $running = false;

		if ( $running || ! function_exists( 'WC' ) || Settings::MODE_OFF === Settings::mode() ) {
			return;
		}

		$session = WC()->session;
		$cart    = WC()->cart;

		if ( ! is_object( $session ) || ! $cart instanceof \WC_Cart ) {
			return;
		}

		$cart_id = (int) $session->get( self::SESSION_ID );

		if ( $cart_id <= 0 ) {
			return;
		}

		$running = true;

		try {
			$row = Carts::find( $cart_id );

			if ( null === $row || Carts::STATUS_ACTIVE !== $row['status'] ) {
				return;
			}

			if ( $cart->is_empty() ) {
				Carts::update_if_active(
					$cart_id,
					array(
						'status'       => 'emptied',
						'next_send_at' => null,
					)
				);
				Events::add( $cart_id, 'emptied', (int) $row['step'], (string) $row['variant'] );
				$session->set( self::SESSION_ID, 0 );
				return;
			}

			$snapshot = Snapshot::from_cart( $cart );

			if ( $snapshot['hash'] === (string) $session->get( self::SESSION_HASH ) || array() === $snapshot['items'] ) {
				return;
			}

			$now  = time();
			$data = array(
				'cart'             => wp_json_encode( array( 'items' => $snapshot['items'] ) ),
				'cart_hash'        => $snapshot['hash'],
				'items_total'      => $snapshot['items_total'],
				'shipping_total'   => $snapshot['shipping_total'],
				'currency'         => $snapshot['currency'],
				'last_activity_at' => Carts::now( $now ),
			);

			if ( 0 === (int) $row['step'] ) {
				$data['next_send_at'] = Carts::now( Schedule::after_activity( $now, (int) Settings::get( 'first_delay_minutes' ) ) );
			}

			Carts::update_if_active( $cart_id, $data );
			$session->set( self::SESSION_HASH, $snapshot['hash'] );
		} finally {
			$running = false;
		}
	}

	/**
	 * Stop sequences and attribute an order.
	 *
	 * @param \WC_Order $order New order.
	 */
	public static function order_placed( \WC_Order $order ): void {
		$session     = function_exists( 'WC' ) ? WC()->session : null;
		$cart_id     = is_object( $session ) ? (int) $session->get( self::SESSION_ID ) : 0;
		$session_key = is_object( $session ) && method_exists( $session, 'get_customer_id' ) ? (string) $session->get_customer_id() : '';
		$email       = Emails::normalize( (string) $order->get_billing_email() );
		$email_hash  = '' === $email ? '' : Secret::email_hash( $email );

		foreach ( Carts::open_matches( $session_key, $email_hash, $cart_id, time() - self::ATTRIBUTION_DAYS * DAY_IN_SECONDS ) as $row ) {
			$recovered = (int) $row['step'] > 0;

			Carts::update(
				(int) $row['id'],
				array(
					'status'       => $recovered ? 'recovered' : 'ordered',
					'order_id'     => $order->get_id(),
					'order_total'  => (float) $order->get_total(),
					'next_send_at' => null,
				)
			);
			Events::add( (int) $row['id'], $recovered ? 'recovered' : 'ordered', (int) $row['step'], (string) $row['variant'], array( 'order_id' => $order->get_id() ) );
		}

		if ( is_object( $session ) ) {
			$session->set( self::SESSION_ID, 0 );
		}
	}

	/**
	 * Find the active row of this session.
	 *
	 * @param object|null $session     WooCommerce session.
	 * @param string      $session_key Session key.
	 * @return array<string, mixed>|null
	 */
	private static function existing_row( ?object $session, string $session_key ): ?array {
		$cart_id = is_object( $session ) && method_exists( $session, 'get' ) ? (int) $session->get( self::SESSION_ID ) : 0;

		if ( $cart_id > 0 ) {
			$row = Carts::find( $cart_id );

			if ( null !== $row && Carts::STATUS_ACTIVE === $row['status'] ) {
				return $row;
			}
		}

		return Carts::find_active_by_session( $session_key );
	}

	/**
	 * Collect personalization data, mostly from store adapters.
	 *
	 * @param \WC_Cart    $cart     Cart.
	 * @param mixed       $customer WooCommerce customer.
	 * @param object|null $session  WooCommerce session.
	 * @return array<string, string>
	 */
	private static function context( \WC_Cart $cart, mixed $customer, ?object $session ): array {
		$defaults = array(
			'recipient'     => '',
			'delivery_date' => '',
			'card_message'  => '',
		);

		/**
		 * Filters personalization data stored with the cart.
		 *
		 * Known keys: recipient (first name of the gift recipient), delivery_date (Y-m-d),
		 * card_message (greeting card text). Adapters may add short string keys.
		 *
		 * @param array<string, string> $context  Context.
		 * @param \WC_Cart              $cart     Cart.
		 * @param mixed                 $customer WooCommerce customer.
		 * @param object|null           $session  WooCommerce session.
		 */
		$context = apply_filters( 'sfac_capture_context', $defaults, $cart, $customer, $session );
		$context = is_array( $context ) ? $context : $defaults;
		$clean   = array();

		foreach ( array_slice( $context, 0, 20, true ) as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$clean[ sanitize_key( (string) $key ) ] = mb_substr( sanitize_textarea_field( (string) $value ), 0, 500 );
		}

		$clean['recipient']     = Names::first( (string) ( $clean['recipient'] ?? '' ) );
		$clean['delivery_date'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $clean['delivery_date'] ?? '' ) ) ? (string) $clean['delivery_date'] : '';
		$clean['card_message']  = (string) ( $clean['card_message'] ?? '' );

		return $clean;
	}

	/**
	 * Pick the sequence segment.
	 *
	 * @param array<string, mixed>  $snapshot Cart snapshot.
	 * @param array<string, string> $context  Personalization data.
	 */
	private static function segment( array $snapshot, array $context ): string {
		/**
		 * Filters the sequence segment of a cart, e.g. "calm" for sensitive purchases.
		 *
		 * @param string                $segment  Segment name.
		 * @param array<string, mixed>  $snapshot Cart snapshot with items and their category ids.
		 * @param array<string, string> $context  Personalization data.
		 */
		$segment = (string) apply_filters( 'sfac_segment', 'default', $snapshot, $context );

		return Sequences::exists( $segment ) ? substr( $segment, 0, 32 ) : 'default';
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
