<?php

namespace App\Repositories;

use App\Models\DiscountSetting;
use App\Repositories\Interfaces\DiscountSettingRepositoryInterface;

class DiscountSettingRepository implements DiscountSettingRepositoryInterface
{
    public function findOrCreate(): DiscountSetting
    {
        $setting = DiscountSetting::find(1);
        
        if (!$setting) {
            $setting = DiscountSetting::create([
                'id' => 1,
                'quantity_threshold' => 10,
                'discount_percentage' => 10.00,
                'discount_label' => 'Bulk Discount',
                'enabled' => true,
            ]);
        }
        
        return $setting->fresh();
    }

    public function update(DiscountSetting $setting, array $data): DiscountSetting
    {
        $setting->update($data);
        return $setting->fresh();
    }
}
