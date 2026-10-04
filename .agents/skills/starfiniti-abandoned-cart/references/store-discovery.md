# Store discovery

Run on the store over SSH from the WordPress root. Read-only; nothing here changes the store.

## Platform

```bash
wp core version
wp plugin list --status=active --fields=name,version
wp option get woocommerce_custom_orders_table_enabled   # HPOS
wp eval 'echo wp_timezone_string();'
wp option get woocommerce_email_from_name; wp option get woocommerce_email_from_address
```

Note: page cache plugin (LiteSpeed, WP Rocket, …), SMTP plugin, consent plugin, multilingual plugin.

## Checkout

- Block or classic? `wp eval 'echo has_block("woocommerce/checkout", wc_get_page_id("checkout")) ? "block" : "classic";'`
- Custom steps? Look for checkout plugins or mu-plugins that wrap the checkout. Find the selector of the field where the shopper types the e-mail and the step it is in. If the e-mail comes late (e.g. step 3 of 4), recipient and delivery data are usually known by then.
- Open the checkout in a browser, fill the e-mail field, and confirm with the network tab that `POST /wp-json/sfac/v1/capture` fires (Test mode, test address).

## Cart data

```bash
wp eval 'wc_load_cart(); foreach ( WC()->cart->get_cart() as $k => $i ) { unset( $i["data"] ); print_r( array_keys( $i ) ); }'
```

Run after adding a typical product with its add-ons in a test session, or read the add-on plugin's `woocommerce_add_cart_item_data` / `add_to_cart` code. Write down:

- custom keys that must survive a restore,
- add-ons stored as separate child items and how they point to the parent,
- validation filters that block re-adding items without `$_POST` data.

## Personal data worth using

- Recipient name for gifts (shipping first name in most stores).
- Delivery date: where is it stored before the order (session key, Store API extension data, additional checkout field)?
- Card message text.

## Segments and wording

- Categories that need the calm sequence (funeral, sympathy, medical) – note their ids including children.
- Product types that change grammar (Slovenian gender: šopek m., aranžma m., darilo n.).
- Real deadlines and delivery rules from the checkout code or the delivery page.
- Claims on the site you may repeat (guarantee, rating source and value, free cancellation, photos before dispatch).

## Look of existing e-mails

Render one order e-mail without sending it and reuse its structure, colours, fonts and logo:

```bash
wp eval '$o = wc_get_order( ORDER_ID ); $e = WC()->mailer()->emails["WC_Email_Customer_Processing_Order"]; $e->object = $o; file_put_contents( "/tmp/order-mail.html", $e->style_inline( $e->get_content() ) );'
```

Remove personal data from the file before sharing it anywhere.

## Legal

Find the privacy policy and cookie policy pages (and their translations). The go-live step adds one paragraph about reminders.
