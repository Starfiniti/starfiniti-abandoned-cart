<?php
/**
 * Multilingual plugin support.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Integration;

/**
 * Detects the shopper language and builds language-specific URLs for
 * TranslatePress, WPML and Polylang. Languages are two-letter codes.
 */
final class Multilingual {

	/**
	 * Return the language of the current request.
	 */
	public static function current(): string {
		global $TRP_LANGUAGE; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- TranslatePress global.

		$language = '';

		if ( is_string( $TRP_LANGUAGE ) && '' !== $TRP_LANGUAGE ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- TranslatePress global.
			$language = $TRP_LANGUAGE; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- TranslatePress global.
		} elseif ( function_exists( 'pll_current_language' ) ) {
			$language = (string) pll_current_language( 'slug' );
		} elseif ( has_filter( 'wpml_current_language' ) ) {
			$language = (string) apply_filters( 'wpml_current_language', null );
		}

		if ( '' === $language ) {
			$language = function_exists( 'determine_locale' ) ? determine_locale() : 'en_US';
		}

		return self::normalize( $language );
	}

	/**
	 * Turn "sl_SI", "en-GB" or "de" into a two-letter code.
	 *
	 * @param string $language Locale or language code.
	 */
	public static function normalize( string $language ): string {
		$code = strtolower( substr( (string) preg_replace( '/[^A-Za-z]/', '', substr( $language, 0, 2 ) ), 0, 2 ) );

		return 2 === strlen( $code ) ? $code : 'en';
	}

	/**
	 * Return the checkout URL in a language.
	 *
	 * @param string $language Two-letter code.
	 */
	public static function checkout_url( string $language ): string {
		$url = wc_get_checkout_url();

		if ( class_exists( 'TRP_Translate_Press' ) ) {
			$translated = self::translatepress_url( $url, $language );

			if ( '' !== $translated ) {
				$url = $translated;
			}
		} elseif ( function_exists( 'pll_get_post' ) ) {
			$page_id = pll_get_post( wc_get_page_id( 'checkout' ), $language );

			if ( $page_id ) {
				$permalink = get_permalink( (int) $page_id );
				$url       = is_string( $permalink ) ? $permalink : $url;
			}
		} elseif ( has_filter( 'wpml_permalink' ) ) {
			$url = (string) apply_filters( 'wpml_permalink', $url, $language );
		}

		/**
		 * Filters the checkout URL used by restore links.
		 *
		 * @param string $url      Checkout URL.
		 * @param string $language Two-letter language code.
		 */
		return (string) apply_filters( 'sfac_checkout_url', $url, $language );
	}

	/**
	 * Translate a URL with TranslatePress.
	 *
	 * @param string $url      URL in the default language.
	 * @param string $language Two-letter code.
	 */
	private static function translatepress_url( string $url, string $language ): string {
		$trp       = \TRP_Translate_Press::get_trp_instance();
		$settings  = $trp->get_component( 'settings' );
		$converter = $trp->get_component( 'url_converter' );

		if ( ! is_object( $settings ) || ! is_object( $converter ) || ! method_exists( $settings, 'get_settings' ) || ! method_exists( $converter, 'get_url_for_language' ) ) {
			return '';
		}

		$all = $settings->get_settings();

		foreach ( (array) ( $all['publish-languages'] ?? array() ) as $code ) {
			$slug = (string) ( $all['url-slugs'][ $code ] ?? $code );

			if ( self::normalize( $slug ) === $language || self::normalize( (string) $code ) === $language ) {
				return (string) $converter->get_url_for_language( $code, $url, '' );
			}
		}

		return '';
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
