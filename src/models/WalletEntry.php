<?php

declare(strict_types=1);

namespace rapttor\payment\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/** Append-only ledger row; balance_after makes the history auditable. */
final class WalletEntry extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%payment_wallet_entry}}';
    }

    public function behaviors(): array
    {
        return [['class' => TimestampBehavior::class, 'updatedAtAttribute' => false]];
    }

}
