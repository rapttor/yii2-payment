<?php

declare(strict_types=1);

namespace rapttor\payment;

/** Pure money math in minor units; no database, so it is unit-tested directly. */
final class Pricing
{
    /**
     * @return array{amount:int, discount:int} amount = what the buyer pays (never below 0), discount = what was taken off
     */
    public static function apply(int $amount, ?int $percent, ?int $amountOff = null): array
    {
        $off = 0;
        if ($percent !== null && $percent > 0) {
            $off = (int) round($amount * min($percent, 100) / 100);
        } elseif ($amountOff !== null && $amountOff > 0) {
            $off = $amountOff;
        }
        $off = min($off, $amount);

        return ['amount' => $amount - $off, 'discount' => $off];
    }

    /** Percentage saved by paying yearly instead of 12 x monthly, rounded to a whole percent. */
    public static function yearlySavingPercent(int $monthly, int $yearly): int
    {
        if ($monthly <= 0) {
            return 0;
        }

        return max(0, (int) round((1 - $yearly / ($monthly * 12)) * 100));
    }

    /** Yearly price that gives `$percent` off 12 x monthly. */
    public static function yearlyFromMonthly(int $monthly, int $percent): int
    {
        return (int) round($monthly * 12 * (100 - $percent) / 100);
    }

    public static function format(int $minor, string $currency): string
    {
        $zero = in_array(strtoupper($currency), ['JPY', 'KRW', 'VND', 'CLP', 'HUF', 'TWD'], true);

        return ($zero ? (string) $minor : number_format($minor / 100, 2, '.', '')) . ' ' . strtoupper($currency);
    }
}
