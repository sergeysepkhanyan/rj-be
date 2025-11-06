<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $value
 * @property mixed $type
 * @property mixed $id
 */
class ReferralResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'value' => $this->value ?? null,
            'type' => $this->type === 'percentage' ? '%' : 'Fixed',
        ];
    }
}

