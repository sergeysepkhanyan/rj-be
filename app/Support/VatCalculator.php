<?php

namespace App\Support;

class VatCalculator
{
    public static function rate(): float
    {
        return (float) config('vat.rate', 0.05);
    }

    /**
     * Calculate VAT breakdown for a price.
     * VAT is always applied (5% by default).
     * The $vatEnabled parameter is kept for backwards compatibility but ignored.
     */
    public static function breakdown(float $basePrice, bool $vatEnabled = true, ?float $rate = null): array
    {
        $rate = $rate ?? self::rate();

        // VAT is always applied
        $vatAmount = round($basePrice * $rate, 2);
        $finalPrice = round($basePrice + $vatAmount, 2);

        return [
            'base_price' => round($basePrice, 2),
            'vat_enabled' => true, // Always true now
            'vat_rate' => $rate,
            'vat_amount' => $vatAmount,
            'final_price' => $finalPrice,
        ];
    }
}
