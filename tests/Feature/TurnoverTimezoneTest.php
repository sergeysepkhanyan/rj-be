<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\ReportsService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Turnover must be bucketed by the salon's local day, not UTC. A payment just
 * after local midnight is stored in the previous UTC day, and must still count
 * for the local day it was actually taken.
 */
class TurnoverTimezoneTest extends TestCase
{
    public function test_payment_after_local_midnight_counts_on_the_local_day(): void
    {
        config(['app.business_timezone' => 'Asia/Dubai']); // UTC+4, no DST

        // 2026-07-08 00:08 Dubai == 2026-07-07 20:08 UTC (how it is stored).
        Order::create([
            'type' => 'booking',
            'status' => 'paid',
            'amount' => 294,
            'currency' => 'AED',
            'reference' => 'TZ-' . uniqid(),
            'paid_at' => Carbon::parse('2026-07-07 20:08:00', 'UTC'),
        ]);

        $svc = app(ReportsService::class);

        $total = fn (string $date) => (float) (
            $svc->todaysTurnover($date)->firstWhere('currency', 'AED')['total'] ?? 0
        );

        // Counted for the local day it was taken (08), not the UTC day (07).
        $this->assertEqualsWithDelta(294.0, $total('2026-07-08'), 0.001, 'counts on local day 08');
        $this->assertEqualsWithDelta(0.0, $total('2026-07-07'), 0.001, 'not on the previous local day');
    }

    public function test_midday_payment_counts_on_its_own_day(): void
    {
        config(['app.business_timezone' => 'Asia/Dubai']);

        Order::create([
            'type' => 'booking', 'status' => 'paid', 'amount' => 100, 'currency' => 'AED',
            'reference' => 'TZ-' . uniqid(),
            'paid_at' => Carbon::parse('2026-07-08 10:00:00', 'UTC'), // 14:00 GST, same day
        ]);

        $total = fn (string $date) => (float) (
            app(ReportsService::class)->todaysTurnover($date)->firstWhere('currency', 'AED')['total'] ?? 0
        );

        $this->assertEqualsWithDelta(100.0, $total('2026-07-08'), 0.001);
    }
}
