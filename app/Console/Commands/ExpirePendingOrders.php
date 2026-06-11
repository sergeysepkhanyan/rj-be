<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Cancel abandoned ecommerce orders stuck in pending payment, restoring stock and gift-card balance.';

    public function __construct(
        protected OrderService $orderService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $holdMinutes = (int) config('payment.order_hold_minutes', 60);
        $threshold = now()->subMinutes($holdMinutes);

        $expired = Order::query()
            ->where('type', OrderType::Ecommerce->value)
            ->where('status', OrderStatus::PendingPayment->value)
            ->where('created_at', '<=', $threshold)
            ->with('latestPayment')
            ->get();

        $count = 0;
        foreach ($expired as $order) {
            // Skip if a payment actually succeeded but the status lagged.
            if ($order->latestPayment && $order->latestPayment->status === 'paid') {
                continue;
            }

            try {
                $this->orderService->cancel($order, ['reason' => 'payment_timeout']);
                $count++;
            } catch (\Throwable $e) {
                $this->error("Failed to cancel order {$order->id}: {$e->getMessage()}");
            }
        }

        $this->info("Expired {$count} pending order(s).");

        return self::SUCCESS;
    }
}
