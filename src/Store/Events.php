<?php
/**
 * Event log repository.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Store;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; names come from Tables.

/**
 * Appends events and summarizes them for reporting.
 */
final class Events {

	/**
	 * Append an event. Never stores e-mail addresses or names.
	 *
	 * @param int                  $cart_id Cart row identifier.
	 * @param string               $type    Event type.
	 * @param int                  $step    Sequence step, 0 when not applicable.
	 * @param string               $variant Subject variant.
	 * @param array<string, mixed> $meta    Non-personal details.
	 */
	public static function add( int $cart_id, string $type, int $step = 0, string $variant = '', array $meta = array() ): void {
		global $wpdb;

		$wpdb->insert(
			Tables::events(),
			array(
				'cart_id'    => $cart_id,
				'type'       => substr( $type, 0, 32 ),
				'step'       => max( 0, min( 255, $step ) ),
				'variant'    => substr( $variant, 0, 1 ),
				'meta'       => array() === $meta ? null : wp_json_encode( $meta ),
				'created_at' => Carts::now(),
			)
		);
	}

	/**
	 * Count events per type and step since a date.
	 *
	 * @param int $since Unix timestamp.
	 * @return list<array{type: string, step: int, variant: string, total: int}>
	 */
	public static function summary( int $since ): array {
		global $wpdb;

		$table = Tables::events();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT type, step, variant, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY type, step, variant ORDER BY type, step, variant", Carts::now( $since ) ),
			ARRAY_A
		);
		$out   = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$out[] = array(
				'type'    => (string) $row['type'],
				'step'    => (int) $row['step'],
				'variant' => (string) $row['variant'],
				'total'   => (int) $row['total'],
			);
		}

		return $out;
	}

	/**
	 * Delete events created before a cutoff.
	 *
	 * @param int $timestamp Cutoff Unix timestamp.
	 */
	public static function delete_older_than( int $timestamp ): int {
		global $wpdb;

		$table = Tables::events();

		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", Carts::now( $timestamp ) ) );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
