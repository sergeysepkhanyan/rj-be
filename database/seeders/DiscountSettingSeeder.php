<?php

namespace Database\Seeders;

use App\Models\DiscountSetting;
use Illuminate\Database\Seeder;

class DiscountSettingSeeder extends Seeder
{
    public function run(): void
    {
        DiscountSetting::firstOrCreate(
            ['id' => 1],
            [
                'quantity_threshold' => 10,
                'discount_percentage' => 10.00,
                'discount_label' => 'Bulk Discount',
                'enabled' => true,
            ]
        );
    }
}
