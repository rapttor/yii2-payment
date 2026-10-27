<?php

declare(strict_types=1);

namespace rapttor\payment\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/** Coupon: percent or fixed amount, optionally limited to a product, a period and a number of uses. */
final class Discount extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_discount}}';
    }

    public function behaviors(): array
    {
        return [['class' => TimestampBehavior::class, 'updatedAtAttribute' => false]];
    }

    public function rules(): array
    {
        return [
            [['code'], 'required'],
            [['code'], 'unique'],
            [['percent'], 'integer', 'min' => 1, 'max' => 100],
            [['amount_off', 'product_id', 'starts_at', 'ends_at', 'max_redemptions'], 'integer'],
            [['currency'], 'match', 'pattern' => '/^[A-Z]{3}$/'],
            [['active'], 'boolean'],
        ];
    }

    /** Usable now, for this product and currency, and not used up. */
    public function appliesTo(Product $product, string $currency, int $now = 0): bool
    {
        $now = $now ?: time();

        return (bool) $this->active
            && ($this->product_id === null || (int) $this->product_id === (int) $product->id)
            && ($this->starts_at === null || (int) $this->starts_at <= $now)
            && ($this->ends_at === null || (int) $this->ends_at >= $now)
            && ($this->max_redemptions === null || (int) $this->redemptions < (int) $this->max_redemptions)
            && ($this->percent !== null || ($this->amount_off !== null && $this->currency === $currency));
    }

}
