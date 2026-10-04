<?php
/**
 * Site secret accessor.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Support;

use Starfiniti\AbandonedCart\Lifecycle\Installer;

/**
 * Returns the per-site secret, creating it on first use.
 */
final class Secret {

	/**
	 * Return the site secret.
	 */
	public static function get(): string {
		$secret = (string) get_option( Installer::SECRET_OPTION, '' );

		if ( '' === $secret ) {
			$secret = wp_generate_password( 64, false, false );
			update_option( Installer::SECRET_OPTION, $secret, false );
		}

		return $secret;
	}

	/**
	 * Hash an address with the site secret.
	 *
	 * @param string $email Normalized address.
	 */
	public static function email_hash( string $email ): string {
		return Emails::hash( $email, self::get() );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
