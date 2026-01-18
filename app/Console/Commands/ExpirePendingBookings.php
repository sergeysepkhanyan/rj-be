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
        $expired = Booking::query()
            ->where('status', 'pending_payment')
            ->where('payment_mode', 'pay_now')
            ->where(function ($q) use ($now) {
                $q->whereNotNull('expires_at')
                    ->where('expires_at', '<=', $now)
                    ->orWhereNull('expires_at')
                    ->where('created_at', '<=', $now->copy()->subMinutes((int) config('payment.booking_hold_minutes', 10)));
            })
            ->get();

        $count = 0;

        foreach ($expired as $booking) {
            if ($booking->payment_status === 'paid') {
                continue;
            }

            $booking->update([
                'status' => 'cancelled',
                'payment_status' => 'unpaid',
                'cancelled_at' => $now,
                'cancel_reason' => 'payment_timeout',
            ]);

            $order = $booking->order;
            if ($order) {
                $this->orderService->cancel($order, ['reason' => 'payment_timeout']);
                $payment = $order->latestPayment;
                if ($payment && $payment->status !== 'paid') {
                    $this->paymentRepository->update($payment, [
                        'status' => 'expired',
                        'expired_at' => $now,
                    ]);
                }
            }

            $count++;
        }

        $this->info("Expired {$count} pending bookings.");

        return Command::SUCCESS;
    }
}
