<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $type
 * @property mixed $status
 * @property mixed $reference
 * @property mixed $amount
 * @property mixed $currency
 * @property mixed $meta
 * @property mixed $created_at
 * @property mixed $latestPayment
 * @property mixed $deliveryAddress
 * @property mixed $shippingAddress
 * @property mixed $billingAddress
 * @property mixed $user
 * @property mixed $items
 * @property mixed $orderable
 * @property mixed $delivery_status
 * @property mixed $delivery_status_updated_at
 * @property mixed $paid_at
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $shippingAddress = $this->whenLoaded('shippingAddress') ? $this->shippingAddress : null;
        $customerName = null;
        $customerEmail = null;
        if ($this->whenLoaded('user') && $this->user) {
            $customerName = trim(($this->user->name ?? '') . ' ' . ($this->user->last_name ?? ''));
            $customerEmail = $this->user->email;
        } else {
            $customerName = $this->meta['customer_name'] ?? null;
            $customerEmail = $this->meta['customer_email'] ?? null;
        }
        $subtotal = 0;
        $tax = 0;
        $items = [];
        $quantity = 0;

        if ($this->type === 'ecommerce' && $this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                $itemSubtotal = (float) $item->subtotal;
                $itemUnitPrice = (float) $item->unit_price;
                $itemQuantity = (int) $item->quantity;

                // Calculate tax (assuming 5% VAT rate)
                $vatRate = 0.05;
                $itemBasePrice = $itemSubtotal / (1 + $vatRate);
                $itemTax = $itemSubtotal - $itemBasePrice;

                $subtotal += $itemBasePrice;
                $tax += $itemTax;
                $quantity += $itemQuantity; // Sum quantities

                $items[] = [
                    'id' => $item->id,
                    'productId' => $item->product_id,
                    'name' => $item->product?->name ?? 'Unknown Product',
                    'skuId' => $item->product?->sku_id,
                    'quantity' => $itemQuantity,
                    'unitPrice' => (string) $itemUnitPrice,
                    'subtotal' => (string) $itemSubtotal,
                    'currency' => $item->currency ?? $this->currency,
                ];
            }
        } elseif ($this->type === 'booking' && $this->relationLoaded('orderable')) {
            $booking = $this->orderable;
            if ($booking instanceof Booking && $booking->relationLoaded('services')) {
                foreach ($booking->services as $service) {
                    $servicePrice = (float) ($service->final_price ?? $service->price ?? 0);
                    $vatRate = 0.05;
                    $basePrice = $servicePrice / (1 + $vatRate);
                    $serviceTax = $servicePrice - $basePrice;

                    $subtotal += $basePrice;
                    $tax += $serviceTax;

                    $items[] = [
                        'id' => $service->id,
                        'serviceId' => $service->bookable_id,
                        'name' => $service->bookable?->name ?? 'Unknown Service',
                        'quantity' => 1,
                        'unitPrice' => (string) $servicePrice,
                        'subtotal' => (string) $servicePrice,
                        'currency' => $this->currency,
                    ];
                }
            }
        }

        // Format address string
        $addressString = null;
        if ($shippingAddress) {
            $parts = array_filter([
                $shippingAddress->address,
                $shippingAddress->additional_address,
                $shippingAddress->city,
                $shippingAddress->state,
                $shippingAddress->zip_code,
            ]);
            $addressString = $parts ? implode(', ', $parts) : null;
        }

        // Delivery status timeline (for ecommerce orders)
        $deliveryStatuses = [];
        if ($this->type === 'ecommerce') {
            $statuses = [
                'ordered' => $this->created_at,
                'out_for_delivery' => null,
                'arriving' => null,
                'delivered' => null,
            ];

            // If delivery_status is set, mark it and previous ones
            if ($this->delivery_status) {
                $found = false;
                foreach (['ordered', 'out_for_delivery', 'arriving', 'delivered'] as $status) {
                    if ($status === $this->delivery_status) {
                        $found = true;
                        $statuses[$status] = $this->delivery_status_updated_at ?? $this->updated_at;
                    } elseif ($found) {
                        break;
                    } else {
                        $statuses[$status] = $this->created_at; // Mark as completed
                    }
                }
            }

            $deliveryStatuses = [
                [
                    'status' => 'ordered',
                    'label' => 'Ordered',
                    'date' => $statuses['ordered']?->format('D, d F Y'),
                    'checked' => true,
                ],
                [
                    'status' => 'out_for_delivery',
                    'label' => 'Out for delivery',
                    'date' => $statuses['out_for_delivery']?->format('D, d F Y'),
                    'checked' => $statuses['out_for_delivery'] !== null,
                ],
                [
                    'status' => 'arriving',
                    'label' => 'Arriving',
                    'date' => $statuses['arriving']?->format('D, d F Y'),
                    'checked' => $statuses['arriving'] !== null,
                ],
                [
                    'status' => 'delivered',
                    'label' => 'Delivered',
                    'date' => $statuses['delivered']?->format('D, d F Y'),
                    'checked' => $statuses['delivered'] !== null,
                ],
            ];
        }

        return [
            'id'        => $this->id,
            'reference' => $this->reference ?? "#{$this->id}",
            'type'      => $this->type,
            'status'    => $this->status,
            'deliveryStatus' => $this->delivery_status,
            'amount'   => (string) $this->amount,
            'currency' => $this->currency ?? 'AED',
            'meta' => $this->meta,
            'createdAt' => $this->created_at,
            'paidAt' => $this->paid_at,

            // Detailed fields for admin view
            'customer' => [
                'id' => $this->user_id,
                'name' => $customerName,
                'email' => $customerEmail,
            ],
            'date' => $this->created_at?->format('D, d F Y'),
            'time' => $this->created_at?->format('h:i A'),
            'address' => $addressString,
            'quantity' => $quantity > 0 ? $quantity : 1,
            'price' => (string) round($subtotal, 2),
            'tax' => (string) round($tax, 2),
            'total' => (string) $this->amount,
            'items' => $items,
            'deliveryStatuses' => $deliveryStatuses,

            // Payment information
            'latestPayment' => $this->whenLoaded('latestPayment', function () {
                $clientSecret = null;
                if ($this->latestPayment->provider === 'stripe') {
                    $clientSecret = data_get($this->latestPayment->raw, 'client_secret');
                }
                return [
                    'id'            => $this->latestPayment->id,
                    'provider'      => $this->latestPayment->provider,
                    'flow'          => $this->latestPayment->flow,
                    'status'        => $this->latestPayment->status,
                    'externalId'   => $this->latestPayment->external_id,
                    'checkoutUrl'  => $this->latestPayment->checkout_url,
                    'clientSecret' => $clientSecret,
                    'createdAt'    => $this->latestPayment->created_at,
                ];
            }),

            'deliveryAddress' => $this->whenLoaded('deliveryAddress', function () {
                return new AddressResource($this->deliveryAddress);
            }),
            'shippingAddress' => $this->whenLoaded('shippingAddress', function () {
                return new AddressResource($this->shippingAddress);
            }),
            'billingAddress' => $this->whenLoaded('billingAddress', function () {
                return new AddressResource($this->billingAddress);
            }),
        ];
    }
}


