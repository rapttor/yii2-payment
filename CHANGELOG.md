# Changelog

## 0.1.0 (unreleased)
- Provider interface, `Payments` component, local catalog (products, prices, discounts), orders, subscriptions, wallet ledger, idempotent webhook processing.
- Providers: Stripe, Paddle Billing, Lemon Squeezy, PayPal (one-time), Sandbox (fake).
- Catalog sync to Stripe and Paddle (`payments/sync`), API/doc links per provider, `publicUrl` option.
- Docs: setup guides, provider matrix, analysis; `AGENTS.md` and `llms.txt` for AI agents.
- Backoffice module `rapttor\payment\backoffice\Module`.
