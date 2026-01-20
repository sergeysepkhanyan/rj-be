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
        $customerFullName = null;
        $customerEmail = null;
        if ($this->whenLoaded('user') && $this->user) {
            $firstName = $this->user->name ?? '';
            $lastName = $this->user->last_name ?? '';
            $customerName = trim("{$firstName} {$lastName}");
            $customerFullName = trim("{$firstName} {$lastName}");
            $customerEmail = $this->user->email;
        } else {
            $customerName = $this->meta['customer_name'] ?? null;
            $customerFullName = $customerName;
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

                $vatRate = 0.05;
                $itemBasePrice = $itemSubtotal / (1 + $vatRate);
                $itemTax = $itemSubtotal - $itemBasePrice;

                $subtotal += $itemBasePrice;
                $tax += $itemTax;
                $quantity += $itemQuantity;

                $product = $item->product;
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

                $items[] = [
                    'id' => $item->id,
                    'productId' => $item->product_id,
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

                    $serviceImage = null;
                    $bookable = $service->bookable;
                    if ($bookable) {
                        if (isset($bookable->image) && $bookable->image) {
                            $serviceImage = asset('storage/' . $bookable->image);
                        }
                    }

                    $items[] = [
                        'id' => $service->id,
                        'productId' => $service->bookable_id ?? $service->id,
                        'name' => $bookable?->name ?? 'Unknown Service',
                        'image' => $serviceImage,
                        'quantity' => 1,
                        'unitPrice' => (string) $servicePrice,
                        'subtotal' => (string) $servicePrice,
                        'finalPrice' => (string) $servicePrice,
                        'tax' => (string) round($serviceTax, 2),
                        'type' => 'service',
                    ];
                }
            }
        }

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

        $deliveryStatuses = [];
        if ($this->type === 'ecommerce') {
            $statuses = [
                'ordered' => $this->created_at,
                'out_for_delivery' => null,
                'arriving' => null,
                'delivered' => null,
            ];

            if ($this->delivery_status) {
                $found = false;
                foreach (['ordered', 'out_for_delivery', 'arriving', 'delivered'] as $status) {
                    if ($status === $this->delivery_status) {
                        $found = true;
                        $statuses[$status] = $this->delivery_status_updated_at ?? $this->updated_at;
                    } elseif ($found) {
                        break;
                    } else {
                        $statuses[$status] = $this->created_at;
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

        $orderDiscountType = null;
        $orderDiscountValue = null;
        $orderDiscountLabel = null;
        $orderDiscountAmount = null;

        if ($this->type === 'booking' && $this->relationLoaded('orderable')) {
            $booking = $this->orderable;
            if ($booking instanceof Booking) {
                $orderDiscountType = $booking->discount_type;
                $orderDiscountValue = $booking->discount_value;
                $orderDiscountLabel = $booking->discount_label;
                if ($booking->price && $booking->final_price) {
                    $orderDiscountAmount = (float) $booking->price - (float) $booking->final_price;
                }
            }
        }

        return [
            'id'        => $this->id,
            'reference' => $this->reference ?? "#{$this->id}",
            'type'      => $this->type,
            'status'    => $this->status,
            'deliveryStatus' => $this->delivery_status,
            'amount'   => (string) $this->amount,
            'currency' => $this->currency ?? 'AED',
            'discount_type' => $orderDiscountType,
            'discount_value' => $orderDiscountValue ? (float) $orderDiscountValue : null,
            'discount_label' => $orderDiscountLabel,
            'discount_amount' => $orderDiscountAmount ? (float) $orderDiscountAmount : null,
            'items' => $items,
            'meta' => $this->meta,
            'createdAt' => $this->created_at,
            'paidAt' => $this->paid_at,
            'customer' => [
                'id' => $this->user_id,
                'name' => $customerName,
                'fullName' => $customerFullName,
                'email' => $customerEmail,
            ],
            'purchaseDate' => $this->created_at?->format('Y-m-d'),
            'purchaseTime' => $this->created_at?->format('H:i:s'),
            'purchaseDateTime' => $this->created_at?->format('Y-m-d H:i:s'),
            'date' => $this->created_at?->format('D, d F Y'),
            'time' => $this->created_at?->format('h:i A'),
            'address' => $addressString,
            'addressInfo' => $this->whenLoaded('shippingAddress', function () {
                if (!$this->shippingAddress) {
                    return null;
                }
                return [
                    'name' => $this->shippingAddress->name,
                    'lastName' => $this->shippingAddress->last_name,
                    'mobile' => $this->shippingAddress->mobile,
                    'address' => $this->shippingAddress->address,
                    'additionalAddress' => $this->shippingAddress->additional_address,
                    'city' => $this->shippingAddress->city,
                    'state' => $this->shippingAddress->state,
                    'zipCode' => $this->shippingAddress->zip_code,
                ];
            }),
            'quantity' => $quantity > 0 ? $quantity : 1,
            'price' => (string) round($subtotal, 2),
            'tax' => (string) round($tax, 2),
            'total' => (string) $this->amount,
            'deliveryStatuses' => $deliveryStatuses,
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


