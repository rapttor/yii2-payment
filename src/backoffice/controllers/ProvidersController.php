<?php

declare(strict_types=1);

namespace rapttor\payment\backoffice\controllers;

use rapttor\payment\models\Provider;
use Yii;

/** Enable or disable each provider, set test/live, order, and restrict by country or currency. */
final class ProvidersController extends BaseController
{
    public function actionIndex(): string
    {
        $payments = $this->payments();
        $rows = [];
        foreach ($payments->providerIds() as $id) {
            $rows[$id] = Provider::findOne($id) ?? new Provider(['code' => $id, 'enabled' => 0, 'mode' => 'test', 'sort' => 100]);
        }

        return $this->render('@rapttor/payment/backoffice/views/providers/index', [
            'payments' => $payments,
            'rows' => $rows,
            'webhookUrl' => static fn (string $id): string => $payments->webhookUrl($id),
        ]);
    }

    public function actionSync(string $id): \yii\web\Response
    {
        try {
            $log = (new \rapttor\payment\services\CatalogSync($this->payments()))->run($id, (bool) Yii::$app->request->post('force'));
            Yii::$app->session->setFlash('success', "$id catalog synced: " . implode('; ', $log));
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    public function actionSave(string $id): \yii\web\Response
    {
        if (!in_array($id, $this->payments()->providerIds(), true)) {
            throw new \yii\web\NotFoundHttpException();
        }
        $row = Provider::findOne($id) ?? new Provider(['code' => $id]);
        $post = Yii::$app->request->post('Provider', []);
        $row->enabled = !empty($post['enabled']);
        $row->mode = $post['mode'] ?? 'test';
        $row->sort = (int) ($post['sort'] ?? 100);
        $row->countries = trim((string) ($post['countries'] ?? '')) ?: null;
        $row->currencies = trim((string) ($post['currencies'] ?? '')) ?: null;
        if ($row->enabled && !$this->payments()->provider($id)->isConfigured()) {
            Yii::$app->session->setFlash('error', "$id has no keys yet; it was saved as disabled.");
            $row->enabled = false;
        }
        if ($row->save()) {
            Yii::$app->session->setFlash('success', "$id saved.");
        } else {
            Yii::$app->session->setFlash('error', json_encode($row->errors));
        }

        return $this->redirect(['index']);
    }
}
