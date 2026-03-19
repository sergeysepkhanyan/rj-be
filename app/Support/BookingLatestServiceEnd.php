<?php

namespace App\Support;

use App\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Latest end instant across booking line items (UTC), for scheduling follow-ups.
 */
final class BookingLatestServiceEnd
{
    public static function latestEndUtc(Booking $booking): ?CarbonInterface
    {
        $booking->loadMissing('services');

        $latest = null;

        foreach ($booking->services as $svc) {
            $tz = $svc->timezone ?: $booking->timezone ?: 'UTC';
            $dateStr = self::formatDate($svc->date);
            if ($dateStr === '') {
                continue;
            }

            $timeStr = trim((string) $svc->end_time);
            if ($timeStr === '') {
                continue;
            }
            if (strlen($timeStr) === 5) {
                $timeStr .= ':00';
            }

            try {
                $endLocal = Carbon::createFromFormat('Y-m-d H:i:s', "{$dateStr} {$timeStr}", $tz);
            } catch (\Throwable) {
                continue;
            }

            $utc = $endLocal->copy()->utc();
            if ($latest === null || $utc->gt($latest)) {
                $latest = $utc;
            }
        }

        if ($latest !== null) {
            return $latest;
        }

        return self::fallbackFromBookingRowUtc($booking);
    }

    private static function formatDate(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return is_string($date) ? substr(trim($date), 0, 10) : '';
    }

    private static function fallbackFromBookingRowUtc(Booking $booking): ?CarbonInterface
    {
        $dateStr = self::formatDate($booking->date);
        if ($dateStr === '') {
            return null;
        }

        $tz = $booking->timezone ?: 'UTC';
        $timeStr = trim((string) $booking->end_time);
        if ($timeStr === '') {
            return null;
        }
        if (strlen($timeStr) === 5) {
            $timeStr .= ':00';
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', "{$dateStr} {$timeStr}", $tz)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
