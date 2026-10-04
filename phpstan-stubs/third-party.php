<?php
/**
 * Static-analysis declarations for optional multilingual plugins.
 * This file is never loaded at runtime.
 *
 * @package StarfinitiAbandonedCart
 */

// phpcs:ignoreFile

/** TranslatePress main class. */
class TRP_Translate_Press {
	/** @return self */
	public static function get_trp_instance() {}

	/**
	 * @param string $component Component name.
	 * @return object|null
	 */
	public function get_component( $component ) {}
}

/**
 * Polylang: current language.
 *
 * @param string $field Field.
 * @return string|false
 */
function pll_current_language( $field = 'slug' ) {}

/**
 * Polylang: translated post id.
 *
 * @param int    $post_id  Post id.
 * @param string $language Language slug.
 * @return int|false|null
 */
function pll_get_post( $post_id, $language = '' ) {}
