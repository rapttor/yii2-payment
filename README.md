# rapttor/yii2-payment

One Yii2 extension for all the ways an app gets paid. Your code talks to `Yii::$app->payments`; providers are plug-ins you
switch on and off in a backoffice screen.

| Provider | One-time | Subscriptions | Refund | Status |
|---|---|---|---|---|
| Sandbox (fake, no keys) | yes | yes | yes | ready |
| Stripe | yes | yes | yes + catalog sync | ready, test against your sandbox |
| Paddle Billing | yes | yes | full + catalog sync | ready, test against your sandbox |
| Lemon Squeezy | yes | yes (variant per price) | full | ready, test against your sandbox |
| PayPal | yes | not yet | yes | ready, test against your sandbox |
| Monri, Payten, Klarna, Swish, Payoneer | | | | planned, see `docs/PROVIDERS.md` |

## Documentation

- [Setup guides](docs/setup/README.md): install, tunnels for webhooks, go-live checklist; per provider: [Stripe](docs/setup/stripe.md), [Paddle](docs/setup/paddle.md), [Lemon Squeezy](docs/setup/lemonsqueezy.md), [PayPal](docs/setup/paypal.md)
- [Providers](docs/PROVIDERS.md): capability matrix, what can be controlled by API, official API links
- [Analysis](docs/ANALYSIS.md): decisions, possibilities, risks, suggested order
- For AI agents: [AGENTS.md](AGENTS.md) and [llms.txt](llms.txt)

## How it works

- **Local catalog is the source of truth**: `product` (what is sold and what it grants), `price` (per currency, one-time or
  `month`/`year`; a yearly discount is just a second price, see `Pricing::yearlyFromMonthly()`), `discount` (percent or fixed, dates, max uses).
- A buyer picks a provider; the package creates an `order` (uuid), the provider charges the **final** amount, and the uuid comes
  back in the webhook. Providers with their own catalog (Stripe, Paddle) can be mapped per price in `provider_refs`
  (`{"stripe":"price_...","paddle":"pri_..."}`); otherwise the amount is sent inline.
- Webhooks are verified, normalised to `WebhookEvent`, deduplicated by `(provider, event_id)` and applied in one transaction.
  Coins/top-ups are credited to the wallet ledger exactly once; a full refund claws them back.
- Prices, products and coupons you want to edit at the provider are one click away: each provider lists "open in Stripe / Paddle / PayPal" links.

## Install

```bash
composer require rapttor/yii2-payment
php yii migrate --interactive=0       # the extension registers its migrations
```

Common config (keys from env, never from the database):

```php
'components' => ['payments' => [
    'class' => rapttor\payment\Payments::class,
    'returnUrl' => ['/site/shop'],            // buyer lands here with ?order=<uuid>&status=paid|processing|cancelled
    'providers' => [
        'stripe' => ['class' => rapttor\payment\providers\StripeProvider::class,
            'secretKey' => getenv('STRIPE_SECRET_KEY'), 'webhookSecret' => getenv('STRIPE_WEBHOOK_SECRET')],
        'paddle' => ['class' => rapttor\payment\providers\PaddleProvider::class,
            'apiKey' => getenv('PADDLE_API_KEY'), 'webhookSecret' => getenv('PADDLE_WEBHOOK_SECRET')],
        'paypal' => ['class' => rapttor\payment\providers\PayPalProvider::class,
            'clientId' => getenv('PAYPAL_CLIENT_ID'), 'clientSecret' => getenv('PAYPAL_CLIENT_SECRET'), 'webhookId' => getenv('PAYPAL_WEBHOOK_ID')],
    ],
]],
```

Backend config (backoffice, protect it with an RBAC permission):

```php
'modules' => ['payments-admin' => ['class' => rapttor\payment\backoffice\Module::class, 'permission' => 'admin']],
```

Open `/payments-admin`, enable a provider (it needs its keys), set test/live. Register the webhook URL shown there at the provider:
`https://your.host/payments/webhook/<provider>` (publicly reachable; use a tunnel in development).

## Use

```php
// in a controller (POST): start a purchase
$session = Yii::$app->payments->checkout('coins-500-eur', 'stripe', Yii::$app->user->id, $couponOrNull);
return $this->redirect($session->redirectUrl);

// or post a form to /payments/checkout with fields price, provider, discount

// react when money arrives (grant features, send mail)
Yii::$app->payments->on(Payments::EVENT_ORDER_PAID, function (rapttor\payment\PaymentEvent $e) {
    // $e->order->product->code, $e->order->user_id ...
});

// wallet (coins, gems, EUR...), every change needs an idempotency key
$w = Yii::$app->payments->wallet();
$w->balance($userId, 'COIN');
$w->debit($userId, 'COIN', 50, 'buy-sword', 'sword:' . $purchaseId);   // throws InsufficientFundsException
```

Product `grants` JSON: `{"coins":500,"asset":"COIN"}` credits the wallet on payment; type `topup` credits the price amount in the price currency.
Anything else (`{"plan":"pro"}`) is yours to apply in an `EVENT_ORDER_PAID` handler.

Console: `php yii payments/status`, `payments/enable <id>`, `payments/disable <id>`, `payments/sync <id> [--force]` (push the catalog to Stripe or Paddle).

Set `publicUrl` on the component to the frontend's public URL so webhook/return URLs are right when the backoffice runs on another host or you use a tunnel.

## Security notes

- Credentials are config/env only. The backoffice never shows or stores them.
- Every webhook is signature-checked (Stripe and Paddle HMAC with timestamp tolerance, PayPal via its verify API) before anything is read.
- Amounts always come from our database, never from the buyer's request. Currency of a paid event must match the order.
- Sandbox provider is refused when `YII_ENV_PROD`.
- Not legal or tax advice: Paddle and Lemon Squeezy act as merchant of record, Stripe and PayPal do not (you handle VAT). Pair with `rapttor/yii2-legal` (`feature.payments`, refunds page).
