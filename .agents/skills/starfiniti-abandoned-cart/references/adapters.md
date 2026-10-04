# Store adapters

An adapter is a mu-plugin that connects the generic plugin to one store. Keep it small; only add what the store needs. Every callback that runs during checkout or cart requests is wrapped in `try/catch ( \Throwable )`.

## Filters and actions

| Hook | Use |
|---|---|
| `sfac_capture_context` ( array $context, WC_Cart, $customer, $session ) | Add `recipient`, `delivery_date` (Y-m-d), `card_message`, or short custom keys. They become placeholders. |
| `sfac_snapshot_item` ( array $item, array $cart_item ) | Set `parent_key` for add-on items; drop internal keys. |
| `sfac_restore_items` ( null, array $items, array $row ) | Restore items yourself (parents with add-ons). Return `array( 'restored' => n, 'failed' => n )` or null. |
| `sfac_cart_restored` ( array $row, int $step ) | Put session data back (e.g. delivery date). |
| `sfac_segment` ( string, array $snapshot, array $context ) | Return `calm` or a custom segment. `$snapshot['items'][*]['categories']` holds category ids with ancestors. |
| `sfac_sequences` ( array $definitions, array $settings ) | Change steps, delays or blocks; add segments. |
| `sfac_copy` ( array $copy, string $language ) | Change copy in PHP instead of JSON. |
| `sfac_copy_vars` ( array $vars, array $row, int $step, string $language ) | Grammar forms, product nouns, extra values. |
| `sfac_info_box` ( null, array $row, array $context, string $language, int $step ) | Return `array( 'label' => …, 'text' => … )`, e.g. an honest delivery deadline. |
| `sfac_brand` ( array $brand, string $language ) | Brand tokens in PHP. |
| `sfac_pack_dirs` ( list $dirs ) | Extra store pack directories. |
| `sfac_checkout_selectors` ( list $selectors ) | E-mail inputs on custom checkouts. |
| `sfac_notice_anchor` ( string ) | Element after which the notice or consent checkbox appears. |
| `sfac_checkout_url` ( string $url, string $language ) | Custom checkout URL per language. |
| `sfac_coupon_prefix`, `sfac_coupon_before_save` | Coupon code prefix, exclusions. |
| `sfac_offline_payment_methods` | Methods that never get the "payment failed" e-mail (default bacs, cod, cheque). |
| `sfac_email_model`, `sfac_email_block`, `sfac_email_rows`, `sfac_email_html`, `sfac_mail_headers` | Last-resort changes to rendering and sending. |
| `sfac_settings` | Force settings from code (e.g. per environment). |

## Worked example: Nina & Valentin

Facts found during discovery (2026-10):

- 4-step block checkout (nv-native-checkout); the e-mail is typed in step 3 into `#nv-billing-email-proxy`, wrapped in `.nv-email-proxy`.
- Recipient: shipping first name (step 1). Delivery date: `WC()->session->get( 'nv_checkout_options' )['delivery_date']`.
- Add-ons (nv-product-extras) are child items with `nv_extra_child`, `nv_parent_key`, `nv_extra_type` (upgrade, photo, gift, greeting); greeting text in `nv_ug_text`. Children cannot be added directly (validation blocks service products), so restore re-adds the parent with a rebuilt `$_POST['nv_extras']` and nonce `nv_product_extras`; the plugin's own `add_children()` then recreates the add-ons.
- Category 63 (Žalni aranžmaji) → calm segment. Category 60 (Bucket of Love) → noun "aranžma"; gifts 68/69 → "darilo"; default "šopek".
- Deadlines: weekday before 15:00 same-day delivery in Ljubljana area, express within 2 hours until 16:00, Sunday delivery when ordered by Saturday 12:00.
- Coupon decided by the owner: 10 % on products, 2 days, until 20:00. Signature "Nina" pending her approval.

```php
<?php
// wp-content/mu-plugins/starfiniti-ac/adapter.php (loaded by mu-plugins/starfiniti-ac.php)

add_filter( 'sfac_checkout_selectors', static fn( array $s ): array => array_merge( array( '#nv-billing-email-proxy' ), $s ) );
add_filter( 'sfac_notice_anchor', static fn(): string => '.nv-email-proxy' );

add_filter( 'sfac_capture_context', static function ( array $context, $cart, $customer, $session ): array {
	try {
		if ( $customer instanceof \WC_Customer ) {
			$context['recipient'] = (string) $customer->get_shipping_first_name();
		}
		$options                  = is_object( $session ) ? (array) $session->get( 'nv_checkout_options', array() ) : array();
		$context['delivery_date'] = (string) ( $options['delivery_date'] ?? '' );
		foreach ( $cart->get_cart() as $item ) {
			if ( 'greeting' === ( $item['nv_extra_type'] ?? '' ) && '' !== (string) ( $item['nv_ug_text'] ?? '' ) ) {
				$context['card_message'] = (string) $item['nv_ug_text'];
			}
		}
	} catch ( \Throwable $e ) {
		unset( $e );
	}
	return $context;
}, 10, 4 );

add_filter( 'sfac_snapshot_item', static function ( array $item, array $cart_item ): array {
	$item['parent_key'] = (string) ( $cart_item['nv_parent_key'] ?? '' );
	return $item;
}, 10, 2 );

add_filter( 'sfac_segment', static function ( string $segment, array $snapshot ): string {
	foreach ( $snapshot['items'] as $item ) {
		if ( in_array( 63, (array) ( $item['categories'] ?? array() ), true ) ) {
			return 'calm';
		}
	}
	return $segment;
}, 10, 2 );

// sfac_restore_items: for each parent, build $_POST['nv_extras'] from its children
// (upgrade qty, photo, gifts[id] = qty, greeting { type, text, attachment_id }),
// set $_POST['nv_product_extras_nonce'] = wp_create_nonce( 'nv_product_extras' ),
// call WC()->cart->add_to_cart( parent… ), then unset the $_POST keys.
// sfac_cart_restored: put a future delivery date back into nv_checkout_options.
// sfac_copy_vars: noun forms by category; sfac_info_box: the deadline rules above.
```
