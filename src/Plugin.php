<?php
/**
 * Main plugin bootstrap.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Starfiniti\AbandonedCart\Admin\AdminPage;
use Starfiniti\AbandonedCart\Capture\CheckoutScript;
use Starfiniti\AbandonedCart\Capture\Hooks;
use Starfiniti\AbandonedCart\Capture\RestController;
use Starfiniti\AbandonedCart\Http\Endpoints;
use Starfiniti\AbandonedCart\Lifecycle\Installer;
use Starfiniti\AbandonedCart\Privacy\Privacy;
use Starfiniti\AbandonedCart\Sequence\Scheduler;
use Starfiniti\AbandonedCart\Support\Logger;
use Starfiniti\AbandonedCart\Updates\GitHubUpdater;
use Throwable;

/**
 * Coordinates the plugin lifecycle.
 */
final class Plugin {

	/**
	 * Minimum supported PHP version.
	 */
	public const MINIMUM_PHP = '8.1';

	/**
	 * Minimum supported WordPress version.
	 */
	public const MINIMUM_WORDPRESS = '6.6';

	/**
	 * Minimum supported WooCommerce version.
	 */
	public const MINIMUM_WOOCOMMERCE = '9.0';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether runtime initialization completed.
	 *
	 * @var bool
	 */
	private bool $ready = false;

	/**
	 * Unmet runtime requirement.
	 *
	 * @var RequirementFailure|null
	 */
	private ?RequirementFailure $requirement_failure = null;

	/**
	 * Whether installation or migration failed.
	 *
	 * @var bool
	 */
	private bool $initialization_failed = false;

	/**
	 * Return the singleton plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register lifecycle hooks.
	 */
	public function boot(): void {
		GitHubUpdater::register();
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'initialize' ), 20 );
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
	}

	/**
	 * Declare compatibility with WooCommerce features.
	 */
	public function declare_woocommerce_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		try {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', SFAC_PLUGIN_FILE, true );
			FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SFAC_PLUGIN_FILE, true );
		} catch ( Throwable $error ) {
			Logger::exception( 'Starfiniti Abandoned Cart could not declare WooCommerce compatibility.', $error );
		}
	}

	/**
	 * Initialize the plugin after dependencies have loaded.
	 */
	public function initialize(): void {
		$this->requirement_failure = Requirements::runtime_failure();

		if ( $this->requirement_failure instanceof RequirementFailure ) {
			add_action( 'admin_notices', array( $this, 'render_requirement_notice' ) );

			/**
			 * Fires when Starfiniti Abandoned Cart refuses to initialize due to requirements.
			 *
			 * @param string $failure_code Stable failure code.
			 */
			do_action( 'sfac_requirements_failed', $this->requirement_failure->code() );
			return;
		}

		try {
			Installer::install_or_upgrade();
			Scheduler::register();
			Hooks::register();
			RestController::register();
			CheckoutScript::register();
			Endpoints::register();
			Privacy::register();
			AdminPage::register();
		} catch ( Throwable $error ) {
			$this->initialization_failed = true;
			Logger::exception( 'Starfiniti Abandoned Cart initialization failed.', $error );
			add_action( 'admin_notices', array( $this, 'render_initialization_notice' ) );

			/**
			 * Fires after a caught initialization failure.
			 *
			 * @param Throwable $error Caught initialization error.
			 */
			do_action( 'sfac_initialization_failed', $error );
			return;
		}

		$this->ready = true;

		/**
		 * Fires after Starfiniti Abandoned Cart has validated dependencies and migrations.
		 *
		 * @param Plugin $plugin Main plugin instance.
		 */
		do_action( 'sfac_loaded', $this );
	}

	/**
	 * Load translations from the plugin languages directory.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'starfiniti-abandoned-cart',
			false,
			dirname( plugin_basename( SFAC_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Report whether runtime initialization completed.
	 */
	public function is_ready(): bool {
		return $this->ready;
	}

	/**
	 * Render the current dependency notice.
	 */
	public function render_requirement_notice(): void {
		if ( ! $this->requirement_failure instanceof RequirementFailure ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $this->requirement_failure->message() )
		);
	}

	/**
	 * Render a generic caught-initialization failure notice.
	 */
	public function render_initialization_notice(): void {
		if ( ! $this->initialization_failed ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Starfiniti Abandoned Cart could not initialize safely. Review the WooCommerce logs for details.', 'starfiniti-abandoned-cart' )
		);
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}
}
