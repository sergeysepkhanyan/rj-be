<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Tests\TestCase;

class ProductSeoTest extends TestCase
{
    public function test_product_slug_auto_generation(): void
    {
        $category = ProductCategory::factory()->create();
        
        $product = Product::create([
            'name' => 'Test Product',
            'product_category_id' => $category->id,
            'price' => 100,
            'currency' => 'AED',
            'status' => 'active',
        ]);

        $this->assertNotNull($product->slug);
        $this->assertStringContainsString('test-product', $product->slug);
    }

    public function test_product_by_slug_endpoint(): void
    {
        $category = ProductCategory::factory()->create();
        
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'product_category_id' => $category->id,
            'price' => 100,
            'currency' => 'AED',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/products/by-slug/{$product->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.product.name', 'Test Product');
    }

    public function test_product_by_slug_returns_404_for_invalid_slug(): void
    {
        $response = $this->getJson('/api/products/by-slug/invalid-slug');

        $response->assertStatus(404);
    }
}
