<?php
/**
 * Page cache integration.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Integration;

/**
 * Marks the current response as uncacheable for WordPress and common page caches.
 */
final class Cache {

	/**
	 * Prevent caching of the current response.
	 */
	public static function nocache(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		/**
		 * LiteSpeed Cache: never cache this response.
		 */
		do_action( 'litespeed_control_set_nocache', 'starfiniti abandoned cart link' );

		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
