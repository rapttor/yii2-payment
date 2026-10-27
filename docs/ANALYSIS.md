# Analysis: what this package can become

## Decisions already made (and why)
1. **Local catalog is the source of truth.** Providers differ (Stripe/Paddle have catalogs, PayPal/card gateways do not). One local `product/price/discount` model makes backoffice, pricing pages and limit messages agree (see `../../rapttor.md` §7 "single source of truth") and lets the same product be sold through any provider.
2. **Credentials live in env/config, never in the DB.** A database leak or an XSS in the backoffice cannot expose live keys. The backoffice only toggles, orders and restricts providers.
3. **Webhooks are the truth, returns are a hint.** Money state changes only from verified, deduplicated provider events (PayPal capture on return is the exception, deduplicated against its webhook).
4. **Integer minor units everywhere; amounts come from our database.**
5. **Wallets are a ledger** with idempotency keys, so coins can be sold, spent, refunded and audited.

## Possibilities, by value

### Selling
- One-time products, coin bundles (with bonus %), wallet top-up, lifetime unlock.
- Subscriptions: monthly + yearly (`Pricing::yearlyFromMonthly`), trials, plan upgrade/downgrade (roadmap: proration through provider APIs).
- Coupons: percent/fixed, per product, date window, max uses (done); per-user, first-purchase-only, referral codes, automatic seasonal sales (roadmap).
- Regional pricing: one price row per currency already works; add country → price lookup (roadmap) and per-country provider choice (done via provider country/currency limits).
- Provider routing: show Monri/Payten for RS/BA/HR cards, Swish for SE, Klarna for Nordics, Paddle/Lemon Squeezy when you want a MoR to handle VAT, PayPal as a fallback. Fallback on provider error (roadmap).

### Money inside the app
- **Profile wallets** (EUR balance) and **game coins/gems** (any asset code) with `credit/debit/history`. Spending is atomic and idempotent.
- Pay for a purchase from wallet balance (roadmap: provider id `wallet`, instant, no fees).
- Payouts / creator earnings (roadmap, needs Payoneer/PayPal Payouts/Stripe Connect and KYC; legally heavy).

### Operating it
- Backoffice: providers on/off, mode, ordering, country/currency limits, orders, refunds, webhook log (done). Next: wallet screens, subscription list with cancel, revenue dashboard (MRR, refunds, by provider), failed-webhook replay.
- "Open in provider" links and API links per provider (done) so prices/coupons can also be edited where they are native; catalog sync pushes our prices there (Stripe, Paddle done).
- Events (`EVENT_ORDER_PAID`, `EVENT_ORDER_REFUNDED`, `EVENT_SUBSCRIPTION_RENEWED`) are the integration surface for the host app (grant plan, send receipt via MailerSend, unlock theme).
- Console + cron: reconcile pending orders against provider APIs, expire old pending orders, retry failed fulfilment (roadmap, goes in the `scheduler` container).

### Optional capabilities worth adding
| Idea | Value | Cost |
|---|---|---|
| Provider-native coupons mapped to our discounts | coupon stats at the provider | M |
| Customer portal links (cancel, update card) | fewer support requests, required by many laws | S per provider |
| Invoices/receipts PDF + numbering | needed where you are the seller (Stripe/PayPal/Monri) | M |
| Dunning emails on failed renewals | recover revenue | S (events exist) |
| Tax: VAT rates, OSS, reverse charge | legal; MoR providers do this for you | L |
| Fraud/risk rules (velocity, country mismatch) | fewer chargebacks | M |
| Multi-currency display and rounding rules | conversion | S |
| Mobile in-app purchase bridge (Apple/Google) | app stores require their own billing for digital goods in apps | L, separate package |

## Risks and obligations (not legal advice)
- **Merchant of record:** with Stripe, PayPal, Monri, Payten, Klarna, Swish *you* are the seller: VAT/sales tax, invoices, refund rights, chargebacks. Paddle and Lemon Squeezy take that role but have category restrictions and fees; check they allow your product (games with virtual currency, adult, gambling are often restricted).
- **PCI:** use hosted pages only (all current providers do). Never collect card numbers in your own form; that keeps you at the lightest PCI level.
- **Consumer law:** withdrawal right for digital content needs express consent at checkout; subscriptions need clear renewal notice and easy cancel; loot-box odds disclosure. Use `rapttor/yii2-legal` with `feature.payments`, `subscriptions`, `credits`, `game` and its "Promises" list.
- **Virtual currency:** coins that can be cashed out may count as e-money; keep coins spend-only unless you have legal advice.
- **Webhook reliability:** providers retry; our idempotency handles duplicates. Out-of-order events (refund before paid) are logged as `unknown order`; reconcile job on the roadmap covers it.
- **Secrets in demo:** `.env` is git-ignored; only `.env.example` is committed.

## Suggested order
1. Run real sandbox purchases for Stripe, Paddle, PayPal, Lemon Squeezy; fix payload differences.
2. Subscriptions screen + customer portal links + cancel.
3. Wallet screens and "pay from wallet".
4. Monri (local market, `../../Monri-PHP` as base), then Payten.
5. Reconcile cron, revenue dashboard, dunning.
6. Klarna / Swish / Payoneer when a project needs them.
