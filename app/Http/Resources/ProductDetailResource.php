<?php

namespace App\Http\Resources;

class ProductDetailResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        return [
            'id' => $data['id'] ?? null,
            'details' => $data['details'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }
}



