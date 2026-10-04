<?php
/**
 * Explicit opt-in uninstall cleanup.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Lifecycle;

use Starfiniti\AbandonedCart\Coupons\CouponService;
use Starfiniti\AbandonedCart\Sequence\Scheduler;
use Starfiniti\AbandonedCart\Settings;
use Starfiniti\AbandonedCart\Store\Tables;

/**
 * Deletes only plugin-owned data on sites that explicitly opted in.
 */
final class Uninstaller {

	/**
	 * Run uninstall cleanup across the current installation.
	 */
	public static function run(): void {
		if ( ! is_multisite() ) {
			self::maybe_delete_current_site_data();
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );

			try {
				self::maybe_delete_current_site_data();
			} finally {
				restore_current_blog();
			}
		}
	}

	/**
	 * Delete current-site data only after explicit consent.
	 */
	private static function maybe_delete_current_site_data(): void {
		Scheduler::unschedule();

		if ( ! Settings::should_delete_data_on_uninstall() ) {
			return;
		}

		CouponService::delete_all_unused();
		delete_option( Installer::PLUGIN_VERSION_OPTION );
		delete_option( Installer::SCHEMA_VERSION_OPTION );
		delete_option( Installer::SECRET_OPTION );
		delete_option( Settings::OPTION_NAME );
		Tables::drop();
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
