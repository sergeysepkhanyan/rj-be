<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ServicePackage;
use App\Models\ServicePackageItem;
use App\Models\ServicePackagePurchase;
use App\Models\ServicePackageUsage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServicePackageService
{
    /**
     * Get active packages with items and their sub-service names (public listing).
     */
    public function getPublicPackages(): Collection
    {
        return ServicePackage::active()
            ->with(['items.subService'])
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();
    }

    /**
     * Get user's active, non-expired purchases with items, usage counts, and remaining visits.
     */
    public function getUserActivePackages(int $userId): Collection
    {
        $purchases = ServicePackagePurchase::where('user_id', $userId)
            ->active()
            ->where('expires_at', '>', now())
            ->with(['servicePackage.items.subService', 'usages'])
            ->orderBy('expires_at')
            ->get();

        return $purchases->map(function (ServicePackagePurchase $purchase) {
            $package = $purchase->servicePackage;
            if (!$package) {
                return null;
            }

            $itemsProgress = $package->items->map(function (ServicePackageItem $item) use ($purchase) {
                $usedCount = $purchase->usages
                    ->where('service_package_item_id', $item->id)
                    ->count();

                return [
                    'id' => $item->id,
                    'subService' => $item->subService ? [
                        'id' => $item->subService->id,
                        'name' => $item->subService->name,
                        'nameAr' => $item->subService->name_ar,
                    ] : null,
                    'totalVisits' => $item->total_visits,
                    'isUnlimited' => $item->isUnlimited(),
                    'dailyLimit' => $item->daily_limit,
                    'usedCount' => $usedCount,
                    'remainingVisits' => $item->isUnlimited() ? -1 : max(0, $item->total_visits - $usedCount),
                    'canRedeemToday' => $purchase->canRedeemToday($item->id),
                ];
            });

            return [
                'id' => $purchase->id,
                'code' => $purchase->code,
                'status' => $purchase->status,
                'purchasedAt' => $purchase->purchased_at?->toIso8601String(),
                'expiresAt' => $purchase->expires_at?->toIso8601String(),
                'package' => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'nameAr' => $package->name_ar,
                    'image' => $package->image ? asset('storage/' . $package->image) : null,
                ],
                'items' => $itemsProgress,
            ];
        })->filter();
    }

    /**
     * Redeem a visit from a package purchase. Uses lockForUpdate() for concurrency protection.
     */
    public function redeemVisit(ServicePackagePurchase $purchase, int $itemId, Booking $booking): ServicePackageUsage
    {
        return DB::transaction(function () use ($purchase, $itemId, $booking) {
            // Lock the purchase row for concurrent redemption protection
            $purchase = ServicePackagePurchase::lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== 'active') {
                throw new \RuntimeException('This package purchase is no longer active.');
            }

            if ($purchase->isExpired()) {
                $purchase->update(['status' => 'expired']);
                throw new \RuntimeException('This package has expired.');
            }

            $item = ServicePackageItem::where('id', $itemId)
                ->where('service_package_id', $purchase->service_package_id)
                ->firstOrFail();

            if (!$item->isUnlimited()) {
                $remaining = $purchase->getRemainingVisits($itemId);
                if ($remaining <= 0) {
                    throw new \RuntimeException('No remaining visits for this service.');
                }
            } else {
                // Unlimited: check daily limit
                $todayCount = $purchase->usages()
                    ->where('service_package_item_id', $itemId)
                    ->whereDate('used_at', today())
                    ->count();

                if ($todayCount >= $item->daily_limit) {
                    throw new \RuntimeException('Daily limit reached for this service.');
                }
            }

            $usage = ServicePackageUsage::create([
                'service_package_purchase_id' => $purchase->id,
                'service_package_item_id' => $itemId,
                'booking_id' => $booking->id,
                'used_at' => now(),
            ]);

            // Check if all items are fully used after this redemption
            if ($purchase->isFullyUsed()) {
                $purchase->update(['status' => 'fully_used']);
            }

            return $usage;
        });
    }

    /**
     * Reverse a package usage when a booking is cancelled.
     */
    public function reverseUsage(Booking $booking): void
    {
        $usage = ServicePackageUsage::where('booking_id', $booking->id)->first();

        if (!$usage) {
            return;
        }

        $purchaseId = $usage->service_package_purchase_id;
        $usage->delete();

        // If purchase was fully_used, reactivate it
        $purchase = ServicePackagePurchase::find($purchaseId);
        if ($purchase && $purchase->status === 'fully_used') {
            // Only reactivate if not expired
            if (!$purchase->isExpired()) {
                $purchase->update(['status' => 'active']);
            }
        }
    }

    /**
     * Get all purchases for a client (admin view) with usage history.
     */
    public function getClientPackages(int $userId): Collection
    {
        return ServicePackagePurchase::where('user_id', $userId)
            ->with([
                'servicePackage.items.subService',
                'usages.item.subService',
                'usages.booking',
                'order',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Find active purchases past expires_at and mark as expired.
     */
    public function expireOverduePackages(): int
    {
        return ServicePackagePurchase::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
