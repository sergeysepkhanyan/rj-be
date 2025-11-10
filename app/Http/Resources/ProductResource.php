<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $files
 * @property mixed $details
 * @property mixed $main_image
 */
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'description' => $this->description ?? null,
            'max_quantity' => $this->max_quantity ?? null,
            'price' => $this->price ?? null,
            'currency' => $this->currency ?? null,
            'main_image' => $this->main_image ? asset('storage/' . $this->main_image) : null,
            'referral_id' => $this->referral_id ?? null,
            'discount' => $this->discount ?? null,
            'discount_type' => $this->discount_type ?? null,
            'discount_amount' => $this->discount_amount ?? null,
            'status' => $this->status ?? null,
            'images' => FileResource::collection($this->files),
            'details' => ProductDetailResource::collection($this->details),
        ];
    }
}


