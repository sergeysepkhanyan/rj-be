<?php

namespace Tests\Feature;

use App\Models\DiscountSetting;
use App\Models\Product;
use App\Models\ProductCategory;
use Tests\TestCase;

class DiscountSettingTest extends TestCase
{
    public function test_admin_can_view_discount_settings(): void
    {
        $this->actingAsAdmin();

        DiscountSetting::create([
            'id' => 1,
            'quantity_threshold' => 10,
            'discount_percentage' => 10.00,
            'discount_label' => 'Bulk Discount',
            'enabled' => true,
        ]);

        $response = $this->getJson('/api/admin/discount-setting');

        $response->assertStatus(200)
            ->assertJsonPath('data.quantityThreshold', 10)
            ->assertJsonPath('data.discountPercentage', 10.00);
    }

    public function test_admin_can_update_discount_settings(): void
    {
        $this->actingAsAdmin();

        DiscountSetting::create(['id' => 1]);

        $response = $this->putJson('/api/admin/discount-setting', [
            'quantityThreshold' => 15,
            'discountPercentage' => 15.00,
            'discountLabel' => 'New Bulk Discount',
            'enabled' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.quantityThreshold', 15)
            ->assertJsonPath('data.discountPercentage', 15.00);
    }
}
