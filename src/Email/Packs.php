<?php
/**
 * Store packs.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Email;

/**
 * Finds store pack directories with brand.json and copy/{language}.json.
 *
 * Default location: wp-content/mu-plugins/starfiniti-ac/. Packs live outside the
 * plugin so updates never overwrite them, and outside uploads so no PHP sits in
 * a writable public directory.
 */
final class Packs {

	/**
	 * Return existing pack directories.
	 *
	 * @return list<string>
	 */
	public static function directories(): array {
		$defaults = array();

		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$defaults[] = WPMU_PLUGIN_DIR . '/starfiniti-ac';
		}

		/**
		 * Filters store pack directories; later directories override earlier ones.
		 *
		 * @param list<string> $directories Absolute paths.
		 */
		$directories = apply_filters( 'sfac_pack_dirs', $defaults );
		$existing    = array();

		foreach ( is_array( $directories ) ? $directories : array() as $directory ) {
			if ( is_string( $directory ) && is_dir( $directory ) ) {
				$existing[] = rtrim( $directory, '/\\' );
			}
		}

		return $existing;
	}

	/**
	 * Read a JSON object file.
	 *
	 * @param string $path Absolute path.
	 * @return array<string, mixed>|null
	 */
	public static function json( string $path ): ?array {
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local pack file.

		if ( false === $contents ) {
			return null;
		}

		$data = json_decode( $contents, true );

		return is_array( $data ) && ! array_is_list( $data ) ? $data : null;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
