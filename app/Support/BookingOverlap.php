<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Detect overlapping booking intervals on the same calendar date (local timezone).
 */
final class BookingOverlap
{
    public static function intervalsOverlap(
        string $dateA,
        string $startA,
        string $endA,
        string $dateB,
        string $startB,
        string $endB,
        string $timezone
    ): bool {
        if ($dateA !== $dateB) {
            return false;
        }

        $norm = static fn (string $t): string => strlen($t) === 5 ? $t.':00' : $t;

        try {
            $aStart = Carbon::createFromFormat('Y-m-d H:i:s', $dateA.' '.$norm($startA), $timezone);
            $aEnd = Carbon::createFromFormat('Y-m-d H:i:s', $dateA.' '.$norm($endA), $timezone);
            $bStart = Carbon::createFromFormat('Y-m-d H:i:s', $dateB.' '.$norm($startB), $timezone);
            $bEnd = Carbon::createFromFormat('Y-m-d H:i:s', $dateB.' '.$norm($endB), $timezone);
        } catch (\Throwable) {
            return false;
        }

        return $aStart->lt($bEnd) && $aEnd->gt($bStart);
    }
}
