# Paddle Billing setup

Docs: [API](https://developer.paddle.com/api-reference/overview) · [Webhooks](https://developer.paddle.com/webhooks/overview) · [Default payment link](https://developer.paddle.com/build/transactions/default-payment-link) · [Create transaction](https://developer.paddle.com/api-reference/transactions/create-transaction)

Paddle is a merchant of record: it collects and remits VAT and invoices the buyer. It must approve your business and product type for live use; the sandbox is separate and instant.

1. Create a **sandbox** account at sandbox-vendors.paddle.com (separate from live).
2. **Developer tools → Authentication**: create an API key, copy to `PADDLE_API_KEY`. (Client-side token is only for Paddle.js overlays; stored as `PADDLE_CLIENT_TOKEN` for future use.)
3. **Checkout → Website approval**: add your domain and wait until it is approved. Then **Checkout → Checkout settings → Default payment link**: a page on that domain that loads Paddle.js (the demo uses `https://<your host>/site/pay`). Without it Paddle answers `transaction_default_checkout_url_not_set`. Alternatively set `checkoutUrl` on the provider: it is sent as `checkout.url` per transaction (domain still has to be approved). The page must call `Paddle.Initialize({token})` with `PADDLE_CLIENT_TOKEN`.
4. **Developer tools → Notifications → New destination**: type *webhook*, URL `https://<your host>/payments/webhook/paddle`, events `transaction.completed`, `transaction.payment_failed`, `subscription.canceled`, `adjustment.updated`. Copy the destination's *secret key* to `PADDLE_WEBHOOK_SECRET`.
5. Set `PAYMENTS_MODE=test` (sandbox host `sandbox-api.paddle.com`), recreate containers, switch **Paddle** on in `/payments-admin`.
6. Optional: `php yii payments/sync paddle` creates products and prices (tax category `standard`; change `taxCategory` in the provider config).
7. Test card: `4242 4242 4242 4242`, any future expiry, CVC `100`. More in Paddle's sandbox docs.
8. Refunds are *adjustments*; the package does full refunds. Partial refunds are not implemented yet.

Without catalog sync the package sends a *non-catalog price* with an inline product for each order, so you can try it before creating anything in Paddle.

Switching to live: create the live account, repeat steps 2–4 there with live values, `PAYMENTS_MODE=live`.


## One account, many apps

Paddle has no stores: one catalog, and every notification destination receives every event. Set `app` on the `Payments` component (demo: `PAYMENTS_APP` in `.env`). The package then adds `custom_data.app` to products, prices and transactions, prefixes provider-side codes (`cetirizida-pro`), puts `[app]` in price descriptions, and ignores webhooks tagged for another app. Create one notification destination per app (own URL and secret) and approve each app's domain. Run `payments/sync paddle --force` after changing the slug; products that already exist at Paddle keep their old tags.
