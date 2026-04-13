<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = $this->product;
        $quantity = (int) ($this->quantity ?? 0);

        $authUser = auth()->user();
        $unitPrice = $product ? $product->getFinalPriceForUser($authUser) : 0.0;
        $rawPrice = (float) ($product?->price ?? 0);
        $tierPercent = $product ? $product->getTierDiscountPercentForUser($authUser) : 0.0;
        $hasAnyDiscount = $product
            ? ($product->hasDiscount() || ($tierPercent > 0 && $unitPrice < $rawPrice))
            : false;

        return [
            'id' => $this->id,
            'product' => $product ? new ProductResource($product) : null,
            'quantity' => $quantity,
            'unitPrice' => (string) round($unitPrice, 2),
            'originalPrice' => $hasAnyDiscount ? (string) round($rawPrice, 2) : null,
            'subtotal' => (string) round($unitPrice * $quantity, 2),
            'tierDiscountPercent' => $tierPercent,
        ];
    }
}
