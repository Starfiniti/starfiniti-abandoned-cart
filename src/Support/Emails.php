<?php
/**
 * E-mail address helpers.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Support;

/**
 * Normalizes, hashes and masks e-mail addresses.
 */
final class Emails {

	/**
	 * Normalize an address; returns an empty string when invalid.
	 *
	 * @param string $email Raw address.
	 */
	public static function normalize( string $email ): string {
		$email = strtolower( trim( $email ) );

		if ( strlen( $email ) > 190 || ! preg_match( '/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $email ) ) {
			return '';
		}

		if ( function_exists( 'is_email' ) && ! is_email( $email ) ) {
			return '';
		}

		return $email;
	}

	/**
	 * Hash an address with the site secret.
	 *
	 * @param string $email  Normalized address.
	 * @param string $secret Site secret.
	 */
	public static function hash( string $email, string $secret ): string {
		return hash_hmac( 'sha256', strtolower( trim( $email ) ), $secret );
	}

	/**
	 * Mask an address for admin lists: "ma***@example.com".
	 *
	 * @param string $email Address.
	 */
	public static function mask( string $email ): string {
		$at = strpos( $email, '@' );

		if ( false === $at ) {
			return '***';
		}

		return substr( $email, 0, min( 2, $at ) ) . '***' . substr( $email, $at );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
