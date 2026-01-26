<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DiscountSettingResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        
        return [
            'id' => $this->resource->id ?? null,
            'quantityThreshold' => $this->resource->quantity_threshold ?? 10,
            'discountPercentage' => (float) ($this->resource->discount_percentage ?? 10.00),
            'discountLabel' => $this->resource->discount_label ?? 'Bulk Discount',
            'enabled' => (bool) ($this->resource->enabled ?? true),
        ];
    }
}
