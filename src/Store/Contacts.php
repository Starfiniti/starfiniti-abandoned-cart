<?php
/**
 * Contact preferences keyed by hashed e-mail address.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Store;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; names come from Tables.

/**
 * Stores opt-outs, coupon and sequence cooldowns without storing addresses.
 */
final class Contacts {

	/**
	 * Return the contact row of a hashed address.
	 *
	 * @param string $email_hash Hashed address.
	 * @return array<string, mixed>|null
	 */
	public static function find( string $email_hash ): ?array {
		global $wpdb;

		$table = Tables::contacts();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email_hash = %s", $email_hash ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Check whether an address unsubscribed.
	 *
	 * @param string $email_hash Hashed address.
	 */
	public static function is_opted_out( string $email_hash ): bool {
		$row = self::find( $email_hash );

		return null !== $row && ! empty( $row['optout_at'] );
	}

	/**
	 * Check whether a timestamp column is newer than a number of days.
	 *
	 * @param string $email_hash Hashed address.
	 * @param string $column     Column name: last_coupon_at or last_sequence_at.
	 * @param int    $days       Cooldown in days.
	 * @param int    $now        Current Unix timestamp.
	 */
	public static function within( string $email_hash, string $column, int $days, int $now ): bool {
		if ( $days <= 0 || ! in_array( $column, array( 'last_coupon_at', 'last_sequence_at' ), true ) ) {
			return false;
		}

		$row = self::find( $email_hash );

		if ( null === $row || empty( $row[ $column ] ) ) {
			return false;
		}

		$time = strtotime( (string) $row[ $column ] . ' UTC' );

		return false !== $time && $time > $now - $days * DAY_IN_SECONDS;
	}

	/**
	 * Set one timestamp column, creating the contact when needed.
	 *
	 * @param string $email_hash Hashed address.
	 * @param string $column     Column name: optout_at, last_coupon_at or last_sequence_at.
	 */
	public static function touch( string $email_hash, string $column ): void {
		global $wpdb;

		if ( '' === $email_hash || ! in_array( $column, array( 'optout_at', 'last_coupon_at', 'last_sequence_at' ), true ) ) {
			return;
		}

		$now = Carts::now();

		if ( null === self::find( $email_hash ) ) {
			$wpdb->insert(
				Tables::contacts(),
				array(
					'email_hash' => $email_hash,
					$column      => $now,
					'updated_at' => $now,
				)
			);

			return;
		}

		$wpdb->update(
			Tables::contacts(),
			array(
				$column      => $now,
				'updated_at' => $now,
			),
			array( 'email_hash' => $email_hash )
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
