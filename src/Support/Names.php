<?php
/**
 * First-name cleanup for personalization.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Support;

/**
 * Turns whatever a shopper typed into a first name that is safe to print, or nothing.
 */
final class Names {

	/**
	 * Return a cleaned first name or an empty string.
	 *
	 * "MAJA NOVAK" becomes "Maja", "ana" becomes "Ana", "x1" or "a" become "".
	 *
	 * @param string $name Raw name.
	 */
	public static function first( string $name ): string {
		$name  = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $name ) ) );
		$parts = explode( ' ', $name );
		$first = $parts[0];

		if ( ! preg_match( "/^[\p{L}][\p{L}'\-]{1,29}$/u", $first ) ) {
			return '';
		}

		if ( mb_strtoupper( $first ) === $first || mb_strtolower( $first ) === $first ) {
			$first = mb_convert_case( $first, MB_CASE_TITLE, 'UTF-8' );
		}

		return $first;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
