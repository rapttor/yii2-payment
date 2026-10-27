<?php

declare(strict_types=1);

namespace rapttor\payment\backoffice\controllers;

use rapttor\payment\dto\WebhookEvent;
use rapttor\payment\models\Event;
use rapttor\payment\models\Order;
use rapttor\payment\Pricing;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;

/** Orders, the webhook log, and refunds. */
final class OrdersController extends BaseController
{
    public function actionIndex(): string
    {
        return $this->render('@rapttor/payment/backoffice/views/crud/index', [
            'title' => 'Orders',
            'columns' => [
                'id', 'uuid', 'user_id', 'provider', 'status',
                ['label' => 'Amount', 'value' => static fn (Order $o) => Pricing::format((int) $o->amount, $o->currency)],
                ['label' => 'Product', 'value' => static fn (Order $o) => $o->product->name],
                'created_at:datetime',
                ['label' => '', 'format' => 'raw', 'value' => static fn (Order $o) => $o->status === Order::PAID
                    ? \yii\helpers\Html::beginForm(['refund', 'id' => $o->id]) . \yii\helpers\Html::submitButton('Refund', ['class' => 'btn btn-sm btn-outline-danger', 'data-confirm' => 'Refund this order in full?']) . \yii\helpers\Html::endForm()
                    : ''],
            ],
            'provider' => new ActiveDataProvider(['query' => Order::find()->orderBy(['id' => SORT_DESC]), 'pagination' => ['pageSize' => 25]]),
        ]);
    }

    public function actionEvents(): string
    {
        return $this->render('@rapttor/payment/backoffice/views/crud/index', [
            'title' => 'Webhook log',
            'columns' => ['id', 'provider', 'type', 'order_uuid', 'result', 'created_at:datetime'],
            'provider' => new ActiveDataProvider(['query' => Event::find()->orderBy(['id' => SORT_DESC]), 'pagination' => ['pageSize' => 50]]),
        ]);
    }

    public function actionRefund(int $id): \yii\web\Response
    {
        $order = Order::findOne($id) ?? throw new NotFoundHttpException();
        if ($order->status !== Order::PAID || !$order->payment_id) {
            Yii::$app->session->setFlash('error', 'Only paid orders with a provider payment id can be refunded.');

            return $this->redirect(['index']);
        }
        try {
            $this->payments()->provider($order->provider)->refund($order->payment_id);
            // Real providers confirm by webhook; the fake one has none, so apply it here.
            if ($order->provider === 'sandbox') {
                $this->payments()->processor()->handle(new WebhookEvent('sandbox', 'sandbox:' . $order->uuid . ':refund', WebhookEvent::REFUNDED, $order->uuid, $order->payment_id, null, (int) $order->amount, $order->currency));
            }
            Yii::$app->session->setFlash('success', 'Refund requested.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['index']);
    }
}
