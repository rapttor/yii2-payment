<?php

declare(strict_types=1);

namespace rapttor\payment\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/** Webhook log and idempotency guard. */
final class Event extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_event}}';
    }

    public function behaviors(): array
    {
        return [['class' => TimestampBehavior::class, 'updatedAtAttribute' => false]];
    }

}
