<?php

declare(strict_types=1);

namespace rapttor\payment;

use yii\base\BootstrapInterface;
use yii\console\Application as ConsoleApplication;
use yii\web\Application as WebApplication;

/**
 * Web: maps /payments/webhook/<provider>, /payments/return/<provider>, /payments/checkout. Console: adds the package migrations.
 * Does nothing until a `payments` component is configured.
 */
final class Bootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        if (!$app->has('payments')) {
            return;
        }
        /** @var Payments $payments */
        $payments = $app->get('payments');
        $p = $payments->routePrefix;
        if ($app instanceof WebApplication) {
            $app->controllerMap[$p] ??= ['class' => controllers\PaymentsController::class];
            $app->getUrlManager()->addRules([
                "$p/webhook/<provider:[a-z0-9_-]+>" => "$p/webhook",
                "$p/return/<provider:[a-z0-9_-]+>" => "$p/return",
                "$p/checkout" => "$p/checkout",
                "$p/sandbox/pay" => "$p/sandbox-pay",
            ], false);
        } elseif ($app instanceof ConsoleApplication) {
            $app->controllerMap['payments'] ??= ['class' => console\PaymentsController::class];
            $migrate = $app->controllerMap['migrate'] ?? ['class' => \yii\console\controllers\MigrateController::class];
            if (is_array($migrate)) {
                $migrate['migrationNamespaces'] = array_values(array_unique([...($migrate['migrationNamespaces'] ?? []), 'rapttor\payment\migrations']));
                $app->controllerMap['migrate'] = $migrate;
            }
        }
    }
}
