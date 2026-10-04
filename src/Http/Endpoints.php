<?php
/**
 * Restore and unsubscribe links.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Http;

use Starfiniti\AbandonedCart\Capture\CaptureService;
use Starfiniti\AbandonedCart\Cart\Restorer;
use Starfiniti\AbandonedCart\Email\Brand;
use Starfiniti\AbandonedCart\Email\Copy;
use Starfiniti\AbandonedCart\Integration\Cache;
use Starfiniti\AbandonedCart\Integration\Multilingual;
use Starfiniti\AbandonedCart\Store\Carts;
use Starfiniti\AbandonedCart\Store\Contacts;
use Starfiniti\AbandonedCart\Store\Events;
use Starfiniti\AbandonedCart\Support\Logger;
use Starfiniti\AbandonedCart\Support\Secret;
use Starfiniti\AbandonedCart\Support\Tokens;
use Throwable;

// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Signed one-time links from e-mails; the token is the authorization.

/**
 * Handles ?sfac=restore&t=…&s=N and ?sfac=unsubscribe&t=… on the front end.
 */
final class Endpoints {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'wp_loaded', array( self::class, 'handle' ), 30 );
	}

	/**
	 * Build the restore link of a step.
	 *
	 * @param array<string, mixed> $row  Cart row.
	 * @param int                  $step Step number.
	 */
	public static function restore_url( array $row, int $step ): string {
		return add_query_arg(
			array(
				'sfac' => 'restore',
				't'    => self::token( $row ),
				's'    => $step,
			),
			home_url( '/' )
		);
	}

	/**
	 * Build the unsubscribe link.
	 *
	 * @param array<string, mixed> $row Cart row.
	 */
	public static function unsubscribe_url( array $row ): string {
		return add_query_arg(
			array(
				'sfac' => 'unsubscribe',
				't'    => self::token( $row ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Route a link request.
	 */
	public static function handle(): void {
		if ( empty( $_GET['sfac'] ) || is_admin() ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['sfac'] ) );

		if ( ! in_array( $action, array( 'restore', 'unsubscribe' ), true ) ) {
			return;
		}

		Cache::nocache();

		$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
		$row   = self::row_for_token( $token );

		if ( 'restore' === $action ) {
			$step = isset( $_GET['s'] ) ? absint( $_GET['s'] ) : 1;
			self::restore( $row, max( 1, min( 9, $step ) ) );
			return;
		}

		self::unsubscribe( $row );
	}

	/**
	 * Restore the cart and continue to the checkout.
	 *
	 * @param array<string, mixed>|null $row  Cart row or null for an invalid link.
	 * @param int                       $step Step of the e-mail.
	 */
	private static function restore( ?array $row, int $step ): void {
		$target = home_url( '/' );

		try {
			$language = null !== $row ? (string) $row['locale'] : Multilingual::current();
			$copy     = Copy::for_language( '' !== $language ? $language : 'en' );

			if ( null === $row || in_array( (string) $row['status'], array( 'ordered', 'recovered' ), true ) ) {
				wp_safe_redirect( $target );
				exit;
			}

			if ( '' === (string) $row['cart'] ) {
				self::notice( Copy::text( $copy, 'expired_link', array() ), 'notice' );
				wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $target );
				exit;
			}

			$result = Restorer::restore( $row, $step );

			if ( 0 === $result['restored'] ) {
				self::notice( Copy::text( $copy, 'expired_link', array() ), 'notice' );
				wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $target );
				exit;
			}

			$session = function_exists( 'WC' ) ? WC()->session : null;

			if ( $session instanceof \WC_Session_Handler ) {
				if ( Carts::STATUS_ACTIVE === (string) $row['status'] ) {
					$session->set( CaptureService::SESSION_ID, (int) $row['id'] );
				}

				$session->set_customer_session_cookie( true );
			}

			Carts::update( (int) $row['id'], array( 'clicks' => (int) $row['clicks'] + 1 ) );
			Events::add( (int) $row['id'], 'click', $step, (string) $row['variant'] );

			if ( function_exists( 'wc_clear_notices' ) ) {
				wc_clear_notices();
			}

			self::notice( Copy::text( $copy, 'restored', array() ), 'success' );

			$target = add_query_arg(
				array(
					'utm_source'   => 'email',
					'utm_medium'   => 'abandoned_cart',
					'utm_campaign' => 'reminder_' . $step,
					'utm_content'  => strtolower( (string) $row['variant'] ),
				),
				Multilingual::checkout_url( '' !== $language ? $language : 'en' )
			);
		} catch ( Throwable $error ) {
			Logger::exception( 'Cart restore failed.', $error );
		}

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Show the unsubscribe confirmation or process it.
	 *
	 * @param array<string, mixed>|null $row Cart row or null for an invalid link.
	 */
	private static function unsubscribe( ?array $row ): void {
		$language = null !== $row && '' !== (string) $row['locale'] ? (string) $row['locale'] : Multilingual::current();
		$copy     = Copy::for_language( $language );
		$is_post  = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );

		if ( $is_post && null !== $row ) {
			try {
				Contacts::touch( (string) $row['email_hash'], 'optout_at' );

				if ( Carts::STATUS_ACTIVE === (string) $row['status'] ) {
					Carts::update(
						(int) $row['id'],
						array(
							'status'       => 'unsubscribed',
							'next_send_at' => null,
						)
					);
				}

				Events::add( (int) $row['id'], 'unsubscribed', (int) $row['step'], (string) $row['variant'] );
			} catch ( Throwable $error ) {
				Logger::exception( 'Unsubscribe failed.', $error );
			}
		}

		if ( $is_post && isset( $_POST['List-Unsubscribe'] ) ) {
			status_header( 200 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'OK';
			exit;
		}

		$done = $is_post || null === $row;
		self::page(
			$language,
			Copy::text( $copy, $done ? 'unsubscribe_page.done_title' : 'unsubscribe_page.title', array() ),
			Copy::text( $copy, $done ? 'unsubscribe_page.done_text' : 'unsubscribe_page.text', array() ),
			$done ? '' : Copy::text( $copy, 'unsubscribe_page.button', array() ),
			Copy::text( $copy, 'unsubscribe_page.back', array() )
		);
	}

	/**
	 * Print a minimal branded page and stop.
	 *
	 * @param string $language Two-letter code.
	 * @param string $title    Heading.
	 * @param string $text     Text.
	 * @param string $button   Confirm button label; empty for no form.
	 * @param string $back     Back link label.
	 */
	private static function page( string $language, string $title, string $text, string $button, string $back ): void {
		$brand = Brand::get( $language );
		$form  = '';

		if ( '' !== $button ) {
			$form = '<form method="post"><button type="submit" style="margin-top:20px;padding:14px 26px;border:0;border-radius:0;background:' . esc_attr( (string) $brand['accent'] ) . ';color:' . esc_attr( (string) $brand['accent_text'] ) . ';font:700 13px/1 sans-serif;letter-spacing:2px;text-transform:uppercase;cursor:pointer;">' . esc_html( $button ) . '</button></form>';
		}

		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		printf(
			'<!DOCTYPE html><html lang="%1$s"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>%2$s</title></head><body style="margin:0;background:%3$s;font-family:%4$s;color:%5$s;"><main style="max-width:520px;margin:12vh auto;padding:32px 24px;background:#fff;"><h1 style="margin:0 0 12px;font-family:%6$s;font-weight:400;font-size:28px;color:%7$s;">%2$s</h1><p style="margin:0;font-size:16px;line-height:1.5;">%8$s</p>%9$s<p style="margin:28px 0 0;"><a href="%10$s" style="color:%7$s;">%11$s</a></p></main></body></html>',
			esc_attr( $language ),
			esc_html( $title ),
			esc_attr( (string) $brand['bg'] ),
			esc_attr( (string) $brand['font_body'] ),
			esc_attr( (string) $brand['text'] ),
			esc_attr( (string) $brand['font_heading'] ),
			esc_attr( (string) $brand['heading'] ),
			esc_html( $text ),
			$form, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
			esc_url( (string) $brand['store_url'] ),
			esc_html( $back )
		);
		exit;
	}

	/**
	 * Add a WooCommerce notice when available.
	 *
	 * @param string $message Notice text.
	 * @param string $type    Notice type.
	 */
	private static function notice( string $message, string $type ): void {
		if ( '' !== $message && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $type );
		}
	}

	/**
	 * Return the verified row of a token.
	 *
	 * @param string $token Token from the link.
	 * @return array<string, mixed>|null
	 */
	private static function row_for_token( string $token ): ?array {
		$cart_id = Tokens::cart_id( $token );

		if ( $cart_id <= 0 ) {
			return null;
		}

		$row = Carts::find( $cart_id );

		if ( null === $row || ! Tokens::verify( $token, (string) $row['created_at'], Secret::get() ) ) {
			return null;
		}

		return $row;
	}

	/**
	 * Return the token of a row.
	 *
	 * @param array<string, mixed> $row Cart row.
	 */
	private static function token( array $row ): string {
		return Tokens::create( (int) ( $row['id'] ?? 0 ), (string) ( $row['created_at'] ?? '' ), Secret::get() );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
