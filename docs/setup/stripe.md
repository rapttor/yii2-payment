# Stripe setup

Docs: [API](https://docs.stripe.com/api) · [Checkout](https://docs.stripe.com/payments/checkout) · [Webhooks](https://docs.stripe.com/webhooks) · [Test cards](https://docs.stripe.com/testing)

1. Create a Stripe account and stay in **Test mode** (toggle in the dashboard).
2. **Developers → API keys**: copy the *Secret key* (`sk_test_...`) to `STRIPE_SECRET_KEY`. The publishable key is not needed for hosted Checkout (kept as `STRIPE_PUBLISHABLE_KEY` for your own use).
3. **Developers → Webhooks → Add endpoint**: URL `https://<your host>/payments/webhook/stripe`. Events to send:
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `invoice.paid`, `customer.subscription.deleted`, `charge.refunded`.
   Copy the *Signing secret* (`whsec_...`) to `STRIPE_WEBHOOK_SECRET`. (CLI alternative: `stripe listen --forward-to .../payments/webhook/stripe`.)
4. Recreate the containers so env is reloaded (`docker compose up -d`), open `/payments-admin`, switch **Stripe** on.
5. Optional catalog sync: `php yii payments/sync stripe` creates products and recurring/one-time prices and stores their ids on our prices. Prices are immutable at Stripe, so a changed amount creates a new price and archives the old one.
6. Test: pay with `4242 4242 4242 4242`, any future date, any CVC. 3-D Secure test: `4000 0027 6000 3184`. Declined: `4000 0000 0000 0002`.
7. Refund from **Backoffice → Orders → Refund**; the `charge.refunded` webhook then marks the order refunded and takes coins back.

What the package does at Stripe: creates a Checkout Session (`mode=payment` or `subscription`), puts our order uuid in `metadata[order]` and `client_reference_id`, and an `Idempotency-Key` per order.

Tax: Stripe is not a merchant of record. Enable [Stripe Tax](https://docs.stripe.com/tax) or handle VAT yourself.
Customer portal (cancel, change card): configure in Stripe → Settings → Billing → Customer portal; per-user portal sessions are on the roadmap.
