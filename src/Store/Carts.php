<?php
/**
 * Captured cart repository.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Store;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; names come from Tables.

/**
 * Reads and writes rows of the captured carts table.
 */
final class Carts {

	/**
	 * Statuses that still receive e-mails.
	 */
	public const STATUS_ACTIVE = 'active';

	/**
	 * Return the current UTC time in MySQL format.
	 *
	 * @param int|null $timestamp Optional Unix timestamp.
	 */
	public static function now( ?int $timestamp = null ): string {
		return gmdate( 'Y-m-d H:i:s', $timestamp ?? time() );
	}

	/**
	 * Find one row.
	 *
	 * @param int $id Row identifier.
	 * @return array<string, mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;

		$table = Tables::carts();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find the newest active row for a WooCommerce session.
	 *
	 * @param string $session_key WooCommerce customer session key.
	 * @return array<string, mixed>|null
	 */
	public static function find_active_by_session( string $session_key ): ?array {
		global $wpdb;

		if ( '' === $session_key ) {
			return null;
		}

		$table = Tables::carts();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE session_key = %s AND status = %s ORDER BY id DESC LIMIT 1", $session_key, self::STATUS_ACTIVE ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a row.
	 *
	 * @param array<string, mixed> $data Column values.
	 */
	public static function insert( array $data ): int {
		global $wpdb;

		$now  = self::now();
		$data = array_merge(
			array(
				'created_at' => $now,
				'updated_at' => $now,
			),
			$data
		);

		$wpdb->insert( Tables::carts(), $data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a row.
	 *
	 * @param int                  $id   Row identifier.
	 * @param array<string, mixed> $data Column values.
	 */
	public static function update( int $id, array $data ): void {
		global $wpdb;

		$data['updated_at'] = self::now();
		$wpdb->update( Tables::carts(), $data, array( 'id' => $id ) );
	}

	/**
	 * Update a row only while it is still active.
	 *
	 * @param int                  $id   Row identifier.
	 * @param array<string, mixed> $data Column values.
	 */
	public static function update_if_active( int $id, array $data ): void {
		global $wpdb;

		$data['updated_at'] = self::now();
		$wpdb->update(
			Tables::carts(),
			$data,
			array(
				'id'     => $id,
				'status' => self::STATUS_ACTIVE,
			)
		);
	}

	/**
	 * Mark other active rows of the same address as replaced.
	 *
	 * @param string $email_hash Hashed address.
	 * @param int    $keep_id    Row that stays active.
	 */
	public static function replace_others( string $email_hash, int $keep_id ): void {
		global $wpdb;

		$table = Tables::carts();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'replaced', next_send_at = NULL, updated_at = %s WHERE email_hash = %s AND status = %s AND id <> %d",
				self::now(),
				$email_hash,
				self::STATUS_ACTIVE,
				$keep_id
			)
		);
	}

	/**
	 * Return active rows whose next e-mail is due.
	 *
	 * @param int $timestamp Current Unix timestamp.
	 * @param int $limit     Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public static function due( int $timestamp, int $limit ): array {
		global $wpdb;

		$table = Tables::carts();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND next_send_at IS NOT NULL AND next_send_at <= %s ORDER BY next_send_at ASC LIMIT %d",
				self::STATUS_ACTIVE,
				self::now( $timestamp ),
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Find rows that an order can close: active ones, or finished ones still inside the attribution window.
	 *
	 * @param string $session_key Session key, may be empty.
	 * @param string $email_hash  Hashed address, may be empty.
	 * @param int    $cart_id     Row identifier from the session, may be 0.
	 * @param int    $since       Unix timestamp; finished rows updated before it are ignored.
	 * @return list<array<string, mixed>>
	 */
	public static function open_matches( string $session_key, string $email_hash, int $cart_id, int $since ): array {
		global $wpdb;

		$table = Tables::carts();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ( status = %s OR ( status IN ('done','expired') AND updated_at >= %s ) ) AND ( id = %d OR ( session_key <> '' AND session_key = %s ) OR ( email_hash <> '' AND email_hash = %s ) )",
				self::STATUS_ACTIVE,
				self::now( $since ),
				$cart_id,
				$session_key,
				$email_hash
			),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Return ordered rows whose order should be checked for a failed payment.
	 *
	 * @param int $older_than Unix timestamp; orders newer than this are not checked yet.
	 * @param int $newer_than Unix timestamp; older orders are skipped.
	 * @return list<array<string, mixed>>
	 */
	public static function payment_checks( int $older_than, int $newer_than ): array {
		global $wpdb;

		$table = Tables::carts();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status IN ('ordered','recovered') AND payment_check = 0 AND order_id > 0 AND updated_at <= %s AND updated_at >= %s LIMIT 20",
				self::now( $older_than ),
				self::now( $newer_than )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Return rows whose coupon expired but was not cleaned up.
	 *
	 * @param int $timestamp Current Unix timestamp.
	 * @return list<array<string, mixed>>
	 */
	public static function expired_coupons( int $timestamp ): array {
		global $wpdb;

		$table = Tables::carts();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE coupon_id > 0 AND coupon_expires_at IS NOT NULL AND coupon_expires_at < %s LIMIT 50",
				self::now( $timestamp )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Delete rows created before a cutoff.
	 *
	 * @param int $timestamp Cutoff Unix timestamp.
	 */
	public static function delete_older_than( int $timestamp ): int {
		global $wpdb;

		$table = Tables::carts();

		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", self::now( $timestamp ) ) );
	}

	/**
	 * Delete every row of an address (privacy eraser).
	 *
	 * @param string $email_hash Hashed address.
	 */
	public static function delete_by_email_hash( string $email_hash ): int {
		global $wpdb;

		$table = Tables::carts();

		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE email_hash = %s", $email_hash ) );
	}

	/**
	 * Return rows of an address (privacy exporter).
	 *
	 * @param string $email_hash Hashed address.
	 * @return list<array<string, mixed>>
	 */
	public static function by_email_hash( string $email_hash ): array {
		global $wpdb;

		$table = Tables::carts();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email_hash = %s ORDER BY id DESC LIMIT 100", $email_hash ), ARRAY_A );

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Return the newest rows for the admin overview.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public static function latest( int $limit ): array {
		global $wpdb;

		$table = Tables::carts();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Count rows per status since a date.
	 *
	 * @param int $since Unix timestamp.
	 * @return array<string, int>
	 */
	public static function status_counts( int $since ): array {
		global $wpdb;

		$table  = Tables::carts();
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY status", self::now( $since ) ), ARRAY_A );
		$counts = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Sum recovered order totals since a date.
	 *
	 * @param int $since Unix timestamp.
	 */
	public static function recovered_revenue( int $since ): float {
		global $wpdb;

		$table = Tables::carts();

		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(order_total), 0) FROM {$table} WHERE status = 'recovered' AND created_at >= %s", self::now( $since ) ) );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
