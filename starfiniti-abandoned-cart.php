<?php
/**
 * Plugin Name:       Starfiniti Abandoned Cart for WooCommerce
 * Plugin URI:        https://starfiniti.com/
 * Description:       Abandoned cart recovery for WooCommerce: timed reminder e-mails, personal coupons and one-click cart restore. Off until you switch it on.
 * Version:           0.1.0
 * Update URI:        https://github.com/Starfiniti/starfiniti-abandoned-cart
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Starfiniti
 * Author URI:        https://starfiniti.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       starfiniti-abandoned-cart
 * Domain Path:       /languages
 * WC requires at least: 9.0
 * WC tested up to:      11.1
 *
 * @package StarfinitiAbandonedCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SFAC_VERSION', '0.1.0' );
define( 'SFAC_PLUGIN_FILE', __FILE__ );
define( 'SFAC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SFAC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$sfac_autoloader = SFAC_PLUGIN_DIR . 'vendor/autoload.php';

if ( is_readable( $sfac_autoloader ) ) {
	require_once $sfac_autoloader;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$namespace = 'Starfiniti\\AbandonedCart\\';

			if ( 0 !== strpos( $class_name, $namespace ) ) {
				return;
			}

			$relative_class = substr( $class_name, strlen( $namespace ) );
			$class_file     = SFAC_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( is_readable( $class_file ) ) {
				require_once $class_file;
			}
		}
	);
}

register_activation_hook(
	SFAC_PLUGIN_FILE,
	array( Starfiniti\AbandonedCart\Lifecycle\Activator::class, 'activate' )
);

register_deactivation_hook(
	SFAC_PLUGIN_FILE,
	array( Starfiniti\AbandonedCart\Lifecycle\Deactivator::class, 'deactivate' )
);

Starfiniti\AbandonedCart\Plugin::instance()->boot();
