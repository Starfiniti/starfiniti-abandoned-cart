<?php
/**
 * Sequence time calculations.
 *
 * @package StarfinitiAbandonedCart
 */

declare(strict_types=1);

namespace Starfiniti\AbandonedCart\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Starfiniti\AbandonedCart\Sequence\Schedule;

/**
 * Covers quiet hours, coupon expiry and the last-day e-mail.
 */
final class ScheduleTest extends TestCase {

	/**
	 * Ljubljana timezone.
	 *
	 * @var DateTimeZone
	 */
	private DateTimeZone $zone;

	/**
	 * Prepare the timezone.
	 */
	protected function setUp(): void {
		$this->zone = new DateTimeZone( 'Europe/Ljubljana' );
	}

	/**
	 * Build a timestamp from local time.
	 *
	 * @param string $local Local date-time.
	 */
	private function at( string $local ): int {
		return ( new DateTimeImmutable( $local, $this->zone ) )->getTimestamp();
	}

	/**
	 * Format a timestamp in local time.
	 *
	 * @param int $timestamp Unix timestamp.
	 */
	private function local( int $timestamp ): string {
		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $this->zone )->format( 'Y-m-d H:i' );
	}

	/**
	 * Delays count from the last activity.
	 */
	public function test_after_activity_adds_minutes(): void {
		self::assertSame( 1000 + 3600, Schedule::after_activity( 1000, 60 ) );
		self::assertSame( 1000, Schedule::after_activity( 1000, -5 ) );
	}

	/**
	 * Late evening moves to 08:00 the next day, early morning to 08:00 the same day.
	 */
	public function test_quiet_hours_move_to_morning(): void {
		self::assertSame( '2026-10-13 08:00', $this->local( Schedule::outside_quiet_hours( $this->at( '2026-10-12 22:30' ), $this->zone, 21, 8 ) ) );
		self::assertSame( '2026-10-12 08:00', $this->local( Schedule::outside_quiet_hours( $this->at( '2026-10-12 03:10' ), $this->zone, 21, 8 ) ) );
		self::assertSame( '2026-10-12 08:00', $this->local( Schedule::outside_quiet_hours( $this->at( '2026-10-12 07:59' ), $this->zone, 21, 8 ) ) );
	}

	/**
	 * Daytime is untouched and equal hours disable quiet time.
	 */
	public function test_daytime_is_unchanged(): void {
		$noon = $this->at( '2026-10-12 12:00' );

		self::assertSame( $noon, Schedule::outside_quiet_hours( $noon, $this->zone, 21, 8 ) );
		self::assertSame( $this->at( '2026-10-12 08:00' ), Schedule::outside_quiet_hours( $this->at( '2026-10-12 08:00' ), $this->zone, 21, 8 ) );
		self::assertSame( $this->at( '2026-10-12 23:00' ), Schedule::outside_quiet_hours( $this->at( '2026-10-12 23:00' ), $this->zone, 8, 8 ) );
	}

	/**
	 * Jitter is added after the quiet period only.
	 */
	public function test_jitter_only_after_quiet_period(): void {
		self::assertSame( $this->at( '2026-10-13 08:00' ) + 120, Schedule::outside_quiet_hours( $this->at( '2026-10-12 21:00' ), $this->zone, 21, 8, 120 ) );
	}

	/**
	 * A coupon sent on Tuesday evening expires on Thursday at 20:00.
	 */
	public function test_coupon_expiry_is_second_day_at_hour(): void {
		self::assertSame( '2026-10-15 20:00', $this->local( Schedule::coupon_expiry( $this->at( '2026-10-13 18:20' ), $this->zone, 2, 20 ) ) );
	}

	/**
	 * Expiry across the end of daylight saving time keeps the local hour.
	 */
	public function test_coupon_expiry_across_dst_change(): void {
		self::assertSame( '2026-10-26 20:00', $this->local( Schedule::coupon_expiry( $this->at( '2026-10-24 09:00' ), $this->zone, 2, 20 ) ) );
	}

	/**
	 * The last-day e-mail goes out at 10:00 on the expiry day.
	 */
	public function test_last_day_at_hour_before_expiry(): void {
		$expiry = $this->at( '2026-10-15 20:00' );

		self::assertSame( '2026-10-15 10:00', $this->local( Schedule::last_day( $expiry, $this->zone, 10, $this->at( '2026-10-13 18:20' ) ) ) );
	}

	/**
	 * The last-day e-mail is never planned in the past or after expiry.
	 */
	public function test_last_day_is_clamped(): void {
		$expiry = $this->at( '2026-10-15 20:00' );
		$now    = $this->at( '2026-10-15 11:00' );

		self::assertSame( $now + 300, Schedule::last_day( $expiry, $this->zone, 10, $now ) );
		self::assertSame( $expiry - 3600, Schedule::last_day( $expiry, $this->zone, 22, $this->at( '2026-10-13 18:00' ) ) );
	}

	/**
	 * Past delivery dates are detected in local time.
	 */
	public function test_past_date(): void {
		$now = $this->at( '2026-10-15 00:30' );

		self::assertTrue( Schedule::is_past_date( '2026-10-14', $this->zone, $now ) );
		self::assertFalse( Schedule::is_past_date( '2026-10-15', $this->zone, $now ) );
		self::assertFalse( Schedule::is_past_date( 'tomorrow', $this->zone, $now ) );
	}
}
