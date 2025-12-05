<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $duration
 * @property mixed $type
 * @property mixed $price
 * @property mixed $currency
 * @property mixed $duration_unit
 * @property mixed $variants
 * @property mixed $id
 */
class SubServiceItemResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'duration' => $this->duration ?? null,
            'durationUnit' => $this->duration_unit ?? null,
            'price' => $this->price ?? null,
            'currency' => $this->currency ?? null,
        ];
    }
}

