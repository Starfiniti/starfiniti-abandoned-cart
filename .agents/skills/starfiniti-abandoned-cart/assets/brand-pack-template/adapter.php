<?php
/**
 * Store adapter skeleton for Starfiniti Abandoned Cart.
 *
 * Copy to wp-content/mu-plugins/starfiniti-ac/adapter.php and load it from
 * wp-content/mu-plugins/starfiniti-ac.php:
 *
 *     <?php
 *     // Plugin Name: Starfiniti Abandoned Cart – store adapter
 *     require __DIR__ . '/starfiniti-ac/adapter.php';
 *
 * Keep only the parts the store needs. Everything that runs on checkout, cart or
 * order requests stays inside try/catch.
 *
 * @package StarfinitiAbandonedCart
 */

defined( 'ABSPATH' ) || exit;

// Where the shopper types the e-mail on a custom checkout.
add_filter(
	'sfac_checkout_selectors',
	static fn( array $selectors ): array => array_merge( array( '#custom-email-field' ), $selectors )
);

// Personalization: recipient, delivery date, card message.
add_filter(
	'sfac_capture_context',
	static function ( array $context, $cart, $customer, $session ): array {
		try {
			if ( $customer instanceof \WC_Customer ) {
				$context['recipient'] = (string) $customer->get_shipping_first_name();
			}
		} catch ( \Throwable $error ) {
			unset( $error );
		}

		return $context;
	},
	10,
	4
);

// Sensitive purchases get the calm sequence (replace 0 with the category id).
add_filter(
	'sfac_segment',
	static function ( string $segment, array $snapshot ): string {
		foreach ( $snapshot['items'] as $item ) {
			if ( in_array( 0, (array) ( $item['categories'] ?? array() ), true ) ) {
				return 'calm';
			}
		}

		return $segment;
	},
	10,
	2
);

// Grammar forms used by the copy ({noun}, {noun_gen}, {noun_cap}).
add_filter(
	'sfac_copy_vars',
	static function ( array $vars, array $row, int $step, string $language ): array {
		unset( $row, $step );

		if ( 'sl' === $language ) {
			$vars['noun']     = 'šopek';
			$vars['noun_gen'] = 'šopka';
			$vars['noun_cap'] = 'Šopek';
		}

		return $vars;
	},
	10,
	4
);

// Honest deadline box in e-mail 1.
add_filter(
	'sfac_info_box',
	static function ( $box, array $row, array $context, string $language, int $step ) {
		unset( $row, $language );

		if ( 1 !== $step || '' === (string) ( $context['delivery_date'] ?? '' ) ) {
			return $box;
		}

		return array(
			'label' => 'Dostava',
			'text'  => 'Dostavo ste izbrali za izbrani dan. Zaključite naročilo, za vse ostalo poskrbimo mi.',
		);
	},
	10,
	5
);
