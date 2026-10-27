<?php

declare(strict_types=1);

namespace rapttor\payment\dto;

/** Everything a provider needs to start a payment. Amounts are minor units (cents) and already include discounts. */
final class CheckoutRequest
{
    public function __construct(
        public readonly string $orderUuid,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $name,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        /** null = one-time, otherwise month|year|week|day. */
        public readonly ?string $interval = null,
        public readonly int $trialDays = 0,
        public readonly ?string $customerEmail = null,
        /** Id of the matching price in the provider's own catalog, when mapped. Overrides amount/name for catalog providers. */
        public readonly ?string $externalPriceId = null,
        public readonly ?string $locale = null,
        /** @var array<string,scalar> */
        public readonly array $metadata = [],
    ) {
    }
}
