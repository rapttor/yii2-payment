<?php

declare(strict_types=1);

namespace rapttor\payment\dto;

final class CheckoutSession
{
    public function __construct(
        /** Hosted page to send the buyer to. */
        public readonly string $redirectUrl,
        /** Provider's id for the session/order/transaction, stored on our order. */
        public readonly ?string $externalId = null,
    ) {
    }
}
