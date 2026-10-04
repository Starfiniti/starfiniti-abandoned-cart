<?php
/**
 * E-mail copy resolution.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Email;

/**
 * Loads the copy of a language (built-in defaults, store pack JSON, filter) and
 * resolves keys with placeholder fallbacks.
 */
final class Copy {

	/**
	 * Return the merged copy of a language.
	 *
	 * @param string $language Two-letter code.
	 * @return array<string, mixed>
	 */
	public static function for_language( string $language ): array {
		$copy = DefaultCopy::get( $language ) ?? DefaultCopy::get( 'en' ) ?? array();

		foreach ( Packs::directories() as $directory ) {
			$override = Packs::json( $directory . '/copy/' . $language . '.json' );

			if ( null !== $override ) {
				$copy = self::merge( $copy, $override );
			}
		}

		/**
		 * Filters the copy of a language.
		 *
		 * @param array<string, mixed> $copy     Copy.
		 * @param string               $language Two-letter code.
		 */
		$filtered = apply_filters( 'sfac_copy', $copy, $language );

		return is_array( $filtered ) ? $filtered : $copy;
	}

	/**
	 * Merge copy: associative arrays recursively, lists and strings are replaced.
	 *
	 * @param array<string, mixed> $base     Base copy.
	 * @param array<string, mixed> $override Override.
	 * @return array<string, mixed>
	 */
	public static function merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && ! array_is_list( $value ) && ! array_is_list( $base[ $key ] ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
				continue;
			}

			$base[ $key ] = $value;
		}

		return $base;
	}

	/**
	 * Resolve a dotted key to text.
	 *
	 * The value may be a string or a list of alternatives; the first alternative
	 * whose placeholders all have non-empty values wins. Returns '' when none fits.
	 *
	 * @param array<string, mixed>  $copy Copy.
	 * @param string                $key  Dotted key, e.g. "default.e1.title".
	 * @param array<string, string> $vars Placeholder values.
	 */
	public static function text( array $copy, string $key, array $vars ): string {
		$value = self::lookup( $copy, $key );

		if ( is_string( $value ) ) {
			$value = array( $value );
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		foreach ( $value as $candidate ) {
			if ( ! is_string( $candidate ) || '' === trim( $candidate ) ) {
				continue;
			}

			$filled = self::fill( $candidate, $vars );

			if ( null !== $filled ) {
				return $filled;
			}
		}

		return '';
	}

	/**
	 * Resolve a dotted key to a list of pairs, e.g. objections.
	 *
	 * @param array<string, mixed>  $copy Copy.
	 * @param string                $key  Dotted key.
	 * @param array<string, string> $vars Placeholder values.
	 * @return list<array{0: string, 1: string}>
	 */
	public static function pairs( array $copy, string $key, array $vars ): array {
		$value = self::lookup( $copy, $key );
		$pairs = array();

		if ( ! is_array( $value ) ) {
			return $pairs;
		}

		foreach ( $value as $pair ) {
			if ( ! is_array( $pair ) || ! isset( $pair[0], $pair[1] ) ) {
				continue;
			}

			$question = self::fill( (string) $pair[0], $vars );
			$answer   = self::fill( (string) $pair[1], $vars );

			if ( null !== $question && null !== $answer && '' !== $answer ) {
				$pairs[] = array( $question, $answer );
			}
		}

		return $pairs;
	}

	/**
	 * Replace placeholders; null when any placeholder has no value.
	 *
	 * @param string                $text Text with {placeholders}.
	 * @param array<string, string> $vars Values.
	 */
	public static function fill( string $text, array $vars ): ?string {
		$missing = false;
		$result  = preg_replace_callback(
			'/\{([a-z0-9_]+)\}/',
			static function ( array $found ) use ( $vars, &$missing ): string {
				$value = $vars[ $found[1] ] ?? '';

				if ( '' === $value ) {
					$missing = true;
				}

				return $value;
			},
			$text
		);

		return $missing || null === $result ? null : $result;
	}

	/**
	 * Return the value at a dotted key.
	 *
	 * @param array<string, mixed> $copy Copy.
	 * @param string               $key  Dotted key.
	 */
	private static function lookup( array $copy, string $key ): mixed {
		$value = $copy;

		foreach ( explode( '.', $key ) as $part ) {
			if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
				return null;
			}

			$value = $value[ $part ];
		}

		return $value;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
