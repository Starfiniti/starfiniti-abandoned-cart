<?php
/**
 * Runtime and activation requirements.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart;

/**
 * Validates the supported platform.
 */
final class Requirements {

	/**
	 * Official WooCommerce plugin basename.
	 */
	private const WOOCOMMERCE_BASENAME = 'woocommerce/woocommerce.php';

	/**
	 * Return the first runtime requirement failure, if any.
	 */
	public static function runtime_failure(): ?RequirementFailure {
		$platform_failure = self::platform_failure();

		if ( null !== $platform_failure ) {
			return $platform_failure;
		}

		if ( ! defined( 'WC_VERSION' ) || ! class_exists( 'WooCommerce' ) ) {
			return RequirementFailure::woocommerce_missing();
		}

		if ( version_compare( WC_VERSION, Plugin::MINIMUM_WOOCOMMERCE, '<' ) ) {
			return RequirementFailure::version( 'WooCommerce', Plugin::MINIMUM_WOOCOMMERCE, WC_VERSION );
		}

		return null;
	}

	/**
	 * Return the first activation failure, including network-activation rules.
	 *
	 * @param bool $network_wide Whether activation applies to a multisite network.
	 */
	public static function activation_failure( bool $network_wide ): ?RequirementFailure {
		$platform_failure = self::platform_failure();

		if ( null !== $platform_failure ) {
			return $platform_failure;
		}

		if ( ! self::is_woocommerce_active( $network_wide ) || ! defined( 'WC_VERSION' ) ) {
			return RequirementFailure::woocommerce_missing();
		}

		if ( version_compare( WC_VERSION, Plugin::MINIMUM_WOOCOMMERCE, '<' ) ) {
			return RequirementFailure::version( 'WooCommerce', Plugin::MINIMUM_WOOCOMMERCE, WC_VERSION );
		}

		return null;
	}

	/**
	 * Validate the PHP and WordPress version floors.
	 */
	private static function platform_failure(): ?RequirementFailure {
		$current_wordpress = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '0';

		if ( version_compare( PHP_VERSION, Plugin::MINIMUM_PHP, '<' ) ) {
			return RequirementFailure::version( 'PHP', Plugin::MINIMUM_PHP, PHP_VERSION );
		}

		if ( version_compare( $current_wordpress, Plugin::MINIMUM_WORDPRESS, '<' ) ) {
			return RequirementFailure::version( 'WordPress', Plugin::MINIMUM_WORDPRESS, $current_wordpress );
		}

		return null;
	}

	/**
	 * Check whether WooCommerce is active in the required scope.
	 *
	 * @param bool $network_wide Whether activation applies to a multisite network.
	 */
	private static function is_woocommerce_active( bool $network_wide ): bool {
		if ( $network_wide && is_multisite() ) {
			$active_network_plugins = get_site_option( 'active_sitewide_plugins', array() );

			return is_array( $active_network_plugins ) && isset( $active_network_plugins[ self::WOOCOMMERCE_BASENAME ] );
		}

		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		$active_plugins = get_option( 'active_plugins', array() );

		return is_array( $active_plugins ) && in_array( self::WOOCOMMERCE_BASENAME, $active_plugins, true );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
