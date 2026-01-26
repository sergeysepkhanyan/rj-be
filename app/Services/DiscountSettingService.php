<?php

namespace App\Services;

use App\Models\DiscountSetting;
use App\Repositories\Interfaces\DiscountSettingRepositoryInterface;

class DiscountSettingService
{
    public function __construct(
        protected DiscountSettingRepositoryInterface $discountSettingRepository
    ) {}

    public function get(): DiscountSetting
    {
        return $this->discountSettingRepository->findOrCreate();
    }

    public function update(array $data): DiscountSetting
    {
        $setting = $this->discountSettingRepository->findOrCreate();
        return $this->discountSettingRepository->update($setting, $data);
    }

    public function getAutomaticDiscount(int $quantity): array
    {
        $setting = $this->get();
        
        if (!$setting->enabled || $quantity < $setting->quantity_threshold) {
            return [
                'discount_percent' => 0,
                'discount_label' => null,
            ];
        }

        return [
            'discount_percent' => (float) $setting->discount_percentage,
            'discount_label' => $setting->discount_label,
        ];
    }
}
