<?php

declare(strict_types=1);

namespace rapttor\payment\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/** One attempt to buy a price through a provider. The uuid travels to the provider as metadata and comes back in webhooks. */
final class Order extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_order}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const REFUNDED = 'refunded';

    public function getProduct(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getPrice(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Price::class, ['id' => 'price_id']);
    }

}
