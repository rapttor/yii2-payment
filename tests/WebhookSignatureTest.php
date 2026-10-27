<?php

declare(strict_types=1);

namespace rapttor\payment\tests;

use PHPUnit\Framework\TestCase;
use rapttor\payment\dto\WebhookEvent;
use rapttor\payment\providers\InvalidSignatureException;
use rapttor\payment\providers\PaddleProvider;
use rapttor\payment\providers\StripeProvider;

final class WebhookSignatureTest extends TestCase
{
    private function stripe(): StripeProvider
    {
        return new StripeProvider(['secretKey' => 'sk', 'webhookSecret' => 'whsec_test']);
    }

    public function testStripeValidSignatureYieldsPaidEvent(): void
    {
        $body = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'amount_total' => 449, 'currency' => 'eur', 'metadata' => ['order' => 'uuid-1'],
        ]]]);
        $t = time();
        $sig = hash_hmac('sha256', $t . '.' . $body, 'whsec_test');
        $events = $this->stripe()->parseWebhook($body, ['stripe-signature' => "t=$t,v1=$sig"]);
        $this->assertCount(1, $events);
        $this->assertSame(WebhookEvent::PAID, $events[0]->type);
        $this->assertSame('uuid-1', $events[0]->orderUuid);
        $this->assertSame('EUR', $events[0]->currency);
        $this->assertSame(449, $events[0]->amount);
    }

    public function testStripeRejectsWrongSignature(): void
    {
        $this->expectException(InvalidSignatureException::class);
        $this->stripe()->parseWebhook('{}', ['stripe-signature' => 't=' . time() . ',v1=deadbeef']);
    }

    public function testStripeRejectsOldTimestamp(): void
    {
        $body = '{}';
        $t = time() - 3600;
        $this->expectException(InvalidSignatureException::class);
        $this->stripe()->parseWebhook($body, ['stripe-signature' => "t=$t,v1=" . hash_hmac('sha256', $t . '.' . $body, 'whsec_test')]);
    }

    public function testStripeRejectsMissingHeader(): void
    {
        $this->expectException(InvalidSignatureException::class);
        $this->stripe()->parseWebhook('{}', []);
    }

    public function testPaddleValidSignatureYieldsPaidAndSubscriptionStarted(): void
    {
        $p = new PaddleProvider(['apiKey' => 'k', 'webhookSecret' => 'pdl_test']);
        $body = json_encode(['event_id' => 'evt_p1', 'event_type' => 'transaction.completed', 'data' => [
            'id' => 'txn_1', 'origin' => 'web', 'subscription_id' => 'sub_1', 'currency_code' => 'EUR',
            'custom_data' => ['order' => 'uuid-2'], 'details' => ['totals' => ['grand_total' => '900']],
        ]]);
        $ts = time();
        $h1 = hash_hmac('sha256', $ts . ':' . $body, 'pdl_test');
        $events = $p->parseWebhook($body, ['paddle-signature' => "ts=$ts;h1=$h1"]);
        $this->assertSame([WebhookEvent::PAID, WebhookEvent::SUBSCRIPTION_STARTED], array_map(static fn ($e) => $e->type, $events));
        $this->assertNotSame($events[0]->eventId, $events[1]->eventId);
        $this->assertSame(900, $events[0]->amount);
    }

    public function testPaddleRejectsTamperedBody(): void
    {
        $p = new PaddleProvider(['apiKey' => 'k', 'webhookSecret' => 'pdl_test']);
        $ts = time();
        $h1 = hash_hmac('sha256', $ts . ':{"a":1}', 'pdl_test');
        $this->expectException(InvalidSignatureException::class);
        $p->parseWebhook('{"a":2}', ['paddle-signature' => "ts=$ts;h1=$h1"]);
    }
}
