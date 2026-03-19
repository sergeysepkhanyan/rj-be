<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:expire-pending')->everyFiveMinutes();

Schedule::command('bookings:send-post-service-followup')->everyFifteenMinutes();

// Send inventory alerts daily at 9 AM
Schedule::command('inventory:send-alerts')->dailyAt('09:00');
