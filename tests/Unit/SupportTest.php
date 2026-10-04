<?php
/**
 * Small helpers.
 *
 * @package StarfinitiAbandonedCart
 */

declare(strict_types=1);

namespace Starfiniti\AbandonedCart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\AbandonedCart\Cart\Snapshot;
use Starfiniti\AbandonedCart\Coupons\CouponCode;
use Starfiniti\AbandonedCart\Email\Context;
use Starfiniti\AbandonedCart\Settings;
use Starfiniti\AbandonedCart\Support\Emails;
use Starfiniti\AbandonedCart\Support\Names;
use Starfiniti\AbandonedCart\Support\Tokens;

/**
 * Covers tokens, names, addresses, coupon codes, settings and item grouping.
 */
final class SupportTest extends TestCase {

	/**
	 * Tokens verify only with the right row and secret.
	 */
	public function test_tokens(): void {
		$token = Tokens::create( 42, '2026-10-12 16:20:00', 'secret' );

		self::assertSame( 42, Tokens::cart_id( $token ) );
		self::assertTrue( Tokens::verify( $token, '2026-10-12 16:20:00', 'secret' ) );
		self::assertFalse( Tokens::verify( $token, '2026-10-12 16:20:01', 'secret' ) );
		self::assertFalse( Tokens::verify( $token, '2026-10-12 16:20:00', 'other' ) );
		self::assertSame( 0, Tokens::cart_id( '42-xyz' ) );
	}

	/**
	 * Names are cleaned to a printable first name or nothing.
	 */
	public function test_names(): void {
		self::assertSame( 'Maja', Names::first( 'MAJA NOVAK' ) );
		self::assertSame( 'Ana', Names::first( ' ana ' ) );
		self::assertSame( 'Žiga', Names::first( 'žiga' ) );
		self::assertSame( 'McDonald', Names::first( 'McDonald' ) );
		self::assertSame( '', Names::first( 'x1' ) );
		self::assertSame( '', Names::first( 'A' ) );
		self::assertSame( '', Names::first( '' ) );
	}

	/**
	 * Addresses are normalized, hashed and masked.
	 */
	public function test_emails(): void {
		self::assertSame( 'maja@example.com', Emails::normalize( ' Maja@Example.com ' ) );
		self::assertSame( '', Emails::normalize( 'not-an-email' ) );
		self::assertSame( Emails::hash( 'maja@example.com', 's' ), Emails::hash( 'MAJA@example.com ', 's' ) );
		self::assertSame( 'ma***@example.com', Emails::mask( 'maja@example.com' ) );
	}

	/**
	 * Coupon codes use the prefix and the unambiguous alphabet.
	 */
	public function test_coupon_code(): void {
		$code = CouponCode::make( 'n-i v', static fn( int $min, int $max ): int => $max );

		self::assertSame( 'NIV-999999', $code );
		self::assertMatchesRegularExpression( '/^[A-HJ-NP-Z2-9]{6}$/', CouponCode::make( '', 'random_int' ) );
		self::assertSame( 'NIN', CouponCode::default_prefix( 'Nina & Valentin' ) );
		self::assertSame( 'CART', CouponCode::default_prefix( '42' ) );
	}

	/**
	 * Settings are clamped and sanitized.
	 */
	public function test_settings_sanitize(): void {
		$settings = Settings::sanitize(
			array(
				'mode'                => 'everything',
				'test_emails'         => "Test@Example.com, bad, test@example.com\nother@example.org",
				'first_delay_minutes' => 1,
				'coupon_type'         => 'percent',
				'coupon_amount'       => 250,
				'coupon_prefix'       => 'ni-v!',
				'reviews'             => array(
					array(
						'text'   => 'Lovely flowers',
						'author' => 'Ana',
					),
					array( 'text' => '' ),
				),
			)
		);

		self::assertSame( 'off', $settings['mode'] );
		self::assertSame( array( 'test@example.com', 'other@example.org' ), $settings['test_emails'] );
		self::assertSame( 5, $settings['first_delay_minutes'] );
		self::assertSame( 100.0, $settings['coupon_amount'] );
		self::assertSame( 'NIV', $settings['coupon_prefix'] );
		self::assertCount( 1, $settings['reviews'] );
		self::assertTrue( $settings['coupon_enabled'] );
	}

	/**
	 * Only JSON-safe custom data is stored.
	 */
	public function test_snapshot_custom_data(): void {
		$custom = Snapshot::custom_data(
			array(
				'key'           => 'abc',
				'data'          => new \stdClass(),
				'line_total'    => 10,
				'nv_parent_key' => 'p1',
				'nv_extra'      => array(
					'text'   => 'Hi',
					'object' => new \stdClass(),
				),
			)
		);

		self::assertSame(
			array(
				'nv_parent_key' => 'p1',
				'nv_extra'      => array( 'text' => 'Hi' ),
			),
			$custom
		);
	}

	/**
	 * Add-on items are grouped under their parent; orphans stay visible.
	 */
	public function test_group_children(): void {
		$grouped = Context::group(
			array(
				array(
					'key'  => 'a',
					'name' => 'Bouquet',
				),
				array(
					'key'        => 'b',
					'parent_key' => 'a',
					'name'       => 'Card',
				),
				array(
					'key'        => 'c',
					'parent_key' => 'missing',
					'name'       => 'Orphan',
				),
			)
		);

		self::assertCount( 2, $grouped );
		self::assertSame( 'Card', $grouped[0]['children'][0]['name'] );
		self::assertSame( 'Orphan', $grouped[1]['name'] );
	}
}
