<?php
/**
 * Previews and test e-mails.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Email;

use Starfiniti\AbandonedCart\Sequence\Schedule;
use Starfiniti\AbandonedCart\Sequence\Sequences;
use Starfiniti\AbandonedCart\Store\Carts;

/**
 * Builds sample e-mails from real products without touching any customer data.
 */
final class Preview {

	/**
	 * Available scenarios.
	 */
	public const SCENARIOS = array( 'full', 'minimal', 'calm', 'payment_failed' );

	/**
	 * Render a sample e-mail.
	 *
	 * @param string $scenario full, minimal, calm or payment_failed.
	 * @param string $language Two-letter code.
	 * @param int    $step     Step number.
	 * @return array{subject: string, preheader: string, html: string, text: string}
	 */
	public static function render( string $scenario, string $language, int $step ): array {
		$scenario = in_array( $scenario, self::SCENARIOS, true ) ? $scenario : 'full';
		$row      = self::sample_row( $scenario, $language, $step );

		if ( 'payment_failed' === $scenario && class_exists( 'WC_Order' ) ) {
			$order = new \WC_Order();
			$order->set_billing_first_name( self::sample_name( $language ) );
			$order->set_currency( (string) $row['currency'] );
			$order->set_total( (string) (float) $row['items_total'] );

			return Renderer::render( Context::for_payment_failed( $row, $order ) );
		}

		$def = Sequences::step( (string) $row['segment'], $step ) ?? Sequences::step( 'default', 1 ) ?? array();

		return Renderer::render( Context::for_cart( $row, $step, $def ) );
	}

	/**
	 * Send every sample of a language to a test address.
	 *
	 * @param string $email    Test address.
	 * @param string $language Two-letter code.
	 * @return int Number of e-mails accepted by wp_mail().
	 */
	public static function send_test( string $email, string $language ): int {
		$sent  = 0;
		$cases = array(
			array( 'full', 1 ),
			array( 'full', 2 ),
			array( 'full', 3 ),
			array( 'minimal', 1 ),
			array( 'calm', 1 ),
			array( 'payment_failed', 1 ),
		);

		foreach ( $cases as $case ) {
			$message            = self::render( $case[0], $language, $case[1] );
			$message['subject'] = '[TEST] ' . $message['subject'];
			$row                = self::sample_row( $case[0], $language, $case[1] );

			if ( Mailer::send( $email, $message, $row ) ) {
				++$sent;
			}
		}

		return $sent;
	}

	/**
	 * Build a sample cart row.
	 *
	 * @param string $scenario Scenario.
	 * @param string $language Two-letter code.
	 * @param int    $step     Step number.
	 * @return array<string, mixed>
	 */
	public static function sample_row( string $scenario, string $language, int $step ): array {
		$now      = time();
		$timezone = wp_timezone();
		$items    = self::sample_items();
		$total    = 0.0;
		$full     = 'full' === $scenario;
		$calm     = 'calm' === $scenario;

		foreach ( $items as $item ) {
			$total += (float) $item['price'];
		}

		$context = array(
			'recipient'     => $full ? self::sample_name( $language, true ) : '',
			'delivery_date' => $full ? gmdate( 'Y-m-d', $now + 3 * DAY_IN_SECONDS ) : '',
			'card_message'  => $full ? self::sample_message( $language ) : '',
		);

		return array(
			'id'                => 0,
			'created_at'        => Carts::now( $now ),
			'email'             => 'preview@example.com',
			'email_hash'        => '',
			'locale'            => $language,
			'currency'          => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : 'EUR',
			'first_name'        => 'minimal' === $scenario ? '' : self::sample_name( $language ),
			'context'           => wp_json_encode( $context ),
			'cart'              => wp_json_encode( array( 'items' => $items ) ),
			'items_total'       => $total,
			'shipping_total'    => 0,
			'segment'           => $calm ? 'calm' : 'default',
			'variant'           => 'A',
			'status'            => Carts::STATUS_ACTIVE,
			'step'              => max( 0, $step - 1 ),
			'clicks'            => 0,
			'coupon_code'       => ! $calm && $step >= 2 ? 'SAMPLE-7KQ3MX' : '',
			'coupon_expires_at' => Carts::now( Schedule::coupon_expiry( $now, $timezone, 2, 20 ) ),
			'last_activity_at'  => Carts::now( $now ),
		);
	}

	/**
	 * Two real products, or placeholders when the store has none.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function sample_items(): array {
		$items = array();

		if ( function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products(
				array(
					'status' => 'publish',
					'limit'  => 2,
					'type'   => array( 'simple', 'variable' ),
				)
			);

			foreach ( is_array( $products ) ? $products : array() as $index => $product ) {
				if ( ! $product instanceof \WC_Product ) {
					continue;
				}

				$image   = wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_thumbnail' );
				$items[] = array(
					'key'        => 'sample-' . $index,
					'parent_key' => '',
					'product_id' => $product->get_id(),
					'quantity'   => 1,
					'name'       => $product->get_name(),
					'meta'       => '',
					'price'      => (float) wc_get_price_including_tax( $product ),
					'image'      => is_string( $image ) ? $image : '',
				);
			}
		}

		if ( array() === $items ) {
			$items[] = array(
				'key'        => 'sample-0',
				'parent_key' => '',
				'product_id' => 0,
				'quantity'   => 1,
				'name'       => __( 'Sample product', 'starfiniti-abandoned-cart' ),
				'meta'       => '',
				'price'      => 49.0,
				'image'      => '',
			);
		}

		return $items;
	}

	/**
	 * Sample first name.
	 *
	 * @param string $language  Two-letter code.
	 * @param bool   $recipient Recipient instead of customer.
	 */
	private static function sample_name( string $language, bool $recipient = false ): string {
		if ( 'sl' === $language ) {
			return $recipient ? 'Ana' : 'Maja';
		}

		return $recipient ? 'Anna' : 'Emma';
	}

	/**
	 * Sample card message.
	 *
	 * @param string $language Two-letter code.
	 */
	private static function sample_message( string $language ): string {
		return match ( $language ) {
			'sl' => 'Vse najboljše! Objem, Maja',
			'de' => 'Alles Gute! Liebe Grüße, Emma',
			default => 'Happy birthday! Love, Emma',
		};
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
