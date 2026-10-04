<?php
/**
 * E-mail model builder.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Email;

use Starfiniti\AbandonedCart\Http\Endpoints;
use Starfiniti\AbandonedCart\Settings;
use Starfiniti\AbandonedCart\Support\Names;

/**
 * Turns a cart row and a sequence step into everything the renderer needs:
 * resolved texts, items, totals, links, brand tokens and the block list.
 */
final class Context {

	/**
	 * Build the model of a reminder e-mail.
	 *
	 * @param array<string, mixed> $row  Cart row.
	 * @param int                  $step Step number.
	 * @param array<string, mixed> $def  Step definition.
	 * @return array<string, mixed>
	 */
	public static function for_cart( array $row, int $step, array $def ): array {
		$language = (string) ( '' !== (string) $row['locale'] ? $row['locale'] : 'en' );
		$copy     = Copy::for_language( $language );
		$brand    = Brand::get( $language );
		$settings = Settings::all();
		$timezone = wp_timezone();
		$segment  = (string) $row['segment'];
		$prefix   = $segment . '.e' . $step;
		$context  = json_decode( (string) $row['context'], true );
		$context  = is_array( $context ) ? $context : array();
		$cart     = json_decode( (string) $row['cart'], true );
		$items    = is_array( $cart ) && is_array( $cart['items'] ?? null ) ? $cart['items'] : array();
		$currency = (string) $row['currency'];
		$code     = (string) $row['coupon_code'];
		$coupon   = '' !== $code && ( ! empty( $def['coupon'] ) || ! empty( $def['requires_coupon'] ) );
		$expires  = strtotime( (string) $row['coupon_expires_at'] . ' UTC' );
		$items_t  = (float) $row['items_total'];
		$amount   = (float) $settings['coupon_amount'];
		$savings  = 'percent' === $settings['coupon_type'] ? round( $items_t * $amount / 100, 2 ) : min( $amount, $items_t );

		$vars = array(
			'first_name'    => Names::first( (string) $row['first_name'] ),
			'store'         => (string) $brand['store_name'],
			'discount'      => Formatter::discount( (string) $settings['coupon_type'], $amount, $language, $currency ),
			'code'          => $code,
			'savings'       => $coupon ? Formatter::money( $savings, $currency ) : '',
			'expiry_until'  => $coupon && false !== $expires ? Formatter::until( $expires, $timezone, $language ) : '',
			'expiry_time'   => $coupon && false !== $expires ? Formatter::time( $expires, $timezone, $language ) : '',
			'delivery_date' => '',
		);

		foreach ( $context as $key => $value ) {
			if ( is_string( $key ) && is_scalar( $value ) && ! isset( $vars[ $key ] ) ) {
				$vars[ $key ] = (string) $value;
			}
		}

		$vars['recipient'] = Names::first( (string) ( $context['recipient'] ?? '' ) );
		$date              = (string) ( $context['delivery_date'] ?? '' );

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date_time = date_create_immutable( $date . ' 12:00:00', $timezone );

			if ( false !== $date_time ) {
				$vars['delivery_date'] = Formatter::day_date( $date_time->getTimestamp(), $timezone, $language );
			}
		}

		/**
		 * Filters placeholder values of a reminder, e.g. grammar forms for a product type.
		 *
		 * @param array<string, string> $vars     Placeholder values.
		 * @param array<string, mixed>  $row      Cart row.
		 * @param int                   $step     Step number.
		 * @param string                $language Two-letter code.
		 */
		$vars = apply_filters( 'sfac_copy_vars', $vars, $row, $step, $language );
		$vars = is_array( $vars ) ? array_map( 'strval', $vars ) : array();

		$field = static function ( string $name ) use ( $copy, $prefix, $vars, $coupon ): string {
			if ( ! $coupon ) {
				$fallback = Copy::text( $copy, $prefix . '.' . $name . '_nocoupon', $vars );

				if ( '' !== $fallback ) {
					return $fallback;
				}
			}

			return Copy::text( $copy, $prefix . '.' . $name, $vars );
		};

		$subject_a = $field( 'subject_a' );
		$subject_b = $field( 'subject_b' );

		if ( ! $coupon ) {
			$no_coupon = Copy::text( $copy, $prefix . '.subject_nocoupon', $vars );
			$subject_a = '' !== $no_coupon ? $no_coupon : $subject_a;
			$subject_b = '' !== $no_coupon ? $no_coupon : $subject_b;
		}

		$subject  = 'B' === (string) $row['variant'] ? $subject_b : $subject_a;
		$subject  = '' !== $subject ? $subject : ( '' !== $subject_a ? $subject_a : $subject_b );
		$calm     = 'calm' === $segment;
		$discount = $coupon ? ( 'percent' === $settings['coupon_type'] ? round( $items_t * $amount / 100, 2 ) : min( $amount, $items_t ) ) : 0.0;

		/**
		 * Filters the info box of a reminder, e.g. an honest delivery deadline.
		 *
		 * Return array{label: string, text: string} or null for no box.
		 *
		 * @param array{label: string, text: string}|null $box      Info box.
		 * @param array<string, mixed>                    $row      Cart row.
		 * @param array<string, mixed>                    $context  Personalization data.
		 * @param string                                  $language Two-letter code.
		 * @param int                                     $step     Step number.
		 */
		$info_box = apply_filters( 'sfac_info_box', null, $row, $context, $language, $step );

		$model = array(
			'language'     => $language,
			'brand'        => $brand,
			'copy'         => $copy,
			'vars'         => $vars,
			'blocks'       => is_array( $def['blocks'] ?? null ) ? $def['blocks'] : array( 'hero', 'cart', 'totals', 'cta', 'signature' ),
			'subject'      => $subject,
			'preheader'    => $field( 'preheader' ),
			'eyebrow'      => $field( 'eyebrow' ),
			'title'        => $field( 'title' ),
			'greeting'     => Copy::text( $copy, $calm ? 'greeting_calm' : 'greeting', $vars ),
			'body'         => $field( 'body' ),
			'cta'          => $field( 'cta' ),
			'cta_second'   => $field( 'cta_secondary' ),
			'help'         => $field( 'help' ),
			'ps'           => $field( 'ps' ),
			'reframe'      => $field( 'reframe' ),
			'closing'      => Copy::text( $copy, $calm ? 'closing_calm' : 'closing', $vars ),
			'signer'       => '' !== (string) $settings['signer'] ? (string) $settings['signer'] : (string) $brand['store_name'],
			'objections'   => array(
				'title' => $field( 'objections_title' ),
				'items' => Copy::pairs( $copy, $prefix . '.objections', $vars ),
			),
			'reviews'      => is_array( $settings['reviews'] ) ? $settings['reviews'] : array(),
			'rating'       => (string) $settings['rating_text'],
			'coupon'       => $coupon ? array(
				'code'  => $code,
				'line'  => $field( 'coupon_line' ),
				'terms' => $field( 'coupon_terms' ),
				'today' => 3 <= $step,
			) : null,
			'card_message' => (string) ( $context['card_message'] ?? '' ),
			'info_box'     => is_array( $info_box ) && '' !== (string) ( $info_box['text'] ?? '' ) ? $info_box : null,
			'items'        => self::group( $items ),
			'currency'     => $currency,
			'totals'       => array(
				'subtotal' => $items_t,
				'shipping' => (float) $row['shipping_total'],
				'discount' => $discount,
				'total'    => max( 0.0, $items_t + (float) $row['shipping_total'] - $discount ),
			),
			'urls'         => array(
				'cta'         => Endpoints::restore_url( $row, $step ),
				'unsubscribe' => Endpoints::unsubscribe_url( $row ),
			),
			'step'         => $step,
		);

		/**
		 * Filters the complete e-mail model before rendering.
		 *
		 * @param array<string, mixed> $model Model.
		 * @param array<string, mixed> $row   Cart row.
		 */
		$filtered = apply_filters( 'sfac_email_model', $model, $row );

		return is_array( $filtered ) ? $filtered : $model;
	}

	/**
	 * Build the model of a "payment failed" e-mail.
	 *
	 * @param array<string, mixed> $row   Cart row.
	 * @param \WC_Order            $order Unpaid order.
	 * @return array<string, mixed>
	 */
	public static function for_payment_failed( array $row, \WC_Order $order ): array {
		$language = (string) ( '' !== (string) $row['locale'] ? $row['locale'] : 'en' );
		$copy     = Copy::for_language( $language );
		$brand    = Brand::get( $language );
		$settings = Settings::all();
		$vars     = array(
			'first_name'   => Names::first( (string) $order->get_billing_first_name() ),
			'store'        => (string) $brand['store_name'],
			'order_number' => (string) $order->get_order_number(),
		);
		$pay_url  = add_query_arg(
			array(
				'utm_source'   => 'email',
				'utm_medium'   => 'abandoned_cart',
				'utm_campaign' => 'payment_failed',
			),
			$order->get_checkout_payment_url()
		);

		return array(
			'language'     => $language,
			'brand'        => $brand,
			'copy'         => $copy,
			'vars'         => $vars,
			'blocks'       => array( 'hero', 'cta', 'help', 'signature' ),
			'subject'      => Copy::text( $copy, 'payment_failed.subject', $vars ),
			'preheader'    => Copy::text( $copy, 'payment_failed.preheader', $vars ),
			'eyebrow'      => Copy::text( $copy, 'payment_failed.eyebrow', $vars ),
			'title'        => Copy::text( $copy, 'payment_failed.title', $vars ),
			'greeting'     => Copy::text( $copy, 'greeting', $vars ),
			'body'         => Copy::text( $copy, 'payment_failed.body', $vars ),
			'cta'          => Copy::text( $copy, 'payment_failed.cta', $vars ),
			'cta_second'   => '',
			'help'         => Copy::text( $copy, 'payment_failed.help', $vars ),
			'ps'           => '',
			'reframe'      => '',
			'closing'      => Copy::text( $copy, 'closing', $vars ),
			'signer'       => '' !== (string) $settings['signer'] ? (string) $settings['signer'] : (string) $brand['store_name'],
			'objections'   => array(
				'title' => '',
				'items' => array(),
			),
			'reviews'      => array(),
			'rating'       => '',
			'coupon'       => null,
			'card_message' => '',
			'info_box'     => null,
			'items'        => array(),
			'currency'     => (string) $order->get_currency(),
			'totals'       => array(
				'subtotal' => 0.0,
				'shipping' => 0.0,
				'discount' => 0.0,
				'total'    => (float) $order->get_total(),
			),
			'urls'         => array(
				'cta'         => $pay_url,
				'unsubscribe' => Endpoints::unsubscribe_url( $row ),
			),
			'step'         => 0,
		);
	}

	/**
	 * Group child items (add-ons) under their parent item.
	 *
	 * @param array<int|string, mixed> $items Stored items.
	 * @return list<array<string, mixed>>
	 */
	public static function group( array $items ): array {
		$parents  = array();
		$children = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$parent_key = (string) ( $item['parent_key'] ?? '' );

			if ( '' !== $parent_key ) {
				$children[ $parent_key ][] = $item;
				continue;
			}

			$parents[] = $item;
		}

		$known = array();

		foreach ( $parents as $index => $parent ) {
			$key                           = (string) ( $parent['key'] ?? '' );
			$known[ $key ]                 = true;
			$parents[ $index ]['children'] = $children[ $key ] ?? array();
		}

		foreach ( $children as $parent_key => $orphans ) {
			if ( ! isset( $known[ $parent_key ] ) ) {
				foreach ( $orphans as $orphan ) {
					$orphan['children'] = array();
					$parents[]          = $orphan;
				}
			}
		}

		return $parents;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
