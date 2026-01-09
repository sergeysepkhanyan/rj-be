<?php

namespace App\Support;

class VatCalculator
{
    public static function rate(): float
    {
        return (float) config('vat.rate', 0.05);
    }

    public static function breakdown(float $basePrice, bool $vatEnabled, ?float $rate = null): array
    {
        $rate = $rate ?? self::rate();

        $vatAmount = $vatEnabled ? round($basePrice * $rate, 2) : 0.0;
        $finalPrice = round($basePrice + $vatAmount, 2);

        return [
            'base_price' => round($basePrice, 2),
            'vat_enabled' => $vatEnabled,
            'vat_rate' => $rate,
            'vat_amount' => $vatAmount,
            'final_price' => $finalPrice,
        ];
    }
}
