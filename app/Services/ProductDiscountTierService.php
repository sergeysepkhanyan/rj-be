<?php

namespace App\Services;

use App\Mail\ProductDiscountTierDowngradedMail;
use App\Mail\ProductDiscountTierUpgradedMail;
use App\Models\Order;
use App\Models\ProductDiscountTier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class ProductDiscountTierService
{
    public function getAll(): Collection
    {
        return ProductDiscountTier::orderBy('spend_threshold', 'asc')->get();
    }

    public function getActiveTiers(): Collection
    {
        return ProductDiscountTier::where('enabled', true)
            ->orderBy('spend_threshold', 'asc')
            ->get();
    }

    public function create(array $data): ProductDiscountTier
    {
        return ProductDiscountTier::create($data);
    }

    public function update(int $id, array $data): ProductDiscountTier
    {
        $tier = ProductDiscountTier::findOrFail($id);
        $tier->update($data);
        return $tier;
    }

    public function delete(int $id): void
    {
        $tier = ProductDiscountTier::findOrFail($id);
        $tier->delete();
    }

    public function getUserTotalProductSpend(User $user): float
    {
        return (float) Order::where('user_id', $user->id)
            ->where('type', 'ecommerce')
            ->whereIn('status', ['paid', 'fulfilled', 'processing', 'shipped'])
            ->sum('amount');
    }

    public function checkAndUpgradeUser(User $user): void
    {
        $totalSpend = $this->getUserTotalProductSpend($user);

        $matchingTier = ProductDiscountTier::where('enabled', true)
            ->where('spend_threshold', '<=', $totalSpend)
            ->orderBy('spend_threshold', 'desc')
            ->first();

        $newTierId = $matchingTier?->id;
        $previousTierId = $user->product_discount_tier_id;

        if ($previousTierId === $newTierId) {
            return;
        }

        $previousTier = $previousTierId ? ProductDiscountTier::find($previousTierId) : null;
        $user->update(['product_discount_tier_id' => $newTierId]);

        if (! $user->email) {
            return;
        }

        // Earned a tier (or moved to a higher discount) → upgrade email; lost or
        // dropped to a lower discount (e.g. after a refund) → downgrade email.
        $isUpgrade = $matchingTier && (
            $previousTier === null
            || (float) $matchingTier->discount_percentage > (float) $previousTier->discount_percentage
        );

        if ($isUpgrade) {
            Mail::to($user->email)->queue(new ProductDiscountTierUpgradedMail($user, $matchingTier));
        } elseif ($previousTier !== null) {
            Mail::to($user->email)->queue(new ProductDiscountTierDowngradedMail($user, $matchingTier, $previousTier));
        }
    }

    public function getDiscountForUser(User $user): float
    {
        $tier = $user->productDiscountTier;
        return $tier ? (float) $tier->discount_percentage : 0.0;
    }
}
