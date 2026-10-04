<?php
/**
 * Schema installation and upgrades.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Lifecycle;

use Starfiniti\AbandonedCart\Store\Tables;

/**
 * Creates and upgrades plugin-owned tables and options.
 */
final class Installer {

	/**
	 * Option with the installed plugin version.
	 */
	public const PLUGIN_VERSION_OPTION = 'sfac_version';

	/**
	 * Option with the installed schema version.
	 */
	public const SCHEMA_VERSION_OPTION = 'sfac_db_version';

	/**
	 * Option with the secret used to sign links and hash e-mail addresses.
	 */
	public const SECRET_OPTION = 'sfac_secret';

	/**
	 * Current schema version.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Install or upgrade when the stored schema is older than the code.
	 */
	public static function install_or_upgrade(): void {
		if ( '' === (string) get_option( self::SECRET_OPTION, '' ) ) {
			add_option( self::SECRET_OPTION, wp_generate_password( 64, false, false ), '', false );
		}

		if ( (int) get_option( self::SCHEMA_VERSION_OPTION, 0 ) >= self::SCHEMA_VERSION && SFAC_VERSION === get_option( self::PLUGIN_VERSION_OPTION ) ) {
			return;
		}

		Tables::install();
		update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false );
		update_option( self::PLUGIN_VERSION_OPTION, SFAC_VERSION, false );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
