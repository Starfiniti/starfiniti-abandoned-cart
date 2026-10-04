<?php
/**
 * Plugin-owned table names and schema.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Store;

/**
 * Defines the plugin-owned storage.
 */
final class Tables {

	/**
	 * Return the captured carts table name.
	 */
	public static function carts(): string {
		global $wpdb;

		return $wpdb->prefix . 'sfac_carts';
	}

	/**
	 * Return the events table name.
	 */
	public static function events(): string {
		global $wpdb;

		return $wpdb->prefix . 'sfac_events';
	}

	/**
	 * Return the contacts table name (hashed e-mail addresses only).
	 */
	public static function contacts(): string {
		global $wpdb;

		return $wpdb->prefix . 'sfac_contacts';
	}

	/**
	 * Create or update all tables.
	 */
	public static function install(): void {
		global $wpdb;

		if ( ! defined( 'ABSPATH' ) || ! is_object( $wpdb ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$carts    = self::carts();
		$events   = self::events();
		$contacts = self::contacts();

		dbDelta(
			"CREATE TABLE {$carts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				session_key varchar(64) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				email varchar(190) NOT NULL DEFAULT '',
				email_hash char(64) NOT NULL DEFAULT '',
				locale varchar(10) NOT NULL DEFAULT '',
				currency varchar(10) NOT NULL DEFAULT '',
				first_name varchar(100) NOT NULL DEFAULT '',
				context longtext NULL,
				cart longtext NULL,
				cart_hash char(32) NOT NULL DEFAULT '',
				items_total decimal(26,8) NOT NULL DEFAULT 0,
				shipping_total decimal(26,8) NOT NULL DEFAULT 0,
				segment varchar(32) NOT NULL DEFAULT 'default',
				variant char(1) NOT NULL DEFAULT 'A',
				status varchar(20) NOT NULL DEFAULT 'active',
				step tinyint(3) unsigned NOT NULL DEFAULT 0,
				attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
				next_send_at datetime NULL,
				last_activity_at datetime NOT NULL,
				coupon_id bigint(20) unsigned NOT NULL DEFAULT 0,
				coupon_code varchar(40) NOT NULL DEFAULT '',
				coupon_expires_at datetime NULL,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				order_total decimal(26,8) NOT NULL DEFAULT 0,
				payment_check tinyint(3) unsigned NOT NULL DEFAULT 0,
				clicks smallint(5) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status_next (status,next_send_at),
				KEY email_hash (email_hash),
				KEY session_key (session_key),
				KEY order_id (order_id),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$events} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				cart_id bigint(20) unsigned NOT NULL DEFAULT 0,
				type varchar(32) NOT NULL DEFAULT '',
				step tinyint(3) unsigned NOT NULL DEFAULT 0,
				variant char(1) NOT NULL DEFAULT '',
				meta longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY cart_id (cart_id),
				KEY type_created (type,created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$contacts} (
				email_hash char(64) NOT NULL,
				optout_at datetime NULL,
				last_coupon_at datetime NULL,
				last_sequence_at datetime NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (email_hash)
			) {$charset};"
		);
	}

	/**
	 * Drop all tables for explicit uninstall cleanup.
	 */
	public static function drop(): void {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return;
		}

		$carts    = self::carts();
		$events   = self::events();
		$contacts = self::contacts();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$events}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$carts}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$contacts}" );
		// phpcs:enable
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
