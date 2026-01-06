<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $description
 * @property mixed $image
 * @property mixed $services
 * @property mixed $id
 */
class AdminCategoryResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'gender' => $data['gender'] ?? null,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'services' => AdminServiceResource::collection($this->services),
        ];
    }
}

