<?php

declare(strict_types=1);

namespace rapttor\payment\tests;

use PHPUnit\Framework\TestCase;
use rapttor\payment\dto\WebhookEvent;
use rapttor\payment\providers\InvalidSignatureException;
use rapttor\payment\providers\LemonSqueezyProvider;

final class LemonSqueezyTest extends TestCase
{
    private function p(): LemonSqueezyProvider
    {
        return new LemonSqueezyProvider(['apiKey' => 'k', 'storeId' => '1', 'variantId' => '2', 'webhookSecret' => 'ls_secret']);
    }

    public function testPaidOrderMapsToPaid(): void
    {
        $body = json_encode(['meta' => ['event_name' => 'order_created', 'custom_data' => ['order' => 'uuid-9']],
            'data' => ['id' => '77', 'attributes' => ['status' => 'paid', 'total' => 999, 'currency' => 'EUR']]]);
        $events = $this->p()->parseWebhook($body, ['x-signature' => hash_hmac('sha256', $body, 'ls_secret')]);
        $this->assertCount(1, $events);
        $this->assertSame(WebhookEvent::PAID, $events[0]->type);
        $this->assertSame('uuid-9', $events[0]->orderUuid);
        $this->assertSame('77', $events[0]->paymentId);
        $this->assertSame(999, $events[0]->amount);
    }

    public function testBadSignatureRejected(): void
    {
        $this->expectException(InvalidSignatureException::class);
        $this->p()->parseWebhook('{}', ['x-signature' => 'nope']);
    }

    public function testFirstSubscriptionPaymentIsNotARenewal(): void
    {
        $body = json_encode(['meta' => ['event_name' => 'subscription_payment_success'], 'data' => ['id' => '5', 'attributes' => ['billing_reason' => 'initial', 'subscription_id' => 3]]]);
        $this->assertSame([], $this->p()->parseWebhook($body, ['x-signature' => hash_hmac('sha256', $body, 'ls_secret')]));
    }
}
