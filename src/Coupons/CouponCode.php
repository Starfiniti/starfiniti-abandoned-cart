<?php
/**
 * Coupon code generation.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Coupons;

/**
 * Creates readable codes like "NIV-7KQ3MX" without look-alike characters.
 */
final class CouponCode {

	/**
	 * Characters used in codes: no 0/O, 1/I.
	 */
	public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	/**
	 * Length of the random part.
	 */
	public const LENGTH = 6;

	/**
	 * Build a code.
	 *
	 * @param string   $prefix     Prefix; non-alphanumeric characters are removed.
	 * @param callable $random_int Function ( int $min, int $max ): int.
	 */
	public static function make( string $prefix, callable $random_int ): string {
		$prefix = strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', $prefix ) );
		$last   = strlen( self::ALPHABET ) - 1;
		$chars  = '';

		for ( $i = 0; $i < self::LENGTH; $i++ ) {
			$chars .= self::ALPHABET[ (int) $random_int( 0, $last ) ];
		}

		return '' === $prefix ? $chars : $prefix . '-' . $chars;
	}

	/**
	 * Derive a prefix from the store name: "Nina & Valentin" becomes "NIN".
	 *
	 * @param string $store_name Store name.
	 */
	public static function default_prefix( string $store_name ): string {
		$letters = strtoupper( (string) preg_replace( '/[^A-Za-z]/', '', remove_accents( $store_name ) ) );

		return strlen( $letters ) >= 3 ? substr( $letters, 0, 3 ) : 'CART';
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
