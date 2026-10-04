# Go-live

## Before switching to On

- Owner approved: wording per language, signature, coupon value and validity, quoted reviews, rating line.
- Test mode passed every check in `preview-and-testing.md`.
- Legal basis chosen by the owner: notice under the e-mail field with one-click unsubscribe (default) or a consent checkbox. When unsure, the owner asks their lawyer.
- Privacy policy (every language) gets a paragraph. WordPress → Settings → Privacy → Policy guide shows the plugin's suggested text; adapt it, e.g. in Slovenian:

  > Če v blagajni vpišete e-naslov in naročila ne zaključite, shranimo vašo košarico, e-naslov, ime in, če ste jih vpisali, ime prejemnika, datum dostave in besedilo voščila. Pošljemo vam lahko do tri opomnike s povezavo, ki obnovi košarico; eden od njih lahko vsebuje osebno kodo za enkratni popust. V vsakem opomniku se lahko odjavite z enim klikom. Podatke izbrišemo po 30 dneh; odjavo hranimo le kot enosmerni zgoščeni zapis vašega e-naslova.

- Cookie banner: the plugin sets no cookies of its own (it uses the WooCommerce session), so no new cookie category is needed.
- Page cache: restore and unsubscribe URLs send no-cache headers; check that the cache does not serve `?sfac=` URLs (LiteSpeed: they are not cached because the query string is part of the key and the response is marked no-cache).
- SMTP: SPF/DKIM pass for the sender address; send one test to mail-tester.com.

## Switch on

Settings → Mode: On. Confirm the notice line under the e-mail field appears on the live checkout in every language.

## Monitor

- Day 1: Overview shows captured carts; `wc-logs` has no fatal errors; first e-mails sent at the right time.
- Day 7 and 30: captured carts, e-mails per step, clicks, recovered orders and revenue, unsubscribe rate (worry above ~2 % per e-mail), subject A vs B.
- Adjust one thing at a time.
