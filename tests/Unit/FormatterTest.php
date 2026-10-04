<?php
/**
 * Language-aware formatting.
 *
 * @package StarfinitiAbandonedCart
 */

declare(strict_types=1);

namespace Starfiniti\AbandonedCart\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Starfiniti\AbandonedCart\Email\Formatter;

/**
 * Covers dates, deadlines and discounts in Slovenian, English and German.
 */
final class FormatterTest extends TestCase {

	/**
	 * Thursday 15 October 2026, 20:00 in Ljubljana.
	 */
	private function thursday_evening(): int {
		return ( new DateTimeImmutable( '2026-10-15 20:00', new DateTimeZone( 'Europe/Ljubljana' ) ) )->getTimestamp();
	}

	/**
	 * Deadlines use the genitive weekday in Slovenian.
	 */
	public function test_until(): void {
		$zone = new DateTimeZone( 'Europe/Ljubljana' );

		self::assertSame( 'do četrtka, 15. 10., ob 20.00', Formatter::until( $this->thursday_evening(), $zone, 'sl' ) );
		self::assertSame( 'until Thursday 15 Oct at 8 pm', Formatter::until( $this->thursday_evening(), $zone, 'en' ) );
		self::assertSame( 'bis Donnerstag, 15. 10., 20:00 Uhr', Formatter::until( $this->thursday_evening(), $zone, 'de' ) );
		self::assertSame( 'until Thursday 15 Oct at 8 pm', Formatter::until( $this->thursday_evening(), $zone, 'hr' ) );
	}

	/**
	 * Weekday and date.
	 */
	public function test_day_date(): void {
		$zone   = new DateTimeZone( 'Europe/Ljubljana' );
		$friday = ( new DateTimeImmutable( '2026-10-16 12:00', $zone ) )->getTimestamp();

		self::assertSame( 'petek, 16. 10.', Formatter::day_date( $friday, $zone, 'sl' ) );
		self::assertSame( 'Friday 16 Oct', Formatter::day_date( $friday, $zone, 'en' ) );
		self::assertSame( 'Freitag, 16. 10.', Formatter::day_date( $friday, $zone, 'de' ) );
	}

	/**
	 * Percent discounts follow each language's spacing and decimals.
	 */
	public function test_discount(): void {
		self::assertSame( "10\u{00A0}%", Formatter::discount( 'percent', 10.0, 'sl' ) );
		self::assertSame( '10%', Formatter::discount( 'percent', 10.0, 'en' ) );
		self::assertSame( "12,5\u{00A0}%", Formatter::discount( 'percent', 12.5, 'de' ) );
		self::assertSame( '12.5%', Formatter::discount( 'percent', 12.5, 'en' ) );
	}

	/**
	 * Times with minutes.
	 */
	public function test_time_with_minutes(): void {
		$zone = new DateTimeZone( 'Europe/Ljubljana' );
		$time = ( new DateTimeImmutable( '2026-10-15 09:30', $zone ) )->getTimestamp();

		self::assertSame( '9:30 am', Formatter::time( $time, $zone, 'en' ) );
		self::assertSame( '9.30', Formatter::time( $time, $zone, 'sl' ) );
	}
}
