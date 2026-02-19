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
        $reorderPoint = (int) ($data['reorder_point'] ?? 0);
        $currentQuantity = $this->calculateCurrentQuantity($maxQuantity);
        $availability = $currentQuantity > 0 ? 'On Stock' : 'Out';
        $isLowStock = $currentQuantity > 0 && $currentQuantity <= $reorderPoint;

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'nameAr' => $data['name_ar'] ?? null,
            'description' => $data['description'] ?? null,
            'descriptionAr' => $data['description_ar'] ?? null,
            'skuId' => $data['sku_id'] ?? null,
            'productCategoryId' => $data['product_category_id'] ?? null,
            'productCategory' => $this->whenLoaded('productCategory', function () {
                return [
                    'id' => $this->productCategory?->id,
                    'name' => $this->productCategory?->name,
                    'nameAr' => $this->productCategory?->name_ar,
                ];
            }),
            'supplierId' => $data['supplier_id'] ?? null,
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier?->id,
                    'name' => $this->supplier?->name,
                ];
            }),
            'maxQuantity' => $maxQuantity,
            'reorderPoint' => $reorderPoint,
            'currentQuantity' => $currentQuantity,
            'quantity' => $currentQuantity,
            'availability' => $availability,
            'isLowStock' => $isLowStock,
            'price' => (float) ($data['price'] ?? 0),
            'finalPrice' => $this->resource->getFinalPrice(),
            'hasDiscount' => $this->resource->hasDiscount(),
            'costPrice' => $data['cost_price'] ? (float) $data['cost_price'] : null,
            'profitMargin' => $this->resource->getProfitMargin(),
            'currency' => $data['currency'] ?? 'AED',
            'mainImage' => $this->main_image ? asset('storage/' . $this->main_image) : null,
            'referralId' => $data['referral_id'] ?? null,
            'discount' => $data['discount'] ?? null,
            'discountType' => $data['discount_type'] ?? null,
            'discountAmount' => $data['discount_amount'] ? (float) $data['discount_amount'] : null,
            'status' => $data['status'] ?? 'draft',
            'productionDate' => $data['production_date'] ?? null,
            'expiryDate' => $data['expiry_date'] ?? null,
            'isExpired' => $this->resource->isExpired(),
            'isExpiringSoon' => $this->resource->isExpiringSoon(),
            'unitOfSale' => $data['unit_of_sale'] ?? 'piece',
            'salesChannel' => $data['sales_channel'] ?? 'both',
            'productType' => $data['product_type'] ?? 'retail',
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


