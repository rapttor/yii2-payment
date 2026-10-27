<?php

declare(strict_types=1);

namespace rapttor\payment\providers;

use rapttor\payment\Capability;
use rapttor\payment\dto\CheckoutRequest;
use rapttor\payment\dto\CheckoutSession;
use rapttor\payment\dto\WebhookEvent;

/**
 * PayPal Orders v2, one-time payments. The buyer returns to /payments/return/paypal where the order is captured.
 * Subscriptions need PayPal Billing plans and are not supported yet.
 */
final class PayPalProvider extends BaseProvider implements ReturnHandlerInterface
{
    public string $clientId = '';
    public string $clientSecret = '';
    /** Id of the webhook registered in the PayPal developer dashboard (used for signature verification). */
    public string $webhookId = '';

    public function id(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function capabilities(): array
    {
        return [Capability::ONE_TIME, Capability::REFUND];
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->webhookId !== '';
    }

    public function requiredSettings(): array
    {
        return ['clientId (PAYPAL_CLIENT_ID)', 'clientSecret (PAYPAL_CLIENT_SECRET)', 'webhookId (PAYPAL_WEBHOOK_ID)'];
    }

    private function base(): string
    {
        return $this->isLive() ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    public function dashboardLinks(): array
    {
        return [
            'Developer dashboard' => 'https://developer.paypal.com/dashboard/',
            'Transactions' => $this->isLive() ? 'https://www.paypal.com/activity' : 'https://www.sandbox.paypal.com/activity',
        ];
    }

    public function apiLinks(): array
    {
        return [
            'Orders v2' => 'https://developer.paypal.com/docs/api/orders/v2/',
            'Payments v2 (captures, refunds)' => 'https://developer.paypal.com/docs/api/payments/v2/',
            'Webhooks' => 'https://developer.paypal.com/api/rest/webhooks/',
            'Subscriptions v1' => 'https://developer.paypal.com/docs/api/subscriptions/v1/',
            'Sandbox' => 'https://developer.paypal.com/tools/sandbox/',
        ];
    }

    public function createCheckout(CheckoutRequest $r): CheckoutSession
    {
        if ($r->interval !== null) {
            throw new \RuntimeException('paypal: subscriptions are not supported yet.');
        }
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => $r->orderUuid,
                'description' => mb_substr($r->name, 0, 127),
                'amount' => ['currency_code' => strtoupper($r->currency), 'value' => $this->decimal($r->amount, $r->currency)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'return_url' => $r->successUrl,
                'cancel_url' => $r->cancelUrl,
                'user_action' => 'PAY_NOW',
            ]]],
        ];
        $res = $this->http('POST', $this->base() . '/v2/checkout/orders', $this->apiHeaders() + ['PayPal-Request-Id' => $r->orderUuid], json_encode($payload, JSON_THROW_ON_ERROR));
        $body = $this->ensureOk($res, 'create order');
        foreach ($body['links'] ?? [] as $link) {
            if (in_array($link['rel'] ?? '', ['payer-action', 'approve'], true)) {
                return new CheckoutSession((string) $link['href'], (string) $body['id']);
            }
        }
        throw new \RuntimeException('paypal: no approval link in response');
    }

    /** The return URL carries PayPal's `token` (= PayPal order id). Capture it and report the result. */
    public function handleReturn(array $query): array
    {
        $token = (string) ($query['token'] ?? '');
        if ($token === '') {
            return [];
        }
        $res = $this->http('POST', $this->base() . '/v2/checkout/orders/' . rawurlencode($token) . '/capture', $this->apiHeaders() + ['PayPal-Request-Id' => 'capture-' . $token], '{}');
        // 422 ORDER_ALREADY_CAPTURED is fine: the webhook or a reload got there first.
        if ($res['status'] === 422) {
            return [];
        }
        $body = $this->ensureOk($res, 'capture order');
        $unit = $body['purchase_units'][0] ?? [];
        $cap = $unit['payments']['captures'][0] ?? [];
        if (($cap['status'] ?? '') !== 'COMPLETED') {
            return [];
        }
        $order = $cap['custom_id'] ?? $unit['custom_id'] ?? null;

        return [new WebhookEvent('paypal', 'capture:' . $cap['id'], WebhookEvent::PAID, $order, $cap['id'], null, $this->minor((string) $cap['amount']['value'], $cap['amount']['currency_code']), $cap['amount']['currency_code'], $body)];
    }

    public function parseWebhook(string $body, array $headers): array
    {
        $this->verify($body, $headers);
        $e = json_decode($body, true);
        if (!is_array($e) || !isset($e['id'], $e['event_type'])) {
            return [];
        }
        $r = $e['resource'] ?? [];
        $amount = isset($r['amount']['value']) ? $this->minor((string) $r['amount']['value'], $r['amount']['currency_code']) : null;
        $cur = $r['amount']['currency_code'] ?? null;

        switch ($e['event_type']) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                // Same event id as handleReturn() so whichever arrives second is ignored.
                return [new WebhookEvent('paypal', 'capture:' . $r['id'], WebhookEvent::PAID, $r['custom_id'] ?? null, $r['id'], null, $amount, $cur, $e)];
            case 'PAYMENT.CAPTURE.DENIED':
                return [new WebhookEvent('paypal', (string) $e['id'], WebhookEvent::FAILED, $r['custom_id'] ?? null, $r['id'] ?? null, null, null, null, $e)];
            case 'PAYMENT.CAPTURE.REFUNDED':
                $capture = '';
                foreach ($r['links'] ?? [] as $l) {
                    if (($l['rel'] ?? '') === 'up') {
                        $capture = basename((string) $l['href']);
                    }
                }

                return [new WebhookEvent('paypal', (string) $e['id'], WebhookEvent::REFUNDED, $r['custom_id'] ?? null, $capture ?: null, null, $amount, $cur, $e)];
        }

        return [];
    }

    public function refund(string $paymentId, ?int $amount = null, ?string $currency = null): bool
    {
        $payload = $amount !== null ? ['amount' => ['value' => $this->decimal($amount, (string) $currency), 'currency_code' => strtoupper((string) $currency)]] : new \stdClass();
        $this->ensureOk($this->http('POST', $this->base() . '/v2/payments/captures/' . rawurlencode($paymentId) . '/refund', $this->apiHeaders(), json_encode($payload, JSON_THROW_ON_ERROR)), 'refund');

        return true;
    }

    /** PayPal verifies its own signatures through an API call. */
    public function verify(string $body, array $headers): void
    {
        $payload = '{"auth_algo":' . json_encode($headers['paypal-auth-algo'] ?? '')
            . ',"cert_url":' . json_encode($headers['paypal-cert-url'] ?? '')
            . ',"transmission_id":' . json_encode($headers['paypal-transmission-id'] ?? '')
            . ',"transmission_sig":' . json_encode($headers['paypal-transmission-sig'] ?? '')
            . ',"transmission_time":' . json_encode($headers['paypal-transmission-time'] ?? '')
            . ',"webhook_id":' . json_encode($this->webhookId)
            . ',"webhook_event":' . $body . '}';
        $res = $this->http('POST', $this->base() . '/v1/notifications/verify-webhook-signature', $this->apiHeaders(), $payload);
        if (($res['body']['verification_status'] ?? '') !== 'SUCCESS') {
            throw new InvalidSignatureException('PayPal signature verification failed');
        }
    }

    /** @return array<string,string> */
    private function apiHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token(), 'Content-Type' => 'application/json'];
    }

    private ?string $token = null;

    private function token(): string
    {
        if ($this->token === null) {
            $res = $this->http('POST', $this->base() . '/v1/oauth2/token', [
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], 'grant_type=client_credentials');
            $this->token = (string) ($this->ensureOk($res, 'get access token')['access_token'] ?? '');
        }

        return $this->token;
    }

    private const ZERO_DECIMAL = ['JPY', 'KRW', 'VND', 'CLP', 'HUF', 'TWD'];

    private function decimal(int $minor, string $currency): string
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? (string) $minor : number_format($minor / 100, 2, '.', '');
    }

    private function minor(string $value, string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? (int) $value : (int) round(((float) $value) * 100);
    }
}
