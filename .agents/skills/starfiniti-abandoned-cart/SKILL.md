---
name: starfiniti-abandoned-cart
description: Set up, customise or troubleshoot Starfiniti Abandoned Cart for a WooCommerce store - store discovery, store pack (brand + copy JSON), adapter mu-plugin, reminder e-mail HTML and copy, personal coupons, previews, test mode and go-live. Use when a store needs abandoned cart reminders, when changing their wording, design, coupon or timing, or when reminders are not captured or not sent.
---

# Starfiniti Abandoned Cart: store setup

The plugin is generic. Everything store-specific lives in a **store pack** and an **adapter** under `wp-content/mu-plugins/starfiniti-ac/` on the store, never in the plugin repository. Read `docs/PLAN.md` once for the architecture.

## Hard rules

1. **Mode stays Off or Test until the store owner approves the e-mails.** Test mode captures and e-mails only the addresses listed under WooCommerce → Abandoned carts → Settings. Never send to real customers while testing.
2. **Never label a value as approved unless the owner named it** (discount, coupon validity, sender, quoted reviews). A short "ok" to a list of proposals is not approval of a number. Restate the exact value and wait.
3. **Every claim in the copy must exist on the store's site** (delivery promises, guarantees, ratings). Never invent reviews, scarcity or deadlines. Deadlines come from the store's real rules.
4. **Never break checkout.** Adapter code that runs on checkout, Store API, cart or order hooks is wrapped in `try { … } catch ( \Throwable $e ) { … }` and returns the original value. After every deploy check `wp-content/uploads/wc-logs/fatal-errors-*.log`.
5. **Back up before editing a live store** and work in its SSH session with WP-CLI. No personal data in logs, commits or chat.
6. **Sensitive purchases** (funeral, sympathy, medical) get the calm segment: no coupon, no sales wording.

## Workflow

1. **Discover the store** → `references/store-discovery.md`. Note checkout type and the step where the e-mail is entered, add-on/custom cart data, delivery date storage, multilingual plugin, SMTP, cache, legal pages and the look of the existing order e-mails.
2. **Brand pack** → `assets/brand-pack-template/brand.json`. Copy colours, fonts and logo from an existing WooCommerce order e-mail of the store so reminders look like the rest of its mail. HTML rules: `references/email-html.md`.
3. **Copy** → `references/copywriting.md` and `assets/brand-pack-template/copy-sl.json`. Write per language; every personal line needs a neutral fallback.
4. **Coupon** → `references/coupons.md`. Settings: amount, type, validity, expiry hour, cooldown, prefix. Get the value from the owner.
5. **Adapter** → `references/adapters.md` and `assets/brand-pack-template/adapter.php`. Only what the store needs: context (recipient, date, card message), child items, segment, info box, selectors.
6. **Preview and test** → `references/preview-and-testing.md`. Admin preview for every scenario × language, test e-mails, then Test mode with a test address through the real checkout.
7. **Client review** → build one HTML page for the owner with every e-mail × scenario (render them from the admin preview at phone and desktop width, with a short note on when each one is sent). Let the owner approve wording, signature, coupon and quoted reviews; write down what was approved and what is still open.
8. **Go live** → `references/go-live.md`. Notice text, privacy policy, mode On, monitoring after 7 and 30 days.

## Where things are

| Need | Place |
|---|---|
| Settings | WooCommerce → Abandoned carts → Settings (option `sfac_settings`) |
| Preview, test e-mails | WooCommerce → Abandoned carts → Preview and test |
| Captured carts, KPIs | WooCommerce → Abandoned carts → Overview; tables `{prefix}sfac_carts`, `sfac_events`, `sfac_contacts` |
| Logs | WooCommerce → Status → Logs, source `starfiniti-abandoned-cart` |
| Scheduler | Action Scheduler hook `sfac_tick`, group `starfiniti-abandoned-cart`, every 5 minutes |
| Store pack | `wp-content/mu-plugins/starfiniti-ac/brand.json`, `copy/{language}.json` |
| Adapter | `wp-content/mu-plugins/starfiniti-ac.php` (loads `starfiniti-ac/adapter.php`) |

## Troubleshooting quick checks

- Nothing captured: mode, test address list, `sfac_checkout_selectors` match the e-mail field, REST `POST /wp-json/sfac/v1/capture` reachable and not cached, cart not empty, address not opted out or in cooldown.
- Captured but not sent: Action Scheduler runs (`wp action-scheduler run --hooks=sfac_tick` or WP-Cron), quiet hours, `next_send_at`, SMTP log, row status.
- Link opens an empty cart: products deleted or out of stock, child items need `sfac_restore_items`, page cache serving `?sfac=` URLs.
