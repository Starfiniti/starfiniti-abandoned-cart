<?php
/**
 * Cart restore.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Cart;

use Starfiniti\AbandonedCart\Coupons\CouponService;
use Starfiniti\AbandonedCart\Support\Logger;
use Throwable;

/**
 * Rebuilds a stored cart in the current shopper session.
 */
final class Restorer {

	/**
	 * Restore a stored cart.
	 *
	 * @param array<string, mixed> $row  Cart row.
	 * @param int                  $step E-mail step the link came from.
	 * @return array{restored: int, failed: int, coupon: bool}
	 */
	public static function restore( array $row, int $step ): array {
		$result = array(
			'restored' => 0,
			'failed'   => 0,
			'coupon'   => false,
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			return $result;
		}

		$cart  = json_decode( (string) ( $row['cart'] ?? '' ), true );
		$items = is_array( $cart ) && is_array( $cart['items'] ?? null ) ? $cart['items'] : array();

		if ( array() === $items ) {
			return $result;
		}

		$previous = WC()->cart->get_cart_contents();

		WC()->cart->empty_cart();

		/**
		 * Lets a store adapter restore items itself, e.g. parent items with add-ons.
		 *
		 * Return an array with "restored" and "failed" counts when handled, or null to use the default.
		 *
		 * @param array{restored: int, failed: int}|null $handled Null when not handled.
		 * @param list<array<string, mixed>>             $items   Stored items.
		 * @param array<string, mixed>                   $row     Cart row.
		 */
		$handled = apply_filters( 'sfac_restore_items', null, $items, $row );

		if ( is_array( $handled ) ) {
			$result['restored'] = (int) ( $handled['restored'] ?? 0 );
			$result['failed']   = (int) ( $handled['failed'] ?? 0 );
		} else {
			foreach ( $items as $item ) {
				if ( self::add_item( is_array( $item ) ? $item : array() ) ) {
					++$result['restored'];
				} else {
					++$result['failed'];
				}
			}
		}

		if ( 0 === $result['restored'] ) {
			// Nothing could be restored (e.g. out of stock): give the shopper back the cart they had.
			WC()->cart->set_cart_contents( $previous );
			WC()->cart->calculate_totals();

			return $result;
		}

		self::restore_customer( $row );

		/**
		 * Fires after the items were restored, before a coupon is applied.
		 *
		 * @param array<string, mixed> $row  Cart row.
		 * @param int                  $step E-mail step.
		 */
		do_action( 'sfac_cart_restored', $row, $step );

		$code = (string) ( $row['coupon_code'] ?? '' );

		if ( '' !== $code && CouponService::is_usable( $code ) && ! WC()->cart->has_discount( $code ) ) {
			$result['coupon'] = (bool) WC()->cart->apply_coupon( $code );
		}

		WC()->cart->calculate_totals();

		return $result;
	}

	/**
	 * Add one stored item with its custom data.
	 *
	 * @param array<string, mixed> $item Stored item.
	 */
	public static function add_item( array $item ): bool {
		try {
			$added = WC()->cart->add_to_cart(
				(int) ( $item['product_id'] ?? 0 ),
				max( 1, (int) ( $item['quantity'] ?? 1 ) ),
				(int) ( $item['variation_id'] ?? 0 ),
				is_array( $item['variation'] ?? null ) ? $item['variation'] : array(),
				is_array( $item['custom'] ?? null ) ? $item['custom'] : array()
			);

			return false !== $added && '' !== $added;
		} catch ( Throwable $error ) {
			Logger::exception( 'A stored cart item could not be restored.', $error );
			return false;
		}
	}

	/**
	 * Put the known customer fields back into the session.
	 *
	 * @param array<string, mixed> $row Cart row.
	 */
	private static function restore_customer( array $row ): void {
		$customer = WC()->customer;

		if ( ! $customer instanceof \WC_Customer ) {
			return;
		}

		$email = (string) ( $row['email'] ?? '' );

		if ( '' !== $email && '' === (string) $customer->get_billing_email() ) {
			$customer->set_billing_email( $email );
		}

		$first_name = (string) ( $row['first_name'] ?? '' );

		if ( '' !== $first_name && '' === (string) $customer->get_billing_first_name() ) {
			$customer->set_billing_first_name( $first_name );
		}

		$customer->save();
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
