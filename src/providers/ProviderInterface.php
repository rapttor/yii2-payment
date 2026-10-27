<?php

declare(strict_types=1);

namespace rapttor\payment\providers;

use rapttor\payment\dto\CheckoutRequest;
use rapttor\payment\dto\CheckoutSession;
use rapttor\payment\dto\WebhookEvent;

interface ProviderInterface
{
    /** Stable machine id, e.g. "stripe". Used in URLs and the database. */
    public function id(): string;

    public function label(): string;

    /** @return string[] values of {@see \rapttor\payment\Capability} */
    public function capabilities(): array;

    /** True when the credentials needed to talk to the provider are present. */
    public function isConfigured(): bool;

    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /**
     * Verify the signature and turn the notification into events. Must throw {@see InvalidSignatureException}
     * when the request is not genuinely from the provider. Unknown event types return an empty list.
     *
     * @param array<string,string> $headers lower-cased header names
     * @return WebhookEvent[]
     */
    public function parseWebhook(string $body, array $headers): array;

    /** Refund fully (amount null) or partly. Returns true on success. */
    public function refund(string $paymentId, ?int $amount = null, ?string $currency = null): bool;

    /**
     * Links into the provider's own dashboard where prices, products and discounts can be edited.
     * The backoffice shows them as "open in new tab".
     *
     * @return array<string,string> label => url
     */
    public function dashboardLinks(): array;

    /**
     * Official documentation and API reference pages, shown in the backoffice and used by guides.
     *
     * @return array<string,string> label => url
     */
    public function apiLinks(): array;

    /** Environment variables or config keys the owner must fill in, for the backoffice checklist. @return string[] */
    public function requiredSettings(): array;
}
