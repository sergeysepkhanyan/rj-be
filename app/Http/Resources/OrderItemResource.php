<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;
        
        $itemSubtotal = (float) $this->subtotal;
        $itemUnitPrice = (float) $this->unit_price;
        $itemQuantity = (int) $this->quantity;

        $vatRate = 0.05;
        $itemBasePrice = $itemSubtotal / (1 + $vatRate);
        $itemTax = $itemSubtotal - $itemBasePrice;

        $mainImage = null;
        $images = [];

        if ($product) {
            if ($product->main_image) {
                $mainImage = asset('storage/' . $product->main_image);
            }

            if ($product->relationLoaded('files') && $product->files) {
                $images = $product->files->map(function ($file) {
                    return asset('storage/' . $file->path);
                })->all();
            }
        }

        $discount = null;
        $discountType = null;
        $discountAmount = null;
        $originalPrice = null;
        
        if ($product) {
            $discount = (bool) $product->discount;
            $discountType = $product->discount_type;
            $discountAmount = $product->discount_amount;
            $originalPrice = $product->price;
        }

        $itemTaxAmount = $itemTax;
        $itemFinalPrice = $itemSubtotal;

        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'name' => $product?->name ?? 'Unknown Product',
            'image' => $mainImage,
            'quantity' => $itemQuantity,
            'unitPrice' => (string) $itemUnitPrice,
            'subtotal' => (string) $itemSubtotal,
            'finalPrice' => (string) $itemFinalPrice,
            'tax' => (string) round($itemTaxAmount, 2),
            'type' => 'product',
            'skuId' => $product?->sku_id,
            'images' => $images,
            'discount' => $discount,
            'discountType' => $discountType,
            'discountAmount' => $discountAmount ? (string) $discountAmount : null,
            'originalPrice' => $originalPrice ? (string) $originalPrice : null,
            'product' => $this->when($product, function () {
                return new ProductResource($this->product);
            }),
        ];
    }
}
