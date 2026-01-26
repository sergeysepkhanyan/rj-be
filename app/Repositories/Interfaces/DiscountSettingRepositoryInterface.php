<?php

namespace App\Repositories\Interfaces;

use App\Models\DiscountSetting;

interface DiscountSettingRepositoryInterface
{
    public function findOrCreate(): DiscountSetting;
    public function update(DiscountSetting $setting, array $data): DiscountSetting;
}
