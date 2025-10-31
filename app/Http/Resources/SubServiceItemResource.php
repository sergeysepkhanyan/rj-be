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
class SubServiceItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'id' => $this->id ?? null,
            'name' => $this->name ?? null,
            'type' => $this->type ?? null,
        ];
        if($this->type === 'Simple'){
            $data = array_merge($data, [
                'duration' => $this->duration ?? null,
                'duration_unit' => $this->duration_unit ?? null,
                'price' => $this->price ?? null,
                'currency' => $this->currency ?? null,
            ]);
        } else {
            $data = array_merge($data, [
                'variants' => SubServiceItemVariantResource::collection($this->variants)
            ]);
        }
        return $data;
    }
}

