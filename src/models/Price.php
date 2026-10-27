<?php

declare(strict_types=1);

namespace rapttor\payment\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/** Amount (minor units) of a product in one currency, optionally recurring. Monthly and yearly are two rows. */
final class Price extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_price}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['product_id', 'code', 'currency', 'amount'], 'required'],
            [['code'], 'match', 'pattern' => '/^[a-z0-9_.-]+$/'],
            [['code'], 'unique'],
            [['currency'], 'match', 'pattern' => '/^[A-Z]{3}$/'],
            [['amount', 'trial_days', 'product_id'], 'integer', 'min' => 0],
            [['interval'], 'in', 'range' => ['day', 'week', 'month', 'year']],
            [['interval'], 'default', 'value' => null],
            [['active'], 'boolean'],
            [['provider_refs'], 'string'],
        ];
    }

    /** @return array<string,mixed> */
    public function decode(string $attr): array
    {
        $v = json_decode((string) $this->$attr, true);

        return is_array($v) ? $v : [];
    }

    public function getProduct(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function externalId(string $provider): ?string
    {
        return $this->decode('provider_refs')[$provider] ?? null;
    }

}
