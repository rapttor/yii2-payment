<?php

declare(strict_types=1);

namespace rapttor\payment\providers;

/**
 * For providers that keep their own product/price catalog. `CatalogSync` calls this for every active price and stores
 * the returned ids in `payment_price.provider_refs`, so checkout can use the provider's own price (and its tax, invoices, portal).
 */
interface CatalogSyncInterface
{
    /**
     * Create (or reuse) the provider product and create a price for it. Provider prices are immutable, so a changed
     * amount always yields a new price id; archive `$previousPriceId` if given.
     *
     * @param string|null $productRef provider product id already used by a sibling price of the same product
     * @return array{product:string, price:string}
     */
    public function syncPrice(string $name, string $code, int $amount, string $currency, ?string $interval, int $trialDays, ?string $productRef, ?string $previousPriceId): array;
}
