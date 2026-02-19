<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = $this->product;
        $quantity = (int) ($this->quantity ?? 0);
        $unitPrice = $product ? $product->getFinalPrice() : 0;
        $originalPrice = (float) ($product?->price ?? 0);

        return [
            'id' => $this->id,
            'product' => $product ? new ProductResource($product) : null,
            'quantity' => $quantity,
            'unitPrice' => (string) $unitPrice,
            'originalPrice' => $product?->hasDiscount() ? (string) $originalPrice : null,
            'subtotal' => (string) ($unitPrice * $quantity),
        ];
    }
}
