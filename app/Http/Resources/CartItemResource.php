<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = $this->product;
        $quantity = (int) ($this->quantity ?? 0);
        $unitPrice = (float) ($product?->price ?? 0);

        return [
            'id' => $this->id,
            'product' => $product ? new ProductResource($product) : null,
            'quantity' => $quantity,
            'unitPrice' => (string) $unitPrice,
            'subtotal' => (string) ($unitPrice * $quantity),
        ];
    }
}
