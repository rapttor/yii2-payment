<?php

declare(strict_types=1);

namespace rapttor\payment\providers;

use rapttor\payment\Capability;
use rapttor\payment\dto\CheckoutRequest;
use rapttor\payment\dto\CheckoutSession;
use yii\helpers\Url;

/** Fake provider for development: shows a "pay / fail" page and needs no keys. Never enable in production. */
final class SandboxProvider extends BaseProvider
{
    public function id(): string
    {
        return 'sandbox';
    }

    public function label(): string
    {
        return 'Sandbox (fake, no money)';
    }

    public function capabilities(): array
    {
        return [Capability::ONE_TIME, Capability::SUBSCRIPTION, Capability::REFUND];
    }

    public function createCheckout(CheckoutRequest $r): CheckoutSession
    {
        return new CheckoutSession(Url::to(['/payments/sandbox/pay', 'order' => $r->orderUuid], true), 'sandbox-' . $r->orderUuid);
    }

    public function parseWebhook(string $body, array $headers): array
    {
        return [];
    }

    public function refund(string $paymentId, ?int $amount = null, ?string $currency = null): bool
    {
        return true;
    }
}
