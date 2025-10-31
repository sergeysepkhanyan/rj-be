<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $description
 * @property mixed $image
 * @property mixed $id
 * @property mixed $items
 */
class SubServiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'description' => $this->description ?? null,
            'image' => $this->image ?? null,
            'items' => SubServiceItemResource::collection($this->items)
        ];
    }
}

