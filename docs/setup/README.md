# Setup guides

Order: [1. install in an app](#1-install-in-a-yii2-advanced-app) → [2. make webhooks reachable](#2-make-webhooks-reachable-in-development) → provider guide → [3. go live](#3-going-live-checklist).

Provider guides: [Stripe](stripe.md) · [Paddle](paddle.md) · [Lemon Squeezy](lemonsqueezy.md) · [PayPal](paypal.md). Provider reference and API links: [../PROVIDERS.md](../PROVIDERS.md).

## 1. Install in a Yii2 advanced app

```bash
composer require rapttor/yii2-payment
php yii migrate --interactive=0
```

`common/config/main.php` (keys from env, see `demo/project/common/config/main.php` for the full working example):

```php
'payments' => [
    'class' => rapttor\payment\Payments::class,
    'returnUrl' => ['/site/shop'],
    'providers' => [
        'stripe' => ['class' => rapttor\payment\providers\StripeProvider::class,
            'mode' => getenv('PAYMENTS_MODE') ?: 'test', 'secretKey' => getenv('STRIPE_SECRET_KEY'), 'webhookSecret' => getenv('STRIPE_WEBHOOK_SECRET')],
    ],
],
```

`backend/config/main.php`:

```php
'modules' => ['payments-admin' => ['class' => rapttor\payment\backoffice\Module::class, 'permission' => 'admin']],
```

Then:
1. Add the keys to `.env` (the demo ships all names in `.env.example`).
2. Open `/payments-admin`, a provider with keys shows "keys set"; switch it on, pick test/live.
3. Create products and prices (backoffice → Products, Prices) or run `php yii demo/seed-shop` in the demo.
4. For Stripe/Paddle press "Sync catalog" (or `php yii payments/sync stripe`) to create the products/prices there.
5. Buy something with the provider's test card (see its guide) and check Orders and Webhook log.

The Sandbox provider needs no keys: `php yii payments/enable sandbox`. It is refused when `YII_ENV=prod`.

Docker note: the app container must be able to read the package. In development the demo mounts `../yii2-payment` at `/var/yii2-payment` and uses a composer `path` repository; remove both when you switch to the Packagist release.

## 2. Make webhooks reachable in development

Providers call your server, so `localhost` does not work. Use a tunnel to the frontend port (demo: `28081`):

```bash
cloudflared tunnel --url http://localhost:28081      # or: ngrok http 28081
```

The tunnel prints `https://something.trycloudflare.com`. Put it in `PUBLIC_URL` in `.env` (component option `publicUrl`) and recreate the containers. The backoffice then shows the exact webhook URL
`https://something.trycloudflare.com/payments/webhook/<provider id>` and providers return the buyer to the tunnel host. Without `publicUrl` the current request host is used, which is wrong for a backoffice on another port.
Stripe also has the `stripe listen --forward-to localhost:28081/payments/webhook/stripe` CLI, which gives you a `whsec_...` for local use without a public tunnel.

Checking that it works: **Backoffice → Webhook log**. A line with result `paid` means fulfilled; `duplicate` is a retry; `unknown order` means the event was not from an order we created (e.g. a test event from the dashboard).
Wrong signature gives HTTP 400 and a warning in the `payments` log category.

## 3. Going live checklist

- [ ] `PAYMENTS_MODE=live` and **live** keys in the production environment only; never reuse test keys or webhook secrets.
- [ ] New webhook endpoint registered at each provider with the production URL (HTTPS) and the live signing secret.
- [ ] Sandbox provider disabled (it is refused in production anyway).
- [ ] Backoffice behind RBAC (`permission`) and ideally IP allowlist / basic auth in the vhost (see `rapttor.md` §2).
- [ ] Legal pages: Terms, Privacy, Refunds linked at checkout (`rapttor/yii2-legal`, `feature.payments`, `subscriptions`, `credits`).
- [ ] One real low-value purchase and one refund per provider in live mode.
- [ ] Provider dashboards: email alerts for failed webhooks / disputes on; payout account verified.
- [ ] Tax: decide who is the seller (MoR providers vs your own VAT registration).
- [ ] Backups include the `payment_*` tables.
