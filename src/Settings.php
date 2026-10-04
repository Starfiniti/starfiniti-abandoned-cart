<?php
/**
 * Typed plugin settings.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart;

/**
 * Reads, sanitizes and stores the plugin settings.
 */
final class Settings {

	/**
	 * Option that stores the settings.
	 */
	public const OPTION_NAME = 'sfac_settings';

	/**
	 * Nothing is captured or sent.
	 */
	public const MODE_OFF = 'off';

	/**
	 * Only the configured test addresses are captured and e-mailed.
	 */
	public const MODE_TEST = 'test';

	/**
	 * Every shopper who enters an e-mail address is captured.
	 */
	public const MODE_ON = 'on';

	/**
	 * Return the default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'mode'                     => self::MODE_OFF,
			'test_emails'              => array(),
			'consent'                  => 'notice',
			'first_delay_minutes'      => 60,
			'second_delay_hours'       => 24,
			'last_day_hour'            => 10,
			'quiet_start'              => 21,
			'quiet_end'                => 8,
			'sequence_cooldown_days'   => 14,
			'coupon_enabled'           => true,
			'coupon_type'              => 'percent',
			'coupon_amount'            => 10.0,
			'coupon_valid_days'        => 2,
			'coupon_expiry_hour'       => 20,
			'coupon_cooldown_days'     => 60,
			'coupon_prefix'            => '',
			'payment_failed_enabled'   => true,
			'payment_failed_minutes'   => 60,
			'from_name'                => '',
			'reply_to'                 => '',
			'signer'                   => '',
			'rating_text'              => '',
			'reviews'                  => array(),
			'retention_days'           => 30,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Return all sanitized settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored   = get_option( self::OPTION_NAME, array() );
		$settings = self::sanitize( is_array( $stored ) ? $stored : array() );

		/**
		 * Filters the effective settings, for example from a store adapter.
		 *
		 * @param array<string, mixed> $settings Sanitized settings.
		 */
		$filtered = apply_filters( 'sfac_settings', $settings );

		return is_array( $filtered ) ? self::sanitize( $filtered ) : $settings;
	}

	/**
	 * Return one setting.
	 *
	 * @param string $key Setting key.
	 */
	public static function get( string $key ): mixed {
		$settings = self::all();

		return $settings[ $key ] ?? null;
	}

	/**
	 * Return the current mode.
	 */
	public static function mode(): string {
		return (string) self::get( 'mode' );
	}

	/**
	 * Check whether an address is a configured test address.
	 *
	 * @param string $email Normalized e-mail address.
	 */
	public static function is_test_email( string $email ): bool {
		$test_emails = self::get( 'test_emails' );

		return is_array( $test_emails ) && in_array( strtolower( $email ), $test_emails, true );
	}

	/**
	 * Check whether an address may be captured and e-mailed in the current mode.
	 *
	 * @param string $email Normalized e-mail address.
	 */
	public static function allows_email( string $email ): bool {
		$mode = self::mode();

		if ( self::MODE_ON === $mode ) {
			return true;
		}

		return self::MODE_TEST === $mode && self::is_test_email( $email );
	}

	/**
	 * Report whether uninstall may delete plugin data.
	 */
	public static function should_delete_data_on_uninstall(): bool {
		$stored = get_option( self::OPTION_NAME, array() );

		return is_array( $stored ) && ! empty( $stored['delete_data_on_uninstall'] );
	}

	/**
	 * Store new settings.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 */
	public static function update( array $input ): void {
		update_option( self::OPTION_NAME, self::sanitize( $input ), false );
	}

	/**
	 * Sanitize settings and fill defaults.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$output   = $defaults;

		$mode           = (string) ( $input['mode'] ?? $defaults['mode'] );
		$output['mode'] = in_array( $mode, array( self::MODE_OFF, self::MODE_TEST, self::MODE_ON ), true ) ? $mode : self::MODE_OFF;

		$output['test_emails'] = self::sanitize_emails( $input['test_emails'] ?? array() );

		$consent           = (string) ( $input['consent'] ?? $defaults['consent'] );
		$output['consent'] = in_array( $consent, array( 'notice', 'checkbox' ), true ) ? $consent : 'notice';

		$ranges = array(
			'first_delay_minutes'    => array( 5, 1440 ),
			'second_delay_hours'     => array( 1, 168 ),
			'last_day_hour'          => array( 0, 23 ),
			'quiet_start'            => array( 0, 23 ),
			'quiet_end'              => array( 0, 23 ),
			'sequence_cooldown_days' => array( 0, 365 ),
			'coupon_valid_days'      => array( 1, 30 ),
			'coupon_expiry_hour'     => array( 0, 23 ),
			'coupon_cooldown_days'   => array( 0, 365 ),
			'payment_failed_minutes' => array( 15, 1440 ),
			'retention_days'         => array( 7, 365 ),
		);

		foreach ( $ranges as $key => $range ) {
			$value          = isset( $input[ $key ] ) && is_numeric( $input[ $key ] ) ? (int) $input[ $key ] : (int) $defaults[ $key ];
			$output[ $key ] = max( $range[0], min( $range[1], $value ) );
		}

		foreach ( array( 'coupon_enabled', 'payment_failed_enabled', 'delete_data_on_uninstall' ) as $key ) {
			$output[ $key ] = array_key_exists( $key, $input ) ? self::to_bool( $input[ $key ] ) : (bool) $defaults[ $key ];
		}

		$coupon_type           = (string) ( $input['coupon_type'] ?? $defaults['coupon_type'] );
		$output['coupon_type'] = in_array( $coupon_type, array( 'percent', 'fixed_cart' ), true ) ? $coupon_type : 'percent';

		$amount = isset( $input['coupon_amount'] ) && is_numeric( $input['coupon_amount'] ) ? (float) $input['coupon_amount'] : (float) $defaults['coupon_amount'];
		$amount = max( 0.0, $amount );

		if ( 'percent' === $output['coupon_type'] ) {
			$amount = min( 100.0, $amount );
		}

		$output['coupon_amount'] = round( $amount, 2 );

		$prefix                  = strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $input['coupon_prefix'] ?? '' ) ) );
		$output['coupon_prefix'] = substr( $prefix, 0, 8 );

		foreach ( array( 'from_name', 'signer', 'rating_text' ) as $key ) {
			$output[ $key ] = sanitize_text_field( (string) ( $input[ $key ] ?? '' ) );
		}

		$reply_to           = sanitize_email( (string) ( $input['reply_to'] ?? '' ) );
		$output['reply_to'] = is_email( $reply_to ) ? $reply_to : '';

		$output['reviews'] = self::sanitize_reviews( $input['reviews'] ?? array() );

		return $output;
	}

	/**
	 * Sanitize a list of test e-mail addresses.
	 *
	 * @param mixed $value List or comma/newline separated string.
	 * @return list<string>
	 */
	private static function sanitize_emails( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,;]+/', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$emails = array();

		foreach ( $value as $email ) {
			$email = strtolower( sanitize_email( (string) $email ) );

			if ( '' !== $email && is_email( $email ) && ! in_array( $email, $emails, true ) ) {
				$emails[] = $email;
			}
		}

		return array_slice( $emails, 0, 20 );
	}

	/**
	 * Sanitize up to three quoted reviews.
	 *
	 * @param mixed $value Raw reviews.
	 * @return list<array{text: string, author: string}>
	 */
	private static function sanitize_reviews( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$reviews = array();

		foreach ( $value as $review ) {
			if ( ! is_array( $review ) ) {
				continue;
			}

			$text   = trim( sanitize_textarea_field( (string) ( $review['text'] ?? '' ) ) );
			$author = trim( sanitize_text_field( (string) ( $review['author'] ?? '' ) ) );

			if ( '' === $text ) {
				continue;
			}

			$reviews[] = array(
				'text'   => mb_substr( $text, 0, 300 ),
				'author' => mb_substr( $author, 0, 80 ),
			);
		}

		return array_slice( $reviews, 0, 3 );
	}

	/**
	 * Convert a submitted value to a boolean.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function to_bool( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
