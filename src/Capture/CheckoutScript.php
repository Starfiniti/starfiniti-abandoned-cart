<?php
/**
 * Checkout listener script.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Capture;

use Starfiniti\AbandonedCart\Email\Copy;
use Starfiniti\AbandonedCart\Integration\Multilingual;
use Starfiniti\AbandonedCart\Settings;

/**
 * Loads a small script on the checkout that sends the typed address to the
 * capture endpoint and shows the reminder notice (or the consent checkbox).
 */
final class CheckoutScript {

	/**
	 * Script handle.
	 */
	public const HANDLE = 'sfac-checkout';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 30 );
	}

	/**
	 * Enqueue on the checkout page.
	 */
	public static function enqueue(): void {
		if ( Settings::MODE_OFF === Settings::mode() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		$language = Multilingual::current();
		$copy     = Copy::for_language( $language );

		/**
		 * Filters the CSS selectors of e-mail inputs the script listens to.
		 *
		 * @param list<string> $selectors Selectors.
		 */
		$selectors = apply_filters(
			'sfac_checkout_selectors',
			array(
				'.wp-block-woocommerce-checkout-contact-information-block input[type="email"]',
				'#email',
				'#billing_email',
			)
		);

		/**
		 * Filters the selector after whose field the notice or checkbox is placed.
		 *
		 * @param string $anchor Selector; empty uses the first matching e-mail input.
		 */
		$anchor = (string) apply_filters( 'sfac_notice_anchor', '' );

		$show_notice = Settings::MODE_ON === Settings::mode() || current_user_can( 'manage_woocommerce' );

		$config = array(
			'endpoint'  => esc_url_raw( rest_url( 'sfac/v1/capture' ) ),
			'language'  => $language,
			'selectors' => is_array( $selectors ) ? array_values( array_map( 'strval', $selectors ) ) : array(),
			'anchor'    => $anchor,
			'consent'   => (string) Settings::get( 'consent' ),
			'notice'    => $show_notice ? Copy::text( $copy, 'notice', array() ) : '',
			'checkbox'  => $show_notice ? Copy::text( $copy, 'consent_label', array() ) : '',
		);

		wp_register_script( self::HANDLE, SFAC_PLUGIN_URL . 'assets/js/checkout.js', array(), SFAC_VERSION, true );
		wp_add_inline_script( self::HANDLE, 'window.sfacCheckout = ' . wp_json_encode( $config ) . ';', 'before' );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
