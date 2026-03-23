<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductDiscountTier;
use App\Models\User;
use Illuminate\Support\Collection;

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

        if ($user->product_discount_tier_id !== $newTierId) {
            $user->update(['product_discount_tier_id' => $newTierId]);
        }
    }

    public function getDiscountForUser(User $user): float
    {
        $tier = $user->productDiscountTier;
        return $tier ? (float) $tier->discount_percentage : 0.0;
    }
}
