<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $value
 * @property mixed $type
 * @property mixed $id
 */
class ReferralResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        $type = $data['type'] ?? $this->resource->type ?? null;

        // Convert 'percentage' to '%' for frontend display
        if ($type === 'percentage') {
            $type = '%';
        }

        return [
            'id' => $this->resource->id ?? null,
            'name' => $data['name'] ?? $this->resource->name ?? null,
            'nameAr' => $data['name_ar'] ?? $this->resource->name_ar ?? null,
            'value' => $data['value'] ?? $this->resource->value ?? null,
            'type' => $type,
            'visitThreshold' => $this->resource->visit_threshold ?? null,
            'enabled' => (bool) ($this->resource->enabled ?? true),
        ];
    }
}

