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
            'vatEnabled' => (bool) ($this->vat_enabled ?? false),
            'vatRate'    => (float) ($this->vat_rate ?? config('vat.rate', 0.05)),

        ];
    }
}

