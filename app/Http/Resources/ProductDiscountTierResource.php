<?php

namespace App\Http\Resources;

class ProductDiscountTierResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        return [
            'id' => $this->resource->id ?? null,
            'name' => $data['name'] ?? $this->resource->name ?? null,
            'nameAr' => $data['name_ar'] ?? $this->resource->name_ar ?? null,
            'spendThreshold' => $this->resource->spend_threshold ?? null,
            'discountPercentage' => $this->resource->discount_percentage ?? null,
            'enabled' => (bool) ($this->resource->enabled ?? true),
        ];
    }
}
