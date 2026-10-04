<?php
/**
 * End-to-end smoke test on a real WordPress + WooCommerce site.
 *
 * Run with: wp eval-file tests/integration/wp-cli-smoke.php
 * Uses its own test product and address and removes them afterwards. Never sends
 * e-mail: wp_mail() is intercepted.
 *
 * @package StarfinitiAbandonedCart
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DevelopmentFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.GlobalVariablesOverride.Prohibited -- CLI test script.

use Starfiniti\AbandonedCart\Capture\CaptureService;
use Starfiniti\AbandonedCart\Cart\Restorer;
use Starfiniti\AbandonedCart\Coupons\CouponService;
use Starfiniti\AbandonedCart\Plugin;
use Starfiniti\AbandonedCart\Sequence\Runner;
use Starfiniti\AbandonedCart\Settings;
use Starfiniti\AbandonedCart\Store\Carts;
use Starfiniti\AbandonedCart\Store\Contacts;
use Starfiniti\AbandonedCart\Support\Secret;

$sfac_failures = 0;

/**
 * Print a check result.
 *
 * @param bool   $ok    Result.
 * @param string $label Description.
 */
function sfac_check( bool $ok, string $label ): void {
	global $sfac_failures;

	if ( ! $ok ) {
		++$sfac_failures;
	}

	fwrite( STDOUT, ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n" );
}

set_error_handler(
	static function ( int $severity, string $message, string $file, int $line ): bool {
		if ( false !== strpos( $file, 'starfiniti-abandoned-cart' ) ) {
			throw new ErrorException( esc_html( $message ), 0, (int) $severity, esc_html( $file ), (int) $line );
		}

		return false;
	}
);

sfac_check( class_exists( 'WooCommerce' ) && Plugin::instance()->is_ready() && 1 === did_action( 'sfac_loaded' ), 'plugin initialized with WooCommerce' );

$sfac_mails = array();
add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, array $atts ) use ( &$sfac_mails ) {
		$sfac_mails[] = $atts;
		return true;
	},
	10,
	2
);

$sfac_buyer = 'sfac-buyer@example.com';
$sfac_other = 'sfac-other@example.com';
update_option( 'timezone_string', 'Europe/Ljubljana' );
Settings::update(
	array(
		'mode'          => 'test',
		'test_emails'   => $sfac_buyer,
		'coupon_amount' => 10,
		'coupon_type'   => 'percent',
		'coupon_prefix' => 'SMOKE',
	)
);

$sfac_product = new WC_Product_Simple();
$sfac_product->set_name( 'SFAC smoke product' );
$sfac_product->set_regular_price( '50' );
$sfac_product->set_status( 'publish' );
$sfac_product_id = $sfac_product->save();

wc_load_cart();
WC()->cart->empty_cart();
WC()->cart->add_to_cart( $sfac_product_id, 2 );
WC()->cart->calculate_totals();
WC()->customer->set_billing_first_name( 'MAJA' );

// Capture.
sfac_check( 0 === CaptureService::capture( $sfac_other, 'sl', 'smoke' ), 'test mode ignores addresses that are not test addresses' );
$sfac_id  = CaptureService::capture( $sfac_buyer, 'sl', 'smoke' );
$sfac_row = Carts::find( $sfac_id );
sfac_check( $sfac_id > 0 && null !== $sfac_row, 'cart captured' );
sfac_check( 'Maja' === $sfac_row['first_name'] && 'sl' === $sfac_row['locale'] && 'default' === $sfac_row['segment'], 'name cleaned, language and segment stored' );
sfac_check( abs( (float) $sfac_row['items_total'] - 100.0 ) < 0.01, 'cart value stored' );
sfac_check( abs( strtotime( $sfac_row['next_send_at'] . ' UTC' ) - ( time() + 3600 ) ) < 120, 'first e-mail planned one hour after activity' );
$sfac_again = CaptureService::capture( $sfac_buyer, 'sl', 'smoke' );
sfac_check( $sfac_again === $sfac_id, 'second capture in the same session updates the same row' );

// E-mail 1 at noon local time.
$sfac_noon = ( new DateTimeImmutable( 'tomorrow 12:00', wp_timezone() ) )->getTimestamp();
Carts::update( $sfac_id, array( 'next_send_at' => Carts::now( $sfac_noon - 60 ) ) );
delete_transient( 'sfac_tick_lock' );
Runner::tick( $sfac_noon );
$sfac_row = Carts::find( $sfac_id );
sfac_check( 1 === count( $sfac_mails ) && $sfac_buyer === $sfac_mails[0]['to'], 'e-mail 1 sent to the test address' );
sfac_check( 1 === (int) $sfac_row['step'] && 'active' === $sfac_row['status'], 'step 1 recorded, sequence continues' );
sfac_check( false !== strpos( (string) $sfac_mails[0]['message'], 'Maja' ) && false !== strpos( (string) $sfac_mails[0]['message'], 'sfac=restore' ), 'e-mail 1 is personal and has a restore link' );
sfac_check( in_array( 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', (array) $sfac_mails[0]['headers'], true ), 'one-click unsubscribe header present' );

// E-mail 2 with coupon.
$sfac_day2 = $sfac_noon + DAY_IN_SECONDS;
Carts::update( $sfac_id, array( 'next_send_at' => Carts::now( $sfac_day2 - 60 ) ) );
delete_transient( 'sfac_tick_lock' );
Runner::tick( $sfac_day2 );
$sfac_row    = Carts::find( $sfac_id );
$sfac_code   = (string) $sfac_row['coupon_code'];
$sfac_coupon = new WC_Coupon( $sfac_code );
sfac_check( 2 === count( $sfac_mails ) && 0 === strpos( $sfac_code, 'SMOKE-' ), 'e-mail 2 sent with a coupon' );
sfac_check( false !== strpos( (string) $sfac_mails[1]['message'], $sfac_code ), 'coupon code shown in e-mail 2' );
sfac_check( 'percent' === $sfac_coupon->get_discount_type() && 10.0 === (float) $sfac_coupon->get_amount() && 1 === $sfac_coupon->get_usage_limit(), 'coupon is 10 %, single use' );
sfac_check( array( $sfac_buyer ) === $sfac_coupon->get_email_restrictions(), 'coupon restricted to the address' );
$sfac_expiry_local = $sfac_coupon->get_date_expires() ? $sfac_coupon->get_date_expires()->setTimezone( wp_timezone() )->format( 'H:i' ) : '';
sfac_check( '20:00' === $sfac_expiry_local, 'coupon expires at 20:00 local time' );
$sfac_e3_local = ( new DateTimeImmutable( '@' . strtotime( $sfac_row['next_send_at'] . ' UTC' ) ) )->setTimezone( wp_timezone() )->format( 'H:i' );
sfac_check( '10:00' === $sfac_e3_local, 'e-mail 3 planned at 10:00 on the last day' );

// Restore.
WC()->cart->empty_cart();
$sfac_restore = Restorer::restore( $sfac_row, 2 );
sfac_check( 1 === $sfac_restore['restored'] && 2 === WC()->cart->get_cart_contents_count(), 'restore link rebuilds the cart' );
sfac_check( $sfac_restore['coupon'] && WC()->cart->has_discount( wc_format_coupon_code( $sfac_code ) ), 'restore applies the coupon' );
WC()->cart->remove_coupon( $sfac_code );

// E-mail 3.
$sfac_e3 = strtotime( $sfac_row['next_send_at'] . ' UTC' );
delete_transient( 'sfac_tick_lock' );
Runner::tick( $sfac_e3 + 60 );
$sfac_row = Carts::find( $sfac_id );
sfac_check( 3 === count( $sfac_mails ) && 'done' === $sfac_row['status'], 'e-mail 3 sent, sequence done' );

// Janitor after expiry.
$sfac_expired_at = $sfac_coupon->get_date_expires() ? $sfac_coupon->get_date_expires()->getTimestamp() + 60 : time();
delete_transient( 'sfac_tick_lock' );
Runner::tick( $sfac_expired_at );
$sfac_row = Carts::find( $sfac_id );
sfac_check( 0 === wc_get_coupon_id_by_code( $sfac_code ) && 'expired' === $sfac_row['status'] && null === $sfac_row['cart'], 'unused coupon and saved cart deleted after expiry' );

// Unsubscribe blocks future captures.
WC()->session->set( CaptureService::SESSION_ID, 0 );
Contacts::touch( Secret::email_hash( $sfac_buyer ), 'optout_at' );
sfac_check( 0 === CaptureService::capture( $sfac_buyer, 'sl', 'smoke' ), 'unsubscribed address is not captured again' );

// Order stops a sequence.
global $wpdb;
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'sfac_contacts WHERE email_hash = %s', Secret::email_hash( $sfac_buyer ) ) ); // phpcs:ignore WordPress.DB
$sfac_new   = CaptureService::capture( $sfac_buyer, 'en', 'smoke' );
$sfac_order = wc_create_order();
$sfac_order->set_billing_email( $sfac_buyer );
$sfac_order->set_total( '100' );
$sfac_order->save();
CaptureService::order_placed( $sfac_order );
$sfac_new_row = Carts::find( $sfac_new );
sfac_check( $sfac_new > 0 && null !== $sfac_new_row && 'ordered' === $sfac_new_row['status'] && (int) $sfac_new_row['order_id'] === $sfac_order->get_id(), 'order stops the sequence' );

// Cleanup.
WC()->cart->empty_cart();
$sfac_order->delete( true );
wp_delete_post( $sfac_product_id, true );
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'sfac_carts WHERE email = %s', $sfac_buyer ) ); // phpcs:ignore WordPress.DB
CouponService::janitor( time() + 10 * DAY_IN_SECONDS );
Settings::update( array( 'mode' => 'off' ) );
restore_error_handler();

fwrite( STDOUT, 0 === $sfac_failures ? "\nAll checks passed.\n" : "\n{$sfac_failures} check(s) failed.\n" );

if ( $sfac_failures > 0 ) {
	exit( 1 );
}
