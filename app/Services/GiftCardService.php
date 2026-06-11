<?php

namespace App\Services;

use App\Models\GiftCardUsage;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class GiftCardService
{
    /**
     * Restore any gift-card balance that was spent on this order (or its
     * bookings) when the order is cancelled, refunded, or returned. Idempotent
     * via reversed_at, so it is safe to call from multiple lifecycle hooks.
     */
    public function reverseForOrder(Order $order): void
    {
        $usages = collect();

        $usages = $usages->merge(
            GiftCardUsage::where('used_for_type', 'product')
                ->where('used_for_id', $order->id)
                ->whereNull('reversed_at')
                ->get()
        );

        if ($order->getTypeValue() === 'booking') {
            $bookingIds = $order->getAllBookings()->pluck('id')->all();
            if (!empty($bookingIds)) {
                $usages = $usages->merge(
                    GiftCardUsage::where('used_for_type', 'booking')
                        ->whereIn('used_for_id', $bookingIds)
                        ->whereNull('reversed_at')
                        ->get()
                );
            }
        }

        $this->reverseUsages($usages);
    }

    /**
     * @param \Illuminate\Support\Collection<int, GiftCardUsage> $usages
     */
    protected function reverseUsages($usages): void
    {
        foreach ($usages as $usage) {
            DB::transaction(function () use ($usage) {
                $usage = GiftCardUsage::whereKey($usage->id)
                    ->whereNull('reversed_at')
                    ->lockForUpdate()
                    ->first();

                if (!$usage) {
                    return;
                }

                $purchase = $usage->purchase()->lockForUpdate()->first();
                if ($purchase) {
                    $newBalance = (float) $purchase->balance + (float) $usage->amount_used;
                    $purchase->update([
                        'balance' => $newBalance,
                        ...($purchase->status === 'used' && $newBalance > 0 ? ['status' => 'active'] : []),
                    ]);
                }

                $usage->update(['reversed_at' => now()]);
            });
        }
    }
}
