<?php

declare(strict_types=1);

namespace rapttor\payment\services;

use rapttor\payment\models\Wallet;
use rapttor\payment\models\WalletEntry;
use Yii;
use yii\db\Exception as DbException;

/**
 * Per-user balances (coins, gems, EUR...) with an append-only ledger. Every change carries an idempotency key,
 * so a repeated webhook or a double click cannot credit or spend twice.
 */
final class WalletService
{
    public function balance(int $userId, string $asset): int
    {
        return (int) Wallet::find()->select('balance')->where(['user_id' => $userId, 'asset' => strtoupper($asset)])->scalar();
    }

    /** @return int new balance */
    public function credit(int $userId, string $asset, int $amount, string $reason, string $idemKey, ?int $orderId = null): int
    {
        return $this->apply($userId, $asset, abs($amount), $reason, $idemKey, $orderId, true);
    }

    /** Spend. Throws {@see InsufficientFundsException} unless `$allowNegative` (refund clawbacks). @return int new balance */
    public function debit(int $userId, string $asset, int $amount, string $reason, string $idemKey, ?int $orderId = null, bool $allowNegative = false): int
    {
        return $this->apply($userId, $asset, -abs($amount), $reason, $idemKey, $orderId, $allowNegative);
    }

    /** @return WalletEntry[] newest first */
    public function history(int $userId, string $asset, int $limit = 50): array
    {
        $w = Wallet::findOne(['user_id' => $userId, 'asset' => strtoupper($asset)]);

        return $w ? WalletEntry::find()->where(['wallet_id' => $w->id])->orderBy(['id' => SORT_DESC])->limit($limit)->all() : [];
    }

    private function apply(int $userId, string $asset, int $delta, string $reason, string $idemKey, ?int $orderId, bool $allowNegative): int
    {
        $asset = strtoupper($asset);
        $db = Yii::$app->db;

        return $db->transaction(function () use ($db, $userId, $asset, $delta, $reason, $idemKey, $orderId, $allowNegative): int {
            $wallet = Wallet::findOne(['user_id' => $userId, 'asset' => $asset]);
            if ($wallet === null) {
                $wallet = new Wallet(['user_id' => $userId, 'asset' => $asset, 'balance' => 0]);
                try {
                    $wallet->save(false);
                } catch (DbException) {
                    $wallet = Wallet::findOne(['user_id' => $userId, 'asset' => $asset]); // lost a race, use the winner's row
                }
            }
            // Lock the row so concurrent spends serialise.
            $balance = (int) $db->createCommand('SELECT balance FROM {{%payment_wallet}} WHERE id = :id FOR UPDATE', [':id' => $wallet->id])->queryScalar();

            $existing = WalletEntry::findOne(['idem_key' => $idemKey]);
            if ($existing !== null) {
                return $balance; // already applied
            }
            $new = $balance + $delta;
            if ($new < 0 && !$allowNegative) {
                throw new InsufficientFundsException(sprintf('%s balance %d is below %d', $asset, $balance, abs($delta)));
            }
            $db->createCommand()->update('{{%payment_wallet}}', ['balance' => $new, 'updated_at' => time()], ['id' => $wallet->id])->execute();
            (new WalletEntry([
                'wallet_id' => $wallet->id, 'delta' => $delta, 'balance_after' => $new,
                'reason' => $reason, 'order_id' => $orderId, 'idem_key' => $idemKey,
            ]))->save(false);

            return $new;
        });
    }
}
