# Starfiniti Abandoned Cart for WooCommerce

Abandoned cart recovery for WooCommerce, built by Starfiniti for the stores we build and maintain. Free and open source under the GPL.

When a shopper enters an e-mail address at checkout and leaves without ordering, the plugin saves the cart and sends up to three reminders: the first after an hour, the second after a day with a personal single-use coupon, the third on the coupon's last day. Every e-mail has a link that rebuilds the cart (coupon included) and a one-click unsubscribe.

**The plugin is off after activation.** Nothing is captured or sent until you choose Test or On under **WooCommerce → Abandoned carts**.

## Highlights

- Works with the block checkout, the classic checkout and custom multi-step checkouts (configurable field selectors).
- Personal coupons: percent or fixed, bound to the shopper's address, single use, expire at an exact local time and are deleted when unused.
- Honest copy by default; every placeholder has a fallback, so missing data never shows.
- English, Slovenian and German built in; TranslatePress, WPML and Polylang language detection and checkout URLs.
- Store packs: brand colours, fonts, logo, footer and copy per language as JSON files outside the plugin.
- Adapter filters for add-on items, delivery dates, gift recipients, sensitive segments and deadlines.
- Quiet hours, per-address cooldowns, "payment failed" reminder for unpaid online orders.
- Privacy: off by default, notice or consent checkbox at checkout, data retention, WordPress exporter and eraser, hashed opt-outs.
- Test mode, preview of every e-mail and test sending from the admin screen.
- Native WordPress updates from public GitHub Releases with SHA-256 verification. No telemetry, licence server or account.

## Requirements

PHP 8.1+, WordPress 6.6+, WooCommerce 9.0+ (HPOS supported).

## Setting it up for a store

Read `docs/PLAN.md` for the architecture and the AI skill in `.agents/skills/starfiniti-abandoned-cart/` for the step-by-step store setup: discovery, store pack, adapter, copy, coupons, preview and go-live.

## Development

```bash
composer install
composer check
```

`composer check` runs PHPCS (WordPress standards), PHPStan level 6 and PHPUnit. The end-to-end test runs on a site with WooCommerce:

```bash
wp eval-file tests/integration/wp-cli-smoke.php
```

Release package: `npm install && npm run package`.
