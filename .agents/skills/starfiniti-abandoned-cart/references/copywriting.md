# Reminder copy

## Psychology that works and stays honest

- **Sell the moment, not the product.** For gifts the buyer is the giver and the hero is the recipient: "Ana še ne ve, kaj prihaja." The giver wants to look thoughtful.
- **Answer the fears in e-mail 2:** Will they like it? Will it look like the photo? Will it arrive on time? What if I change my mind? Answer each with a real store promise.
- **Use the shopper's own words:** recipient name, card message. The surprise feels "already made", leaving feels like a loss.
- **Real urgency only:** the chosen delivery date and the store's real cut-off times; the coupon expiry is real because the plugin deletes the coupon.
- **Social proof:** a rating only with its source ("4,9/5 na Googlu"); quotes only from real reviews the owner chose.
- **Loss aversion at the end:** e-mail 3 says the code and the saved cart are deleted today. That is true.
- **P.S. lines are read.** Use them for one useful offer (photo before dispatch, personal advice).
- **Calm segment:** sympathy and funeral purchases get short, respectful e-mails without "surprise", discounts or P.S.

## Fallbacks

Every value can be a list; the first alternative whose placeholders all have values is used:

```json
"subject_a": [ "{recipient} še ne ve, kakšno presenečenje prihaja", "Vaše presenečenje je shranjeno" ]
```

Check each line for: no first name, no recipient, buyer = recipient, no card message, no date, date already passed, no coupon (cooldown), product without variants or image.

## Slovenian grammar

- Names only in the nominative: "Ana še ne ve …" works, "za {recipient}" does not (Ana → za Ano).
- Avoid gendered verbs about the recipient ("bo prejel/prejela"); write "kaj podarjate".
- Product nouns differ in gender: šopek (m), aranžma (m), darilo (n). Pass forms through `sfac_copy_vars` (`noun`, `noun_gen`, …) when a sentence needs them.
- Weekday in deadlines uses the genitive: "do četrtka" (the formatter does this in `{expiry_until}`).
- Formal "vi" everywhere; "Pozdravljeni" as greeting.

## Placeholders

`{first_name}`, `{recipient}`, `{store}`, `{discount}` (10 % / 10%), `{code}`, `{savings}`, `{expiry_until}` ("do četrtka, 15. 10., ob 20.00"), `{expiry_time}` ("20.00"), `{delivery_date}` ("petek, 16. 10."), `{order_number}`, every key from `sfac_capture_context`, and whatever `sfac_copy_vars` adds.

## Copy keys

Root: `greeting`, `greeting_calm`, `closing`, `closing_calm`, `notice`, `consent_label`, `restored`, `expired_link`, `labels.*`, `footer.*`, `unsubscribe_page.*`.

Per segment and step, e.g. `default.e2`: `subject_a`, `subject_b`, `subject_nocoupon`, `preheader`, `preheader_nocoupon`, `eyebrow`, `eyebrow_nocoupon`, `title`, `body`, `body_nocoupon`, `coupon_line`, `coupon_terms`, `cta`, `cta_nocoupon`, `cta_secondary`, `objections_title`, `objections` (list of `[question, answer]`), `help`, `ps`, `reframe`. `**bold**` is allowed in body, coupon line, objections and P.S.

`payment_failed`: `subject`, `preheader`, `eyebrow`, `title`, `body`, `cta`, `help`.

## A/B subjects

Rows get variant A or B at random. Write two genuinely different subjects (personal vs. offer-led) and compare clicks and recovered orders per variant in the overview after 2–3 weeks.
