<?php
/**
 * Public capture endpoint used by the checkout script.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Capture;

use Starfiniti\AbandonedCart\Integration\Multilingual;
use Starfiniti\AbandonedCart\Settings;
use Starfiniti\AbandonedCart\Support\Logger;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * POST /wp-json/sfac/v1/capture with { email, language, consent }.
 *
 * Always answers 204 so the endpoint reveals nothing about stored data.
 */
final class RestController {

	/**
	 * Requests per IP and hour.
	 */
	private const RATE_LIMIT = 60;

	/**
	 * Register the route.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Add the route.
	 */
	public static function routes(): void {
		register_rest_route(
			'sfac/v1',
			'/capture',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'capture' ),
				'args'                => array(
					'email'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'language' => array(
						'type' => 'string',
					),
					'consent'  => array(
						'type' => 'boolean',
					),
				),
			)
		);
	}

	/**
	 * Handle a capture request.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function capture( WP_REST_Request $request ): WP_REST_Response {
		try {
			if ( Settings::MODE_OFF === Settings::mode() || ! self::within_rate_limit() ) {
				return new WP_REST_Response( null, 204 );
			}

			if ( function_exists( 'wc_load_cart' ) ) {
				wc_load_cart();
			}

			$language = (string) $request->get_param( 'language' );

			CaptureService::capture(
				(string) $request->get_param( 'email' ),
				'' !== $language ? $language : Multilingual::current(),
				'rest',
				(bool) $request->get_param( 'consent' )
			);
		} catch ( Throwable $error ) {
			Logger::exception( 'REST capture failed.', $error );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Count the request against a per-IP hourly limit.
	 */
	private static function within_rate_limit(): bool {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'sfac_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
