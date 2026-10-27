<?php

declare(strict_types=1);

namespace rapttor\payment\backoffice\controllers;

use rapttor\payment\models\Price;
use rapttor\payment\Pricing;

final class PricesController extends CrudController
{
    protected function modelClass(): string
    {
        return Price::class;
    }

    protected function title(): string
    {
        return 'Prices';
    }

    protected function columns(): array
    {
        return [
            'code', ['label' => 'Product', 'value' => static fn (Price $p) => $p->product->name],
            ['label' => 'Price', 'value' => static fn (Price $p) => Pricing::format((int) $p->amount, $p->currency)],
            ['attribute' => 'interval', 'value' => static fn (Price $p) => $p->interval ?? 'one-time'],
            'trial_days', 'provider_refs', 'active:boolean',
        ];
    }

    protected function fields(): array
    {
        return [
            'product_id' => 'number', 'code' => 'text', 'currency' => 'text', 'amount' => 'number',
            'interval' => 'select:|day|week|month|year', 'trial_days' => 'number', 'provider_refs' => 'textarea', 'active' => 'checkbox',
        ];
    }

    protected function defaults(): array
    {
        return ['active' => 1, 'currency' => 'EUR'];
    }
}
