# Reminder e-mail HTML

The renderer (`src/Email/Renderer.php`) already follows these rules. Use them when you override blocks (`sfac_email_block`, `sfac_email_rows`, `sfac_email_html`) or design a brand pack.

## Structure

- One 600 px table centred in a full-width table; `class="sfac-container"` switches to 100 % below 620 px.
- Layout only with tables (`role="presentation"`, `cellpadding="0" cellspacing="0" border="0"`). No flexbox, grid or positioning.
- Inline styles on every element. The `<style>` block only holds web fonts and the mobile media query.
- Side padding 40 px, 22 px on phones (`class="sfac-px"`).
- Hidden preheader first in `<body>`, padded with `&#847;&zwnj;&nbsp;` so the inbox preview shows only the preheader.
- Wrap the container in `<!--[if mso]>` tables for Outlook.

## Content rules

- Buttons are table cells with a background colour and a full-width `<a style="display:block">` (bulletproof). Square corners unless the brand uses rounded buttons (`button_radius`).
- Images: absolute `https://` URLs, explicit `width`/`height`, `alt`, `display:block`. Product thumbnails 72 × 72. Never data URIs.
- Fonts: a web font only with a safe fallback stack (`'Gilda Display', Georgia, serif`). Body text in a system stack. Slovenian needs latin-ext glyphs (č, š, ž).
- `<meta name="color-scheme" content="light">`; colours must also read well if a client darkens the background.
- Text 15 px / 24 px, headings 27–31 px, labels 11 px uppercase with letter spacing.
- No JavaScript, forms, video or external CSS.
- Total HTML under 102 KB, or Gmail clips the message (the default templates are 5–9 KB).
- Always send the plain-text part (the mailer does this).

## Brand tokens

`brand.json` keys: `bg`, `surface`, `text`, `heading`, `muted`, `line`, `soft`, `accent`, `accent_text`, `highlight`, `highlight_soft`, `star`, `footer_bg`, `footer_text` (hex colours), `font_heading`, `font_body` (font stacks), `font_css` (optional `@font-face` rules), `button_radius`, `logo_url`, `logo_width`, `store_name`, `store_url`, `topbar`, `phone`, `whatsapp`, `email`, `hours`, `help_text`, `trust` (list of `[title, text]`), `links` (list of `[label, url]`), and `languages` with per-language overrides, e.g. `{ "languages": { "en": { "topbar": "…" } } }`. Invalid colours or font stacks fall back to the defaults.

## Blocks

Blocks per step come from the sequence definition (`sfac_sequences`): `hero`, `coupon`, `cta`, `cta_secondary`, `cart`, `card_message`, `totals`, `info_box`, `objections`, `reviews`, `help`, `reframe`, `signature`, `ps`. A block with no content renders nothing. Add a custom block by adding its name to a step and returning a table row from `sfac_email_block`.

## Check before shipping

Preview every scenario and language in the admin, send test e-mails to Gmail (web + app) and Outlook, check on a phone, check links (restore with coupon, unsubscribe page), and check the size of the HTML.
