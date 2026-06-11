<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Expire pending bookings that haven't been paid within the timeout period
        $schedule->command('bookings:expire-pending')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Expire abandoned ecommerce orders, restoring stock and gift-card balance
        $schedule->command('orders:expire-pending')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Send inventory low stock alerts daily
        $schedule->command('inventory:send-alerts')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        // Sync booking payment status with order payment status (fallback for missed webhooks)
        $schedule->command('bookings:sync-payment-status')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
