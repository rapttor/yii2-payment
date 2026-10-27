<?php

declare(strict_types=1);

namespace rapttor\payment\backoffice\controllers;

use rapttor\payment\models\Product;

final class ProductsController extends CrudController
{
    protected function modelClass(): string
    {
        return Product::class;
    }

    protected function title(): string
    {
        return 'Products';
    }

    protected function columns(): array
    {
        return ['code', 'name', ['attribute' => 'type', 'value' => static fn (Product $p) => Product::TYPES[$p->type] ?? $p->type], 'grants', 'active:boolean'];
    }

    protected function fields(): array
    {
        return [
            'code' => 'text', 'name' => 'text', 'type' => 'select:' . implode('|', array_keys(Product::TYPES)),
            'description' => 'textarea', 'grants' => 'textarea', 'active' => 'checkbox',
        ];
    }

    protected function defaults(): array
    {
        return ['active' => 1, 'type' => 'one_time'];
    }
}
