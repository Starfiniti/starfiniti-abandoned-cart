<?php
/**
 * WordPress privacy tools.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Privacy;

use Starfiniti\AbandonedCart\Settings;
use Starfiniti\AbandonedCart\Store\Carts;
use Starfiniti\AbandonedCart\Support\Emails;
use Starfiniti\AbandonedCart\Support\Secret;

/**
 * Personal data exporter and eraser, and a suggested privacy policy paragraph.
 */
final class Privacy {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
		add_action( 'admin_init', array( self::class, 'policy_text' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array<string, mixed> $exporters Exporters.
	 * @return array<string, mixed>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['starfiniti-abandoned-cart'] = array(
			'exporter_friendly_name' => __( 'Abandoned cart reminders', 'starfiniti-abandoned-cart' ),
			'callback'               => array( self::class, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array<string, mixed> $erasers Erasers.
	 * @return array<string, mixed>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['starfiniti-abandoned-cart'] = array(
			'eraser_friendly_name' => __( 'Abandoned cart reminders', 'starfiniti-abandoned-cart' ),
			'callback'             => array( self::class, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export stored carts of an address.
	 *
	 * @param string $email Address.
	 * @param int    $page  Page number.
	 * @return array{data: list<array<string, mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		unset( $page );
		$email = Emails::normalize( $email );
		$data  = array();

		if ( '' !== $email ) {
			foreach ( Carts::by_email_hash( Secret::email_hash( $email ) ) as $row ) {
				$cart  = json_decode( (string) $row['cart'], true );
				$names = array();

				foreach ( is_array( $cart ) && is_array( $cart['items'] ?? null ) ? $cart['items'] : array() as $item ) {
					$names[] = is_array( $item ) ? (string) ( $item['name'] ?? '' ) : '';
				}

				$data[] = array(
					'group_id'    => 'starfiniti-abandoned-cart',
					'group_label' => __( 'Abandoned cart reminders', 'starfiniti-abandoned-cart' ),
					'item_id'     => 'sfac-cart-' . (int) $row['id'],
					'data'        => array(
						array(
							'name'  => __( 'Saved', 'starfiniti-abandoned-cart' ),
							'value' => (string) $row['created_at'],
						),
						array(
							'name'  => __( 'Status', 'starfiniti-abandoned-cart' ),
							'value' => (string) $row['status'],
						),
						array(
							'name'  => __( 'Products', 'starfiniti-abandoned-cart' ),
							'value' => implode( ', ', array_filter( $names ) ),
						),
						array(
							'name'  => __( 'Reminders sent', 'starfiniti-abandoned-cart' ),
							'value' => (string) (int) $row['step'],
						),
					),
				);
			}
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase stored carts of an address. The hashed opt-out stays so an unsubscribe is honoured.
	 *
	 * @param string $email Address.
	 * @param int    $page  Page number.
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		unset( $page );
		$email   = Emails::normalize( $email );
		$removed = '' !== $email ? Carts::delete_by_email_hash( Secret::email_hash( $email ) ) : 0;

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Suggest privacy policy text.
	 */
	public static function policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			'Starfiniti Abandoned Cart',
			wp_kses_post(
				'<p>' . sprintf(
					/* translators: %d: retention in days. */
					__( 'When you enter your e-mail address at checkout and do not complete the order, we save your cart, your e-mail address, your first name and, if you entered them, the recipient first name, delivery date and card message. We may send you up to three reminders with a link that restores your cart, and one of them may contain a personal single-use discount code. Every reminder has a one-click unsubscribe link. The data is deleted after %d days; an unsubscribe is remembered as a one-way hash of your address only.', 'starfiniti-abandoned-cart' ),
					(int) Settings::get( 'retention_days' )
				) . '</p>'
			)
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
