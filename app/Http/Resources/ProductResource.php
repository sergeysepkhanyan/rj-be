<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $files
 * @property mixed $details
 * @property mixed $main_image
 */
class ProductResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'skuId' => $data['sku_id'] ?? null,
            'productCategoryId' => $data['product_category_id'] ?? null,
            'productCategory' => $this->whenLoaded('productCategory', function () {
                return [
                    'id' => $this->productCategory?->id,
                    'name' => $this->productCategory?->name,
                ];
            }),
            'maxQuantity' => $data['max_quantity'] ?? null,
            'currentQuantity' => $data['max_quantity'] ?? null,
            'price' => $data['price'] ?? null,
            'currency' => $data['currency'] ?? null,
            'mainImage' => $this->main_image ? asset('storage/' . $this->main_image) : null,
            'referralId' => $data['referral_id'] ?? null,
            'discount' => $data['discount'] ?? null,
            'discountType' => $data['discount_type'] ?? null,
            'discountAmount' => $data['discount_amount'] ?? null,
            'status' => $data['status'] ?? null,
            'images' => FileResource::collection($this->files),
            'details' => ProductDetailResource::collection($this->details),
        ];
    }
}


