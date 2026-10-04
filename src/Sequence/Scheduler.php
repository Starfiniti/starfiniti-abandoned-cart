<?php
/**
 * Action Scheduler registration.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Sequence;

/**
 * Keeps one recurring "tick" action that sends due e-mails and cleans up.
 */
final class Scheduler {

	/**
	 * Recurring action hook.
	 */
	public const HOOK = 'sfac_tick';

	/**
	 * Action Scheduler group.
	 */
	public const GROUP = 'starfiniti-abandoned-cart';

	/**
	 * Interval in seconds.
	 */
	public const INTERVAL = 300;

	/**
	 * Option with the time of the last schedule check.
	 */
	public const CHECKED_OPTION = 'sfac_schedule_checked';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( self::HOOK, array( Runner::class, 'tick' ) );
		add_action( 'init', array( self::class, 'ensure' ), 20 );
	}

	/**
	 * Schedule the recurring action when it is missing.
	 */
	public static function ensure(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		// Check at most once per hour; the option is autoloaded, so most requests cost no query.
		if ( (int) get_option( self::CHECKED_OPTION, 0 ) > time() - HOUR_IN_SECONDS ) {
			return;
		}

		update_option( self::CHECKED_OPTION, time(), true );

		if ( as_has_scheduled_action( self::HOOK, array(), self::GROUP ) ) {
			return;
		}

		as_schedule_recurring_action( time() + 60, self::INTERVAL, self::HOOK, array(), self::GROUP, true );
	}

	/**
	 * Remove the recurring action.
	 */
	public static function unschedule(): void {
		delete_option( self::CHECKED_OPTION );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
