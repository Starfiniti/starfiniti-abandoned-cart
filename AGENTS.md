# Starfiniti Abandoned Cart for WooCommerce

This directory is an independent WordPress plugin repository. Read `docs/PLAN.md` before changing anything, and the skill in `.agents/skills/starfiniti-abandoned-cart/` before setting the plugin up for a store.

## Non-negotiable boundaries

- The plugin recovers abandoned carts: capture, reminder e-mails, personal coupons, cart restore, unsubscribe, reporting, privacy tools.
- Do not implement newsletters, marketing automation, SMS, payment processing, checkout redesign, telemetry, licensing or a proprietary update service.
- The plugin is **off by default**. Nothing is captured or sent until the store owner switches the mode to Test or On.
- Never break checkout. Every callback on checkout, Store API, cart session or order paths is wrapped in `try/catch ( \Throwable )`, logs through `Support\Logger` and returns the original value. Test the success path, not only error paths.
- Store-specific behaviour (custom checkouts, add-on items, delivery dates, segments, wording) belongs in a per-store adapter that uses the `sfac_*` filters, never in this repository.
- Public PHP identifiers use `sfac_`; PHP classes live under `Starfiniti\AbandonedCart`; CSS classes use `.sfac-*`; the text domain is `starfiniti-abandoned-cart`.
- Native WordPress updates may use only public, stable GitHub Releases from the owned repository with SHA-256 verification.
- WooCommerce is the only required runtime dependency.
- Never commit secrets, customer data, generated dependencies or local WordPress data.
- Complete one approved batch at a time and stop at its gate (see `docs/PLAN.md`).

## Supported baseline

- PHP 8.1+
- WordPress 6.6+
- WooCommerce 9.0+, HPOS, block and classic checkout

## Verification

- PHP: `composer check` (PHPCS, PHPStan level 6, PHPUnit)
- Integration: `wp eval-file tests/integration/wp-cli-smoke.php` on a site with WooCommerce (CI runs it on MySQL)
- Release artifact: `npm run package`
