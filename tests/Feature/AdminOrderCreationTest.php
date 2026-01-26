<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Tests\TestCase;

class AdminOrderCreationTest extends TestCase
{
    public function test_admin_can_create_order_manually(): void
    {
        $this->actingAsAdmin();

        $category = ProductCategory::factory()->create();
        $product = Product::create([
            'name' => 'Test Product',
            'product_category_id' => $category->id,
            'price' => 100,
            'currency' => 'AED',
            'status' => 'active',
            'max_quantity' => 10,
        ]);

        $response = $this->postJson('/api/admin/orders', [
            'customerName' => 'John Doe',
            'customerEmail' => 'john@example.com',
            'customerPhone' => '+971501234567',
            'items' => [
                [
                    'product_id' => $product->id,
                    'name' => 'Test Product',
                    'price' => 100,
                    'quantity' => 2,
                ],
            ],
            'subtotal' => 200,
            'total' => 200,
            'currency' => 'AED',
            'shippingAddress' => [
                'name' => 'John Doe',
                'mobile' => '+971501234567',
                'address' => '123 Main St',
                'city' => 'Dubai',
                'country' => 'United Arab Emirates',
            ],
            'billingSameAsShipping' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'order' => ['id', 'reference', 'customer', 'client'],
                ],
            ]);

        $product->refresh();
        $this->assertEquals(8, $product->max_quantity);
    }

    public function test_order_creation_validates_quantity(): void
    {
        $this->actingAsAdmin();

        $category = ProductCategory::factory()->create();
        $product = Product::create([
            'name' => 'Test Product',
            'product_category_id' => $category->id,
            'price' => 100,
            'currency' => 'AED',
            'status' => 'active',
            'max_quantity' => 5,
        ]);

        $response = $this->postJson('/api/admin/orders', [
            'customerName' => 'John Doe',
            'customerEmail' => 'john@example.com',
            'customerPhone' => '+971501234567',
            'items' => [
                [
                    'product_id' => $product->id,
                    'name' => 'Test Product',
                    'price' => 100,
                    'quantity' => 10,
                ],
            ],
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'AED',
            'shippingAddress' => [
                'name' => 'John Doe',
                'mobile' => '+971501234567',
                'address' => '123 Main St',
                'city' => 'Dubai',
                'country' => 'United Arab Emirates',
            ],
        ]);

        $response->assertStatus(422);
    }
}
