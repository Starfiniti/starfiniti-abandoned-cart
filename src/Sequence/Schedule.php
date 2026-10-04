<?php
/**
 * Time calculations for the sequence.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Sequence;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Pure functions: every input is passed in, nothing reads the clock or the database.
 */
final class Schedule {

	/**
	 * Time of a step that waits for inactivity.
	 *
	 * @param int $last_activity Unix timestamp of the last cart or checkout activity.
	 * @param int $minutes       Delay in minutes.
	 */
	public static function after_activity( int $last_activity, int $minutes ): int {
		return $last_activity + max( 0, $minutes ) * 60;
	}

	/**
	 * Move a time out of the quiet hours to the end of the quiet period.
	 *
	 * @param int          $timestamp Planned Unix timestamp.
	 * @param DateTimeZone $timezone  Store timezone.
	 * @param int          $start     Quiet period start hour (0-23).
	 * @param int          $end       Quiet period end hour (0-23).
	 * @param int          $jitter    Seconds added after the quiet period, spreads sending.
	 */
	public static function outside_quiet_hours( int $timestamp, DateTimeZone $timezone, int $start, int $end, int $jitter = 0 ): int {
		if ( $start === $end ) {
			return $timestamp;
		}

		$local = self::local( $timestamp, $timezone );
		$hour  = (int) $local->format( 'G' );
		$quiet = $start < $end ? ( $hour >= $start && $hour < $end ) : ( $hour >= $start || $hour < $end );

		if ( ! $quiet ) {
			return $timestamp;
		}

		$target = $local->setTime( $end, 0 );

		if ( $target->getTimestamp() <= $timestamp ) {
			$target = $local->modify( '+1 day' )->setTime( $end, 0 );
		}

		return $target->getTimestamp() + max( 0, $jitter );
	}

	/**
	 * Coupon expiry: the local date of sending plus a number of days, at a fixed hour.
	 *
	 * @param int          $sent     Unix timestamp when the coupon e-mail is sent.
	 * @param DateTimeZone $timezone Store timezone.
	 * @param int          $days     Validity in days.
	 * @param int          $hour     Expiry hour (0-23).
	 */
	public static function coupon_expiry( int $sent, DateTimeZone $timezone, int $days, int $hour ): int {
		return self::local( $sent, $timezone )->modify( '+' . max( 1, $days ) . ' days' )->setTime( $hour, 0 )->getTimestamp();
	}

	/**
	 * Time of the "last day" e-mail: the expiry date at a fixed hour, always before expiry and after now.
	 *
	 * @param int          $expiry   Coupon expiry Unix timestamp.
	 * @param DateTimeZone $timezone Store timezone.
	 * @param int          $hour     Sending hour (0-23).
	 * @param int          $now      Current Unix timestamp.
	 */
	public static function last_day( int $expiry, DateTimeZone $timezone, int $hour, int $now ): int {
		$time = self::local( $expiry, $timezone )->setTime( $hour, 0 )->getTimestamp();

		if ( $time >= $expiry ) {
			$time = $expiry - 3600;
		}

		if ( $time <= $now ) {
			$time = $now + 300;
		}

		return $time;
	}

	/**
	 * Check whether a Y-m-d date lies before today in the store timezone.
	 *
	 * @param string       $date     Date in Y-m-d format; anything else is never past.
	 * @param DateTimeZone $timezone Store timezone.
	 * @param int          $now      Current Unix timestamp.
	 */
	public static function is_past_date( string $date, DateTimeZone $timezone, int $now ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}

		return $date < self::local( $now, $timezone )->format( 'Y-m-d' );
	}

	/**
	 * Convert a timestamp to a local date-time.
	 *
	 * @param int          $timestamp Unix timestamp.
	 * @param DateTimeZone $timezone  Timezone.
	 */
	private static function local( int $timestamp, DateTimeZone $timezone ): DateTimeImmutable {
		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
