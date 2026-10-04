<?php
/**
 * WooCommerce capture hooks.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Capture;

use Starfiniti\AbandonedCart\Integration\Multilingual;
use Starfiniti\AbandonedCart\Support\Logger;
use Throwable;

/**
 * Connects capture to WooCommerce. Every callback is isolated: an error here is
 * logged and never reaches the shopper.
 */
final class Hooks {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( self::class, 'store_api_customer' ), 50, 2 );
		add_action( 'woocommerce_checkout_update_order_review', array( self::class, 'classic_review' ), 50 );
		add_action( 'template_redirect', array( self::class, 'account' ), 40 );
		add_action( 'woocommerce_cart_updated', array( self::class, 'cart_updated' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( self::class, 'store_api_order' ) );
		add_action( 'woocommerce_checkout_order_processed', array( self::class, 'classic_order' ), 10, 3 );
	}

	/**
	 * Block checkout pushed customer data.
	 *
	 * @param mixed $customer WooCommerce customer.
	 * @param mixed $request  REST request.
	 */
	public static function store_api_customer( mixed $customer, mixed $request = null ): void {
		unset( $request );

		try {
			if ( $customer instanceof \WC_Customer && '' !== (string) $customer->get_billing_email() ) {
				CaptureService::capture( (string) $customer->get_billing_email(), self::language(), 'store_api' );
			}
		} catch ( Throwable $error ) {
			Logger::exception( 'Block checkout capture failed.', $error );
		}
	}

	/**
	 * Classic checkout refreshed the order review.
	 *
	 * @param mixed $post_data Serialized checkout form.
	 */
	public static function classic_review( mixed $post_data ): void {
		try {
			$fields = array();
			parse_str( is_string( $post_data ) ? $post_data : '', $fields );
			$email = isset( $fields['billing_email'] ) && is_string( $fields['billing_email'] ) ? $fields['billing_email'] : '';

			if ( '' !== $email ) {
				CaptureService::capture( $email, self::language(), 'classic' );
			}
		} catch ( Throwable $error ) {
			Logger::exception( 'Classic checkout capture failed.', $error );
		}
	}

	/**
	 * A logged-in customer opened the checkout.
	 */
	public static function account(): void {
		try {
			if ( ! is_user_logged_in() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
				return;
			}

			$user = wp_get_current_user();

			if ( '' !== (string) $user->user_email ) {
				CaptureService::capture( (string) $user->user_email, Multilingual::current(), 'account' );
			}
		} catch ( Throwable $error ) {
			Logger::exception( 'Account capture failed.', $error );
		}
	}

	/**
	 * The cart session was saved.
	 */
	public static function cart_updated(): void {
		try {
			CaptureService::sync_cart();
		} catch ( Throwable $error ) {
			Logger::exception( 'Cart sync failed.', $error );
		}
	}

	/**
	 * Block checkout created an order.
	 *
	 * @param mixed $order Order.
	 */
	public static function store_api_order( mixed $order ): void {
		try {
			if ( $order instanceof \WC_Order ) {
				CaptureService::order_placed( $order );
			}
		} catch ( Throwable $error ) {
			Logger::exception( 'Order attribution failed.', $error );
		}
	}

	/**
	 * Classic checkout created an order.
	 *
	 * @param mixed $order_id Order id.
	 * @param mixed $posted   Posted data.
	 * @param mixed $order    Order.
	 */
	public static function classic_order( mixed $order_id, mixed $posted = null, mixed $order = null ): void {
		unset( $posted );

		try {
			$order = $order instanceof \WC_Order ? $order : wc_get_order( is_numeric( $order_id ) ? (int) $order_id : 0 );

			if ( $order instanceof \WC_Order ) {
				CaptureService::order_placed( $order );
			}
		} catch ( Throwable $error ) {
			Logger::exception( 'Order attribution failed.', $error );
		}
	}

	/**
	 * Language of a Store API or AJAX request: the page language from the referer when known.
	 */
	private static function language(): string {
		$referer = wp_get_referer();

		if ( is_string( $referer ) && class_exists( 'TRP_Translate_Press' ) ) {
			$path    = (string) wp_parse_url( $referer, PHP_URL_PATH );
			$segment = strtolower( (string) strtok( ltrim( $path, '/' ), '/' ) );

			if ( 2 === strlen( $segment ) ) {
				return $segment;
			}
		}

		return Multilingual::current();
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
