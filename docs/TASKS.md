# Tasks

| Status | Task | Model |
|---|---|---|
| done | Core: provider interface, DTOs, `Payments` component, idempotent event `Processor`, wallet ledger, pricing/discount math, migration | Opus |
| done | Providers: Stripe (checkout, webhook, refund), Paddle Billing (transaction, webhook, refund), PayPal Orders v2 (capture on return, webhook, refund), Sandbox (fake) | Sonnet |
| done | Backoffice module: providers on/off + mode + country/currency limits + "open in provider" links, products, prices, discounts, orders + refund, webhook log | Sonnet |
| done | Demo app wiring (`rapttor.4all/demo`): env keys, path repo, `/site/shop`, `demo/seed-shop`, PHP 8.4 image | Sonnet |
| done | Tests: pricing, Stripe and Paddle signatures and event mapping (11) | Sonnet |
| todo (owner) | Fill sandbox keys in `demo/.env`, expose the demo with a tunnel, register webhook URLs, run a real purchase per provider | owner |
| todo | Verify Paddle (non-catalog price, default payment link) and PayPal (webhook verify) against the real sandboxes; fix payload differences | Opus |
| todo | Provider-native coupons: map our discounts to Stripe coupons / Paddle discounts | Sonnet |
| done | Lemon Squeezy provider, catalog sync (Stripe, Paddle) with console + backoffice button, API links in backoffice, `publicUrl`, docs (PROVIDERS, ANALYSIS, setup guides), llms.txt + AGENTS.md | Opus |
| todo | Providers: Monri (see `../Monri-PHP`), Payten, Klarna, Swish, Payoneer | Sonnet |
| todo | PayPal subscriptions (Billing plans) | Sonnet |
| todo | Customer portal / cancel subscription links per provider; `payments/cancel` | Sonnet |
| todo | Wallet screens in backoffice (balances, ledger, manual adjust with reason) | Sonnet |
| todo | Game coins: spend API (`wallet()->debit`) docs, bundles with bonus %, per-asset exchange | Sonnet |
| todo | Tax/VAT handling note and invoice numbers (Paddle/LS are merchant of record; Stripe/PayPal are not) | Opus |
| todo | Encrypted credentials in DB as an option (today: env only) | Opus |
| todo | Integration tests with SQLite for Processor and WalletService | Sonnet |
| later | Packagist release `v0.1.0`, entry in `../rapttor.md` package table | Haiku |
