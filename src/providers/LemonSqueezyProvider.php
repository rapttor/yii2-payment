<?php

declare(strict_types=1);

namespace rapttor\payment\providers;

use rapttor\payment\Capability;
use rapttor\payment\dto\CheckoutRequest;
use rapttor\payment\dto\CheckoutSession;
use rapttor\payment\dto\WebhookEvent;

/**
 * Lemon Squeezy (merchant of record). Checkouts are created through the API for a variant.
 * One-time purchases use `variantId` with a custom price, so no per-price setup is needed.
 * Subscriptions need a subscription variant per price: put its id in the price's provider_refs as {"lemonsqueezy":"<variant id>"}.
 * Lemon Squeezy has no separate sandbox host: test mode is a flag on the key / checkout.
 */
final class LemonSqueezyProvider extends BaseProvider
{
    public string $apiKey = '';
    public string $storeId = '';
    /** Variant used for one-time purchases with a custom price (create a "Pay what we say" product, any price). */
    public string $variantId = '';
    public string $webhookSecret = '';
    public string $apiBase = 'https://api.lemonsqueezy.com/v1';

    public function id(): string
    {
        return 'lemonsqueezy';
    }

    public function label(): string
    {
        return 'Lemon Squeezy';
    }

    public function capabilities(): array
    {
        return [Capability::ONE_TIME, Capability::SUBSCRIPTION, Capability::REFUND, Capability::PORTAL];
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->storeId !== '' && $this->webhookSecret !== '';
    }

    public function requiredSettings(): array
    {
        return ['apiKey (LEMONSQUEEZY_API_KEY)', 'storeId (LEMONSQUEEZY_STORE_ID)', 'variantId (LEMONSQUEEZY_VARIANT_ID)', 'webhookSecret (LEMONSQUEEZY_WEBHOOK_SECRET)'];
    }

    public function dashboardLinks(): array
    {
        return [
            'Products' => 'https://app.lemonsqueezy.com/products',
            'Discounts' => 'https://app.lemonsqueezy.com/discounts',
            'Orders' => 'https://app.lemonsqueezy.com/orders',
            'Webhooks' => 'https://app.lemonsqueezy.com/settings/webhooks',
            'API keys' => 'https://app.lemonsqueezy.com/settings/api',
        ];
    }

    public function apiLinks(): array
    {
        return [
            'API reference' => 'https://docs.lemonsqueezy.com/api',
            'Checkouts API' => 'https://docs.lemonsqueezy.com/api/checkouts',
            'Products API' => 'https://docs.lemonsqueezy.com/api/products',
            'Discounts API' => 'https://docs.lemonsqueezy.com/api/discounts',
            'Webhooks' => 'https://docs.lemonsqueezy.com/help/webhooks',
            'Signing requests' => 'https://docs.lemonsqueezy.com/help/webhooks/signing-requests',
        ];
    }

    public function createCheckout(CheckoutRequest $r): CheckoutSession
    {
        $variant = $r->externalPriceId ?? $this->variantId;
        if ($variant === '' || ($r->interval !== null && $r->externalPriceId === null)) {
            throw new \RuntimeException('lemonsqueezy: subscriptions need a variant id in the price provider_refs; one-time needs variantId in the config.');
        }
        $attributes = [
            'checkout_data' => ['custom' => ['order' => $r->orderUuid]],
            'product_options' => ['redirect_url' => $r->successUrl, 'name' => $r->name],
            'test_mode' => !$this->isLive(),
        ];
        if ($r->externalPriceId === null) {
            $attributes['custom_price'] = $r->amount; // cents; Lemon Squeezy treats the variant price as a placeholder
        }
        if ($r->customerEmail) {
            $attributes['checkout_data']['email'] = $r->customerEmail;
        }
        $payload = ['data' => [
            'type' => 'checkouts',
            'attributes' => $attributes,
            'relationships' => [
                'store' => ['data' => ['type' => 'stores', 'id' => $this->storeId]],
                'variant' => ['data' => ['type' => 'variants', 'id' => $variant]],
            ],
        ]];
        $res = $this->http('POST', $this->apiBase . '/checkouts', $this->headers(), json_encode($payload, JSON_THROW_ON_ERROR));
        $data = $this->ensureOk($res, 'create checkout')['data'] ?? [];

        return new CheckoutSession((string) ($data['attributes']['url'] ?? ''), (string) ($data['id'] ?? ''));
    }

    public function parseWebhook(string $body, array $headers): array
    {
        $this->verify($body, $headers['x-signature'] ?? '');
        $e = json_decode($body, true);
        if (!is_array($e) || !isset($e['meta']['event_name'], $e['data']['id'])) {
            return [];
        }
        $name = (string) $e['meta']['event_name'];
        $d = $e['data'];
        $a = $d['attributes'] ?? [];
        $order = $e['meta']['custom_data']['order'] ?? null;
        $id = $name . ':' . $d['id'] . ':' . ($a['updated_at'] ?? '');

        switch ($name) {
            case 'order_created':
                if (($a['status'] ?? '') !== 'paid') {
                    return [];
                }

                return [new WebhookEvent('lemonsqueezy', 'order_created:' . $d['id'], WebhookEvent::PAID, $order, (string) $d['id'], null, isset($a['total']) ? (int) $a['total'] : null, $a['currency'] ?? null, $e)];
            case 'order_refunded':
                return [new WebhookEvent('lemonsqueezy', $id, WebhookEvent::REFUNDED, $order, (string) $d['id'], null, isset($a['refunded_amount']) ? (int) $a['refunded_amount'] : null, $a['currency'] ?? null, $e)];
            case 'subscription_created':
                return [new WebhookEvent('lemonsqueezy', $id, WebhookEvent::SUBSCRIPTION_STARTED, $order, null, (string) $d['id'], null, null, $e)];
            case 'subscription_payment_success':
                // First payment is covered by order_created; only renewals count here.
                if (($a['billing_reason'] ?? '') !== 'renewal') {
                    return [];
                }

                return [new WebhookEvent('lemonsqueezy', $id, WebhookEvent::SUBSCRIPTION_RENEWED, $order, null, (string) ($a['subscription_id'] ?? ''), isset($a['total']) ? (int) $a['total'] : null, $a['currency'] ?? null, $e)];
            case 'subscription_cancelled':
            case 'subscription_expired':
                return [new WebhookEvent('lemonsqueezy', $id, WebhookEvent::SUBSCRIPTION_CANCELED, $order, null, (string) $d['id'], null, null, $e)];
        }

        return [];
    }

    public function refund(string $paymentId, ?int $amount = null, ?string $currency = null): bool
    {
        if ($amount !== null) {
            throw new \RuntimeException('lemonsqueezy: partial refunds are not implemented yet.');
        }
        $this->ensureOk($this->http('POST', $this->apiBase . '/orders/' . rawurlencode($paymentId) . '/refund', $this->headers(), '{}'), 'refund');

        return true;
    }

    /** X-Signature: hex HMAC-SHA256 of the raw body with the signing secret. */
    public function verify(string $body, string $signature): void
    {
        if ($signature === '' || $this->webhookSecret === '') {
            throw new InvalidSignatureException('Lemon Squeezy signature missing');
        }
        if (!$this->equals(hash_hmac('sha256', $body, $this->webhookSecret), $signature)) {
            throw new InvalidSignatureException('Lemon Squeezy signature mismatch');
        }
    }

    /** @return array<string,string> */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->apiKey, 'Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'];
    }
}
