# Lemon Squeezy setup

Docs: [API](https://docs.lemonsqueezy.com/api) · [Checkouts](https://docs.lemonsqueezy.com/api/checkouts) · [Webhooks](https://docs.lemonsqueezy.com/help/webhooks) · [Signing requests](https://docs.lemonsqueezy.com/help/webhooks/signing-requests)

Lemon Squeezy is a merchant of record (VAT handled for you). Test mode uses the same host; the `test_mode` flag is set from `PAYMENTS_MODE`.

1. Create a store; switch the dashboard to **Test mode** (bottom left).
2. **Settings → API**: create a key (test mode key while testing) → `LEMONSQUEEZY_API_KEY`.
3. Store id: **Settings → Stores** → `LEMONSQUEEZY_STORE_ID`.
4. **Products → New product** "Custom payment" (single payment, any placeholder price). Open its variant and copy the **variant id** to `LEMONSQUEEZY_VARIANT_ID`. One-time purchases of any amount use this variant with a custom price.
5. **Settings → Webhooks → +**: URL `https://<your host>/payments/webhook/lemonsqueezy`, choose a *signing secret* (any long random string) → `LEMONSQUEEZY_WEBHOOK_SECRET`. Events: `order_created`, `order_refunded`, `subscription_created`, `subscription_payment_success`, `subscription_cancelled`, `subscription_expired`.
6. Switch **Lemon Squeezy** on in `/payments-admin`.
7. Subscriptions: create a subscription product with a variant per interval in the dashboard and put the variant id in the price's *provider refs* (backoffice → Prices): `{"lemonsqueezy":"123456"}`. Without it a subscription price cannot be sold through Lemon Squeezy.
8. Test card: `4242 4242 4242 4242`, any future date, any CVC.

Limits: refunds are full only for now; catalog sync is not implemented (their products are created in the dashboard).
