# Starfiniti Abandoned Cart — implementation plan

Status: batch 1 (core plugin) built and verified locally on 2026-10-04 (PHPCS, PHPStan level 6, PHPUnit, WP-CLI integration smoke test). Next: test on the NIV staging copy (niv.estorly.com). Written 2026-10-04.

## Context

WooCommerce has no built-in abandoned-cart recovery. The ready-made plugins (CartFlows Cart Abandonment Recovery, FunnelKit Automations, Retainful, …) assume a stock classic checkout, ship their own tracking scripts and generic e-mail templates, and break on custom block checkouts, child "add-on" cart items and multilingual sites. Starfiniti builds and maintains many WooCommerce stores, so this becomes a reusable, open plugin in the same family as Starfiniti Cart.

First store: **Nina & Valentin** (ninainvalentin.si). The sequence, copy, coupon rule and e-mail look were worked out with Dejan; the wording still waits for Nina's approval:

- Review preview for the owner (private; local copy `Ninainvalentin/outputs/opomniki-kosarica/`). Still open: signature, two quoted Google reviews, legal line.
- Sequence: e-mail 1 after 1 h (no discount) → e-mail 2 after 24 h with a **personal 10 % coupon valid 2 days** → e-mail 3 on the coupon's last day at 10:00 ("expires today at 20:00") → unused coupon and saved cart deleted at 20:00. Sympathy (funeral) carts get 2 calm e-mails, no coupon.
- Store specifics the plugin must support without hard-coding them: 4-step block checkout where the e-mail is entered in step 3 (`#nv-billing-email-proxy`), add-ons stored as child cart items (`nv_parent_key`, voščilo text in `nv_ug_text`), delivery date in the WC session (`nv_checkout_options['delivery_date']`), TranslatePress SL/EN/DE, LiteSpeed Cache, SureMails SMTP.

Outcome: the plugin installed on NIV in mode **Off → Test**, switched **On** only after Nina approves, plus an AI skill so the next store is set up in hours, not days.

## Boundaries (same rules as Starfiniti Cart)

- Does: capture checkout e-mail + cart, timed e-mail sequence, personal coupons, one-click cart restore, unsubscribe, reporting, privacy tools.
- Does not: newsletters/marketing automation, SMS, payment processing, checkout redesign (only a capture listener and one notice line), telemetry, licensing, proprietary update service.
- Names: namespace `Starfiniti\AbandonedCart`, prefix `sfac_`, CSS `.sfac-*`, text domain `starfiniti-abandoned-cart`, repo `Starfiniti/starfiniti-abandoned-cart`.
- Baseline: PHP 8.1+, WordPress 6.6+, WooCommerce 9.0+, HPOS, block and classic checkout. WooCommerce is the only required dependency.
- Updates from public GitHub Releases with SHA-256 verification (reuse Cart's updater).
- **Off by default.** Nothing is captured until mode is Test or On.
- **Never break checkout.** Every hook on checkout/Store API/order paths is wrapped in `try/catch (\Throwable)` and is fire-and-forget. (Lesson from 2026-10-04: a logging hook fatal-errored on a successful order.)

## Reuse from Starfiniti Cart (`Starfiniti/Starfiniti Cart/`)

Copy and adapt rather than rewrite:

- Bootstrap + PSR-4 fallback autoloader: `starfiniti-cart.php`
- `src/Lifecycle/{Activator,Deactivator,Installer,Uninstaller}.php`, `src/Requirements.php`, `src/RequirementFailure.php`
- `src/Updates/GitHubUpdater.php`, `scripts/package.mjs`, `scripts/make-l10n.mjs`
- `src/Support/Logger.php`, `src/Analytics/{Tables,Repository,Privacy,PublicRateLimiter,Time}.php` as patterns for tables, retention, rate limiting
- `src/Settings.php` (typed settings + sanitization), `src/Admin/AdminPage.php` + React admin build (wp-scripts)
- Tooling: `composer.json` (PHPCS/WPCS, PHPStan + WooCommerce stubs, PHPUnit), `package.json`, `playground/` blueprint, `playwright.config.ts`, `.github/workflows/ci.yml`, `phpcs.xml.dist`, `phpstan.neon.dist`

## Architecture

```
starfiniti-abandoned-cart.php
src/
  Plugin.php  Settings.php  Requirements.php
  Lifecycle/   Activator, Deactivator, Installer (tables, schedule), Uninstaller
  Capture/     StoreApiCapture   woocommerce_store_api_cart_update_customer_from_request
               ClassicCapture    woocommerce_checkout_update_order_review
               RestCapture       POST sfac/v1/capture (checkout script: e-mail blur/change + beacon on hide)
               AccountCapture    logged-in e-mail on checkout view
               CartSync          woocommerce_cart_updated → refresh snapshot / mark emptied
               OrderWatcher      *_checkout_order_processed → ordered; unpaid order after 60 min → payment-failed mail
  Cart/        Snapshot (items, parent/child, display prices incl. tax, images, whitelisted cart item data)
               Restorer (empty → re-add → children → customer fields → coupon → session → redirect)
  Sequence/    Definition (steps, delays, conditions, segment variants), Scheduler (Action Scheduler
               recurring sfac_tick every 5 min, batch + lock), Runner (stop checks, quiet hours, send, next step)
  Coupons/     Factory (unique code, percent/fixed/free-shipping, e-mail restriction, single use, exact expiry
               timestamp), Janitor (delete unused expired + safety sweep), Eligibility (per-e-mail cooldown)
  Email/       Context (snapshot + customer + adapter data), Copy (per-locale strings with fallback chains),
               Blocks (hero, items, card message, totals, cta, coupon, info box, objections, reviews, signature,
               ps, help footer, trust row, legal footer), Layout, Mailer (headers, List-Unsubscribe one-click,
               text part, UTM, A/B subject), Preview (admin render + test send)
  Http/        Endpoints ?sfac=restore&t=…&s=N  /  ?sfac=unsubscribe&t=… (GET + POST one-click), no-cache incl. LiteSpeed
  Integration/ Multilingual (TranslatePress, WPML, Polylang: locale + translated checkout URL), LiteSpeed
  Privacy/     Exporter, Eraser, retention purge, policy text via wp_add_privacy_policy_content
  Admin/       Batch 1: server-rendered settings + preview/test send; Batch 3: React app like Cart
templates/emails/  default layout + blocks (overridable)
languages/   en (source), sl, de
```

### Data

- `sfac_carts`: id, token_hash (link carries the raw token, DB stores SHA-256), session_key, user_id, email, email_hash, locale, currency, first_name, context JSON (recipient, delivery date, card message, segment data), cart JSON, items_total, shipping_total, segment, sequence, subject variant, status, step, attempts, next_send_at, last_activity_at, coupon_id/code/expires_at, order_id, clicks, created_at, updated_at. Indexes on (status, next_send_at), email_hash, session_key, token_hash.
- `sfac_events`: cart_id, type (captured, sent_N, click, coupon_created, coupon_deleted, ordered, recovered, unsubscribed, error), meta JSON, created_at. Drives reporting and audit.
- `sfac_contacts`: email_hash PK, optout_at, last_coupon_at, last_sequence_at (survives cart retention, holds only hashes).
- Statuses: active → ordered | recovered (order within the attribution window after ≥1 e-mail) | unsubscribed | emptied | stopped | expired | done | error.
- Retention: carts and events 30 days (setting), contacts hash-only indefinitely.

### Sequence rules (defaults = NIV, all configurable)

- Trigger: valid e-mail + non-empty cart; each activity pushes e-mail 1 to `last_activity + 60 min`.
- E-mail 2 at `last_activity + 24 h`; creates the coupon at send time (10 %, expires 20:00 local on day +2); no coupon if the e-mail got one in the last 60 days (then no e-mail 3).
- E-mail 3 at 10:00 local on the expiry day. Janitor deletes the unused coupon and clears the saved cart at expiry.
- Quiet hours 21:00–08:00 local → shift to 08:00 + jitter. One sequence per e-mail per 14 days.
- Stop on: order with same session or e-mail, unsubscribe, emptied cart, mode change.
- Segments: a filter returns a segment (NIV: product in category 63 tree → `sympathy` = 2 calm e-mails, no coupon).
- Payment failed: order still `pending`/`failed` 60 min after creation (not BACS/COD) → one e-mail with the order pay link.

### Adapter API (what makes it reusable)

Store-specific behaviour lives in a small per-store adapter, never in the plugin:

| Filter | Purpose | NIV implementation |
|---|---|---|
| `sfac_capture_context` | extra personalisation data | recipient = shipping first name, delivery date from `nv_checkout_options`, card message from greeting child |
| `sfac_snapshot_item` / `sfac_restore_items` | child/add-on items | rebuild `$_POST['nv_extras']` + nonce, re-add parent so nv-product-extras recreates children |
| `sfac_segment` | alternate sequence | category 63 tree → `sympathy` |
| `sfac_copy_vars` | grammar/noun forms | šopek / aranžma / darilo with gender and case forms |
| `sfac_info_box` | honest deadline text | weekday < 15:00 same day, < 16:00 express, Saturday < 12:00 Sunday, chosen date / date passed |
| `sfac_checkout_selectors` | where the e-mail is typed | `#nv-billing-email-proxy` + block contact e-mail |
| `sfac_notice_anchor` | where the notice line goes | after `.nv-email-proxy` |

### Per-store "brand pack" (generated by the skill)

`wp-content/mu-plugins/starfiniti-ac/`: `adapter.php`, `brand.json` (colors, fonts, logo URL, button style, footer contacts, trust items), `copy/{sl,en,de}.json` (overrides of default copy), optional `templates/` overrides, loaded by `mu-plugins/starfiniti-ac.php`. PHP stays out of uploads; survives plugin updates.

## AI skill

Repo path `.agents/skills/starfiniti-abandoned-cart/` (Codex) mirrored to `.claude/skills/starfiniti-abandoned-cart/` (Claude). Short `SKILL.md` + references loaded on demand:

```
SKILL.md                    when to use, workflow, hard rules, links
references/
  store-discovery.md        exact WP-CLI/grep commands: checkout type and step where e-mail is entered,
                            add-on/custom cart data, multilingual plugin, SMTP, cache, consent, legal pages,
                            existing WC e-mail template (render one order e-mail and reuse its look)
  email-html.md             table layout, 600 px, inline CSS, bulletproof buttons, preheader, absolute https
                            images + alt, MSO conditionals, web fonts with fallbacks, light color-scheme meta,
                            < 102 KB (Gmail clipping), text part, no JS, accessibility
  copywriting.md            buyer psychology (gift giver vs self-purchase, fears → answers, social proof,
                            loss aversion only at the real deadline), honest urgency from real store rules,
                            no invented reviews/scarcity, fallbacks for every placeholder, A/B subjects,
                            grammar notes (Slovenian cases → names only in nominative, gendered verbs),
                            sensitive segments (sympathy) tone
  coupons.md                types, defaults, expiry math, cooldown, deletion, abuse rules,
                            "never mark a value as approved unless the owner named it"
  adapters.md               every filter with signature + the NIV adapter as a worked example
  preview-and-testing.md    render matrix (full / missing data / segment × locales × phone/desktop),
                            test send, link checks (restore, coupon, unsubscribe), fatal-error log check
  go-live.md                legal notice + privacy policy text (SL/EN/DE), mode switch, monitoring, KPIs at 7/30 days
assets/
  brand-pack-template/      brand.json schema + example, copy JSON skeleton, adapter.php skeleton
  review-page/              the Nina review page as a template for client approval pages
```

Hard rules in SKILL.md: mode stays Off/Test until the owner approves; never send to real customers while testing; back up before editing a live store; wrap checkout hooks in try/catch; no personal data in logs or chat; every factual claim in copy must exist on the store's site.

## Delivery in batches (each ends at a gate)

1. **Core engine.** Repo scaffold with Cart tooling; tables; settings (mode Off/Test/On, test addresses, coupon %, validity, cooldown, quiet hours, sender name, reply-to, reviews, retention); all capture paths; snapshot/restore; order watcher; sequence runner + default 3-step and segment variants; coupons + janitor; e-mail engine with default neutral template and EN/SL/DE default copy; restore/unsubscribe endpoints; preview + test send; privacy exporter/eraser; logging. *Gate: Dejan checks preview and test e-mails.*
2. **NIV adapter + brand pack.** Adapter per the table above; brand from the existing NIV order e-mails; copy from the review preview once Nina approves it. Deploy to NIV in Off, then Test with a test address through the real checkout up to step 3 (no order). *Gate: Nina approves → On, checkout notice live, privacy policy SL/EN/DE updated.*
3. **React admin.** Overview KPIs (captured, sent per step, clicks, recovered orders and revenue, A/B), carts list with masked e-mails, sequence and copy editors with live preview, CSV export.
4. **Release 1.0.0.** README, INSTALLATION, COMPATIBILITY, KNOWN_LIMITATIONS, PRIVACY docs; sl/de translations; GitHub release with SHA-256; Playground blueprint; skill finalised from what Batch 2 taught.

## Verification

- `composer check` (PHPCS, PHPStan, PHPUnit): scheduling math incl. quiet hours and DST, coupon expiry and cooldown, copy fallback chains, snapshot ↔ restore round trip with child items, token hashing, status transitions.
- `npm run check` and Playground e2e on WooCommerce 11 with block checkout: add to cart → type e-mail → capture via Store API and REST → run `sfac_tick` with a fake clock → e-mails caught with `pre_wp_mail` → restore link rebuilds cart incl. children and applies coupon → order marks recovered → unsubscribe stops the sequence → janitor deletes the coupon.
- NIV (Batch 2): deploy in Off; WP-CLI smoke; Test mode with a test address through the live checkout without ordering; `wc-logs/fatal-errors-*` after every step; endpoints return no-cache through LiteSpeed; test e-mails in Gmail and Outlook; HTML size under 102 KB.

## Open decisions

1. Name and slug `Starfiniti Abandoned Cart` / `starfiniti-abandoned-cart`.
2. Public GPL repo like Starfiniti Cart, or private.
3. Legal basis per store: notice line + one-click opt-out (default) or an explicit consent checkbox. Both supported; the store owner decides.
