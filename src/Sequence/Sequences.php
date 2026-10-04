<?php
/**
 * Sequence definitions.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Sequence;

use Starfiniti\AbandonedCart\Settings;

/**
 * Describes which e-mails a segment receives, when, and which blocks they contain.
 *
 * Built-in segments: "default" (3 e-mails, coupon in the second) and "calm"
 * (2 e-mails, no coupon, for sensitive purchases). Adapters add or change
 * segments with the sfac_sequences filter and pick one with sfac_segment.
 */
final class Sequences {

	/**
	 * Return all sequence definitions.
	 *
	 * @return array<string, array{steps: array<int, array<string, mixed>>}>
	 */
	public static function all(): array {
		$settings = Settings::all();
		$first    = (int) $settings['first_delay_minutes'];
		$second   = (int) $settings['second_delay_hours'] * 60;

		$definitions = array(
			'default' => array(
				'steps' => array(
					1 => array(
						'delay'   => 'activity',
						'minutes' => $first,
						'coupon'  => false,
						'blocks'  => array( 'hero', 'cart', 'card_message', 'totals', 'cta', 'info_box', 'help', 'signature', 'ps' ),
					),
					2 => array(
						'delay'   => 'activity',
						'minutes' => $second,
						'coupon'  => true,
						'blocks'  => array( 'hero', 'coupon', 'cta', 'objections', 'reviews', 'cart', 'totals', 'cta_secondary', 'signature', 'ps' ),
					),
					3 => array(
						'delay'           => 'coupon_last_day',
						'hour'            => (int) $settings['last_day_hour'],
						'requires_coupon' => true,
						'blocks'          => array( 'hero', 'coupon', 'cart', 'totals', 'cta', 'reframe', 'signature', 'ps' ),
					),
				),
			),
			'calm'    => array(
				'steps' => array(
					1 => array(
						'delay'   => 'activity',
						'minutes' => $first,
						'coupon'  => false,
						'blocks'  => array( 'hero', 'cart', 'totals', 'cta', 'help', 'signature' ),
					),
					2 => array(
						'delay'               => 'activity',
						'minutes'             => $second,
						'coupon'              => false,
						'skip_if_date_passed' => true,
						'blocks'              => array( 'hero', 'cart', 'cta', 'signature' ),
					),
				),
			),
		);

		/**
		 * Filters the sequence definitions.
		 *
		 * @param array<string, array{steps: array<int, array<string, mixed>>}> $definitions Definitions per segment.
		 * @param array<string, mixed>                                          $settings    Plugin settings.
		 */
		$filtered = apply_filters( 'sfac_sequences', $definitions, $settings );

		return is_array( $filtered ) && isset( $filtered['default'] ) ? $filtered : $definitions;
	}

	/**
	 * Check whether a segment exists.
	 *
	 * @param string $segment Segment name.
	 */
	public static function exists( string $segment ): bool {
		return isset( self::all()[ $segment ] );
	}

	/**
	 * Return one step of a segment, or null after the last step.
	 *
	 * @param string $segment Segment name.
	 * @param int    $step    Step number starting at 1.
	 * @return array<string, mixed>|null
	 */
	public static function step( string $segment, int $step ): ?array {
		$all   = self::all();
		$steps = $all[ $segment ]['steps'] ?? $all['default']['steps'];
		$def   = $steps[ $step ] ?? null;

		return is_array( $def ) ? $def : null;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
