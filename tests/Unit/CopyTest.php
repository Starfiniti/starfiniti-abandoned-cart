<?php
/**
 * Copy resolution.
 *
 * @package StarfinitiAbandonedCart
 */

declare(strict_types=1);

namespace Starfiniti\AbandonedCart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\AbandonedCart\Email\Copy;
use Starfiniti\AbandonedCart\Email\DefaultCopy;

/**
 * Covers placeholder fallbacks and pack merging.
 */
final class CopyTest extends TestCase {

	/**
	 * The first alternative with all values wins.
	 */
	public function test_fallback_when_value_missing(): void {
		$copy = array(
			'subject' => array( '{recipient} has no idea what is coming', 'Your cart is saved' ),
		);

		self::assertSame( 'Ana has no idea what is coming', Copy::text( $copy, 'subject', array( 'recipient' => 'Ana' ) ) );
		self::assertSame( 'Your cart is saved', Copy::text( $copy, 'subject', array( 'recipient' => '' ) ) );
		self::assertSame( 'Your cart is saved', Copy::text( $copy, 'subject', array() ) );
	}

	/**
	 * Nothing renders when no alternative fits.
	 */
	public function test_empty_when_nothing_fits(): void {
		self::assertSame( '', Copy::text( array( 'ps' => '{missing} text' ), 'ps', array() ) );
		self::assertSame( '', Copy::text( array(), 'default.e1.title', array() ) );
	}

	/**
	 * Pairs drop entries with missing values.
	 */
	public function test_pairs(): void {
		$copy = array(
			'e2' => array(
				'objections' => array(
					array( 'Will they like it?', 'Every {noun} has a guarantee.' ),
					array( 'On time?', 'Yes.' ),
				),
			),
		);

		self::assertSame( array( array( 'On time?', 'Yes.' ) ), Copy::pairs( $copy, 'e2.objections', array() ) );
		self::assertCount( 2, Copy::pairs( $copy, 'e2.objections', array( 'noun' => 'bouquet' ) ) );
	}

	/**
	 * Packs replace lists instead of merging them index by index.
	 */
	public function test_merge_replaces_lists(): void {
		$merged = Copy::merge(
			array(
				'default' => array(
					'e1' => array(
						'title' => array( '{first_name}, a', 'b' ),
						'cta'   => 'Old',
					),
				),
			),
			array( 'default' => array( 'e1' => array( 'title' => array( 'New' ) ) ) )
		);

		self::assertSame( array( 'New' ), $merged['default']['e1']['title'] );
		self::assertSame( 'Old', $merged['default']['e1']['cta'] );
	}

	/**
	 * Every built-in language resolves the main texts.
	 */
	public function test_default_copy_is_complete(): void {
		$keys = array( 'default.e1.subject_a', 'default.e1.title', 'default.e1.cta', 'default.e2.subject_nocoupon', 'calm.e1.title', 'payment_failed.subject', 'footer.unsubscribe', 'unsubscribe_page.button', 'notice' );

		foreach ( array( 'en', 'sl', 'de' ) as $language ) {
			$copy = DefaultCopy::get( $language );

			self::assertIsArray( $copy );

			foreach ( $keys as $key ) {
				self::assertNotSame( '', Copy::text( $copy, $key, array( 'order_number' => '1' ) ), $language . ': ' . $key );
			}
		}

		self::assertNull( DefaultCopy::get( 'xx' ) );
	}
}
