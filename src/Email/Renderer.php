<?php
/**
 * E-mail HTML and text rendering.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Email;

/**
 * Renders a model into e-mail-safe HTML (600 px tables, inline styles,
 * bulletproof buttons, hidden preheader) and a plain-text alternative.
 * Blocks without content render nothing.
 */
final class Renderer {

	/**
	 * Current model while rendering.
	 *
	 * @var array<string, mixed>
	 */
	private static array $model = array();

	/**
	 * Sanitized brand tokens while rendering.
	 *
	 * @var array<string, mixed>
	 */
	private static array $brand = array();

	/**
	 * Render a model.
	 *
	 * @param array<string, mixed> $model E-mail model from Context.
	 * @return array{subject: string, preheader: string, html: string, text: string}
	 */
	public static function render( array $model ): array {
		self::$model = $model;
		self::$brand = self::sanitize_brand( is_array( $model['brand'] ?? null ) ? $model['brand'] : Brand::defaults() );

		$rows = '';

		foreach ( (array) ( $model['blocks'] ?? array() ) as $block ) {
			$rows .= self::block( (string) $block );
		}

		/**
		 * Filters the rendered body rows, e.g. to add a store-specific block.
		 *
		 * @param string               $rows  Table rows.
		 * @param array<string, mixed> $model Model.
		 */
		$rows = (string) apply_filters( 'sfac_email_rows', $rows, $model );

		$html = self::document( $rows );

		return array(
			'subject'   => (string) ( $model['subject'] ?? '' ),
			'preheader' => (string) ( $model['preheader'] ?? '' ),
			'html'      => (string) apply_filters( 'sfac_email_html', $html, $model ),
			'text'      => self::text(),
		);
	}

	/**
	 * Render one block.
	 *
	 * @param string $name Block name.
	 */
	private static function block( string $name ): string {
		$m = self::$model;

		switch ( $name ) {
			case 'hero':
				$inner = self::eyebrow( (string) $m['eyebrow'] ) . self::heading( (string) $m['title'] );

				if ( '' !== (string) $m['greeting'] ) {
					$inner .= self::paragraph( (string) $m['greeting'], 6 );
				}

				return self::row( $inner . self::paragraph( (string) $m['body'], 0 ), '36px 40px 24px' );

			case 'coupon':
				return is_array( $m['coupon'] ) ? self::row( self::coupon_box( $m['coupon'] ), '0 40px 20px' ) : '';

			case 'cta':
				return '' !== (string) $m['cta'] ? self::row( self::button( (string) $m['cta'], (string) $m['urls']['cta'] ), '0 40px 26px' ) : '';

			case 'cta_secondary':
				return '' !== (string) $m['cta_second'] ? self::row( self::button( (string) $m['cta_second'], (string) $m['urls']['cta'] ), '0 40px 26px' ) : '';

			case 'cart':
				return array() !== $m['items'] ? self::row( self::label( self::copy( 'labels.cart' ) ) . self::items(), '4px 40px 16px' ) : '';

			case 'card_message':
				return self::card_message();

			case 'totals':
				return array() !== $m['items'] ? self::row( self::totals(), '0 40px 26px' ) : '';

			case 'info_box':
				return is_array( $m['info_box'] ) ? self::row( self::info_box( $m['info_box'] ), '0 40px 26px' ) : '';

			case 'objections':
				return array() !== $m['objections']['items'] ? self::row( self::label( (string) $m['objections']['title'] ) . self::objections(), '0 40px 22px' ) : '';

			case 'reviews':
				return self::reviews();

			case 'help':
				return '' !== (string) $m['help'] ? self::row( self::paragraph( (string) $m['help'], 0 ), '0 40px 22px' ) : '';

			case 'reframe':
				return '' !== (string) $m['reframe'] ? self::row( self::paragraph( (string) $m['reframe'], 0 ), '0 40px 22px' ) : '';

			case 'signature':
				return self::row( self::signature(), '4px 40px 30px' );

			case 'ps':
				return '' !== (string) $m['ps'] ? self::row( self::ps( (string) $m['ps'] ), '0 40px 34px' ) : '';
		}

		/**
		 * Renders a custom block.
		 *
		 * @param string               $html  Block HTML (table row) or empty.
		 * @param string               $name  Block name.
		 * @param array<string, mixed> $model Model.
		 */
		return (string) apply_filters( 'sfac_email_block', '', $name, $m );
	}

	/**
	 * Full HTML document.
	 *
	 * @param string $rows Body rows.
	 */
	private static function document( string $rows ): string {
		$b         = self::$brand;
		$m         = self::$model;
		$language  = esc_attr( (string) $m['language'] );
		$font_css  = wp_strip_all_tags( (string) $b['font_css'] );
		$preheader = esc_html( (string) $m['preheader'] ) . str_repeat( '&#847;&zwnj;&nbsp;', 30 );
		$topbar    = '' !== (string) $b['topbar']
			? '<tr><td class="sfac-topbar" align="center" style="background:' . $b['footer_bg'] . ';padding:10px 20px;' . self::font() . 'font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:' . $b['footer_text'] . ';">' . esc_html( (string) $b['topbar'] ) . '</td></tr>'
			: '';
		$logo      = '' !== (string) $b['logo_url']
			? '<img src="' . esc_url( (string) $b['logo_url'] ) . '" width="' . (int) $b['logo_width'] . '" alt="' . esc_attr( (string) $b['store_name'] ) . '" style="display:block;width:' . (int) $b['logo_width'] . 'px;max-width:100%;height:auto;margin:0 auto;border:0;">'
			: '<span style="' . self::font( true ) . 'font-size:26px;color:' . $b['heading'] . ';">' . esc_html( (string) $b['store_name'] ) . '</span>';

		return '<!DOCTYPE html><html lang="' . $language . '"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="x-apple-disable-message-reformatting"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . esc_html( (string) $m['subject'] ) . '</title>'
			. '<style type="text/css">' . $font_css . 'body{margin:0;padding:0;-webkit-text-size-adjust:100%;}img{border:0;outline:none;text-decoration:none;}'
			. '@media screen and (max-width:620px){.sfac-container{width:100%!important;}.sfac-px{padding-left:22px!important;padding-right:22px!important;}.sfac-h1{font-size:27px!important;line-height:33px!important;}.sfac-trust td{display:block!important;width:100%!important;padding:0 0 12px!important;}.sfac-topbar{font-size:10px!important;letter-spacing:1px!important;}}</style></head>'
			. '<body style="margin:0;padding:0;background:' . $b['bg'] . ';">'
			. '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:' . $b['bg'] . ';opacity:0;">' . $preheader . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $b['bg'] . ';"><tr><td align="center" style="padding:24px 12px 40px;">'
			. '<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->'
			. '<table role="presentation" class="sfac-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">'
			. $topbar
			. '<tr><td align="center" style="background:' . $b['surface'] . ';padding:28px 32px 22px;border-bottom:1px solid ' . $b['line'] . ';"><a href="' . esc_url( (string) $b['store_url'] ) . '" style="text-decoration:none;">' . $logo . '</a></td></tr>'
			. $rows
			. self::footer()
			. '</table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';
	}

	/**
	 * Footer: help block, trust row and legal line.
	 */
	private static function footer(): string {
		$b    = self::$brand;
		$m    = self::$model;
		$html = '';

		if ( '' !== (string) $b['phone'] || '' !== (string) $b['email'] ) {
			$text = '' !== (string) $b['help_text'] ? esc_html( (string) $b['help_text'] ) : esc_html( (string) $b['email'] );

			if ( '' !== (string) $b['hours'] ) {
				$text .= '<br>' . esc_html( (string) $b['hours'] );
			}

			$buttons = '';

			if ( '' !== (string) $b['phone'] ) {
				$buttons .= '<td style="background:' . $b['surface'] . ';"><a href="tel:' . esc_attr( (string) preg_replace( '/[^0-9+]/', '', (string) $b['phone'] ) ) . '" style="display:inline-block;padding:12px 20px;' . self::font() . 'font-size:12px;font-weight:700;letter-spacing:2px;color:' . $b['footer_bg'] . ';text-decoration:none;">' . esc_html( (string) $b['phone'] ) . '</a></td>';
			}

			if ( '' !== (string) $b['whatsapp'] ) {
				$buttons .= '<td width="10" style="font-size:0;line-height:0;">&nbsp;</td><td style="border:1px solid ' . $b['footer_text'] . ';"><a href="' . esc_url( 'https://wa.me/' . preg_replace( '/[^0-9]/', '', (string) $b['whatsapp'] ) ) . '" style="display:inline-block;padding:11px 18px;' . self::font() . 'font-size:12px;font-weight:700;letter-spacing:2px;color:' . $b['footer_text'] . ';text-decoration:none;">WHATSAPP</a></td>';
			}

			$html .= '<tr><td class="sfac-px" style="background:' . $b['footer_bg'] . ';padding:30px 40px;' . self::font() . '">'
				. '<p style="margin:0 0 6px;' . self::font( true ) . 'font-size:22px;line-height:28px;color:#FFFFFF;">' . esc_html( self::copy( 'footer.help_title' ) ) . '</p>'
				. '<p style="margin:0 0 ' . ( '' !== $buttons ? '18' : '0' ) . 'px;font-size:14px;line-height:22px;color:' . $b['footer_text'] . ';">' . $text . '</p>'
				. ( '' !== $buttons ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>' . $buttons . '</tr></table>' : '' )
				. '</td></tr>';
		}

		$trust = array();

		foreach ( (array) $b['trust'] as $item ) {
			if ( is_array( $item ) && '' !== (string) ( $item[0] ?? '' ) ) {
				$trust[] = '<td valign="top" style="padding:0 8px;' . self::font() . 'font-size:13px;line-height:19px;color:' . $b['text'] . ';"><strong style="color:' . $b['heading'] . ';">' . esc_html( (string) $item[0] ) . '</strong><br>' . esc_html( (string) ( $item[1] ?? '' ) ) . '</td>';
			}
		}

		if ( array() !== $trust ) {
			$html .= '<tr><td class="sfac-px" style="padding:26px 32px 4px;"><table role="presentation" class="sfac-trust" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . implode( '', $trust ) . '</tr></table></td></tr>';
		}

		$links = '<a href="' . esc_url( (string) $m['urls']['unsubscribe'] ) . '" style="color:' . $b['text'] . ';font-weight:700;">' . esc_html( self::copy( 'footer.unsubscribe' ) ) . '</a>';

		foreach ( (array) $b['links'] as $link ) {
			if ( is_array( $link ) && '' !== (string) ( $link[0] ?? '' ) && '' !== (string) ( $link[1] ?? '' ) ) {
				$links .= ' &nbsp;·&nbsp; <a href="' . esc_url( (string) $link[1] ) . '" style="color:' . $b['muted'] . ';">' . esc_html( (string) $link[0] ) . '</a>';
			}
		}

		$html .= '<tr><td align="center" style="padding:22px 24px 0;' . self::font() . 'font-size:12px;line-height:19px;color:' . $b['muted'] . ';">' . esc_html( self::copy( 'footer.reason' ) ) . '<br>' . $links . '</td></tr>';

		return $html;
	}

	/**
	 * Item list with add-on lines.
	 */
	private static function items(): string {
		$b    = self::$brand;
		$m    = self::$model;
		$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid ' . $b['line'] . ';border-bottom:1px solid ' . $b['line'] . ';">';

		foreach ( (array) $m['items'] as $item ) {
			$image = (string) ( $item['image'] ?? '' );
			$thumb = '' !== $image
				? '<img src="' . esc_url( $image ) . '" width="72" height="72" alt="" style="display:block;width:72px;height:72px;object-fit:cover;background:' . $b['soft'] . ';border:0;">'
				: '<div style="width:72px;height:72px;background:' . $b['soft'] . ';"></div>';
			$meta  = (string) ( $item['meta'] ?? '' );
			$qty   = (int) ( $item['quantity'] ?? 1 );
			$meta  = $qty > 1 ? trim( $meta . ' · ' . self::copy( 'labels.quantity' ) . ': ' . $qty, ' ·' ) : $meta;

			$html .= '<tr><td width="72" valign="top" style="padding:18px 16px 12px 0;">' . $thumb . '</td>'
				. '<td valign="top" style="padding:18px 0 12px;' . self::font() . '"><p style="margin:0;' . self::font( true ) . 'font-size:18px;line-height:24px;color:' . $b['heading'] . ';">' . esc_html( (string) ( $item['name'] ?? '' ) ) . '</p>'
				. ( '' !== $meta ? '<p style="margin:4px 0 0;font-size:13px;line-height:19px;color:' . $b['muted'] . ';">' . esc_html( $meta ) . '</p>' : '' ) . '</td>'
				. '<td align="right" valign="top" style="padding:20px 0 12px 12px;' . self::font() . 'font-size:15px;font-weight:700;color:' . $b['heading'] . ';white-space:nowrap;">' . esc_html( self::money( (float) ( $item['price'] ?? 0 ) ) ) . '</td></tr>';

			foreach ( (array) ( $item['children'] ?? array() ) as $child ) {
				$html .= '<tr><td></td><td valign="top" style="padding:0 0 10px;' . self::font() . 'font-size:14px;line-height:20px;color:' . $b['text'] . ';"><span style="color:' . $b['highlight'] . ';font-weight:700;">+</span>&nbsp; ' . esc_html( (string) ( $child['name'] ?? '' ) ) . '</td>'
					. '<td align="right" valign="top" style="padding:0 0 10px 12px;' . self::font() . 'font-size:14px;color:' . $b['text'] . ';white-space:nowrap;">' . esc_html( self::money( (float) ( $child['price'] ?? 0 ) ) ) . '</td></tr>';
			}
		}

		return $html . '</table>';
	}

	/**
	 * Totals table.
	 */
	private static function totals(): string {
		$b      = self::$brand;
		$t      = self::$model['totals'];
		$line   = static function ( string $label, string $value, bool $strong = false ) use ( $b ): string {
			$style = $strong ? 'color:' . $b['highlight'] . ';font-weight:700;' : '';

			return '<tr><td style="padding:4px 0;' . $style . '">' . esc_html( $label ) . '</td><td align="right" style="padding:4px 0;white-space:nowrap;' . $style . '">' . esc_html( $value ) . '</td></tr>';
		};
		$coupon = self::$model['coupon'];
		$html   = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="' . self::font() . 'font-size:14px;color:' . $b['text'] . ';">'
			. $line( self::copy( 'labels.subtotal' ), self::money( (float) $t['subtotal'] ) );

		if ( (float) $t['shipping'] > 0 ) {
			$html .= $line( self::copy( 'labels.shipping' ), self::money( (float) $t['shipping'] ) );
		}

		if ( (float) $t['discount'] > 0 && is_array( $coupon ) ) {
			$html .= $line( self::copy( 'labels.code', array( 'code' => (string) $coupon['code'] ) ), '−' . self::money( (float) $t['discount'] ), true );
		}

		return $html . '<tr><td style="padding:14px 0 0;border-top:2px solid ' . $b['heading'] . ';' . self::font( true ) . 'font-size:21px;color:' . $b['heading'] . ';">' . esc_html( self::copy( 'labels.total' ) ) . '</td>'
			. '<td align="right" style="padding:14px 0 0;border-top:2px solid ' . $b['heading'] . ';' . self::font( true ) . 'font-size:24px;color:' . $b['heading'] . ';white-space:nowrap;">' . esc_html( self::money( (float) $t['total'] ) ) . '</td></tr></table>';
	}

	/**
	 * Card message, or a hint when the store offers one and the shopper left it empty.
	 */
	private static function card_message(): string {
		$b       = self::$brand;
		$message = (string) self::$model['card_message'];

		if ( '' !== $message ) {
			return self::row(
				self::label( self::copy( 'labels.card_message' ) )
				. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid ' . $b['line'] . ';background:' . $b['surface'] . ';"><tr><td style="padding:20px 24px;border-left:3px solid ' . $b['highlight'] . ';"><p style="margin:0;' . self::font( true ) . 'font-size:19px;line-height:28px;color:' . $b['heading'] . ';">»' . esc_html( mb_substr( $message, 0, 160 ) ) . '«</p></td></tr></table>',
				'0 40px 22px'
			);
		}

		$hint = self::copy( 'labels.card_hint' );

		if ( '' === $hint ) {
			return '';
		}

		return self::row(
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px dashed ' . $b['line'] . ';"><tr><td style="padding:14px 18px;' . self::font() . 'font-size:14px;line-height:21px;color:' . $b['text'] . ';"><span style="color:' . $b['highlight'] . ';font-weight:700;">+</span>&nbsp; ' . esc_html( $hint ) . '</td></tr></table>',
			'0 40px 22px'
		);
	}

	/**
	 * Coupon box.
	 *
	 * @param array<string, mixed> $coupon Coupon data.
	 */
	private static function coupon_box( array $coupon ): string {
		$b     = self::$brand;
		$today = ! empty( $coupon['today'] );
		$label = $today ? self::copy( 'labels.valid_today', self::$model['vars'] ) : self::copy( 'labels.your_code' );

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px dashed ' . $b['highlight'] . ';background:' . $b['highlight_soft'] . ';"><tr><td align="center" style="padding:22px 20px;">'
			. self::eyebrow( $label )
			. '<p style="margin:0 0 8px;' . self::font( true ) . 'font-size:30px;line-height:1.15;letter-spacing:4px;color:' . $b['heading'] . ';">' . esc_html( (string) $coupon['code'] ) . '</p>'
			. ( '' !== (string) $coupon['line'] ? '<p style="margin:0;' . self::font() . 'font-size:13px;line-height:20px;color:' . $b['text'] . ';">' . self::rich( (string) $coupon['line'] ) . '</p>' : '' )
			. ( ! $today && '' !== (string) $coupon['terms'] ? '<p style="margin:2px 0 0;' . self::font() . 'font-size:13px;line-height:20px;color:' . $b['text'] . ';">' . esc_html( (string) $coupon['terms'] ) . '</p>' : '' )
			. '</td></tr></table>';
	}

	/**
	 * Info box.
	 *
	 * @param array<string, mixed> $box Label and text.
	 */
	private static function info_box( array $box ): string {
		$b = self::$brand;

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $b['soft'] . ';"><tr><td style="padding:20px 22px;">'
			. ( '' !== (string) ( $box['label'] ?? '' ) ? self::label( (string) $box['label'] ) : '' )
			. self::paragraph( (string) $box['text'], 0 )
			. '</td></tr></table>';
	}

	/**
	 * Objections list.
	 */
	private static function objections(): string {
		$b    = self::$brand;
		$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';

		foreach ( (array) self::$model['objections']['items'] as $index => $pair ) {
			$html .= '<tr><td style="padding:14px 0;' . ( $index > 0 ? 'border-top:1px solid ' . $b['line'] . ';' : '' ) . '">'
				. '<p style="margin:0 0 4px;' . self::font( true ) . 'font-size:18px;line-height:24px;color:' . $b['heading'] . ';">' . esc_html( (string) $pair[0] ) . '</p>'
				. '<p style="margin:0;' . self::font() . 'font-size:14px;line-height:22px;color:' . $b['text'] . ';">' . self::rich( (string) $pair[1] ) . '</p></td></tr>';
		}

		return $html . '</table>';
	}

	/**
	 * Rating line and quoted reviews.
	 */
	private static function reviews(): string {
		$b       = self::$brand;
		$m       = self::$model;
		$inner   = '';
		$reviews = (array) $m['reviews'];

		if ( '' !== (string) $m['rating'] ) {
			$inner .= '<p style="margin:0 0 12px;' . self::font() . 'font-size:15px;color:' . $b['heading'] . ';"><span style="color:' . $b['star'] . ';font-size:19px;letter-spacing:2px;">★★★★★</span>&nbsp; ' . esc_html( (string) $m['rating'] ) . '</p>';
		}

		foreach ( $reviews as $review ) {
			$inner .= '<p style="margin:0 0 10px;padding:12px 16px;background:' . $b['soft'] . ';' . self::font() . 'font-size:14px;line-height:21px;color:' . $b['text'] . ';"><em>»' . esc_html( (string) $review['text'] ) . '«</em>'
				. ( '' !== (string) $review['author'] ? '<br><span style="color:' . $b['muted'] . ';">' . esc_html( (string) $review['author'] ) . '</span>' : '' ) . '</p>';
		}

		return '' !== $inner ? self::row( $inner, '0 40px 22px' ) : '';
	}

	/**
	 * Signature.
	 */
	private static function signature(): string {
		$b      = self::$brand;
		$m      = self::$model;
		$signer = (string) $m['signer'];
		$store  = (string) $b['store_name'];

		return '<p style="margin:0;' . self::font() . 'font-size:15px;line-height:24px;color:' . $b['text'] . ';">' . esc_html( (string) $m['closing'] ) . '</p>'
			. '<p style="margin:2px 0 0;' . self::font( true ) . 'font-size:24px;line-height:30px;color:' . $b['heading'] . ';">' . esc_html( $signer ) . '</p>'
			. ( $signer !== $store ? '<p style="margin:0;' . self::font() . 'font-size:12px;letter-spacing:1.6px;text-transform:uppercase;color:' . $b['muted'] . ';">' . esc_html( $store ) . '</p>' : '' );
	}

	/**
	 * Postscript.
	 *
	 * @param string $text Text.
	 */
	private static function ps( string $text ): string {
		$b = self::$brand;

		return '<p style="margin:0;padding-top:16px;border-top:1px solid ' . $b['line'] . ';' . self::font() . 'font-size:14px;line-height:22px;color:' . $b['text'] . ';"><strong style="color:' . $b['heading'] . ';">P.S.</strong> ' . self::rich( $text ) . '</p>';
	}

	/**
	 * Body row.
	 *
	 * @param string $inner   Row content.
	 * @param string $padding CSS padding.
	 */
	private static function row( string $inner, string $padding ): string {
		return '<tr><td class="sfac-px" align="left" style="background:' . self::$brand['surface'] . ';padding:' . $padding . ';">' . $inner . '</td></tr>';
	}

	/**
	 * Small uppercase label above a heading.
	 *
	 * @param string $text Text.
	 */
	private static function eyebrow( string $text ): string {
		return '' === $text ? '' : '<p style="margin:0 0 12px;' . self::font() . 'font-size:11px;font-weight:700;letter-spacing:2.2px;text-transform:uppercase;color:' . self::$brand['highlight'] . ';">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Section label.
	 *
	 * @param string $text Text.
	 */
	private static function label( string $text ): string {
		return '' === $text ? '' : '<p style="margin:0 0 8px;' . self::font() . 'font-size:11px;font-weight:700;letter-spacing:2.2px;text-transform:uppercase;color:' . self::$brand['muted'] . ';">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Main heading.
	 *
	 * @param string $text Text.
	 */
	private static function heading( string $text ): string {
		return '' === $text ? '' : '<h1 class="sfac-h1" style="margin:0 0 14px;' . self::font( true ) . 'font-size:31px;line-height:38px;font-weight:400;color:' . self::$brand['heading'] . ';">' . esc_html( $text ) . '</h1>';
	}

	/**
	 * Paragraph with **bold** support.
	 *
	 * @param string $text          Text.
	 * @param int    $margin_bottom Bottom margin in pixels.
	 */
	private static function paragraph( string $text, int $margin_bottom ): string {
		return '' === $text ? '' : '<p style="margin:0 0 ' . $margin_bottom . 'px;' . self::font() . 'font-size:15px;line-height:24px;color:' . self::$brand['text'] . ';">' . self::rich( $text ) . '</p>';
	}

	/**
	 * Bulletproof button.
	 *
	 * @param string $label Button label.
	 * @param string $url   Target URL.
	 */
	private static function button( string $label, string $url ): string {
		$b      = self::$brand;
		$radius = (int) $b['button_radius'];

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="background:' . $b['accent'] . ';border-radius:' . $radius . 'px;">'
			. '<a href="' . esc_url( $url ) . '" style="display:block;padding:17px 22px;' . self::font() . 'font-size:13px;font-weight:700;letter-spacing:2.2px;text-transform:uppercase;color:' . $b['accent_text'] . ';text-decoration:none;border-radius:' . $radius . 'px;">' . esc_html( $label ) . '</a></td></tr></table>';
	}

	/**
	 * Escape text and turn **bold** into strong tags.
	 *
	 * @param string $text Text.
	 */
	private static function rich( string $text ): string {
		return (string) preg_replace( '/\*\*(.+?)\*\*/u', '<strong style="color:' . self::$brand['heading'] . ';">$1</strong>', esc_html( $text ) );
	}

	/**
	 * Font declaration.
	 *
	 * @param bool $heading Heading font instead of body font.
	 */
	private static function font( bool $heading = false ): string {
		return 'font-family:' . ( $heading ? self::$brand['font_heading'] : self::$brand['font_body'] ) . ';';
	}

	/**
	 * Resolve a copy key of the current model.
	 *
	 * @param string               $key  Dotted key.
	 * @param array<string, mixed> $vars Extra values.
	 */
	private static function copy( string $key, array $vars = array() ): string {
		$all = array_merge( (array) self::$model['vars'], array_map( 'strval', $vars ) );

		return Copy::text( (array) self::$model['copy'], $key, $all );
	}

	/**
	 * Format money in the model currency.
	 *
	 * @param float $amount Amount.
	 */
	private static function money( float $amount ): string {
		return Formatter::money( $amount, (string) self::$model['currency'] );
	}

	/**
	 * Plain-text version.
	 */
	private static function text(): string {
		$m     = self::$model;
		$lines = array();

		foreach ( array( 'title', 'greeting', 'body' ) as $key ) {
			if ( '' !== (string) $m[ $key ] ) {
				$lines[] = str_replace( '**', '', (string) $m[ $key ] );
			}
		}

		if ( is_array( $m['coupon'] ) ) {
			$lines[] = self::copy( 'labels.your_code' ) . ': ' . (string) $m['coupon']['code'];
			$lines[] = str_replace( '**', '', (string) $m['coupon']['line'] );
		}

		foreach ( (array) $m['items'] as $item ) {
			$lines[] = '- ' . (string) ( $item['name'] ?? '' ) . ( '' !== (string) ( $item['meta'] ?? '' ) ? ' (' . (string) $item['meta'] . ')' : '' ) . ': ' . self::money( (float) ( $item['price'] ?? 0 ) );
		}

		if ( '' !== (string) $m['cta'] ) {
			$lines[] = (string) $m['cta'] . ': ' . (string) $m['urls']['cta'];
		}

		foreach ( array( 'help', 'reframe' ) as $key ) {
			if ( '' !== (string) $m[ $key ] ) {
				$lines[] = (string) $m[ $key ];
			}
		}

		$lines[] = (string) $m['closing'] . "\n" . (string) $m['signer'];

		if ( '' !== (string) $m['ps'] ) {
			$lines[] = 'P.S. ' . str_replace( '**', '', (string) $m['ps'] );
		}

		$lines[] = self::copy( 'footer.reason' ) . "\n" . self::copy( 'footer.unsubscribe' ) . ': ' . (string) $m['urls']['unsubscribe'];

		return implode( "\n\n", array_filter( $lines, static fn( string $line ): bool => '' !== trim( $line ) ) );
	}

	/**
	 * Keep only safe CSS values in brand tokens.
	 *
	 * @param array<string, mixed> $brand Raw tokens.
	 * @return array<string, mixed>
	 */
	private static function sanitize_brand( array $brand ): array {
		$defaults = Brand::defaults();
		$colors   = array( 'bg', 'surface', 'text', 'heading', 'muted', 'line', 'soft', 'accent', 'accent_text', 'highlight', 'highlight_soft', 'star', 'footer_bg', 'footer_text' );

		foreach ( $colors as $key ) {
			$value         = (string) ( $brand[ $key ] ?? '' );
			$brand[ $key ] = preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ? $value : $defaults[ $key ];
		}

		foreach ( array( 'font_heading', 'font_body' ) as $key ) {
			$value         = (string) ( $brand[ $key ] ?? '' );
			$brand[ $key ] = preg_match( '/^[A-Za-z0-9 ,\'"\-]+$/', $value ) ? str_replace( '"', "'", $value ) : $defaults[ $key ];
		}

		$brand['button_radius'] = max( 0, min( 30, (int) ( $brand['button_radius'] ?? 0 ) ) );
		$brand['logo_width']    = max( 60, min( 300, (int) ( $brand['logo_width'] ?? 150 ) ) );

		return array_merge( $defaults, $brand );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
