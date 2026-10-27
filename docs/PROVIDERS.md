# Providers: capabilities, API links, what you can control by API

Legend: **MoR** = merchant of record (the provider sells to the buyer, handles VAT/sales tax and invoices; you get a payout). Without MoR you are the seller and handle tax yourself.
Links were checked to respond on 2026-10-25; provider menus and fields change, trust the provider docs over this page.

## Implemented

| | Stripe | Paddle Billing | Lemon Squeezy | PayPal | Sandbox |
|---|---|---|---|---|---|
| id | `stripe` | `paddle` | `lemonsqueezy` | `paypal` | `sandbox` |
| MoR | no (Stripe Tax optional) | **yes** | **yes** | no | n/a |
| One-time | yes | yes | yes | yes | yes |
| Subscriptions | yes | yes | yes (variant per price) | not yet | yes |
| Refund from backoffice | full + partial | full | full | full + partial | yes |
| Catalog sync (products/prices by API) | **yes** (`payments/sync stripe`) | **yes** | not yet | n/a (no catalog) | n/a |
| Test environment | test-mode keys, same host | separate host `sandbox-api.paddle.com` | test-mode flag on key and checkout | separate host `api-m.sandbox.paypal.com` | built in |
| Webhook security | HMAC `Stripe-Signature` + timestamp | HMAC `Paddle-Signature` + timestamp | HMAC `X-Signature` | verify API call with webhook id | none (internal) |

### What can be controlled by API (so the backoffice or a script can do it)

| Thing | Stripe | Paddle | Lemon Squeezy | PayPal |
|---|---|---|---|---|
| Products and prices | create/archive via API (used by `CatalogSync`) | create/archive via API (used by `CatalogSync`) | read; create checkouts per variant (products are made in the dashboard) | no catalog; amount sent per order |
| Discounts / coupons | Coupons + Promotion codes API | Discounts API | Discounts API | none (we apply our own discount before charging) |
| Yearly vs monthly | two recurring prices | two prices with billing cycles | one variant per interval | n/a |
| Trials | `trial_period_days` | `trial_period` on price | variant setting | n/a |
| Customer portal | Billing portal sessions | customer portal sessions | customer portal URL on subscription | PayPal account |

Our own discounts (`payment_discount`) are applied **by us** before the charge, so they work on every provider the same way. When a coupon is applied the inline amount is sent, not the provider's catalog price (so provider-side tax rules still apply, but provider-side coupon reporting does not). Provider-native coupons are a roadmap item.

### Links

**Stripe**: [API reference](https://docs.stripe.com/api) · [Checkout](https://docs.stripe.com/payments/checkout) · [Webhooks](https://docs.stripe.com/webhooks) · [Products](https://docs.stripe.com/api/products) · [Prices](https://docs.stripe.com/api/prices) · [Coupons](https://docs.stripe.com/api/coupons) · [Subscriptions](https://docs.stripe.com/billing/subscriptions/build-subscriptions) · [Test cards](https://docs.stripe.com/testing) · [Customer portal](https://docs.stripe.com/customer-management)

**Paddle Billing**: [API reference](https://developer.paddle.com/api-reference/overview) · [Webhooks](https://developer.paddle.com/webhooks/overview) · [Products](https://developer.paddle.com/api-reference/products/overview) · [Prices](https://developer.paddle.com/api-reference/prices/overview) · [Discounts](https://developer.paddle.com/api-reference/discounts/overview) · [Create transaction](https://developer.paddle.com/api-reference/transactions/create-transaction) · [Default payment link](https://developer.paddle.com/build/transactions/default-payment-link) · [Adjustments (refunds)](https://developer.paddle.com/api-reference/adjustments/overview)

**Lemon Squeezy**: [API](https://docs.lemonsqueezy.com/api) · [Checkouts](https://docs.lemonsqueezy.com/api/checkouts) · [Products](https://docs.lemonsqueezy.com/api/products) · [Discounts](https://docs.lemonsqueezy.com/api/discounts) · [Webhooks](https://docs.lemonsqueezy.com/help/webhooks) · [Signing requests](https://docs.lemonsqueezy.com/help/webhooks/signing-requests)

**PayPal**: [Orders v2](https://developer.paypal.com/docs/api/orders/v2/) · [Payments v2 (captures, refunds)](https://developer.paypal.com/docs/api/payments/v2/) · [Webhooks](https://developer.paypal.com/api/rest/webhooks/) · [Subscriptions v1](https://developer.paypal.com/docs/api/subscriptions/v1/) · [Sandbox](https://developer.paypal.com/tools/sandbox/)

## Planned

| Provider | Typical use | Notes | Docs |
|---|---|---|---|
| Monri | Cards in Bosnia, Croatia, Serbia, Montenegro (local acquirer) | Redirect form with digest signature or REST payments; a PHP client exists next to this repo in `../../Monri-PHP`. Not a MoR. Local currencies (BAM, RSD, EUR). | [docs.monri.com](https://docs.monri.com/) |
| Payten | Cards via Payten / Asseco SEE in the Balkans | Acquirer gateway; API docs are partner-only, so get access from your Payten account manager first. The public docs hosts did not respond when checked. | ask Payten |
| Klarna | Buy now pay later, Nordics/EU/US | Create session, authorize, capture; webhooks (push) confirm. Order lines are mandatory. Not a MoR. | [docs.klarna.com](https://docs.klarna.com/) |
| Swish | Instant payments in Sweden | Merchant certificate (mTLS), payment request, callback; SEK only; mobile app deep link. | [developer.swish.nu](https://developer.swish.nu/) |
| Payoneer | Receiving B2B/marketplace payments, payouts | Checkout and payout APIs are program-specific; needs onboarding with Payoneer. | [developer.payoneer.com](https://developer.payoneer.com/) |

Adding one is a single class; see `AGENTS.md` ("New provider").
