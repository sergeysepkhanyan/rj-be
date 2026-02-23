<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncBookingPaymentStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:sync-payment-status
                            {--dry-run : Preview changes without applying them}
                            {--limit=100 : Maximum number of bookings to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync booking payment status with associated order payment status (fallback for missed webhooks)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made.');
        }

        $this->info('Checking for booking/order payment status mismatches...');

        // Find bookings where payment_status doesn't match their associated order
        // Only check confirmed bookings with online payment that have an order
        $bookings = Booking::query()
            ->where('status', 'confirmed')
            ->where('payment_status', 'unpaid')
            ->where('payment_mode', 'pay_online')
            ->whereHas('order', function ($query) {
                $query->where('payment_status', 'paid');
            })
            ->with('order')
            ->limit($limit)
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No payment status mismatches found.');
            return self::SUCCESS;
        }

        $this->info("Found {$bookings->count()} booking(s) with potential payment status mismatch.");

        $updatedCount = 0;
        $errors = [];

        foreach ($bookings as $booking) {
            try {
                $order = $booking->order;

                if (!$order) {
                    continue;
                }

                // Verify the order is actually paid
                if ($order->payment_status !== 'paid') {
                    continue;
                }

                $this->line("Booking #{$booking->id} (Order #{$order->id}): unpaid -> paid");

                if (!$dryRun) {
                    DB::transaction(function () use ($booking) {
                        $booking->update([
                            'payment_status' => 'paid',
                        ]);
                    });

                    Log::info('SyncBookingPaymentStatus: Updated booking payment status', [
                        'booking_id' => $booking->id,
                        'order_id' => $order->id,
                        'old_status' => 'unpaid',
                        'new_status' => 'paid',
                    ]);
                }

                $updatedCount++;
            } catch (\Exception $e) {
                $errors[] = "Booking #{$booking->id}: {$e->getMessage()}";
                Log::error('SyncBookingPaymentStatus: Failed to update booking', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also check for the reverse: orders that are cancelled/refunded
        $cancelledBookings = Booking::query()
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('payment_status', 'paid')
            ->whereHas('order', function ($query) {
                $query->whereIn('payment_status', ['cancelled', 'refunded']);
            })
            ->with('order')
            ->limit($limit)
            ->get();

        foreach ($cancelledBookings as $booking) {
            try {
                $order = $booking->order;

                if (!$order) {
                    continue;
                }

                $newStatus = $order->payment_status;
                $this->line("Booking #{$booking->id} (Order #{$order->id}): paid -> {$newStatus}");

                if (!$dryRun) {
                    DB::transaction(function () use ($booking, $newStatus) {
                        $booking->update([
                            'payment_status' => $newStatus,
                            'status' => $newStatus === 'cancelled' ? 'cancelled' : $booking->status,
                        ]);
                    });

                    Log::info('SyncBookingPaymentStatus: Updated booking payment status (cancelled/refunded)', [
                        'booking_id' => $booking->id,
                        'order_id' => $order->id,
                        'old_status' => 'paid',
                        'new_status' => $newStatus,
                    ]);
                }

                $updatedCount++;
            } catch (\Exception $e) {
                $errors[] = "Booking #{$booking->id}: {$e->getMessage()}";
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("Dry run complete. {$updatedCount} booking(s) would be updated.");
        } else {
            $this->info("Sync complete. {$updatedCount} booking(s) updated.");
        }

        if (!empty($errors)) {
            $this->error("Errors encountered:");
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }

        return self::SUCCESS;
    }
}
