<?php
/**
 * Cart snapshot.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Cart;

/**
 * Turns a live WooCommerce cart into a JSON-safe description that can be
 * e-mailed and restored later.
 */
final class Snapshot {

	/**
	 * Cart item keys owned by WooCommerce that are never stored as custom data.
	 */
	private const INTERNAL_KEYS = array(
		'key',
		'product_id',
		'variation_id',
		'variation',
		'quantity',
		'data',
		'data_hash',
		'line_tax_data',
		'line_subtotal',
		'line_subtotal_tax',
		'line_total',
		'line_tax',
	);

	/**
	 * Build a snapshot from a cart.
	 *
	 * @param \WC_Cart $cart Live cart.
	 * @return array{items: list<array<string, mixed>>, items_total: float, shipping_total: float, currency: string, hash: string}
	 */
	public static function from_cart( \WC_Cart $cart ): array {
		$items = array();
		$total = 0.0;

		foreach ( $cart->get_cart() as $key => $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}

			$product = $cart_item['data'] ?? null;

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$item = self::describe( (string) $key, $cart_item, $product );

			/**
			 * Filters one stored cart item, e.g. to link add-on items to their parent.
			 *
			 * @param array<string, mixed> $item      Stored item.
			 * @param array<string, mixed> $cart_item Live cart item.
			 */
			$item = apply_filters( 'sfac_snapshot_item', $item, $cart_item );

			if ( ! is_array( $item ) ) {
				continue;
			}

			$items[] = $item;
			$total  += (float) ( $item['price'] ?? 0 );
		}

		$shipping = (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax();

		return array(
			'items'          => $items,
			'items_total'    => round( $total, 4 ),
			'shipping_total' => round( $shipping, 4 ),
			'currency'       => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '',
			'hash'           => self::hash( $items ),
		);
	}

	/**
	 * Describe one cart item.
	 *
	 * @param string               $key       Cart item key.
	 * @param array<string, mixed> $cart_item Live cart item.
	 * @param \WC_Product          $product   Item product.
	 * @return array<string, mixed>
	 */
	private static function describe( string $key, array $cart_item, \WC_Product $product ): array {
		$product_id   = (int) ( $cart_item['product_id'] ?? $product->get_id() );
		$variation_id = (int) ( $cart_item['variation_id'] ?? 0 );
		$parent       = $variation_id > 0 ? wc_get_product( $product_id ) : null;
		$name         = $parent instanceof \WC_Product ? $parent->get_name() : $product->get_name();
		$meta         = '';

		if ( $product instanceof \WC_Product_Variation && function_exists( 'wc_get_formatted_variation' ) ) {
			$meta = wp_strip_all_tags( (string) wc_get_formatted_variation( $product, true, true, false ) );
		}

		$image_id = (int) $product->get_image_id();

		if ( 0 === $image_id && $parent instanceof \WC_Product ) {
			$image_id = (int) $parent->get_image_id();
		}

		$image = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';

		return array(
			'key'          => $key,
			'parent_key'   => '',
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'variation'    => self::json_safe( is_array( $cart_item['variation'] ?? null ) ? $cart_item['variation'] : array() ),
			'quantity'     => max( 1, (int) ( $cart_item['quantity'] ?? 1 ) ),
			'name'         => wp_strip_all_tags( $name ),
			'meta'         => $meta,
			'price'        => round( (float) ( $cart_item['line_subtotal'] ?? 0 ) + (float) ( $cart_item['line_subtotal_tax'] ?? 0 ), 4 ),
			'image'        => is_string( $image ) ? $image : '',
			'categories'   => self::category_ids( $product_id ),
			'custom'       => self::custom_data( $cart_item ),
		);
	}

	/**
	 * Return the custom (non-WooCommerce) cart item data in JSON-safe form.
	 *
	 * @param array<string, mixed> $cart_item Live cart item.
	 * @return array<string, mixed>
	 */
	public static function custom_data( array $cart_item ): array {
		$custom = array();

		foreach ( $cart_item as $key => $value ) {
			if ( in_array( (string) $key, self::INTERNAL_KEYS, true ) ) {
				continue;
			}

			$safe = self::json_safe( $value );

			if ( null !== $safe ) {
				$custom[ (string) $key ] = $safe;
			}
		}

		return $custom;
	}

	/**
	 * Keep only scalars and arrays of scalars.
	 *
	 * @param mixed $value Any value.
	 * @return mixed
	 */
	public static function json_safe( mixed $value ): mixed {
		if ( is_scalar( $value ) || null === $value ) {
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return null;
		}

		$out = array();

		foreach ( $value as $key => $item ) {
			$safe = self::json_safe( $item );

			if ( null !== $safe || null === $item ) {
				$out[ $key ] = $safe;
			}
		}

		return $out;
	}

	/**
	 * Hash the parts of the items that define the cart contents.
	 *
	 * @param list<array<string, mixed>> $items Stored items.
	 */
	public static function hash( array $items ): string {
		$parts = array();

		foreach ( $items as $item ) {
			$parts[] = array(
				$item['product_id'] ?? 0,
				$item['variation_id'] ?? 0,
				$item['quantity'] ?? 0,
				$item['custom'] ?? array(),
			);
		}

		return md5( (string) wp_json_encode( $parts ) );
	}

	/**
	 * Return product category ids including ancestors.
	 *
	 * @param int $product_id Product identifier.
	 * @return list<int>
	 */
	private static function category_ids( int $product_id ): array {
		if ( ! function_exists( 'wc_get_product_term_ids' ) ) {
			return array();
		}

		$ids = array();

		foreach ( wc_get_product_term_ids( $product_id, 'product_cat' ) as $term_id ) {
			$ids[] = (int) $term_id;

			foreach ( get_ancestors( (int) $term_id, 'product_cat', 'taxonomy' ) as $ancestor ) {
				$ids[] = (int) $ancestor;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
