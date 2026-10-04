<?php
/**
 * Starfiniti Abandoned Cart uninstall handler.
 *
 * Plugin data is deliberately retained unless delete_data_on_uninstall was
 * explicitly enabled in the sfac_settings option.
 *
 * @package StarfinitiAbandonedCart
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$namespace = 'Starfiniti\\AbandonedCart\\';

		if ( 0 !== strpos( $class_name, $namespace ) ) {
			return;
		}

		$class_file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $namespace ) ) ) . '.php';

		if ( is_readable( $class_file ) ) {
			require_once $class_file;
		}
	}
);

Starfiniti\AbandonedCart\Lifecycle\Uninstaller::run();
