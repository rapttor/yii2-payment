<?php

declare(strict_types=1);

namespace rapttor\payment\migrations;

use yii\db\Migration;

final class m261025_000001_create_payment_tables extends Migration
{
    public function safeUp(): void
    {
        $t = $this->db->driverName === 'mysql' ? 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : null;

        // Which providers are switched on in the backoffice. Credentials are NOT stored here (env/config only).
        $this->createTable('{{%payment_provider}}', [
            'code' => $this->string(32)->notNull(),
            'enabled' => $this->boolean()->notNull()->defaultValue(false),
            'mode' => $this->string(8)->notNull()->defaultValue('test'),
            'sort' => $this->integer()->notNull()->defaultValue(100),
            'countries' => $this->string(500)->null()->comment('comma separated ISO codes; empty = all'),
            'currencies' => $this->string(500)->null()->comment('comma separated; empty = all'),
            'updated_at' => $this->integer()->notNull(),
            'PRIMARY KEY(code)',
        ], $t);

        // Local catalog = source of truth for what is sold and for how much.
        $this->createTable('{{%payment_product}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(64)->notNull(),
            'type' => $this->string(16)->notNull()->comment('one_time|subscription|coins|topup'),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'grants' => $this->text()->null()->comment('JSON: what the buyer receives, e.g. {"coins":500}'),
            'active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $t);
        $this->createIndex('ux_payment_product_code', '{{%payment_product}}', 'code', true);

        $this->createTable('{{%payment_price}}', [
            'id' => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'code' => $this->string(64)->notNull(),
            'currency' => $this->string(3)->notNull(),
            'amount' => $this->bigInteger()->notNull()->comment('minor units'),
            'interval' => $this->string(8)->null()->comment('null = one-time; day|week|month|year'),
            'trial_days' => $this->integer()->notNull()->defaultValue(0),
            'active' => $this->boolean()->notNull()->defaultValue(true),
            'provider_refs' => $this->text()->null()->comment('JSON: {"stripe":"price_...","paddle":"pri_..."}'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $t);
        $this->createIndex('ux_payment_price_code', '{{%payment_price}}', 'code', true);
        $this->addForeignKey('fk_payment_price_product', '{{%payment_price}}', 'product_id', '{{%payment_product}}', 'id', 'CASCADE');

        $this->createTable('{{%payment_discount}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(64)->notNull(),
            'percent' => $this->smallInteger()->null()->comment('1..100'),
            'amount_off' => $this->bigInteger()->null()->comment('minor units, with currency'),
            'currency' => $this->string(3)->null(),
            'product_id' => $this->integer()->null()->comment('null = any product'),
            'starts_at' => $this->integer()->null(),
            'ends_at' => $this->integer()->null(),
            'max_redemptions' => $this->integer()->null(),
            'redemptions' => $this->integer()->notNull()->defaultValue(0),
            'active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->integer()->notNull(),
        ], $t);
        $this->createIndex('ux_payment_discount_code', '{{%payment_discount}}', 'code', true);

        $this->createTable('{{%payment_order}}', [
            'id' => $this->primaryKey(),
            'uuid' => $this->string(36)->notNull(),
            'user_id' => $this->integer()->null(),
            'product_id' => $this->integer()->notNull(),
            'price_id' => $this->integer()->notNull(),
            'discount_id' => $this->integer()->null(),
            'provider' => $this->string(32)->notNull(),
            'status' => $this->string(16)->notNull()->defaultValue('pending')->comment('pending|paid|failed|refunded'),
            'currency' => $this->string(3)->notNull(),
            'amount' => $this->bigInteger()->notNull()->comment('charged, minor units'),
            'discount_amount' => $this->bigInteger()->notNull()->defaultValue(0),
            'external_id' => $this->string(128)->null()->comment('provider session/order id'),
            'payment_id' => $this->string(128)->null()->comment('provider payment/capture id, used for refunds'),
            'subscription_id' => $this->integer()->null(),
            'fulfilled_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $t);
        $this->createIndex('ux_payment_order_uuid', '{{%payment_order}}', 'uuid', true);
        $this->createIndex('ix_payment_order_user', '{{%payment_order}}', 'user_id');
        $this->createIndex('ix_payment_order_payment', '{{%payment_order}}', ['provider', 'payment_id']);

        $this->createTable('{{%payment_subscription}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->null(),
            'product_id' => $this->integer()->notNull(),
            'price_id' => $this->integer()->notNull(),
            'provider' => $this->string(32)->notNull(),
            'external_id' => $this->string(128)->notNull(),
            'status' => $this->string(16)->notNull()->defaultValue('active')->comment('active|canceled'),
            'current_period_end' => $this->integer()->null(),
            'canceled_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $t);
        $this->createIndex('ux_payment_subscription_ext', '{{%payment_subscription}}', ['provider', 'external_id'], true);

        // Webhook log; unique (provider, event_id) makes processing idempotent.
        $this->createTable('{{%payment_event}}', [
            'id' => $this->primaryKey(),
            'provider' => $this->string(32)->notNull(),
            'event_id' => $this->string(160)->notNull(),
            'type' => $this->string(32)->notNull(),
            'order_uuid' => $this->string(36)->null(),
            'result' => $this->string(255)->null(),
            'payload' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
        ], $t);
        $this->createIndex('ux_payment_event', '{{%payment_event}}', ['provider', 'event_id'], true);

        $this->createTable('{{%payment_wallet}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'asset' => $this->string(16)->notNull()->comment('COIN, GEM, EUR...'),
            'balance' => $this->bigInteger()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $t);
        $this->createIndex('ux_payment_wallet', '{{%payment_wallet}}', ['user_id', 'asset'], true);

        $this->createTable('{{%payment_wallet_entry}}', [
            'id' => $this->primaryKey(),
            'wallet_id' => $this->integer()->notNull(),
            'delta' => $this->bigInteger()->notNull(),
            'balance_after' => $this->bigInteger()->notNull(),
            'reason' => $this->string(64)->notNull(),
            'order_id' => $this->integer()->null(),
            'idem_key' => $this->string(160)->notNull(),
            'created_at' => $this->integer()->notNull(),
        ], $t);
        $this->createIndex('ux_payment_wallet_entry_idem', '{{%payment_wallet_entry}}', 'idem_key', true);
        $this->addForeignKey('fk_payment_wallet_entry_wallet', '{{%payment_wallet_entry}}', 'wallet_id', '{{%payment_wallet}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        foreach (['wallet_entry', 'wallet', 'event', 'subscription', 'order', 'discount', 'price', 'product', 'provider'] as $t) {
            $this->dropTable('{{%payment_' . $t . '}}');
        }
    }
}
