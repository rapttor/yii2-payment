# Instructions for AI coding agents: rapttor/yii2-payment

Yii2 package: many payment providers behind `Yii::$app->payments`, local catalog, orders, wallets, webhooks, backoffice module. Read `README.md`, then `docs/PROVIDERS.md` (capabilities, API links) and the guide for the provider you touch (`docs/setup/*.md`). Machine-readable index: `llms.txt`.

You are not a lawyer or tax adviser. Never claim the result makes a shop legally compliant (VAT, consumer law, e-money).

## Integrate into a Yii2 advanced app (follow in order)

1. `composer require rapttor/yii2-payment`, then `php yii migrate --interactive=0` (the extension registers its own migrations). In development against local source use a composer `path` repository and mount the folder into the container (see `docs/setup/README.md`).
2. Register the component in `common/config/main.php` with **only the providers the owner wants**; keys from `getenv()`/env helper. Add every key name to `.env.example` (empty) and tell the owner to fill `.env`. **Never write real keys yourself, never print them, never commit `.env`.** Ask the owner which providers, which currencies and whether prices are tax-inclusive.
3. Mount the backoffice module in the **backend** app only, with `permission` set to an RBAC permission or role that exists (`admin` in the demo). Without `permission` any logged-in backend user gets in.
4. Set `publicUrl` (env `PUBLIC_URL`) to the public HTTPS URL of the **frontend** when testing webhooks through a tunnel; in production it can stay null if the frontend host serves `/payments/*`.
5. Create catalog data (backoffice, or a seed command like `demo/seed-shop`): products (`type`: one_time|subscription|coins|topup; `grants` JSON), prices (minor units, currency, optional `interval` month|year), discounts. Yearly price: `Pricing::yearlyFromMonthly($monthly, $percentOff)`.
6. Offer purchase: POST to `/payments/checkout` with `price` (price code), `provider`, optional `discount`; or call `Yii::$app->payments->checkout($priceCode, $providerId, $userId, $discount, $email)` and redirect to `redirectUrl`. List choices with `Yii::$app->payments->available($currency, $country, Capability::ONE_TIME|SUBSCRIPTION)` (only enabled and configured providers). Never render a provider the user cannot use.
7. Fulfil in an event handler, not in the return action: `Yii::$app->payments->on(Payments::EVENT_ORDER_PAID, ...)`. The return URL only shows status (`?order=<uuid>&status=paid|processing|cancelled`); `processing` means the webhook has not arrived yet, show "confirming" and let the page be reloaded.
8. Wallet spend: `Yii::$app->payments->wallet()->debit($userId, 'COIN', $amount, $reason, $idempotencyKey)`; catch `InsufficientFundsException`. The idempotency key must be stable per business action (e.g. `sword:<purchaseId>`), never random per request.
9. Link Terms, Privacy and Refunds at checkout (`rapttor/yii2-legal` if installed; set its `feature.payments`, `subscriptions`, `credits` flags to match).
10. Verify: `php yii payments/status`, then a purchase through the Sandbox provider, then the real sandbox of each enabled provider; confirm the Webhook log shows `paid` and the wallet/feature was granted exactly once (replay the same webhook: result must be `duplicate`).

## Rules you must follow

- **Credentials**: env/config only. Not in the database, backoffice, logs, URLs, JS, or repo files. `requiredSettings()` lists what a provider needs.
- **Amounts**: integer minor units, always from `payment_price` / the order, never from request parameters. Currency of a paid event must equal the order's (the Processor enforces this).
- **Webhooks**: always verify the signature before parsing; throw `InvalidSignatureException` (the controller answers 400). Never skip verification "for testing"; use the Sandbox provider or the provider's test tools.
- **Idempotency**: event ids must be stable per real-world fact; if two channels report the same fact (return + webhook) give them the same event id (see PayPal `capture:<id>`). Wallet changes need idempotency keys.
- **No card data** ever touches our server: hosted checkout pages only.
- **Sandbox provider** is for development; it is refused when `YII_ENV_PROD`. Do not enable it in production configs.
- Do not store provider-specific columns on our tables; use `provider_refs` (JSON) for ids and `WebhookEvent->raw` (logged in `payment_event`) for payloads.
- Do not change `src/migrations/m261025_000001_*` once released; add a new migration.
- Keep the package dependent on Yii2 only (curl via `BaseProvider::http`), no provider SDKs.

## New provider (one class, no core change)

1. `src/providers/<Name>Provider.php`, `final`, extends `BaseProvider`. Implement `id()` (lowercase, matches URL), `label()`, `capabilities()` (`Capability::*`; only what really works), `isConfigured()`, `requiredSettings()` (env names), `dashboardLinks()` (where to edit prices/coupons), `apiLinks()` (official docs, verify they respond), `createCheckout(CheckoutRequest)` → `CheckoutSession` (hosted page URL; send `orderUuid` as provider metadata/custom field), `parseWebhook($rawBody, $lowerCasedHeaders)` → `WebhookEvent[]` (verify first), `refund()`.
2. If the buyer must be captured on return (PayPal, card gateways with redirect): implement `ReturnHandlerInterface::handleReturn()`. If the provider has its own catalog: implement `CatalogSyncInterface`.
3. Map to `WebhookEvent::PAID|FAILED|REFUNDED|SUBSCRIPTION_STARTED|SUBSCRIPTION_RENEWED|SUBSCRIPTION_CANCELED`. First payment of a subscription is `PAID` (+ `SUBSCRIPTION_STARTED` with a different event id); later cycles are `SUBSCRIPTION_RENEWED`.
4. Tests in `tests/`: valid signature → expected events; wrong/missing/old signature → exception; tampered body → exception. Run `vendor/bin/phpunit`.
5. Document in `docs/PROVIDERS.md` (matrix + links) and `docs/setup/<id>.md`; add env names to the demo `.env.example` and register the provider in the demo config; update `docs/TASKS.md`.
6. Provider facts you cannot verify (no docs access, partner-only API): say so in the docs and in the class comment; do not guess endpoints.

## Debugging

- Order stuck `pending`: Webhook log empty → provider cannot reach the URL (tunnel, `publicUrl`, firewall) or signing secret wrong (HTTP 400 in provider dashboard). Log has `unknown order` → uuid missing in provider metadata or order created by another environment. `currency mismatch` → provider converted currency; compare `WebhookEvent->currency`.
- Paddle "no checkout.url": set the Default payment link on an approved domain.
- Lemon Squeezy subscription fails: price needs `{"lemonsqueezy":"<variant id>"}` in `provider_refs`.
- Coins credited twice: look for a wallet call without an idempotency key in host-app code.

## Working method (Rapttor)

Plan as a task list with model tags in `docs/TASKS.md`: **Haiku** mechanical (docs, env names, translations), **Sonnet** ordinary features (new provider, backoffice screen), **Opus** money/security/data-model/legal decisions. Money handling, signature checks and migrations always get Opus-level review.
