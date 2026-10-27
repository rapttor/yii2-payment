<?php

declare(strict_types=1);

namespace rapttor\payment\dto;

/** A provider notification normalised to one shape, so the rest of the package never sees provider payloads. */
final class WebhookEvent
{
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const REFUNDED = 'refunded';
    public const SUBSCRIPTION_STARTED = 'subscription_started';
    public const SUBSCRIPTION_RENEWED = 'subscription_renewed';
    public const SUBSCRIPTION_CANCELED = 'subscription_canceled';

    public function __construct(
        public readonly string $provider,
        /** Unique per provider; used for idempotency. */
        public readonly string $eventId,
        public readonly string $type,
        /** Our order uuid, read back from the metadata we sent. May be null for events we cannot attribute. */
        public readonly ?string $orderUuid = null,
        /** Provider's payment/transaction/capture id (needed for refunds). */
        public readonly ?string $paymentId = null,
        public readonly ?string $subscriptionId = null,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        /** @var array<string,mixed> */
        public readonly array $raw = [],
    ) {
    }
}
