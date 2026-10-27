<?php

declare(strict_types=1);

namespace rapttor\payment\providers;

use rapttor\payment\dto\WebhookEvent;

/** For providers (PayPal, card gateways) where the buyer's return must be confirmed server-side before money is captured. */
interface ReturnHandlerInterface
{
    /**
     * @param array<string,mixed> $query the return URL's query parameters
     * @return WebhookEvent[]
     */
    public function handleReturn(array $query): array;
}
