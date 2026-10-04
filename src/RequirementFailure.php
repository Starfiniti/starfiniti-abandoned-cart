<?php
/**
 * Runtime requirement failure value object.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart;

/**
 * Describes one unmet runtime requirement without depending on WP_Error.
 */
final class RequirementFailure {

	/**
	 * Version requirement failure code.
	 */
	private const VERSION = 'version';

	/**
	 * Missing WooCommerce failure code.
	 */
	private const WOOCOMMERCE_MISSING = 'woocommerce_missing';

	/**
	 * Failure code.
	 *
	 * @var string
	 */
	private readonly string $code;

	/**
	 * Component label for version failures.
	 *
	 * @var string
	 */
	private readonly string $component;

	/**
	 * Required component version.
	 *
	 * @var string
	 */
	private readonly string $required;

	/**
	 * Current component version.
	 *
	 * @var string
	 */
	private readonly string $current;

	/**
	 * Create a version requirement failure.
	 *
	 * @param string $component Component label.
	 * @param string $required  Required version.
	 * @param string $current   Current version.
	 */
	public static function version( string $component, string $required, string $current ): self {
		return new self( self::VERSION, $component, $required, $current );
	}

	/**
	 * Create a missing WooCommerce failure.
	 */
	public static function woocommerce_missing(): self {
		return new self( self::WOOCOMMERCE_MISSING );
	}

	/**
	 * Return the stable failure code.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * Return the user-facing failure message.
	 */
	public function message(): string {
		if ( self::WOOCOMMERCE_MISSING === $this->code ) {
			return __( 'Starfiniti Abandoned Cart for WooCommerce requires WooCommerce to be installed and active.', 'starfiniti-abandoned-cart' );
		}

		return sprintf(
			/* translators: 1: component name, 2: required version, 3: current version. */
			__( 'Starfiniti Abandoned Cart requires %1$s %2$s or newer. This site is running %3$s.', 'starfiniti-abandoned-cart' ),
			$this->component,
			$this->required,
			$this->current
		);
	}

	/**
	 * Construct a failure.
	 *
	 * @param string $code      Stable failure code.
	 * @param string $component Component label.
	 * @param string $required  Required version.
	 * @param string $current   Current version.
	 */
	private function __construct( string $code, string $component = '', string $required = '', string $current = '' ) {
		$this->code      = $code;
		$this->component = $component;
		$this->required  = $required;
		$this->current   = $current;
	}
}
