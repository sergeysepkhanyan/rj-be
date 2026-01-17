<?php

namespace App\Http\Resources;

class ProductCategoryResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
        ];
    }
}
