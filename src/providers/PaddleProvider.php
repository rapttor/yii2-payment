<?php

declare(strict_types=1);

namespace rapttor\payment\providers;

use rapttor\payment\Capability;
use rapttor\payment\dto\CheckoutRequest;
use rapttor\payment\dto\CheckoutSession;
use rapttor\payment\dto\WebhookEvent;

/**
 * Paddle Billing. Creates a transaction and sends the buyer to its checkout URL.
 * Needs a "Default payment link" under Paddle > Checkout settings (and an approved domain) or checkout.url is empty.
 */
final class PaddleProvider extends BaseProvider implements CatalogSyncInterface
{
    public string $apiKey = '';
    public string $webhookSecret = '';
    public int $tolerance = 300;
    /** Paddle tax category for non-catalog products. */
    public string $taxCategory = 'standard';
    /** Page that loads Paddle.js (must be on an approved domain). Sent per transaction, so no account-wide default link is needed. */
    public ?string $checkoutUrl = null;

    public function id(): string
    {
        return 'paddle';
    }

    public function label(): string
    {
        return 'Paddle';
    }

    public function capabilities(): array
    {
        return [Capability::ONE_TIME, Capability::SUBSCRIPTION, Capability::REFUND, Capability::CATALOG_SYNC, Capability::PORTAL];
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->webhookSecret !== '';
    }

    public function requiredSettings(): array
    {
        return ['apiKey (PADDLE_API_KEY)', 'webhookSecret (PADDLE_WEBHOOK_SECRET)'];
    }

    private function base(): string
    {
        return $this->isLive() ? 'https://api.paddle.com' : 'https://sandbox-api.paddle.com';
    }

    public function dashboardLinks(): array
    {
        $b = $this->isLive() ? 'https://vendors.paddle.com' : 'https://sandbox-vendors.paddle.com';

        return [
            'Products' => $b . '/products-v2',
            'Prices and discounts' => $b . '/discounts-v2',
            'Transactions' => $b . '/transactions-v2',
            'Notifications' => $b . '/notifications-simulator',
            'Checkout settings' => $b . '/checkout-settings',
        ];
    }

    public function apiLinks(): array
    {
        return [
            'API reference' => 'https://developer.paddle.com/api-reference/overview',
            'Webhooks' => 'https://developer.paddle.com/webhooks/overview',
            'Products API' => 'https://developer.paddle.com/api-reference/products/overview',
            'Prices API' => 'https://developer.paddle.com/api-reference/prices/overview',
            'Discounts API' => 'https://developer.paddle.com/api-reference/discounts/overview',
            'Create transaction' => 'https://developer.paddle.com/api-reference/transactions/create-transaction',
            'Default payment link' => 'https://developer.paddle.com/build/transactions/default-payment-link',
            'Adjustments (refunds)' => 'https://developer.paddle.com/api-reference/adjustments/overview',
        ];
    }

    public function createCheckout(CheckoutRequest $r): CheckoutSession
    {
        $item = ['quantity' => 1];
        if ($r->externalPriceId) {
            $item['price_id'] = $r->externalPriceId;
        } else {
            $price = [
                'description' => $r->name,
                'name' => $r->name,
                'unit_price' => ['amount' => (string) $r->amount, 'currency_code' => strtoupper($r->currency)],
                'product' => ['name' => $r->name, 'tax_category' => $this->taxCategory],
            ];
            if ($r->interval !== null) {
                $price['billing_cycle'] = ['interval' => $r->interval, 'frequency' => 1];
                if ($r->trialDays > 0) {
                    $price['trial_period'] = ['interval' => 'day', 'frequency' => $r->trialDays];
                }
            }
            $item['price'] = $price;
        }
        $payload = [
            'items' => [$item],
            'custom_data' => ['order' => $r->orderUuid] + $this->appTag() + array_map('strval', $r->metadata),
            'collection_mode' => 'automatic',
        ];
        if ($this->checkoutUrl) {
            $payload['checkout'] = ['url' => $this->checkoutUrl];
        }
        if ($r->customerEmail) {
            // Paddle needs a customer id for prefill; the buyer types the email at checkout otherwise.
            $payload['custom_data']['email'] = $r->customerEmail;
        }
        $res = $this->http('POST', $this->base() . '/transactions', $this->headers(), json_encode($payload, JSON_THROW_ON_ERROR));
        $data = $this->ensureOk($res, 'create transaction')['data'] ?? [];
        $url = $data['checkout']['url'] ?? null;
        if (!$url) {
            throw new \RuntimeException('paddle: no checkout.url returned; set a Default payment link in Paddle > Checkout settings.');
        }

        return new CheckoutSession((string) $url, (string) $data['id']);
    }

    public function parseWebhook(string $body, array $headers): array
    {
        $this->verify($body, $headers['paddle-signature'] ?? '');
        $e = json_decode($body, true);
        if (!is_array($e) || !isset($e['event_id'], $e['event_type'])) {
            return [];
        }
        $d = $e['data'] ?? [];
        $tag = $d['custom_data']['app'] ?? null;
        if ($this->app !== '' && $tag !== null && $tag !== $this->app) {
            return []; // another app's event on the shared account
        }
        $id = (string) $e['event_id'];
        $order = $d['custom_data']['order'] ?? null;
        $cur = $d['currency_code'] ?? null;
        $total = isset($d['details']['totals']['grand_total']) ? (int) $d['details']['totals']['grand_total'] : null;

        switch ($e['event_type']) {
            case 'transaction.completed':
                $origin = $d['origin'] ?? '';
                $renewal = $origin === 'subscription_recurring';
                $out = [new WebhookEvent('paddle', $id, $renewal ? WebhookEvent::SUBSCRIPTION_RENEWED : WebhookEvent::PAID, $order, $d['id'] ?? null, $d['subscription_id'] ?? null, $total, $cur, $e)];
                if (!$renewal && !empty($d['subscription_id'])) {
                    $out[] = new WebhookEvent('paddle', $id . ':sub', WebhookEvent::SUBSCRIPTION_STARTED, $order, null, $d['subscription_id'], null, null, $e);
                }

                return $out;
            case 'transaction.payment_failed':
                return [new WebhookEvent('paddle', $id, WebhookEvent::FAILED, $order, $d['id'] ?? null, null, null, null, $e)];
            case 'subscription.canceled':
                return [new WebhookEvent('paddle', $id, WebhookEvent::SUBSCRIPTION_CANCELED, $order, null, $d['id'] ?? null, null, null, $e)];
            case 'adjustment.updated':
                if (($d['action'] ?? '') === 'refund' && ($d['status'] ?? '') === 'approved') {
                    $amount = isset($d['totals']['total']) ? (int) $d['totals']['total'] : null;

                    return [new WebhookEvent('paddle', $id, WebhookEvent::REFUNDED, null, $d['transaction_id'] ?? null, null, $amount, $d['currency_code'] ?? null, $e)];
                }
        }

        return [];
    }

    public function refund(string $paymentId, ?int $amount = null, ?string $currency = null): bool
    {
        // Paddle refunds are adjustments; "full" needs the line items, so fetch the transaction first.
        $tx = $this->ensureOk($this->http('GET', $this->base() . '/transactions/' . rawurlencode($paymentId), $this->headers()), 'read transaction')['data'] ?? [];
        $items = [];
        foreach ($tx['details']['line_items'] ?? [] as $li) {
            $items[] = ['item_id' => $li['id'], 'type' => 'full'];
        }
        if ($amount !== null) {
            throw new \RuntimeException('paddle: partial refunds are not implemented yet.');
        }
        $payload = ['action' => 'refund', 'transaction_id' => $paymentId, 'reason' => 'Refund requested from backoffice', 'items' => $items];
        $this->ensureOk($this->http('POST', $this->base() . '/adjustments', $this->headers(), json_encode($payload, JSON_THROW_ON_ERROR)), 'refund');

        return true;
    }

    public function syncPrice(string $name, string $code, int $amount, string $currency, ?string $interval, int $trialDays, ?string $productRef, ?string $previousPriceId): array
    {
        if ($productRef === null) {
            $res = $this->http('POST', $this->base() . '/products', $this->headers(), json_encode(['name' => $name, 'tax_category' => $this->taxCategory, 'custom_data' => ['code' => $this->prefixed($code)] + $this->appTag()], JSON_THROW_ON_ERROR));
            $productRef = (string) ($this->ensureOk($res, 'create product')['data']['id'] ?? '');
        }
        $payload = [
            'product_id' => $productRef, 'description' => ($this->app !== '' ? "[{$this->app}] " : '') . $name,
            'unit_price' => ['amount' => (string) $amount, 'currency_code' => strtoupper($currency)],
            'custom_data' => ['price_code' => $this->prefixed($code)] + $this->appTag(),
        ];
        if ($interval !== null) {
            $payload['billing_cycle'] = ['interval' => $interval, 'frequency' => 1];
            if ($trialDays > 0) {
                $payload['trial_period'] = ['interval' => 'day', 'frequency' => $trialDays];
            }
        }
        $res = $this->http('POST', $this->base() . '/prices', $this->headers(), json_encode($payload, JSON_THROW_ON_ERROR));
        $price = (string) ($this->ensureOk($res, 'create price')['data']['id'] ?? '');
        if ($previousPriceId && $previousPriceId !== $price) {
            $this->http('PATCH', $this->base() . '/prices/' . rawurlencode($previousPriceId), $this->headers(), '{"status":"archived"}');
        }

        return ['product' => $productRef, 'price' => $price];
    }

    /** Paddle-Signature: ts=...;h1=hmac(secret, "ts:body"). */
    public function verify(string $body, string $header): void
    {
        $ts = null;
        $hashes = [];
        foreach (explode(';', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 'ts') {
                $ts = (int) $v;
            } elseif ($k === 'h1') {
                $hashes[] = $v;
            }
        }
        if ($ts === null || $hashes === [] || $this->webhookSecret === '') {
            throw new InvalidSignatureException('Paddle signature missing');
        }
        if (abs(time() - $ts) > $this->tolerance) {
            throw new InvalidSignatureException('Paddle signature timestamp outside tolerance');
        }
        $expected = hash_hmac('sha256', $ts . ':' . $body, $this->webhookSecret);
        foreach ($hashes as $h) {
            if ($this->equals($expected, $h)) {
                return;
            }
        }
        throw new InvalidSignatureException('Paddle signature mismatch');
    }

    /** @return array<string,string> */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'];
    }
}
