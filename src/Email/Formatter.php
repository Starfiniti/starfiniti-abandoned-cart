<?php
/**
 * Language-aware formatting.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Email;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Formats money, discounts, dates and times the way each language writes them.
 * Unknown languages use English formats.
 */
final class Formatter {

	/**
	 * Weekday names (0 = Sunday).
	 */
	private const DAYS = array(
		'en'     => array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
		'sl'     => array( 'nedelja', 'ponedeljek', 'torek', 'sreda', 'četrtek', 'petek', 'sobota' ),
		'sl_gen' => array( 'nedelje', 'ponedeljka', 'torka', 'srede', 'četrtka', 'petka', 'sobote' ),
		'de'     => array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' ),
	);

	/**
	 * Short English month names.
	 */
	private const MONTHS = array( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' );

	/**
	 * Format money as plain text with the store currency settings.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 */
	public static function money( float $amount, string $currency = '' ): string {
		if ( function_exists( 'wc_price' ) ) {
			$args = '' !== $currency ? array( 'currency' => $currency ) : array();

			return trim( html_entity_decode( wp_strip_all_tags( wc_price( $amount, $args ) ), ENT_QUOTES, 'UTF-8' ) );
		}

		return number_format( $amount, 2, ',', '.' ) . ( '' !== $currency ? ' ' . $currency : '' );
	}

	/**
	 * Format a discount: "10 %" (sl, de), "10%" (en) or a money amount.
	 *
	 * @param string $type     percent or fixed_cart.
	 * @param float  $amount   Discount amount.
	 * @param string $language Two-letter code.
	 * @param string $currency Currency code.
	 */
	public static function discount( string $type, float $amount, string $language, string $currency = '' ): string {
		if ( 'percent' !== $type ) {
			return self::money( $amount, $currency );
		}

		$english   = 'en' === self::language( $language );
		$separator = $english ? '.' : ',';
		$number    = rtrim( rtrim( number_format( $amount, 2, $separator, '' ), '0' ), $separator );

		return $english ? $number . '%' : $number . "\u{00A0}%";
	}

	/**
	 * Weekday and date: "petek, 16. 10.", "Friday 16 Oct", "Freitag, 16. 10.".
	 *
	 * @param int          $timestamp Unix timestamp.
	 * @param DateTimeZone $timezone  Store timezone.
	 * @param string       $language  Two-letter code.
	 */
	public static function day_date( int $timestamp, DateTimeZone $timezone, string $language ): string {
		$local = self::local( $timestamp, $timezone );
		$day   = (int) $local->format( 'w' );

		return match ( self::language( $language ) ) {
			'sl' => self::DAYS['sl'][ $day ] . ', ' . $local->format( 'j. n.' ),
			'de' => self::DAYS['de'][ $day ] . ', ' . $local->format( 'j. n.' ),
			default => self::DAYS['en'][ $day ] . ' ' . $local->format( 'j' ) . ' ' . self::MONTHS[ (int) $local->format( 'n' ) - 1 ],
		};
	}

	/**
	 * Time of day: "20.00", "8 pm", "20:00 Uhr".
	 *
	 * @param int          $timestamp Unix timestamp.
	 * @param DateTimeZone $timezone  Store timezone.
	 * @param string       $language  Two-letter code.
	 */
	public static function time( int $timestamp, DateTimeZone $timezone, string $language ): string {
		$local = self::local( $timestamp, $timezone );

		return match ( self::language( $language ) ) {
			'sl' => $local->format( 'G.i' ),
			'de' => $local->format( 'G:i' ) . ' Uhr',
			default => '00' === $local->format( 'i' ) ? $local->format( 'g a' ) : $local->format( 'g:i a' ),
		};
	}

	/**
	 * Deadline phrase: "do četrtka, 15. 10., ob 20.00", "until Thursday 15 Oct at 8 pm",
	 * "bis Donnerstag, 15. 10., 20:00 Uhr".
	 *
	 * @param int          $timestamp Unix timestamp.
	 * @param DateTimeZone $timezone  Store timezone.
	 * @param string       $language  Two-letter code.
	 */
	public static function until( int $timestamp, DateTimeZone $timezone, string $language ): string {
		$local = self::local( $timestamp, $timezone );
		$day   = (int) $local->format( 'w' );
		$time  = self::time( $timestamp, $timezone, $language );

		return match ( self::language( $language ) ) {
			'sl' => 'do ' . self::DAYS['sl_gen'][ $day ] . ', ' . $local->format( 'j. n.' ) . ', ob ' . $time,
			'de' => 'bis ' . self::DAYS['de'][ $day ] . ', ' . $local->format( 'j. n.' ) . ', ' . $time,
			default => 'until ' . self::day_date( $timestamp, $timezone, 'en' ) . ' at ' . $time,
		};
	}

	/**
	 * Return a supported formatting language.
	 *
	 * @param string $language Two-letter code.
	 */
	private static function language( string $language ): string {
		return in_array( $language, array( 'sl', 'de' ), true ) ? $language : 'en';
	}

	/**
	 * Convert a timestamp to local time.
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
