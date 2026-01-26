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

        $maxQuantity = (int) ($data['max_quantity'] ?? 0);
        $currentQuantity = $this->calculateCurrentQuantity($maxQuantity);
        $availability = $currentQuantity > 0 ? 'On Stock' : 'Out';

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
            'maxQuantity' => $maxQuantity,
            'currentQuantity' => $currentQuantity,
            'quantity' => $currentQuantity,
            'availability' => $availability,
            'price' => $data['price'] ?? null,
            'currency' => $data['currency'] ?? null,
            'mainImage' => $this->main_image ? asset('storage/' . $this->main_image) : null,
            'referralId' => $data['referral_id'] ?? null,
            'discount' => $data['discount'] ?? null,
            'discountType' => $data['discount_type'] ?? null,
            'discountAmount' => $data['discount_amount'] ?? null,
            'status' => $data['status'] ?? null,
            'metaTitle' => $data['meta_title'] ?? null,
            'metaTitleAr' => $data['meta_title_ar'] ?? null,
            'metaDescription' => $data['meta_description'] ?? null,
            'metaDescriptionAr' => $data['meta_description_ar'] ?? null,
            'slug' => $data['slug'] ?? null,
            'redirectUrl' => $data['redirect_url'] ?? null,
            'createdAt' => $this->created_at ?? null,
            'images' => FileResource::collection($this->files),
            'details' => ProductDetailResource::collection($this->details),
        ];
    }

    protected function calculateCurrentQuantity(int $maxQuantity): int
    {
        // Return max_quantity directly - quantity is now managed by decrementing max_quantity on successful payment
        return max(0, $maxQuantity);
    }
}


