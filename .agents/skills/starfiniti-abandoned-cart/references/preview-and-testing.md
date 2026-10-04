# Preview and testing

## 1. Admin preview

WooCommerce → Abandoned carts → Preview and test. Check every combination:

| Scenario | Steps | Why |
|---|---|---|
| All data | 1, 2, 3 | Names, card message, date, coupon |
| Missing data | 1, 2 | Every fallback line |
| Calm | 1, 2 | Sensitive purchases |
| Payment failed | – | Unpaid online order |

… in every store language. Read every line out loud: grammar, names in nominative, no empty placeholders, no claim the store cannot back.

## 2. Test e-mails

"Send all samples" sends six e-mails to an address you control. Check in Gmail (web and phone app) and Outlook: layout, fonts, images, button width, preheader, subject length (≤ 50 characters show fully on phones). Sample coupon codes do not work by design.

## 3. Test mode on the store

1. Settings → Mode: Test, test addresses: your own address(es).
2. In a private window: add a typical product with add-ons, go to checkout, type the test address, leave.
3. Overview shows the cart with status `active` and the next e-mail time.
4. Speed up: `wp db query "UPDATE {prefix}sfac_carts SET next_send_at = UTC_TIMESTAMP() WHERE email = 'you@…'"` then `wp action-scheduler run --hooks=sfac_tick --force` (or `wp eval 'Starfiniti\AbandonedCart\Sequence\Runner::tick();'`). Mind quiet hours.
5. Repeat for e-mail 2 (coupon created, restricted to your address) and 3.
6. Click the restore link in a fresh browser: cart rebuilt with add-ons, coupon applied, checkout in the right language.
7. Click unsubscribe, confirm: status `unsubscribed`, no more e-mails, a new capture with the same address is ignored.
8. Place a real order with a test address and check the row turns `ordered` / `recovered`.
9. `ls -la wp-content/uploads/wc-logs/ | grep fatal` and WooCommerce → Status → Logs (`starfiniti-abandoned-cart`).

## 4. Automated

In the plugin repository: `composer check` and `wp eval-file tests/integration/wp-cli-smoke.php` on a site with WooCommerce (never on a live store's production data without Test mode and a backup).
