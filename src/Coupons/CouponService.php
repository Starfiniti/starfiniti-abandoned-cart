<?php
/**
 * Personal reminder coupons.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Coupons;

use Starfiniti\AbandonedCart\Sequence\Schedule;
use Starfiniti\AbandonedCart\Settings;
use Starfiniti\AbandonedCart\Store\Carts;
use Starfiniti\AbandonedCart\Store\Contacts;
use Starfiniti\AbandonedCart\Store\Events;

/**
 * Creates single-use, address-bound coupons that expire at an exact time and
 * deletes them when they were not used.
 */
final class CouponService {

	/**
	 * Coupon meta that links a coupon to its cart row.
	 */
	public const META_KEY = '_sfac_cart_id';

	/**
	 * Create a coupon for a cart, or return null when coupons are off or the address is in cooldown.
	 *
	 * @param array<string, mixed> $row Cart row.
	 * @param int                  $now Current Unix timestamp.
	 * @return array{id: int, code: string, expires: int}|null
	 */
	public static function create( array $row, int $now ): ?array {
		$settings = Settings::all();

		if ( empty( $settings['coupon_enabled'] ) || (float) $settings['coupon_amount'] <= 0 || ! class_exists( 'WC_Coupon' ) ) {
			return null;
		}

		$email_hash = (string) $row['email_hash'];

		if ( Contacts::within( $email_hash, 'last_coupon_at', (int) $settings['coupon_cooldown_days'], $now ) ) {
			return null;
		}

		$prefix = '' !== (string) $settings['coupon_prefix'] ? (string) $settings['coupon_prefix'] : CouponCode::default_prefix( (string) get_bloginfo( 'name' ) );

		/**
		 * Filters the coupon code prefix.
		 *
		 * @param string               $prefix Prefix.
		 * @param array<string, mixed> $row    Cart row.
		 */
		$prefix = (string) apply_filters( 'sfac_coupon_prefix', $prefix, $row );
		$code   = '';

		for ( $attempt = 0; $attempt < 5 && '' === $code; $attempt++ ) {
			$candidate = CouponCode::make( $prefix, 'wp_rand' );
			$code      = 0 === (int) wc_get_coupon_id_by_code( $candidate ) ? $candidate : '';
		}

		if ( '' === $code ) {
			return null;
		}

		$expires = Schedule::coupon_expiry( $now, wp_timezone(), (int) $settings['coupon_valid_days'], (int) $settings['coupon_expiry_hour'] );
		$coupon  = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( (string) $settings['coupon_type'] );
		$coupon->set_amount( (float) $settings['coupon_amount'] );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_email_restrictions( array( (string) $row['email'] ) );
		$coupon->set_date_expires( $expires );
		$coupon->set_description(
			sprintf(
				/* translators: %d: internal cart number. */
				__( 'Abandoned cart reminder #%d. Deleted automatically when it expires unused.', 'starfiniti-abandoned-cart' ),
				(int) $row['id']
			)
		);
		$coupon->update_meta_data( self::META_KEY, (string) (int) $row['id'] );

		/**
		 * Fires before a reminder coupon is saved, e.g. to exclude product categories.
		 *
		 * @param \WC_Coupon           $coupon Coupon.
		 * @param array<string, mixed> $row    Cart row.
		 */
		do_action( 'sfac_coupon_before_save', $coupon, $row );

		$coupon_id = (int) $coupon->save();

		if ( $coupon_id <= 0 ) {
			return null;
		}

		Contacts::touch( $email_hash, 'last_coupon_at' );

		return array(
			'id'      => $coupon_id,
			'code'    => $code,
			'expires' => $expires,
		);
	}

	/**
	 * Check whether a code exists, has not expired and was not used.
	 *
	 * @param string $code Coupon code.
	 */
	public static function is_usable( string $code ): bool {
		if ( '' === $code || ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return false;
		}

		$coupon_id = (int) wc_get_coupon_id_by_code( $code );

		if ( $coupon_id <= 0 ) {
			return false;
		}

		$coupon  = new \WC_Coupon( $coupon_id );
		$expires = $coupon->get_date_expires();

		if ( null !== $expires && time() > $expires->getTimestamp() ) {
			return false;
		}

		$limit = (int) $coupon->get_usage_limit();

		return 0 === $limit || (int) $coupon->get_usage_count() < $limit;
	}

	/**
	 * Delete unused expired coupons and clear the saved carts they belonged to.
	 *
	 * @param int $now Current Unix timestamp.
	 */
	public static function janitor( int $now ): void {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return;
		}

		foreach ( Carts::expired_coupons( $now ) as $row ) {
			self::delete_if_unused( (int) $row['coupon_id'] );

			$update = array( 'coupon_id' => 0 );

			if ( in_array( (string) $row['status'], array( Carts::STATUS_ACTIVE, 'done' ), true ) ) {
				$update['status']       = 'expired';
				$update['cart']         = null;
				$update['next_send_at'] = null;
			}

			Carts::update( (int) $row['id'], $update );
			Events::add( (int) $row['id'], 'coupon_deleted', 0, (string) $row['variant'] );
		}

		$stale = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded safety sweep of plugin coupons.
					array(
						'key'     => self::META_KEY,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'date_expires',
						'value'   => $now - 3600,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		foreach ( $stale as $coupon_id ) {
			self::delete_if_unused( (int) $coupon_id );
		}
	}

	/**
	 * Delete every unused plugin coupon (uninstall).
	 */
	public static function delete_all_unused(): void {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return;
		}

		$offset = 0;

		for ( $batch = 0; $batch < 50; $batch++ ) {
			$coupon_ids = get_posts(
				array(
					'post_type'      => 'shop_coupon',
					'post_status'    => 'any',
					'posts_per_page' => 100,
					'offset'         => $offset,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time uninstall cleanup.
				)
			);

			if ( array() === $coupon_ids ) {
				return;
			}

			foreach ( $coupon_ids as $coupon_id ) {
				if ( ! self::delete_if_unused( (int) $coupon_id ) ) {
					++$offset;
				}
			}
		}
	}

	/**
	 * Delete one coupon when nobody used it.
	 *
	 * @param int $coupon_id Coupon post id.
	 */
	public static function delete_if_unused( int $coupon_id ): bool {
		if ( $coupon_id <= 0 ) {
			return false;
		}

		$coupon = new \WC_Coupon( $coupon_id );

		if ( 0 === $coupon->get_id() || (int) $coupon->get_usage_count() > 0 ) {
			return false;
		}

		$coupon->delete( true );

		return true;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
