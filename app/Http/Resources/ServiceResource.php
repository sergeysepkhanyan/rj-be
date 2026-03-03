<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $description
 * @property mixed $image
 * @property mixed $subServices
 * @property mixed $id
 * @property mixed $files
 */
class ServiceResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'images' => FileResource::collection($this->whenLoaded('files')),
            'subservices' => SubServiceResource::collection($this->subServices),
            'category' => new AdminCategoryResource($this->whenLoaded('category')),

        ];
    }
}

