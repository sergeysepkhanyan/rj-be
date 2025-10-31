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
 * @property mixed $id
 */
class SubServiceItemVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? null,
            'duration' => $this->duration ?? null,
            'duration_unit' => $this->duration_unit ?? null,
            'price' => $this->price ?? null,
            'currency' => $this->currency ?? null,
        ];
    }
}

