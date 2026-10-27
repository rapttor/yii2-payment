<?php

declare(strict_types=1);

namespace rapttor\payment\tests;

use PHPUnit\Framework\TestCase;
use rapttor\payment\Pricing;

final class PricingTest extends TestCase
{
    public function testPercentDiscount(): void
    {
        $this->assertSame(['amount' => 449, 'discount' => 50], Pricing::apply(499, 10));
    }

    public function testFixedDiscountNeverGoesBelowZero(): void
    {
        $this->assertSame(['amount' => 0, 'discount' => 300], Pricing::apply(300, null, 1000));
    }

    public function testNoDiscount(): void
    {
        $this->assertSame(['amount' => 900, 'discount' => 0], Pricing::apply(900, null));
    }

    public function testYearlyDiscountRoundTrip(): void
    {
        $yearly = Pricing::yearlyFromMonthly(900, 20);
        $this->assertSame(8640, $yearly);
        $this->assertSame(20, Pricing::yearlySavingPercent(900, $yearly));
    }

    public function testFormat(): void
    {
        $this->assertSame('4.99 EUR', Pricing::format(499, 'eur'));
        $this->assertSame('500 JPY', Pricing::format(500, 'JPY'));
    }
}
