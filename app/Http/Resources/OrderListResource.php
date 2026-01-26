<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListResource extends JsonResource
{
    public function toArray($request): array
    {
        $items = $this->type === 'booking'
            ? $this->mapBookingItems()
            : $this->mapProductItems();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'reference' => $this->reference,
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
            'createdAt' => $this->created_at,
            'address' => $this->formatAddress($this->shippingAddress),
            'items' => $items,
        ];
    }

    protected function mapProductItems(): array
    {
        return $this->items->map(function ($item) {
            return [
                'id' => $item->product_id,
                'name' => $item->product?->name,
                'skuId' => $item->product?->sku_id,
                'quantity' => (int) $item->quantity,
                'unitPrice' => (string) $item->unit_price,
                'subtotal' => (string) $item->subtotal,
                'type' => 'product',
            ];
        })->all();
    }

    protected function mapBookingItems(): array
    {
        if (!$this->orderable || !$this->orderable instanceof Booking) {
            return [];
        }

        return $this->orderable->services->map(function ($service) {
            $name = $service->bookable?->name;
            $price = $service->final_price ?? $service->price ?? 0;

            return [
                'id' => $service->bookable_id,
                'name' => $name,
                'quantity' => 1,
                'unitPrice' => (string) $price,
                'subtotal' => (string) $price,
                'type' => 'service',
            ];
        })->all();
    }

    protected function formatAddress($address): ?string
    {
        if (!$address) {
            return null;
        }

        $parts = array_filter([
            $address->address,
            $address->additional_address,
            $address->city,
            $address->country?->name ?? ($address->country_id ? 'Country #' . $address->country_id : null),
            $address->zip_code,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }
}
