<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductDiscountTier;
use App\Models\User;
use App\Services\ProductDiscountTierService;
use Tests\TestCase;

/**
 * Product spend-tier discount: the tier must be applied exactly ONCE in the
 * price a user is charged (FOUND-02 double-discount was a display-only bug — the
 * backend was, and must stay, single-applied), and the tier upgrades on spend
 * and downgrades when spend drops (refund/cancel).
 */
class ProductDiscountTest extends TestCase
{
    public function test_product_tier_discount_is_applied_exactly_once(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::create([
            'name' => 'Serum',
            'product_category_id' => $category->id,
            'price' => 100,
            'currency' => 'AED',
            'status' => 'active',
            'max_quantity' => 10,
        ]);

        $tier = ProductDiscountTier::create([
            'name' => 'Gold',
            'spend_threshold' => 0,
            'discount_percentage' => 20,
            'enabled' => true,
        ]);
        $user = User::factory()->create();
        $user->product_discount_tier_id = $tier->id;
        $user->save();

        // Tier applied once: 100 - 20% = 80 (NOT 64, which a double-apply would give).
        $this->assertEqualsWithDelta(80.0, $product->getFinalPriceForUser($user->fresh()), 0.001);
        // Anonymous / public price never has the tier applied.
        $this->assertEqualsWithDelta(100.0, $product->getFinalPriceForUser(null), 0.001);
    }

    public function test_tier_upgrades_on_spend_and_downgrades_on_refund(): void
    {
        $service = app(ProductDiscountTierService::class);
        $tier = ProductDiscountTier::create([
            'name' => 'Gold',
            'spend_threshold' => 500,
            'discount_percentage' => 10,
            'enabled' => true,
        ]);
        $user = User::factory()->create();

        // No qualifying spend yet → no tier.
        $service->checkAndUpgradeUser($user);
        $this->assertNull($user->fresh()->product_discount_tier_id);

        // A paid ecommerce order over the threshold → tier assigned.
        $order = Order::create([
            'user_id' => $user->id,
            'type' => 'ecommerce',
            'status' => 'paid',
            'amount' => 600,
            'currency' => 'AED',
            'reference' => 'E-' . uniqid(),
            'paid_at' => now(),
        ]);
        $service->checkAndUpgradeUser($user->fresh());
        $this->assertSame($tier->id, $user->fresh()->product_discount_tier_id);

        // Refunding that order drops qualifying spend below the threshold → downgrade.
        $order->update(['status' => 'refunded']);
        $service->checkAndUpgradeUser($user->fresh());
        $this->assertNull($user->fresh()->product_discount_tier_id);
    }
}
