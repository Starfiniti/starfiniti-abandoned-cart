<?php
/**
 * Plugin deactivation.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Lifecycle;

use Starfiniti\AbandonedCart\Sequence\Scheduler;

/**
 * Stops scheduled work. Data and coupons stay until uninstall.
 */
final class Deactivator {

	/**
	 * Unschedule the recurring tick.
	 */
	public static function deactivate(): void {
		Scheduler::unschedule();
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
