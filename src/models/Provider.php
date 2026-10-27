<?php

declare(strict_types=1);

namespace rapttor\payment\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/** Switch + mode + restrictions for one provider code. */
final class Provider extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_provider}}';
    }

    public function behaviors(): array
    {
        return [['class' => TimestampBehavior::class, 'createdAtAttribute' => false]];
    }

    public static function primaryKey(): array
    {
        return ['code'];
    }

    public function rules(): array
    {
        return [
            [['code'], 'required'],
            [['enabled'], 'boolean'],
            [['mode'], 'in', 'range' => ['test', 'live']],
            [['sort'], 'integer'],
            [['countries', 'currencies'], 'string', 'max' => 500],
        ];
    }

    /** @return string[] upper-case codes from a comma list; empty = no restriction */
    public static function list(?string $csv): array
    {
        return array_values(array_filter(array_map(static fn ($s) => strtoupper(trim($s)), explode(',', (string) $csv))));
    }

}
