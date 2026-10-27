<?php

declare(strict_types=1);

namespace rapttor\payment;

/** What a provider can do; the backoffice and the checkout use this to decide what to offer. */
final class Capability
{
    public const ONE_TIME = 'one_time';
    public const SUBSCRIPTION = 'subscription';
    public const REFUND = 'refund';
    /** Provider keeps its own product/price catalog that we can push to (Stripe, Paddle, Lemon Squeezy). */
    public const CATALOG_SYNC = 'catalog_sync';
    /** Provider hosts a customer portal (manage card, cancel). */
    public const PORTAL = 'portal';
}
