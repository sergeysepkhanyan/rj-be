<?php

namespace Tests\Feature;

use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The admin order summary must balance: Subtotal - Discount + Tax - Gift Card = Total,
 * and must never invent VAT on a gift-card sale (a multi-purpose voucher is taxed on redemption).
 */
class AdminOrderSummaryTest extends TestCase
{
    /** @return array<string,mixed> */
    private function summarize(Order $order): array
    {
        $order->refresh()->loadMissing('items.product');

        return (new AdminOrderResource($order))->toArray(Request::create('/'));
    }

    private function makeProduct(float $price = 100.0): Product
    {
        return Product::create([
            'name' => 'Hair Serum',
            'product_category_id' => ProductCategory::factory()->create()->id,
            'price' => $price,
            'currency' => 'AED',
            'status' => 'active',
            'max_quantity' => 10,
        ]);
    }

    public function test_gift_card_order_carries_no_vat_and_totals_the_face_value(): void
    {
        $order = Order::create([
            'type' => 'gift_card',
            'status' => 'paid',
            'amount' => 1000,
            'currency' => 'AED',
            'reference' => 'GCO-1',
            'meta' => ['gift_card_name' => 'Gift Card 1000'],
        ]);

        $summary = $this->summarize($order);

        $this->assertSame('1000', $summary['subtotal']);
        $this->assertSame('0', $summary['tax']);
        $this->assertSame('1000', $summary['total'], 'Total must equal the amount actually charged.');
    }

    public function test_discounted_product_order_shows_a_discount_line_without_changing_the_total(): void
    {
        $product = $this->makeProduct(100.0);

        // Sold at 80 each (was 100), qty 2 => paid 160, saving 40.
        $order = Order::create([
            'type' => 'ecommerce', 'status' => 'paid', 'amount' => 168,
            'currency' => 'AED', 'reference' => 'EPO-1', 'meta' => [],
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 2, 'unit_price' => 80, 'original_price' => 100,
            'discount_type' => 'fixed', 'discount_amount' => 20,
            'subtotal' => 160, 'currency' => 'AED',
        ]);

        $summary = $this->summarize($order);

        $this->assertSame(40.0, $summary['discountAmount']);
        $this->assertSame('200', $summary['subtotal'], 'Subtotal is the pre-discount price.');
        $this->assertSame('8', $summary['tax'], 'VAT is charged on the discounted 160, not on 200.');
        // 200 - 40 + 8 = 168
        $this->assertSame('168', $summary['total']);
    }

    public function test_tier_discount_with_no_stored_discount_amount_still_produces_a_discount_line(): void
    {
        $product = $this->makeProduct(100.0);

        // Loyalty tier: original_price set, discount_type/discount_amount both null.
        $order = Order::create([
            'type' => 'ecommerce', 'status' => 'paid', 'amount' => 94.5,
            'currency' => 'AED', 'reference' => 'EPO-2', 'meta' => [],
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_price' => 90, 'original_price' => 100,
            'discount_type' => null, 'discount_amount' => null,
            'subtotal' => 90, 'currency' => 'AED',
        ]);

        $summary = $this->summarize($order);

        $this->assertSame(10.0, $summary['discountAmount'], 'Tier saving must be derived from original_price.');
        $this->assertSame('94.5', $summary['total']);
    }

    public function test_undiscounted_product_order_has_no_discount_line(): void
    {
        $product = $this->makeProduct(100.0);

        $order = Order::create([
            'type' => 'ecommerce', 'status' => 'paid', 'amount' => 105,
            'currency' => 'AED', 'reference' => 'EPO-3', 'meta' => [],
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_price' => 100, 'original_price' => null,
            'subtotal' => 100, 'currency' => 'AED',
        ]);

        $summary = $this->summarize($order);

        $this->assertNull($summary['discountAmount']);
        $this->assertSame('100', $summary['subtotal']);
        $this->assertSame('105', $summary['total']);
    }

    public function test_order_level_meta_discount_is_not_double_counted_with_item_discounts(): void
    {
        $product = $this->makeProduct(100.0);

        $order = Order::create([
            'type' => 'ecommerce', 'status' => 'paid', 'amount' => 100,
            'currency' => 'AED', 'reference' => 'EPO-4',
            'meta' => [
                'order_type' => 'in_store',
                'discount_type' => 'percentage', 'discount_value' => 10,
                'discount_label' => 'Staff', 'discount_amount' => 10,
            ],
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_price' => 100, 'original_price' => null,
            'subtotal' => 100, 'currency' => 'AED',
        ]);

        $summary = $this->summarize($order);

        $this->assertSame('percentage', $summary['discountType']);
        $this->assertSame(10.0, $summary['discountAmount'], 'Meta discount wins; item pass must not add to it.');
    }
}
