<?php
/**
 * Built-in e-mail copy.
 *
 * @package StarfinitiAbandonedCart
 */

namespace Starfiniti\AbandonedCart\Email;

/**
 * Neutral default texts in English, Slovenian and German.
 *
 * Every value is a string or a list of alternatives: the first alternative whose
 * {placeholders} all have values is used, so missing data never shows. Store
 * packs override any key per language (see docs/PLAN.md and the AI skill).
 * The defaults make no claims about delivery, guarantees or reviews; those
 * belong in a store pack because only the store can confirm them.
 */
final class DefaultCopy {

	/**
	 * Return the copy of a language, or null when there is no built-in copy.
	 *
	 * @param string $language Two-letter code.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $language ): ?array {
		return match ( $language ) {
			'en' => self::english(),
			'sl' => self::slovenian(),
			'de' => self::german(),
			default => null,
		};
	}

	/**
	 * English copy.
	 *
	 * @return array<string, mixed>
	 */
	private static function english(): array {
		return array(
			'greeting'         => array( 'Hi {first_name},', 'Hello,' ),
			'greeting_calm'    => array( 'Dear {first_name},', 'Hello,' ),
			'closing'          => 'Warm regards,',
			'closing_calm'     => 'Kind regards,',
			'notice'           => "If you don't complete your order, we may send you up to 3 reminders. Unsubscribe with one click.",
			'consent_label'    => "Remind me if I don't complete my order (up to 3 e-mails, unsubscribe anytime).",
			'restored'         => 'Your cart has been restored.',
			'expired_link'     => 'This link has expired. Your cart is no longer saved.',
			'labels'           => array(
				'cart'         => 'Your cart',
				'card_message' => 'Your message',
				'card_hint'    => '',
				'subtotal'     => 'Subtotal',
				'shipping'     => 'Shipping',
				'total'        => 'Total',
				'code'         => 'Code {code}',
				'quantity'     => 'Qty',
				'your_code'    => 'Your personal code',
				'valid_today'  => 'Valid until {expiry_time} today',
			),
			'footer'           => array(
				'reason'      => 'You are receiving this e-mail because you started an order at {store}.',
				'unsubscribe' => 'No more reminders',
				'help_title'  => 'Any questions?',
			),
			'unsubscribe_page' => array(
				'title'      => 'Stop cart reminders',
				'text'       => 'Confirm and we will not send you any more cart reminders.',
				'button'     => 'Unsubscribe',
				'done_title' => 'You are unsubscribed',
				'done_text'  => 'We will not send you any more cart reminders.',
				'back'       => 'Back to the shop',
			),
			'default'          => array(
				'e1' => array(
					'subject_a' => 'Your cart is waiting for you',
					'subject_b' => 'Your cart is saved',
					'preheader' => 'Everything is ready, just one click to finish.',
					'eyebrow'   => 'Your cart is saved',
					'title'     => 'You left something in your cart',
					'body'      => 'Your order was not completed, so we saved your cart exactly as you left it. You can pick up right where you stopped.',
					'cta'       => 'Complete my order',
					'help'      => 'Did something stop you? Just reply to this e-mail and we will help.',
					'ps'        => '',
				),
				'e2' => array(
					'subject_a'          => 'Still thinking it over? (+ a gift for you)',
					'subject_b'          => '{discount} off your cart, just for you',
					'subject_nocoupon'   => 'Still thinking it over?',
					'preheader'          => 'Your personal code is valid {expiry_until}.',
					'preheader_nocoupon' => 'Your cart is still saved.',
					'eyebrow'            => 'A personal gift for you',
					'eyebrow_nocoupon'   => 'Your cart is still waiting',
					'title'              => 'Still thinking it over?',
					'body'               => 'To make the decision easier, here is **{discount} off** your cart.',
					'body_nocoupon'      => 'Your cart is still saved. If you have a question, just reply to this e-mail.',
					'coupon_line'        => '**{discount} off** · you save {savings} on this cart',
					'coupon_terms'       => 'valid {expiry_until} · just for you · single use',
					'cta'                => 'Complete my order · code applied',
					'cta_nocoupon'       => 'Complete my order',
					'cta_secondary'      => 'Complete my order',
					'objections_title'   => 'What might be on your mind',
					'objections'         => array(),
					'ps'                 => '',
				),
				'e3' => array(
					'subject_a' => 'Your {discount} code expires today at {expiry_time}',
					'subject_b' => 'Last day: {discount} off your cart',
					'preheader' => 'After that, we delete the code and your saved cart.',
					'eyebrow'   => 'Last day',
					'title'     => 'Your code is valid until {expiry_time} today.',
					'body'      => 'Just a quick reminder: your personal {discount} code is valid until {expiry_time} today. After that, we delete it together with your saved cart.',
					'cta'       => 'Complete my order with the discount',
					'reframe'   => '',
					'ps'        => '',
				),
			),
			'calm'             => array(
				'e1' => array(
					'subject_a' => 'Your order is saved',
					'subject_b' => 'Your order is saved',
					'preheader' => 'We saved it so you can complete it when you are ready.',
					'eyebrow'   => 'Your order is saved',
					'title'     => 'Your order was not completed.',
					'body'      => 'We saved your order so you can complete it whenever you are ready.',
					'cta'       => 'Complete my order',
					'help'      => 'If you need any help, just reply to this e-mail.',
				),
				'e2' => array(
					'subject_a' => 'Can we help with your order?',
					'subject_b' => 'Can we help with your order?',
					'preheader' => 'Your order is still saved.',
					'eyebrow'   => 'Can we help?',
					'title'     => 'Your order is still waiting.',
					'body'      => 'If you wish, you can complete it here. Just reply to this e-mail if you have any questions.',
					'cta'       => 'Complete my order',
				),
			),
			'payment_failed'   => array(
				'subject'   => 'Payment did not go through, your order is still waiting',
				'preheader' => 'You can pay for order {order_number} with one click.',
				'eyebrow'   => 'Order {order_number}',
				'title'     => 'Payment did not go through.',
				'body'      => 'We could not complete your order because the payment failed. You can try again or choose another payment method.',
				'cta'       => 'Pay for my order',
				'help'      => 'If it still does not work, reply to this e-mail and we will sort it out together.',
			),
		);
	}

	/**
	 * Slovenian copy.
	 *
	 * @return array<string, mixed>
	 */
	private static function slovenian(): array {
		return array(
			'greeting'         => array( 'Pozdravljeni, {first_name}!', 'Pozdravljeni!' ),
			'greeting_calm'    => array( 'Pozdravljeni, {first_name}.', 'Pozdravljeni.' ),
			'closing'          => 'Toplo,',
			'closing_calm'     => 'Lep pozdrav,',
			'notice'           => 'Če naročila ne zaključite, vam lahko pošljemo do 3 opomnike. Odjava z enim klikom.',
			'consent_label'    => 'Pošljite mi opomnik, če naročila ne zaključim (največ 3 e-maili, odjava kadar koli).',
			'restored'         => 'Vaša košarica je obnovljena.',
			'expired_link'     => 'Povezava je potekla. Košarica ni več shranjena.',
			'labels'           => array(
				'cart'         => 'Vaša košarica',
				'card_message' => 'Vaše sporočilo',
				'card_hint'    => '',
				'subtotal'     => 'Vmesni seštevek',
				'shipping'     => 'Dostava',
				'total'        => 'Skupaj',
				'code'         => 'Koda {code}',
				'quantity'     => 'Količina',
				'your_code'    => 'Vaša osebna koda',
				'valid_today'  => 'Velja še danes do {expiry_time}',
			),
			'footer'           => array(
				'reason'      => 'To sporočilo ste prejeli, ker ste začeli naročilo na {store}.',
				'unsubscribe' => 'Ne želim več opomnikov',
				'help_title'  => 'Imate vprašanje?',
			),
			'unsubscribe_page' => array(
				'title'      => 'Odjava od opomnikov',
				'text'       => 'Potrdite in ne bomo vam več pošiljali opomnikov o košarici.',
				'button'     => 'Odjava',
				'done_title' => 'Odjava je uspela',
				'done_text'  => 'Opomnikov o košarici vam ne bomo več pošiljali.',
				'back'       => 'Nazaj v trgovino',
			),
			'default'          => array(
				'e1' => array(
					'subject_a' => 'Vaša košarica vas še čaka',
					'subject_b' => 'Vaša košarica je shranjena',
					'preheader' => 'Vse je pripravljeno, manjka le še en klik.',
					'eyebrow'   => 'Vaša košarica je shranjena',
					'title'     => 'Nekaj ste pozabili v košarici',
					'body'      => 'Naročilo ni bilo zaključeno, zato smo košarico shranili točno takšno, kot ste jo pustili. Nadaljujete lahko tam, kjer ste ostali.',
					'cta'       => 'Dokončaj naročilo',
					'help'      => 'Vas je kaj ustavilo? Preprosto odgovorite na ta e-mail in pomagali vam bomo.',
					'ps'        => '',
				),
				'e2' => array(
					'subject_a'          => 'Še razmišljate? (+ darilo za vas)',
					'subject_b'          => '{discount} popusta na vašo košarico, samo za vas',
					'subject_nocoupon'   => 'Še razmišljate?',
					'preheader'          => 'Vaša osebna koda velja {expiry_until}.',
					'preheader_nocoupon' => 'Vaša košarica je še vedno shranjena.',
					'eyebrow'            => 'Osebno darilo za vas',
					'eyebrow_nocoupon'   => 'Vaša košarica še čaka',
					'title'              => 'Še razmišljate?',
					'body'               => 'Da bo odločitev lažja, vam podarjamo **{discount} popusta** na vašo košarico.',
					'body_nocoupon'      => 'Vaša košarica je še vedno shranjena. Če imate vprašanje, preprosto odgovorite na ta e-mail.',
					'coupon_line'        => '**{discount} popusta** · pri tej košarici prihranite {savings}',
					'coupon_terms'       => 'velja {expiry_until} · samo za vas · enkratna uporaba',
					'cta'                => 'Dokončaj naročilo · koda je že vnesena',
					'cta_nocoupon'       => 'Dokončaj naročilo',
					'cta_secondary'      => 'Dokončaj naročilo',
					'objections_title'   => 'Kar vas morda skrbi',
					'objections'         => array(),
					'ps'                 => '',
				),
				'e3' => array(
					'subject_a' => 'Vaša koda za {discount} popusta poteče danes ob {expiry_time}',
					'subject_b' => 'Zadnji dan: {discount} popusta na vašo košarico',
					'preheader' => 'Potem kodo in shranjeno košarico izbrišemo.',
					'eyebrow'   => 'Zadnji dan',
					'title'     => 'Vaša koda velja še danes do {expiry_time}.',
					'body'      => 'Samo kratek opomnik: vaša osebna koda za {discount} popusta velja še danes do {expiry_time}. Potem jo izbrišemo, skupaj s shranjeno košarico.',
					'cta'       => 'Dokončaj naročilo s popustom',
					'reframe'   => '',
					'ps'        => '',
				),
			),
			'calm'             => array(
				'e1' => array(
					'subject_a' => 'Vaše naročilo je shranjeno',
					'subject_b' => 'Vaše naročilo je shranjeno',
					'preheader' => 'Shranili smo ga, da ga dokončate, ko boste pripravljeni.',
					'eyebrow'   => 'Vaše naročilo je shranjeno',
					'title'     => 'Naročilo ni bilo zaključeno.',
					'body'      => 'Vaše naročilo smo shranili, da ga lahko dokončate, ko boste pripravljeni.',
					'cta'       => 'Dokončaj naročilo',
					'help'      => 'Če potrebujete pomoč, preprosto odgovorite na ta e-mail.',
				),
				'e2' => array(
					'subject_a' => 'Smo vam lahko v pomoč pri naročilu?',
					'subject_b' => 'Smo vam lahko v pomoč pri naročilu?',
					'preheader' => 'Naročilo še čaka. Za vprašanja smo vam na voljo.',
					'eyebrow'   => 'Smo vam lahko v pomoč?',
					'title'     => 'Vaše naročilo še čaka.',
					'body'      => 'Če želite, ga lahko dokončate tukaj. Za vsa vprašanja smo vam na voljo, preprosto odgovorite na ta e-mail.',
					'cta'       => 'Dokončaj naročilo',
				),
			),
			'payment_failed'   => array(
				'subject'   => 'Plačilo ni uspelo, vaše naročilo še čaka',
				'preheader' => 'Naročilo št. {order_number} lahko plačate z enim klikom.',
				'eyebrow'   => 'Naročilo št. {order_number}',
				'title'     => 'Plačilo ni uspelo.',
				'body'      => 'Naročila nismo mogli zaključiti, ker plačilo ni uspelo. Lahko poskusite znova ali izberete drug način plačila.',
				'cta'       => 'Plačaj naročilo',
				'help'      => 'Če se plačilo spet ne izide, nam odgovorite na ta e-mail in rešili bomo skupaj.',
			),
		);
	}

	/**
	 * German copy.
	 *
	 * @return array<string, mixed>
	 */
	private static function german(): array {
		return array(
			'greeting'         => array( 'Hallo {first_name},', 'Guten Tag,' ),
			'greeting_calm'    => array( 'Guten Tag {first_name},', 'Guten Tag,' ),
			'closing'          => 'Herzliche Grüße,',
			'closing_calm'     => 'Mit freundlichen Grüßen,',
			'notice'           => 'Wenn Sie die Bestellung nicht abschließen, senden wir Ihnen bis zu 3 Erinnerungen. Abmeldung mit einem Klick.',
			'consent_label'    => 'Erinnern Sie mich, falls ich die Bestellung nicht abschließe (höchstens 3 E-Mails, jederzeit abbestellbar).',
			'restored'         => 'Ihr Warenkorb wurde wiederhergestellt.',
			'expired_link'     => 'Dieser Link ist abgelaufen. Ihr Warenkorb ist nicht mehr gespeichert.',
			'labels'           => array(
				'cart'         => 'Ihr Warenkorb',
				'card_message' => 'Ihre Nachricht',
				'card_hint'    => '',
				'subtotal'     => 'Zwischensumme',
				'shipping'     => 'Versand',
				'total'        => 'Gesamt',
				'code'         => 'Code {code}',
				'quantity'     => 'Menge',
				'your_code'    => 'Ihr persönlicher Code',
				'valid_today'  => 'Gültig heute bis {expiry_time}',
			),
			'footer'           => array(
				'reason'      => 'Sie erhalten diese E-Mail, weil Sie bei {store} eine Bestellung begonnen haben.',
				'unsubscribe' => 'Keine Erinnerungen mehr',
				'help_title'  => 'Haben Sie Fragen?',
			),
			'unsubscribe_page' => array(
				'title'      => 'Erinnerungen abbestellen',
				'text'       => 'Bestätigen Sie, und wir senden Ihnen keine Warenkorb-Erinnerungen mehr.',
				'button'     => 'Abbestellen',
				'done_title' => 'Sie sind abgemeldet',
				'done_text'  => 'Wir senden Ihnen keine Warenkorb-Erinnerungen mehr.',
				'back'       => 'Zurück zum Shop',
			),
			'default'          => array(
				'e1' => array(
					'subject_a' => 'Ihr Warenkorb wartet auf Sie',
					'subject_b' => 'Ihr Warenkorb ist gespeichert',
					'preheader' => 'Alles ist vorbereitet, es fehlt nur noch ein Klick.',
					'eyebrow'   => 'Ihr Warenkorb ist gespeichert',
					'title'     => 'Sie haben etwas im Warenkorb vergessen',
					'body'      => 'Ihre Bestellung wurde nicht abgeschlossen, deshalb haben wir Ihren Warenkorb genau so gespeichert, wie Sie ihn verlassen haben. Sie können dort weitermachen, wo Sie aufgehört haben.',
					'cta'       => 'Bestellung abschließen',
					'help'      => 'Hat Sie etwas aufgehalten? Antworten Sie einfach auf diese E-Mail, wir helfen gerne.',
					'ps'        => '',
				),
				'e2' => array(
					'subject_a'          => 'Noch am Überlegen? (+ ein Geschenk für Sie)',
					'subject_b'          => '{discount} Rabatt auf Ihren Warenkorb, nur für Sie',
					'subject_nocoupon'   => 'Noch am Überlegen?',
					'preheader'          => 'Ihr persönlicher Code gilt {expiry_until}.',
					'preheader_nocoupon' => 'Ihr Warenkorb ist weiterhin gespeichert.',
					'eyebrow'            => 'Ein persönliches Geschenk für Sie',
					'eyebrow_nocoupon'   => 'Ihr Warenkorb wartet noch',
					'title'              => 'Noch am Überlegen?',
					'body'               => 'Damit Ihnen die Entscheidung leichter fällt, schenken wir Ihnen **{discount} Rabatt** auf Ihren Warenkorb.',
					'body_nocoupon'      => 'Ihr Warenkorb ist weiterhin gespeichert. Bei Fragen antworten Sie einfach auf diese E-Mail.',
					'coupon_line'        => '**{discount} Rabatt** · bei diesem Warenkorb sparen Sie {savings}',
					'coupon_terms'       => 'gültig {expiry_until} · nur für Sie · einmalig',
					'cta'                => 'Bestellung abschließen · Code ist eingelöst',
					'cta_nocoupon'       => 'Bestellung abschließen',
					'cta_secondary'      => 'Bestellung abschließen',
					'objections_title'   => 'Was Sie vielleicht beschäftigt',
					'objections'         => array(),
					'ps'                 => '',
				),
				'e3' => array(
					'subject_a' => 'Ihr Code über {discount} läuft heute um {expiry_time} ab',
					'subject_b' => 'Letzter Tag: {discount} Rabatt auf Ihren Warenkorb',
					'preheader' => 'Danach löschen wir den Code und Ihren gespeicherten Warenkorb.',
					'eyebrow'   => 'Letzter Tag',
					'title'     => 'Ihr Code gilt noch heute bis {expiry_time}.',
					'body'      => 'Nur eine kurze Erinnerung: Ihr persönlicher Code über {discount} Rabatt gilt noch heute bis {expiry_time}. Danach löschen wir ihn, zusammen mit Ihrem gespeicherten Warenkorb.',
					'cta'       => 'Mit Rabatt bestellen',
					'reframe'   => '',
					'ps'        => '',
				),
			),
			'calm'             => array(
				'e1' => array(
					'subject_a' => 'Ihre Bestellung ist gespeichert',
					'subject_b' => 'Ihre Bestellung ist gespeichert',
					'preheader' => 'Wir haben sie gespeichert, damit Sie sie abschließen können, wann immer Sie bereit sind.',
					'eyebrow'   => 'Ihre Bestellung ist gespeichert',
					'title'     => 'Ihre Bestellung wurde nicht abgeschlossen.',
					'body'      => 'Wir haben Ihre Bestellung gespeichert, damit Sie sie abschließen können, wann immer Sie bereit sind.',
					'cta'       => 'Bestellung abschließen',
					'help'      => 'Wenn Sie Hilfe brauchen, antworten Sie einfach auf diese E-Mail.',
				),
				'e2' => array(
					'subject_a' => 'Können wir Ihnen bei der Bestellung helfen?',
					'subject_b' => 'Können wir Ihnen bei der Bestellung helfen?',
					'preheader' => 'Ihre Bestellung wartet noch. Bei Fragen sind wir für Sie da.',
					'eyebrow'   => 'Können wir helfen?',
					'title'     => 'Ihre Bestellung wartet noch.',
					'body'      => 'Wenn Sie möchten, können Sie sie hier abschließen. Bei Fragen antworten Sie einfach auf diese E-Mail.',
					'cta'       => 'Bestellung abschließen',
				),
			),
			'payment_failed'   => array(
				'subject'   => 'Zahlung fehlgeschlagen, Ihre Bestellung wartet noch',
				'preheader' => 'Bestellung Nr. {order_number} können Sie mit einem Klick bezahlen.',
				'eyebrow'   => 'Bestellung Nr. {order_number}',
				'title'     => 'Die Zahlung ist fehlgeschlagen.',
				'body'      => 'Wir konnten Ihre Bestellung nicht abschließen, weil die Zahlung fehlgeschlagen ist. Sie können es erneut versuchen oder eine andere Zahlungsart wählen.',
				'cta'       => 'Bestellung bezahlen',
				'help'      => 'Falls es weiterhin nicht klappt, antworten Sie auf diese E-Mail, und wir lösen es gemeinsam.',
			),
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
