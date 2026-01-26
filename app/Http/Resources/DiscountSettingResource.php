<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DiscountSettingResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        
        return [
            'id' => $this->id ?? null,
            'quantityThreshold' => $data['quantity_threshold'] ?? $this->quantity_threshold ?? 10,
            'discountPercentage' => $data['discount_percentage'] ?? $this->discount_percentage ?? 10.00,
            'discountLabel' => $data['discount_label'] ?? $this->discount_label ?? 'Bulk Discount',
            'enabled' => $data['enabled'] ?? $this->enabled ?? true,
        ];
    }
}
