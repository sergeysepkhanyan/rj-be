<?php

namespace App\Services;

use App\Mail\GiftCardBalanceRestoredMail;
use App\Models\GiftCardUsage;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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

        if ($order->getTypeValue() === 'service_package') {
            $usages = $usages->merge(
                GiftCardUsage::where('used_for_type', 'service_package')
                    ->where('used_for_id', $order->id)
                    ->whereNull('reversed_at')
                    ->get()
            );
        }

        $this->reverseUsages($usages);
    }

    /**
     * @param \Illuminate\Support\Collection<int, GiftCardUsage> $usages
     */
    protected function reverseUsages($usages): void
    {
        foreach ($usages as $usage) {
            $restored = DB::transaction(function () use ($usage) {
                $usage = GiftCardUsage::whereKey($usage->id)
                    ->whereNull('reversed_at')
                    ->lockForUpdate()
                    ->first();

                if (!$usage) {
                    return null;
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

                return $purchase ? ['purchase' => $purchase, 'amount' => (float) $usage->amount_used] : null;
            });

            // Notify the card holder that their balance was credited back (after commit).
            if ($restored && $restored['purchase']->notificationEmail()) {
                Mail::to($restored['purchase']->notificationEmail())
                    ->queue(new GiftCardBalanceRestoredMail($restored['purchase'], $restored['amount']));
            }
        }
    }
}
