<?php
/**
 * Signed link tokens.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Support;

/**
 * Creates and verifies "{id}-{signature}" tokens for restore and unsubscribe links.
 *
 * Nothing secret is stored per cart: the signature is an HMAC of the row id and
 * its creation time with the site secret.
 */
final class Tokens {

	/**
	 * Create a token.
	 *
	 * @param int    $cart_id    Cart row identifier.
	 * @param string $created_at Row creation time (UTC, MySQL format).
	 * @param string $secret     Site secret.
	 */
	public static function create( int $cart_id, string $created_at, string $secret ): string {
		return $cart_id . '-' . self::signature( $cart_id, $created_at, $secret );
	}

	/**
	 * Return the cart id from a token, or 0 when malformed.
	 *
	 * @param string $token Token from a link.
	 */
	public static function cart_id( string $token ): int {
		if ( ! preg_match( '/^(\d{1,19})-([a-f0-9]{32})$/', $token, $matches ) ) {
			return 0;
		}

		return (int) $matches[1];
	}

	/**
	 * Verify a token against its row.
	 *
	 * @param string $token      Token from a link.
	 * @param string $created_at Row creation time.
	 * @param string $secret     Site secret.
	 */
	public static function verify( string $token, string $created_at, string $secret ): bool {
		$cart_id = self::cart_id( $token );

		if ( 0 === $cart_id ) {
			return false;
		}

		return hash_equals( self::create( $cart_id, $created_at, $secret ), $token );
	}

	/**
	 * Compute the signature part.
	 *
	 * @param int    $cart_id    Cart row identifier.
	 * @param string $created_at Row creation time.
	 * @param string $secret     Site secret.
	 */
	private static function signature( int $cart_id, string $created_at, string $secret ): string {
		return substr( hash_hmac( 'sha256', 'sfac|' . $cart_id . '|' . $created_at, $secret ), 0, 32 );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
