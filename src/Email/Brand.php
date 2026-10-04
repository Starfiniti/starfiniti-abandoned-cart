<?php
/**
 * E-mail brand tokens.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Email;

/**
 * Colors, fonts, logo and footer details for the e-mails.
 *
 * Sources, later wins: built-in neutral defaults, brand.json of each store pack
 * (with optional "languages": { "sl": { ... } } overrides), the sfac_brand filter.
 */
final class Brand {

	/**
	 * Return the brand tokens for a language.
	 *
	 * @param string $language Two-letter code.
	 * @return array<string, mixed>
	 */
	public static function get( string $language ): array {
		$brand = self::defaults();

		foreach ( Packs::directories() as $directory ) {
			$pack = Packs::json( $directory . '/brand.json' );

			if ( null === $pack ) {
				continue;
			}

			$languages = is_array( $pack['languages'] ?? null ) ? $pack['languages'] : array();
			unset( $pack['languages'] );
			$brand = array_merge( $brand, $pack );

			if ( is_array( $languages[ $language ] ?? null ) ) {
				$brand = array_merge( $brand, $languages[ $language ] );
			}
		}

		/**
		 * Filters the brand tokens of a language.
		 *
		 * @param array<string, mixed> $brand    Tokens.
		 * @param string               $language Two-letter code.
		 */
		$filtered = apply_filters( 'sfac_brand', $brand, $language );

		return is_array( $filtered ) ? array_merge( self::defaults(), $filtered ) : $brand;
	}

	/**
	 * Neutral defaults that work for any store.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$logo    = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$email   = (string) get_option( 'woocommerce_email_from_address', '' );

		return array(
			'bg'             => '#F5F3F0',
			'surface'        => '#FFFFFF',
			'text'           => '#4A443E',
			'heading'        => '#1E1A16',
			'muted'          => '#8B8178',
			'line'           => '#E5DED6',
			'soft'           => '#F2EDE7',
			'accent'         => '#1E1A16',
			'accent_text'    => '#FFFFFF',
			'highlight'      => '#B3245B',
			'highlight_soft' => '#FBEEF3',
			'star'           => '#B08D4F',
			'footer_bg'      => '#2B2520',
			'footer_text'    => '#EDE6DE',
			'font_heading'   => "Georgia,'Times New Roman',serif",
			'font_body'      => "'Helvetica Neue',Helvetica,Arial,sans-serif",
			'font_css'       => '',
			'button_radius'  => 0,
			'logo_url'       => is_string( $logo ) ? $logo : '',
			'logo_width'     => 150,
			'store_name'     => trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'store_url'      => home_url( '/' ),
			'topbar'         => '',
			'phone'          => '',
			'whatsapp'       => '',
			'email'          => '' !== $email ? $email : (string) get_option( 'admin_email', '' ),
			'hours'          => '',
			'help_text'      => '',
			'trust'          => array(),
			'links'          => array(),
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
