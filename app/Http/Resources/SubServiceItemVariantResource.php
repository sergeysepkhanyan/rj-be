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
class SubServiceItemVariantResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'duration' => $data['duration'] ?? null,
            'duration_unit' => $data['duration_unit'] ?? null,
            'price' => $data['price'] ?? null,
            'currency' => $data['currency'] ?? null,
        ];
    }
}
