<?php

declare(strict_types=1);

namespace rapttor\payment\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/** A recurring purchase tracked from provider events. */
final class Subscription extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_subscription}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

}
