<?php

declare(strict_types=1);

namespace rapttor\payment\providers;

use rapttor\payment\Capability;
use rapttor\payment\dto\CheckoutRequest;
use rapttor\payment\dto\CheckoutSession;
use rapttor\payment\dto\WebhookEvent;

/** Stripe Checkout (hosted page). Webhook: point Stripe at /payments/webhook/stripe and copy the signing secret. */
final class StripeProvider extends BaseProvider implements CatalogSyncInterface
{
    public string $secretKey = '';
    public string $webhookSecret = '';
    /** Seconds a webhook timestamp may differ from now. */
    public int $tolerance = 300;
    public string $apiBase = 'https://api.stripe.com/v1';

    public function id(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function capabilities(): array
    {
        return [Capability::ONE_TIME, Capability::SUBSCRIPTION, Capability::REFUND, Capability::CATALOG_SYNC, Capability::PORTAL];
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && $this->webhookSecret !== '';
    }

    public function requiredSettings(): array
    {
        return ['secretKey (STRIPE_SECRET_KEY)', 'webhookSecret (STRIPE_WEBHOOK_SECRET)'];
    }

    public function dashboardLinks(): array
    {
        $b = $this->isLive() ? 'https://dashboard.stripe.com' : 'https://dashboard.stripe.com/test';

        return [
            'Products and prices' => $b . '/products',
            'Coupons' => $b . '/coupons',
            'Payments' => $b . '/payments',
            'Webhooks' => $b . '/webhooks',
        ];
    }

    public function apiLinks(): array
    {
        return [
            'API reference' => 'https://docs.stripe.com/api',
            'Checkout' => 'https://docs.stripe.com/payments/checkout',
            'Webhooks' => 'https://docs.stripe.com/webhooks',
            'Products API' => 'https://docs.stripe.com/api/products',
            'Prices API' => 'https://docs.stripe.com/api/prices',
            'Coupons API' => 'https://docs.stripe.com/api/coupons',
            'Subscriptions' => 'https://docs.stripe.com/billing/subscriptions/build-subscriptions',
            'Test cards' => 'https://docs.stripe.com/testing',
            'Customer portal' => 'https://docs.stripe.com/customer-management',
        ];
    }

    public function createCheckout(CheckoutRequest $r): CheckoutSession
    {
        $subscription = $r->interval !== null;
        $f = [
            'mode' => $subscription ? 'subscription' : 'payment',
            'success_url' => $r->successUrl,
            'cancel_url' => $r->cancelUrl,
            'client_reference_id' => $r->orderUuid,
            'metadata[order]' => $r->orderUuid,
        ];
        if ($r->customerEmail) {
            $f['customer_email'] = $r->customerEmail;
        }
        if ($r->locale) {
            $f['locale'] = $r->locale;
        }
        if ($r->externalPriceId) {
            $f['line_items[0][price]'] = $r->externalPriceId;
        } else {
            $f['line_items[0][price_data][currency]'] = strtolower($r->currency);
            $f['line_items[0][price_data][unit_amount]'] = $r->amount;
            $f['line_items[0][price_data][product_data][name]'] = $r->name;
            if ($subscription) {
                $f['line_items[0][price_data][recurring][interval]'] = $r->interval;
            }
        }
        $f['line_items[0][quantity]'] = 1;
        if ($subscription) {
            $f['subscription_data[metadata][order]'] = $r->orderUuid;
            if ($r->trialDays > 0) {
                $f['subscription_data[trial_period_days]'] = $r->trialDays;
            }
        } else {
            $f['payment_intent_data[metadata][order]'] = $r->orderUuid;
        }
        foreach ($r->metadata as $k => $v) {
            $f["metadata[$k]"] = (string) $v;
        }
        $body = $this->ensureOk($this->post('/checkout/sessions', $f, $r->orderUuid), 'create checkout session');

        return new CheckoutSession((string) $body['url'], (string) $body['id']);
    }

    public function parseWebhook(string $body, array $headers): array
    {
        $this->verify($body, $headers['stripe-signature'] ?? '');
        $e = json_decode($body, true);
        if (!is_array($e) || !isset($e['id'], $e['type'])) {
            return [];
        }
        $o = $e['data']['object'] ?? [];
        $id = (string) $e['id'];
        $order = $o['metadata']['order'] ?? $o['client_reference_id'] ?? null;

        switch ($e['type']) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                if (($o['payment_status'] ?? '') === 'unpaid') {
                    return [];
                }
                $out = [new WebhookEvent('stripe', $id, WebhookEvent::PAID, $order, $o['payment_intent'] ?? null, $o['subscription'] ?? null, $o['amount_total'] ?? null, isset($o['currency']) ? strtoupper($o['currency']) : null, $e)];
                if (!empty($o['subscription'])) {
                    $out[] = new WebhookEvent('stripe', $id . ':sub', WebhookEvent::SUBSCRIPTION_STARTED, $order, null, $o['subscription'], null, null, $e);
                }

                return $out;
            case 'checkout.session.async_payment_failed':
                return [new WebhookEvent('stripe', $id, WebhookEvent::FAILED, $order, $o['payment_intent'] ?? null, null, null, null, $e)];
            case 'invoice.paid':
                // First invoice is covered by checkout.session.completed; only later cycles are renewals.
                if (($o['billing_reason'] ?? '') !== 'subscription_cycle') {
                    return [];
                }
                $subOrder = $o['subscription_details']['metadata']['order'] ?? null;

                return [new WebhookEvent('stripe', $id, WebhookEvent::SUBSCRIPTION_RENEWED, $subOrder, $o['payment_intent'] ?? null, $o['subscription'] ?? null, $o['amount_paid'] ?? null, isset($o['currency']) ? strtoupper($o['currency']) : null, $e)];
            case 'customer.subscription.deleted':
                return [new WebhookEvent('stripe', $id, WebhookEvent::SUBSCRIPTION_CANCELED, $o['metadata']['order'] ?? null, null, $o['id'] ?? null, null, null, $e)];
            case 'charge.refunded':
                return [new WebhookEvent('stripe', $id, WebhookEvent::REFUNDED, $o['metadata']['order'] ?? null, $o['payment_intent'] ?? null, null, $o['amount_refunded'] ?? null, isset($o['currency']) ? strtoupper($o['currency']) : null, $e)];
        }

        return [];
    }

    public function refund(string $paymentId, ?int $amount = null, ?string $currency = null): bool
    {
        $f = ['payment_intent' => $paymentId];
        if ($amount !== null) {
            $f['amount'] = $amount;
        }
        $this->ensureOk($this->post('/refunds', $f, 'refund-' . $paymentId . '-' . ($amount ?? 'full')), 'refund');

        return true;
    }

    public function syncPrice(string $name, string $code, int $amount, string $currency, ?string $interval, int $trialDays, ?string $productRef, ?string $previousPriceId): array
    {
        if ($productRef === null) {
            $productRef = (string) $this->ensureOk($this->post('/products', ['name' => $name, 'metadata[code]' => $code], 'product-' . $code), 'create product')['id'];
        }
        $f = ['product' => $productRef, 'currency' => strtolower($currency), 'unit_amount' => $amount, 'metadata[price_code]' => $code];
        if ($interval !== null) {
            $f['recurring[interval]'] = $interval;
        }
        $price = (string) $this->ensureOk($this->post('/prices', $f, 'price-' . $code . '-' . $amount . $currency . $interval), 'create price')['id'];
        if ($previousPriceId && $previousPriceId !== $price) {
            $this->post('/prices/' . rawurlencode($previousPriceId), ['active' => 'false'], 'archive-' . $previousPriceId);
        }

        return ['product' => $productRef, 'price' => $price];
    }

    /** Stripe-Signature: t=timestamp,v1=hmac(secret, "t.body"). */
    public function verify(string $body, string $header): void
    {
        $t = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $t = (int) $v;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        if ($t === null || $sigs === [] || $this->webhookSecret === '') {
            throw new InvalidSignatureException('Stripe signature missing');
        }
        if (abs(time() - $t) > $this->tolerance) {
            throw new InvalidSignatureException('Stripe signature timestamp outside tolerance');
        }
        $expected = hash_hmac('sha256', $t . '.' . $body, $this->webhookSecret);
        foreach ($sigs as $s) {
            if ($this->equals($expected, $s)) {
                return;
            }
        }
        throw new InvalidSignatureException('Stripe signature mismatch');
    }

    /** @param array<string,scalar> $fields */
    private function post(string $path, array $fields, string $idempotencyKey): array
    {
        return $this->http('POST', $this->apiBase . $path, [
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Idempotency-Key' => $idempotencyKey,
        ], http_build_query($fields));
    }
}
