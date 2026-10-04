<?php
/**
 * E-mail sending.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Email;

use Starfiniti\AbandonedCart\Http\Endpoints;
use Starfiniti\AbandonedCart\Settings;

/**
 * Sends rendered e-mails through wp_mail() (so the store's SMTP plugin applies)
 * with a text alternative and one-click unsubscribe headers.
 */
final class Mailer {

	/**
	 * Plain-text body for the next PHPMailer instance.
	 *
	 * @var string
	 */
	private static string $alt_body = '';

	/**
	 * Send a rendered e-mail.
	 *
	 * @param string                                                                $to      Recipient.
	 * @param array{subject: string, preheader: string, html: string, text: string} $message Rendered e-mail.
	 * @param array<string, mixed>                                                  $row     Cart row.
	 */
	public static function send( string $to, array $message, array $row ): bool {
		if ( '' === $message['subject'] || '' === $message['html'] ) {
			return false;
		}

		$settings   = Settings::all();
		$brand      = Brand::get( (string) ( $row['locale'] ?? 'en' ) );
		$from_email = (string) get_option( 'woocommerce_email_from_address', '' );
		$from_email = is_email( $from_email ) ? $from_email : (string) get_option( 'admin_email' );
		$from_name  = '' !== (string) $settings['from_name'] ? (string) $settings['from_name'] : (string) $brand['store_name'];
		$reply_to   = '' !== (string) $settings['reply_to'] ? (string) $settings['reply_to'] : $from_email;
		$headers    = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . self::header_text( $from_name ) . ' <' . $from_email . '>',
			'Reply-To: ' . $reply_to,
		);

		if ( isset( $row['id'] ) && (int) $row['id'] > 0 ) {
			// URL only: a mailto unsubscribe would land in an inbox where nobody processes it.
			$headers[] = 'List-Unsubscribe: <' . Endpoints::unsubscribe_url( $row ) . '>';
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}

		/**
		 * Filters the headers of a reminder.
		 *
		 * @param list<string>         $headers Headers.
		 * @param array<string, mixed> $row     Cart row.
		 */
		$headers        = apply_filters( 'sfac_mail_headers', $headers, $row );
		self::$alt_body = $message['text'];

		add_action( 'phpmailer_init', array( self::class, 'add_text_part' ) );

		try {
			$sent = wp_mail( $to, $message['subject'], $message['html'], is_array( $headers ) ? $headers : array() );
		} finally {
			remove_action( 'phpmailer_init', array( self::class, 'add_text_part' ) );
			self::$alt_body = '';
		}

		return (bool) $sent;
	}

	/**
	 * Add the plain-text alternative.
	 *
	 * @param object $phpmailer PHPMailer instance.
	 */
	public static function add_text_part( object $phpmailer ): void {
		if ( '' !== self::$alt_body && property_exists( $phpmailer, 'AltBody' ) ) {
			$phpmailer->AltBody = self::$alt_body; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
		}
	}

	/**
	 * Remove characters that could break a header.
	 *
	 * @param string $text Header text.
	 */
	private static function header_text( string $text ): string {
		return trim( str_replace( array( "\r", "\n", '<', '>', '"' ), '', $text ) );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
