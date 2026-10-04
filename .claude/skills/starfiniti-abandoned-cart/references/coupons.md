# Coupons

## How they work

- Created only when e-mail 2 is sent; a shopper who orders before never receives one.
- WooCommerce coupon: percent or fixed cart, `individual_use`, `usage_limit` 1, `usage_limit_per_user` 1, e-mail restriction to the shopper's address, `date_expires` at an exact local time.
- Expiry: local date of sending + `coupon_valid_days`, at `coupon_expiry_hour` (default: 2 days, 20:00).
- E-mail 3 goes out at `last_day_hour` (default 10:00) on the expiry day.
- The janitor (every tick) deletes unused expired coupons, clears the saved cart and marks the row `expired`. Used coupons stay because they belong to an order.
- One coupon per address per `coupon_cooldown_days` (default 60) so nobody can farm discounts by abandoning carts. Without a coupon, e-mail 2 uses the `*_nocoupon` copy and e-mail 3 is skipped.
- Codes: `PREFIX-XXXXXX` without look-alike characters. Prefix from settings or the first letters of the store name.
- Restore links from e-mail 2 and 3 apply the code automatically.

## Decisions to get from the owner

Restate each value and wait for an explicit answer. "Ok" to a list of proposals is not an answer to a number.

- Type and amount (percent on products is the common choice; fixed amounts often read as "free shipping").
- Validity and expiry hour.
- Exclusions (gift cards, sale items, subscriptions) – add with `sfac_coupon_before_save`:

```php
add_action( 'sfac_coupon_before_save', static function ( \WC_Coupon $coupon ): void {
	$coupon->set_excluded_product_categories( array( 63 ) );
} );
```

## Checks

- Coupon visible under Marketing → Coupons with the description "Abandoned cart reminder #…".
- Applying it with another address fails; with the shopper's address it works once.
- After expiry it disappears from the coupon list within 5 minutes (one tick).
