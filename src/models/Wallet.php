<?php

declare(strict_types=1);

namespace rapttor\payment\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/** Balance of one asset (COIN, EUR...) for one user. Change it only through WalletService. */
final class Wallet extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_wallet}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

}
