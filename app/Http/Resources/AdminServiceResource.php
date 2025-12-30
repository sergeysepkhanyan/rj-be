<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $description
 * @property mixed $image
 * @property mixed $subServices
 * @property mixed $id
 */
class AdminServiceResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'gender' => $data['gender'] ?? null,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'subservices' => SubServiceResource::collection($this->subServices),
        ];
    }
}

