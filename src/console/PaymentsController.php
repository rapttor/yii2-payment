<?php

declare(strict_types=1);

namespace rapttor\payment\console;

use rapttor\payment\models\Provider;
use rapttor\payment\Payments;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/** `php yii payments/status` shows every provider; `payments/enable <id>` / `payments/disable <id>` switch one. */
final class PaymentsController extends Controller
{
    public function actionStatus(): int
    {
        /** @var Payments $payments */
        $payments = Yii::$app->get('payments');
        foreach ($payments->providerIds() as $id) {
            $p = $payments->provider($id);
            $row = Provider::findOne($id);
            $this->stdout(sprintf("%-12s %-9s %-12s %-10s %s\n", $id, $row?->enabled ? 'enabled' : 'disabled', $p->isConfigured() ? 'configured' : 'NO KEYS', $row?->mode ?? '-', $p->isConfigured() ? '' : 'needs: ' . implode(', ', $p->requiredSettings())));
        }

        return ExitCode::OK;
    }

    /** `payments/sync stripe` pushes products and prices to the provider's catalog; --force re-creates every price. */
    public bool $force = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), $actionID === 'sync' ? ['force'] : []);
    }

    public function actionSync(string $id): int
    {
        foreach ((new \rapttor\payment\services\CatalogSync(Yii::$app->get('payments')))->run($id, $this->force) as $line) {
            $this->stdout($line . "\n");
        }

        return ExitCode::OK;
    }

    public function actionEnable(string $id, string $mode = 'test'): int
    {
        return $this->set($id, true, $mode);
    }

    public function actionDisable(string $id): int
    {
        return $this->set($id, false, null);
    }

    private function set(string $id, bool $on, ?string $mode): int
    {
        /** @var Payments $payments */
        $payments = Yii::$app->get('payments');
        if (!in_array($id, $payments->providerIds(), true)) {
            $this->stderr("Unknown provider '$id'.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }
        $row = Provider::findOne($id) ?? new Provider(['code' => $id]);
        $row->enabled = $on;
        if ($mode !== null) {
            $row->mode = $mode;
        }
        $row->save();
        $this->stdout(($on ? 'Enabled ' : 'Disabled ') . "$id\n");

        return ExitCode::OK;
    }
}
