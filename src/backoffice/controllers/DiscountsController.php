<?php

declare(strict_types=1);

namespace rapttor\payment\backoffice\controllers;

use rapttor\payment\models\Discount;

final class DiscountsController extends CrudController
{
    protected function modelClass(): string
    {
        return Discount::class;
    }

    protected function title(): string
    {
        return 'Discounts';
    }

    protected function columns(): array
    {
        return ['code', 'percent', 'amount_off', 'currency', 'product_id', 'starts_at:datetime', 'ends_at:datetime', 'redemptions', 'max_redemptions', 'active:boolean'];
    }

    protected function fields(): array
    {
        return [
            'code' => 'text', 'percent' => 'number', 'amount_off' => 'number', 'currency' => 'text', 'product_id' => 'number',
            'starts_at' => 'number', 'ends_at' => 'number', 'max_redemptions' => 'number', 'active' => 'checkbox',
        ];
    }

    protected function defaults(): array
    {
        return ['active' => 1];
    }
}
