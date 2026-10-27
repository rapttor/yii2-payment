<?php

declare(strict_types=1);

namespace rapttor\payment\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/** Something sold. `grants` JSON says what the buyer gets, e.g. {"coins":500,"asset":"COIN"}. */
final class Product extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_product}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public const TYPES = ['one_time' => 'One-time', 'subscription' => 'Subscription', 'coins' => 'Coins / credits', 'topup' => 'Wallet top-up'];

    public function rules(): array
    {
        return [
            [['code', 'type', 'name'], 'required'],
            [['code'], 'match', 'pattern' => '/^[a-z0-9_.-]+$/'],
            [['code'], 'unique'],
            [['type'], 'in', 'range' => array_keys(self::TYPES)],
            [['name'], 'string', 'max' => 255],
            [['description', 'grants'], 'string'],
            [['grants'], 'validateJson'],
            [['active'], 'boolean'],
        ];
    }

    public function validateJson(string $attr): void
    {
        if ($this->$attr !== null && $this->$attr !== '' && !is_array(json_decode((string) $this->$attr, true))) {
            $this->addError($attr, 'Must be valid JSON, e.g. {"coins":500}.');
        }
    }

    /** @return array<string,mixed> */
    public function decode(string $attr): array
    {
        $v = json_decode((string) $this->$attr, true);

        return is_array($v) ? $v : [];
    }

    public function getPrices(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Price::class, ['product_id' => 'id']);
    }

}
