<?php

declare(strict_types=1);

namespace rapttor\payment\services;

use rapttor\payment\dto\WebhookEvent;
use rapttor\payment\models\Discount;
use rapttor\payment\models\Event;
use rapttor\payment\models\Order;
use rapttor\payment\models\Subscription;
use rapttor\payment\Payments;
use rapttor\payment\PaymentEvent;
use Yii;
use yii\db\IntegrityException;

/** Applies normalised provider events to orders, subscriptions and wallets. Idempotent per (provider, event id). */
final class Processor
{
    public function __construct(private readonly Payments $payments)
    {
    }

    /** @return string short result for the webhook log */
    public function handle(WebhookEvent $e): string
    {
        $after = [];
        $result = Yii::$app->db->transaction(function () use ($e, &$after): string {
            $log = new Event(['provider' => $e->provider, 'event_id' => $e->eventId, 'type' => $e->type, 'order_uuid' => $e->orderUuid, 'payload' => json_encode($e->raw)]);
            try {
                $log->save(false);
            } catch (IntegrityException) {
                return 'duplicate';
            }
            $order = $this->findOrder($e);
            $result = match ($e->type) {
                WebhookEvent::PAID => $this->paid($e, $order, $after),
                WebhookEvent::FAILED => $this->failed($order),
                WebhookEvent::REFUNDED => $this->refunded($e, $order, $after),
                WebhookEvent::SUBSCRIPTION_STARTED => $this->subStarted($e, $order),
                WebhookEvent::SUBSCRIPTION_RENEWED => $this->subRenewed($e, $order, $after),
                WebhookEvent::SUBSCRIPTION_CANCELED => $this->subCanceled($e),
                default => 'ignored',
            };
            $log->updateAttributes(['result' => $result]);

            return $result;
        });
        foreach ($after as [$name, $order, $amount]) {
            $this->payments->trigger($name, new PaymentEvent(['order' => $order, 'amount' => $amount]));
        }

        return $result;
    }

    private function findOrder(WebhookEvent $e): ?Order
    {
        if ($e->orderUuid) {
            return Order::findOne(['uuid' => $e->orderUuid, 'provider' => $e->provider]);
        }
        if ($e->paymentId) {
            return Order::findOne(['provider' => $e->provider, 'payment_id' => $e->paymentId]);
        }

        return null;
    }

    private function paid(WebhookEvent $e, ?Order $order, array &$after): string
    {
        if ($order === null) {
            return 'unknown order';
        }
        if ($order->status === Order::PAID) {
            return 'already paid';
        }
        if ($e->currency !== null && strtoupper($e->currency) !== $order->currency) {
            return 'currency mismatch, not fulfilled';
        }
        $order->status = Order::PAID;
        $order->payment_id = $e->paymentId ?? $order->payment_id;
        $this->fulfil($order, $e->eventId);
        $order->fulfilled_at = time();
        $order->save(false);
        if ($order->discount_id) {
            Discount::updateAllCounters(['redemptions' => 1], ['id' => $order->discount_id]);
        }
        $after[] = [Payments::EVENT_ORDER_PAID, $order, $e->amount];

        return 'paid';
    }

    private function failed(?Order $order): string
    {
        if ($order !== null && $order->status === Order::PENDING) {
            $order->updateAttributes(['status' => Order::FAILED, 'updated_at' => time()]);
        }

        return $order ? 'failed' : 'unknown order';
    }

    private function refunded(WebhookEvent $e, ?Order $order, array &$after): string
    {
        if ($order === null) {
            return 'unknown order';
        }
        // Partial refunds keep the order paid; only a full refund claws back wallet credit.
        if ($e->amount !== null && $e->amount < (int) $order->amount) {
            $after[] = [Payments::EVENT_ORDER_REFUNDED, $order, $e->amount];

            return 'partial refund noted';
        }
        $order->updateAttributes(['status' => Order::REFUNDED, 'updated_at' => time()]);
        $credit = $this->walletCredit($order);
        if ($credit !== null && $order->user_id) {
            $this->payments->wallet()->debit((int) $order->user_id, $credit[0], $credit[1], 'refund', 'refund:' . $order->uuid, (int) $order->id, true);
        }
        $after[] = [Payments::EVENT_ORDER_REFUNDED, $order, $e->amount];

        return 'refunded';
    }

    private function subStarted(WebhookEvent $e, ?Order $order): string
    {
        if ($order === null || !$e->subscriptionId) {
            return 'unknown order';
        }
        $sub = Subscription::findOne(['provider' => $e->provider, 'external_id' => $e->subscriptionId]) ?? new Subscription([
            'provider' => $e->provider, 'external_id' => $e->subscriptionId, 'user_id' => $order->user_id,
            'product_id' => $order->product_id, 'price_id' => $order->price_id, 'status' => 'active',
        ]);
        $sub->status = 'active';
        $sub->save(false);
        $order->updateAttributes(['subscription_id' => $sub->id]);

        return 'subscription started';
    }

    private function subRenewed(WebhookEvent $e, ?Order $order, array &$after): string
    {
        $sub = $e->subscriptionId ? Subscription::findOne(['provider' => $e->provider, 'external_id' => $e->subscriptionId]) : null;
        if ($sub === null) {
            return 'unknown subscription';
        }
        $base = $order ?? Order::findOne(['subscription_id' => $sub->id]);
        if ($base === null) {
            return 'unknown order';
        }
        $credit = $this->walletCredit($base);
        if ($credit !== null && $sub->user_id) {
            $this->payments->wallet()->credit((int) $sub->user_id, $credit[0], $credit[1], 'renewal', 'renewal:' . $e->provider . ':' . $e->eventId, (int) $base->id);
        }
        $sub->updateAttributes(['status' => 'active', 'updated_at' => time()]);
        $after[] = [Payments::EVENT_SUBSCRIPTION_RENEWED, $base, $e->amount];

        return 'renewed';
    }

    private function subCanceled(WebhookEvent $e): string
    {
        $sub = $e->subscriptionId ? Subscription::findOne(['provider' => $e->provider, 'external_id' => $e->subscriptionId]) : null;
        if ($sub === null) {
            return 'unknown subscription';
        }
        $sub->updateAttributes(['status' => 'canceled', 'canceled_at' => time(), 'updated_at' => time()]);

        return 'canceled';
    }

    private function fulfil(Order $order, string $eventId): void
    {
        $credit = $this->walletCredit($order);
        if ($credit !== null && $order->user_id) {
            $this->payments->wallet()->credit((int) $order->user_id, $credit[0], $credit[1], 'purchase', 'order:' . $order->uuid, (int) $order->id);
        }
    }

    /** @return array{0:string,1:int}|null [asset, amount] to put in the buyer's wallet for this order */
    private function walletCredit(Order $order): ?array
    {
        $product = $order->product;
        $grants = $product->decode('grants');
        if (isset($grants['coins'])) {
            return [strtoupper((string) ($grants['asset'] ?? 'COIN')), (int) $grants['coins']];
        }
        if ($product->type === 'topup') {
            return [strtoupper($order->currency), (int) $order->price->amount];
        }

        return null;
    }
}
