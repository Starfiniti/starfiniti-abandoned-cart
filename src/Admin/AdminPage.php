<?php
/**
 * Administration screen.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Admin;

use Starfiniti\AbandonedCart\Email\Formatter;
use Starfiniti\AbandonedCart\Email\Preview;
use Starfiniti\AbandonedCart\Integration\Multilingual;
use Starfiniti\AbandonedCart\Settings;
use Starfiniti\AbandonedCart\Store\Carts;
use Starfiniti\AbandonedCart\Store\Events;
use Starfiniti\AbandonedCart\Support\Emails;

/**
 * WooCommerce → Abandoned carts: overview, settings, preview and test e-mails.
 */
final class AdminPage {

	/**
	 * Page slug.
	 */
	public const SLUG = 'starfiniti-abandoned-cart';

	/**
	 * Required capability.
	 */
	private const CAPABILITY = 'manage_woocommerce';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 60 );
		add_action( 'admin_post_sfac_save', array( self::class, 'save' ) );
		add_action( 'admin_post_sfac_test', array( self::class, 'test' ) );
	}

	/**
	 * Add the submenu.
	 */
	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Abandoned carts', 'starfiniti-abandoned-cart' ),
			__( 'Abandoned carts', 'starfiniti-abandoned-cart' ),
			self::CAPABILITY,
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation only.
		$tabs = array(
			'overview' => __( 'Overview', 'starfiniti-abandoned-cart' ),
			'settings' => __( 'Settings', 'starfiniti-abandoned-cart' ),
			'preview'  => __( 'Preview and test', 'starfiniti-abandoned-cart' ),
		);
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'overview';

		echo '<div class="wrap"><h1>' . esc_html__( 'Abandoned carts', 'starfiniti-abandoned-cart' ) . '</h1>';
		self::mode_notice();
		echo '<nav class="nav-tab-wrapper">';

		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
				esc_url( self::url( $key ) ),
				$key === $tab ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}

		echo '</nav>';

		if ( 'settings' === $tab ) {
			self::settings();
		} elseif ( 'preview' === $tab ) {
			self::preview();
		} else {
			self::overview();
		}

		echo '</div>';
	}

	/**
	 * Explain the current mode.
	 */
	private static function mode_notice(): void {
		$mode     = Settings::mode();
		$messages = array(
			Settings::MODE_OFF  => __( 'Mode: Off. Nothing is captured and no e-mails are sent.', 'starfiniti-abandoned-cart' ),
			Settings::MODE_TEST => __( 'Mode: Test. Only the test addresses are captured and e-mailed.', 'starfiniti-abandoned-cart' ),
			Settings::MODE_ON   => __( 'Mode: On. Shoppers who enter an e-mail address at checkout receive reminders.', 'starfiniti-abandoned-cart' ),
		);
		$class    = Settings::MODE_ON === $mode ? 'notice-success' : ( Settings::MODE_TEST === $mode ? 'notice-warning' : 'notice-info' );

		printf( '<div class="notice %1$s inline"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $messages[ $mode ] ?? '' ) );
	}

	/**
	 * Overview tab.
	 */
	private static function overview(): void {
		$since    = time() - 30 * DAY_IN_SECONDS;
		$statuses = Carts::status_counts( $since );
		$sent     = array();
		$clicks   = 0;

		foreach ( Events::summary( $since ) as $event ) {
			if ( 'sent' === $event['type'] ) {
				$sent[ $event['step'] ] = ( $sent[ $event['step'] ] ?? 0 ) + $event['total'];
			} elseif ( 'click' === $event['type'] ) {
				$clicks += $event['total'];
			}
		}

		ksort( $sent );

		$cards = array(
			__( 'Carts saved', 'starfiniti-abandoned-cart' )     => (string) array_sum( $statuses ),
			__( 'E-mails sent', 'starfiniti-abandoned-cart' )    => (string) array_sum( $sent ),
			__( 'Link clicks', 'starfiniti-abandoned-cart' )     => (string) $clicks,
			__( 'Recovered orders', 'starfiniti-abandoned-cart' ) => (string) ( $statuses['recovered'] ?? 0 ),
			__( 'Recovered revenue', 'starfiniti-abandoned-cart' ) => Formatter::money( Carts::recovered_revenue( $since ) ),
		);

		echo '<h2>' . esc_html__( 'Last 30 days', 'starfiniti-abandoned-cart' ) . '</h2><div style="display:flex;flex-wrap:wrap;gap:12px;">';

		foreach ( $cards as $label => $value ) {
			printf( '<div style="background:#fff;border:1px solid #dcdcde;padding:14px 18px;min-width:150px;"><div style="font-size:12px;color:#646970;">%1$s</div><div style="font-size:24px;font-weight:600;">%2$s</div></div>', esc_html( $label ), esc_html( $value ) );
		}

		echo '</div>';

		if ( array() !== $sent ) {
			$parts = array();

			foreach ( $sent as $step => $total ) {
				/* translators: 1: step number, 2: number of e-mails. */
				$parts[] = sprintf( __( 'e-mail %1$d: %2$d', 'starfiniti-abandoned-cart' ), $step, $total );
			}

			echo '<p>' . esc_html( implode( ' · ', $parts ) ) . '</p>';
		}

		echo '<h2>' . esc_html__( 'Latest carts', 'starfiniti-abandoned-cart' ) . '</h2><table class="widefat striped"><thead><tr>';

		$columns = array(
			__( 'Saved', 'starfiniti-abandoned-cart' ),
			__( 'Customer', 'starfiniti-abandoned-cart' ),
			__( 'Language', 'starfiniti-abandoned-cart' ),
			__( 'Value', 'starfiniti-abandoned-cart' ),
			__( 'Sequence', 'starfiniti-abandoned-cart' ),
			__( 'Sent', 'starfiniti-abandoned-cart' ),
			__( 'Status', 'starfiniti-abandoned-cart' ),
			__( 'Next e-mail', 'starfiniti-abandoned-cart' ),
			__( 'Coupon', 'starfiniti-abandoned-cart' ),
			__( 'Order', 'starfiniti-abandoned-cart' ),
		);

		foreach ( $columns as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}

		echo '</tr></thead><tbody>';

		$rows = Carts::latest( 30 );

		if ( array() === $rows ) {
			echo '<tr><td colspan="10">' . esc_html__( 'No carts saved yet.', 'starfiniti-abandoned-cart' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			$order_id = (int) $row['order_id'];
			$wc_order = $order_id > 0 ? wc_get_order( $order_id ) : false;
			$order    = $wc_order instanceof \WC_Order ? '<a href="' . esc_url( $wc_order->get_edit_order_url() ) . '">#' . esc_html( $wc_order->get_order_number() ) . '</a>' : '';

			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$d</td><td>%7$s</td><td>%8$s</td><td>%9$s</td><td>%10$s</td></tr>',
				esc_html( self::local_time( (string) $row['created_at'] ) ),
				esc_html( Emails::mask( (string) $row['email'] ) ),
				esc_html( (string) $row['locale'] ),
				esc_html( Formatter::money( (float) $row['items_total'], (string) $row['currency'] ) ),
				esc_html( (string) $row['segment'] . ' / ' . (string) $row['variant'] ),
				(int) $row['step'],
				esc_html( (string) $row['status'] ),
				esc_html( self::local_time( (string) $row['next_send_at'] ) ),
				esc_html( (string) $row['coupon_code'] ),
				wp_kses_post( $order )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Settings tab.
	 */
	private static function settings(): void {
		$s = Settings::all();

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Settings saved.', 'starfiniti-abandoned-cart' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'sfac_save' );
		echo '<input type="hidden" name="action" value="sfac_save"><table class="form-table" role="presentation">';

		$modes = array(
			Settings::MODE_OFF  => __( 'Off', 'starfiniti-abandoned-cart' ),
			Settings::MODE_TEST => __( 'Test (only the addresses below)', 'starfiniti-abandoned-cart' ),
			Settings::MODE_ON   => __( 'On', 'starfiniti-abandoned-cart' ),
		);
		$radio = '';

		foreach ( $modes as $value => $label ) {
			$radio .= '<label style="margin-right:16px;"><input type="radio" name="mode" value="' . esc_attr( $value ) . '"' . checked( $s['mode'], $value, false ) . '> ' . esc_html( $label ) . '</label>';
		}

		self::field( __( 'Mode', 'starfiniti-abandoned-cart' ), $radio );
		self::field( __( 'Test addresses', 'starfiniti-abandoned-cart' ), '<textarea name="test_emails" rows="3" cols="50">' . esc_textarea( implode( "\n", (array) $s['test_emails'] ) ) . '</textarea><p class="description">' . esc_html__( 'One per line. Only these are captured in Test mode.', 'starfiniti-abandoned-cart' ) . '</p>' );
		self::field(
			__( 'Legal basis at checkout', 'starfiniti-abandoned-cart' ),
			self::select(
				'consent',
				(string) $s['consent'],
				array(
					'notice'   => __( 'Notice under the e-mail field, one-click unsubscribe', 'starfiniti-abandoned-cart' ),
					'checkbox' => __( 'Consent checkbox, capture only when ticked', 'starfiniti-abandoned-cart' ),
				)
			)
		);
		self::field( __( 'First e-mail after', 'starfiniti-abandoned-cart' ), self::number( 'first_delay_minutes', $s ) . ' ' . esc_html__( 'minutes of inactivity', 'starfiniti-abandoned-cart' ) );
		self::field( __( 'Second e-mail after', 'starfiniti-abandoned-cart' ), self::number( 'second_delay_hours', $s ) . ' ' . esc_html__( 'hours of inactivity', 'starfiniti-abandoned-cart' ) );
		self::field( __( 'Last e-mail on the coupon\'s last day at', 'starfiniti-abandoned-cart' ), self::number( 'last_day_hour', $s ) . ':00' );
		self::field( __( 'Quiet hours', 'starfiniti-abandoned-cart' ), self::number( 'quiet_start', $s ) . ':00 – ' . self::number( 'quiet_end', $s ) . ':00' );
		self::field( __( 'Coupon', 'starfiniti-abandoned-cart' ), self::checkbox( 'coupon_enabled', $s, __( 'Personal coupon in the second e-mail', 'starfiniti-abandoned-cart' ) ) );
		self::field(
			__( 'Discount', 'starfiniti-abandoned-cart' ),
			'<input type="number" step="0.01" min="0" name="coupon_amount" value="' . esc_attr( (string) $s['coupon_amount'] ) . '" class="small-text"> '
			. self::select(
				'coupon_type',
				(string) $s['coupon_type'],
				array(
					'percent'    => '%',
					'fixed_cart' => get_woocommerce_currency_symbol(),
				)
			)
		);
		self::field( __( 'Coupon valid for', 'starfiniti-abandoned-cart' ), self::number( 'coupon_valid_days', $s ) . ' ' . esc_html__( 'days, until', 'starfiniti-abandoned-cart' ) . ' ' . self::number( 'coupon_expiry_hour', $s ) . ':00' );
		self::field( __( 'One coupon per address every', 'starfiniti-abandoned-cart' ), self::number( 'coupon_cooldown_days', $s ) . ' ' . esc_html__( 'days', 'starfiniti-abandoned-cart' ) );
		self::field( __( 'Coupon prefix', 'starfiniti-abandoned-cart' ), '<input type="text" name="coupon_prefix" value="' . esc_attr( (string) $s['coupon_prefix'] ) . '" class="small-text" maxlength="8"> <span class="description">' . esc_html__( 'Empty uses the first letters of the store name.', 'starfiniti-abandoned-cart' ) . '</span>' );
		self::field( __( 'One sequence per address every', 'starfiniti-abandoned-cart' ), self::number( 'sequence_cooldown_days', $s ) . ' ' . esc_html__( 'days', 'starfiniti-abandoned-cart' ) );
		self::field( __( 'Failed payments', 'starfiniti-abandoned-cart' ), self::checkbox( 'payment_failed_enabled', $s, __( 'Send one reminder for unpaid online orders after', 'starfiniti-abandoned-cart' ) ) . ' ' . self::number( 'payment_failed_minutes', $s ) . ' ' . esc_html__( 'minutes', 'starfiniti-abandoned-cart' ) );
		self::field( __( 'Sender name', 'starfiniti-abandoned-cart' ), self::text( 'from_name', $s ) );
		self::field( __( 'Reply-to address', 'starfiniti-abandoned-cart' ), self::text( 'reply_to', $s ) );
		self::field( __( 'Signature', 'starfiniti-abandoned-cart' ), self::text( 'signer', $s ) . '<p class="description">' . esc_html__( 'Name under the e-mails, e.g. the owner\'s first name. Empty uses the store name.', 'starfiniti-abandoned-cart' ) . '</p>' );
		self::field( __( 'Rating line', 'starfiniti-abandoned-cart' ), self::text( 'rating_text', $s ) . '<p class="description">' . esc_html__( 'Only a rating you can prove, e.g. "4.9/5 on Google".', 'starfiniti-abandoned-cart' ) . '</p>' );

		$reviews = (array) $s['reviews'];
		$inputs  = '';

		for ( $i = 0; $i < 3; $i++ ) {
			$review  = is_array( $reviews[ $i ] ?? null ) ? $reviews[ $i ] : array(
				'text'   => '',
				'author' => '',
			);
			$inputs .= '<p><textarea name="reviews[' . $i . '][text]" rows="2" cols="60" placeholder="' . esc_attr__( 'Real review text', 'starfiniti-abandoned-cart' ) . '">' . esc_textarea( (string) $review['text'] ) . '</textarea><br><input type="text" name="reviews[' . $i . '][author]" value="' . esc_attr( (string) $review['author'] ) . '" placeholder="' . esc_attr__( 'Author', 'starfiniti-abandoned-cart' ) . '"></p>';
		}

		self::field( __( 'Quoted reviews', 'starfiniti-abandoned-cart' ), $inputs . '<p class="description">' . esc_html__( 'Only real reviews you may quote.', 'starfiniti-abandoned-cart' ) . '</p>' );
		self::field( __( 'Keep data for', 'starfiniti-abandoned-cart' ), self::number( 'retention_days', $s ) . ' ' . esc_html__( 'days', 'starfiniti-abandoned-cart' ) );
		self::field( __( 'Uninstall', 'starfiniti-abandoned-cart' ), self::checkbox( 'delete_data_on_uninstall', $s, __( 'Delete all plugin data and unused coupons when the plugin is deleted', 'starfiniti-abandoned-cart' ) ) );

		echo '</table>';
		submit_button();
		echo '</form>';
	}

	/**
	 * Preview tab.
	 */
	private static function preview(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only preview selection.
		$scenario = isset( $_GET['scenario'] ) ? sanitize_key( wp_unslash( $_GET['scenario'] ) ) : 'full';
		$language = isset( $_GET['language'] ) ? Multilingual::normalize( sanitize_key( wp_unslash( $_GET['language'] ) ) ) : Multilingual::current();
		$step     = isset( $_GET['step'] ) ? max( 1, min( 3, absint( $_GET['step'] ) ) ) : 1;
		$sent     = isset( $_GET['sent'] ) ? absint( $_GET['sent'] ) : null;
		// phpcs:enable

		if ( null !== $sent ) {
			/* translators: %d: number of e-mails. */
			echo '<div class="notice notice-info inline"><p>' . esc_html( sprintf( __( '%d test e-mails were handed to the mail system.', 'starfiniti-abandoned-cart' ), $sent ) ) . '</p></div>';
		}

		$languages = array();

		foreach ( array( Multilingual::current(), 'en', 'sl', 'de' ) as $code ) {
			$languages[ $code ] = $code;
		}

		$scenarios = array(
			'full'           => __( 'All data (names, message, date)', 'starfiniti-abandoned-cart' ),
			'minimal'        => __( 'Missing data', 'starfiniti-abandoned-cart' ),
			'calm'           => __( 'Calm sequence', 'starfiniti-abandoned-cart' ),
			'payment_failed' => __( 'Payment failed', 'starfiniti-abandoned-cart' ),
		);

		$steps    = array(
			'1' => '1',
			'2' => '2',
			'3' => '3',
		);
		$controls = self::select( 'scenario', $scenario, $scenarios ) . self::select( 'step', (string) $step, $steps ) . self::select( 'language', $language, $languages );

		echo '<form method="get" style="margin:16px 0;"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="tab" value="preview">';
		echo $controls; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in select().
		submit_button( __( 'Show', 'starfiniti-abandoned-cart' ), 'secondary', '', false );
		echo '</form>';

		$message = Preview::render( $scenario, $language, $step );

		printf(
			'<p><strong>%1$s</strong> %2$s<br><span style="color:#646970;">%3$s</span></p>',
			esc_html__( 'Subject:', 'starfiniti-abandoned-cart' ),
			esc_html( $message['subject'] ),
			esc_html( $message['preheader'] )
		);
		printf( '<iframe title="%1$s" srcdoc="%2$s" style="width:100%%;max-width:680px;height:1100px;border:1px solid #dcdcde;background:#fff;"></iframe>', esc_attr__( 'E-mail preview', 'starfiniti-abandoned-cart' ), esc_attr( $message['html'] ) );

		echo '<h2>' . esc_html__( 'Send test e-mails', 'starfiniti-abandoned-cart' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'sfac_test' );
		echo '<input type="hidden" name="action" value="sfac_test"><input type="hidden" name="language" value="' . esc_attr( $language ) . '">';
		echo '<input type="email" name="email" required class="regular-text" value="' . esc_attr( wp_get_current_user()->user_email ) . '"> ';
		submit_button( __( 'Send all samples', 'starfiniti-abandoned-cart' ), 'primary', '', false );
		echo '<p class="description">' . esc_html__( 'Sends six sample e-mails in the selected language. Sample coupon codes do not work.', 'starfiniti-abandoned-cart' ) . '</p></form>';
	}

	/**
	 * Save settings.
	 */
	public static function save(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'starfiniti-abandoned-cart' ) );
		}

		check_admin_referer( 'sfac_save' );

		$input = wp_unslash( $_POST );
		$input = is_array( $input ) ? $input : array();

		foreach ( array( 'coupon_enabled', 'payment_failed_enabled', 'delete_data_on_uninstall' ) as $key ) {
			$input[ $key ] = isset( $input[ $key ] ) ? '1' : '0';
		}

		Settings::update( $input );
		wp_safe_redirect( add_query_arg( 'updated', '1', self::url( 'settings' ) ) );
		exit;
	}

	/**
	 * Send test e-mails.
	 */
	public static function test(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to send test e-mails.', 'starfiniti-abandoned-cart' ) );
		}

		check_admin_referer( 'sfac_test' );

		$email    = isset( $_POST['email'] ) ? Emails::normalize( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : '';
		$language = isset( $_POST['language'] ) ? Multilingual::normalize( sanitize_key( wp_unslash( $_POST['language'] ) ) ) : 'en';
		$sent     = '' !== $email ? Preview::send_test( $email, $language ) : 0;

		wp_safe_redirect(
			add_query_arg(
				array(
					'sent'     => $sent,
					'language' => $language,
				),
				self::url( 'preview' )
			)
		);
		exit;
	}

	/**
	 * Print a settings row.
	 *
	 * @param string $label Label.
	 * @param string $html  Escaped field HTML.
	 */
	private static function field( string $label, string $html ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fields are escaped by their builders.
	}

	/**
	 * Number input.
	 *
	 * @param string               $name     Setting key.
	 * @param array<string, mixed> $settings Settings.
	 */
	private static function number( string $name, array $settings ): string {
		return '<input type="number" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $settings[ $name ] ) . '" class="small-text">';
	}

	/**
	 * Text input.
	 *
	 * @param string               $name     Setting key.
	 * @param array<string, mixed> $settings Settings.
	 */
	private static function text( string $name, array $settings ): string {
		return '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $settings[ $name ] ) . '" class="regular-text">';
	}

	/**
	 * Checkbox.
	 *
	 * @param string               $name     Setting key.
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $label    Label.
	 */
	private static function checkbox( string $name, array $settings, string $label ): string {
		return '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $settings[ $name ] ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}

	/**
	 * Select box.
	 *
	 * @param string                    $name    Field name.
	 * @param string                    $current Current value.
	 * @param array<int|string, string> $options Options; PHP stores numeric string keys as integers.
	 */
	private static function select( string $name, string $current, array $options ): string {
		$html = '<select name="' . esc_attr( $name ) . '">';

		foreach ( $options as $value => $label ) {
			$html .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		return $html . '</select> ';
	}

	/**
	 * Admin URL of a tab.
	 *
	 * @param string $tab Tab key.
	 */
	private static function url( string $tab ): string {
		return add_query_arg(
			array(
				'page' => self::SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Format a UTC MySQL time in the site timezone.
	 *
	 * @param string $utc UTC time.
	 */
	private static function local_time( string $utc ): string {
		$time = '' !== $utc ? strtotime( $utc . ' UTC' ) : false;

		return false === $time ? '' : wp_date( 'j. n. H:i', $time );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
