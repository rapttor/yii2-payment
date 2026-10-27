<?php

declare(strict_types=1);

namespace rapttor\payment\services;

use rapttor\payment\models\Price;
use rapttor\payment\Payments;
use rapttor\payment\providers\CatalogSyncInterface;

/** Pushes the local catalog to providers that have their own (Stripe, Paddle). Safe to run repeatedly. */
final class CatalogSync
{
    public function __construct(private readonly Payments $payments)
    {
    }

    /**
     * @return string[] one log line per price
     */
    public function run(string $providerId, bool $force = false): array
    {
        $provider = $this->payments->provider($providerId);
        if (!$provider instanceof CatalogSyncInterface) {
            throw new \InvalidArgumentException("$providerId does not support catalog sync.");
        }
        $log = [];
        /** @var Price $price */
        foreach (Price::find()->with('product')->where(['active' => 1])->orderBy(['id' => SORT_ASC])->each() as $price) {
            $refs = $price->decode('provider_refs');
            $sig = $price->amount . ':' . $price->currency . ':' . ($price->interval ?? '') . ':' . $price->trial_days;
            if (!$force && isset($refs[$providerId]) && ($refs[$providerId . '_sig'] ?? '') === $sig) {
                $log[] = "{$price->code}: up to date";
                continue;
            }
            $productRef = $refs[$providerId . '_product'] ?? $this->siblingProduct($price, $providerId);
            $res = $provider->syncPrice($price->product->name, $price->code, (int) $price->amount, $price->currency, $price->interval, (int) $price->trial_days, $productRef, $refs[$providerId] ?? null);
            $refs[$providerId] = $res['price'];
            $refs[$providerId . '_product'] = $res['product'];
            $refs[$providerId . '_sig'] = $sig;
            $price->provider_refs = json_encode($refs);
            $price->save(false);
            $log[] = "{$price->code}: {$res['price']}";
        }

        return $log;
    }

    private function siblingProduct(Price $price, string $providerId): ?string
    {
        foreach (Price::find()->where(['product_id' => $price->product_id])->andWhere(['<>', 'id', $price->id])->each() as $sibling) {
            $ref = $sibling->decode('provider_refs')[$providerId . '_product'] ?? null;
            if ($ref) {
                return (string) $ref;
            }
        }

        return null;
    }
}
