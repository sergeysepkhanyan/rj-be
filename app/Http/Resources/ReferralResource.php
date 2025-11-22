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
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'value' => $data['value'] ?? null,
            'type' => $data['type'] === 'percentage' ? '%' : 'Fixed',
        ];
    }
}

