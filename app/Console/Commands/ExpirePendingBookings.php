<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending';
    protected $description = 'Cancel pending pay-now bookings after hold time';

    public function __construct(
        protected OrderService $orderService,
        protected PaymentRepositoryInterface $paymentRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $holdMinutes = (int) config('payment.booking_hold_minutes', 10);
        $createdAtThreshold = $now->copy()->subMinutes($holdMinutes);

        // Optimized query: fix OR logic and add eager loading
        $expired = Booking::query()
            ->where('status', 'pending_payment')
            ->where('payment_mode', 'pay_now')
            ->where('payment_status', '!=', 'paid') // Skip already paid bookings
            ->where(function ($q) use ($now, $createdAtThreshold) {
                // Bookings with expires_at set and expired
                $q->whereNotNull('expires_at')
                    ->where('expires_at', '<=', $now)
                    // OR bookings without expires_at but created before threshold
                    ->orWhere(function ($subQ) use ($createdAtThreshold) {
                        $subQ->whereNull('expires_at')
                            ->where('created_at', '<=', $createdAtThreshold);
                    });
            })
            ->with(['order.latestPayment']) // Eager load to avoid N+1 queries
            ->get();

        $count = 0;

        foreach ($expired as $booking) {
            try {
                // Double-check payment status (in case changed between query and processing)
                if ($booking->payment_status === 'paid') {
                    continue;
                }

                // Update booking
                $booking->update([
                    'status' => 'cancelled',
                    'payment_status' => 'unpaid',
                    'cancelled_at' => $now,
                    'cancel_reason' => 'payment_timeout',
                ]);

                // Cancel order and update payment if exists
                if ($booking->order) {
                    $this->orderService->cancel($booking->order, ['reason' => 'payment_timeout']);
                    
                    if ($booking->order->latestPayment && $booking->order->latestPayment->status !== 'paid') {
                        $this->paymentRepository->update($booking->order->latestPayment, [
                            'status' => 'expired',
                            'expired_at' => $now,
                        ]);
                    }
                }

                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to cancel booking {$booking->id}: {$e->getMessage()}");
                // Continue with next booking
            }
        }

        if ($count > 0) {
            $this->info("Expired {$count} pending bookings.");
        }

        return Command::SUCCESS;
    }
}
