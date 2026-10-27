<?php

declare(strict_types=1);

namespace rapttor\payment;

use rapttor\payment\models\Order;
use yii\base\Event;

/** Raised on the `payments` component after the database is updated. Grant features in your handler. */
final class PaymentEvent extends Event
{
    public ?Order $order = null;
    public ?int $amount = null;
}
