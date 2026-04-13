<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
        // For gift cards, show recipient as the customer
        if ($this->type === 'gift_card') {
            $customerName = $this->meta['recipient_name'] ?? $this->meta['customer_name'] ?? null;
        } else {
            $customerName = $this->meta['customer_name'] ?? null;
        }
        $customerEmail = $this->meta['customer_email'] ?? null;
        $customerPhone = $this->meta['customer_phone'] ?? null;

        // For booking orders, fall back to booking's customer info when meta is incomplete
        if ($this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof Booking) {
            $booking = $this->orderable;
            if (!$customerName && $booking->customer_name) {
                $customerName = $booking->customer_name;
            }
            if (!$customerEmail && $booking->customer_email) {
                $customerEmail = $booking->customer_email;
            }
            if (!$customerPhone && $booking->customer_phone) {
                $customerPhone = $booking->customer_phone;
            }
        }

        $clientName = null;
        $clientEmail = null;
        $clientPhone = null;
        if ($this->whenLoaded('user') && $this->user) {
            $firstName = $this->user->name ?? '';
            $lastName = $this->user->last_name ?? '';
            $clientName = trim("{$firstName} {$lastName}");
            $clientEmail = $this->user->email;
            $clientPhone = $this->user->mobile;
        }
        $subtotal = 0;
        $tax = 0;
        $quantity = 0;

        // VAT rate (5%)
        $vatRate = 0.05;

        if ($this->type === 'gift_card') {
            $subtotal = (float) $this->amount;
            $tax = 0;
            $quantity = 1;
        } elseif ($this->type === 'ecommerce' && $this->relationLoaded('items') && $this->items->isNotEmpty()) {
            foreach ($this->items as $item) {
                $itemSubtotal = (float) $item->subtotal;
                // Tax is added on top of the price (tax-exclusive pricing)
                $itemTax = $itemSubtotal * $vatRate;

                $subtotal += $itemSubtotal;
                $tax += $itemTax;
                $quantity += (int) $item->quantity;
            }
        } elseif ($this->type === 'ecommerce') {
            // Items not loaded - calculate from stored amount
            // Total = Subtotal + Tax = Subtotal * 1.05
            // Therefore: Subtotal = Total / 1.05
            $totalAmount = (float) $this->amount;
            $subtotal = round($totalAmount / (1 + $vatRate), 2);
            $tax = round($totalAmount - $subtotal, 2);
            $quantity = 1; // Default quantity when items aren't loaded
        } elseif ($this->type === 'booking' && $this->relationLoaded('orderable')) {
            // Get all bookings (including batch bookings)
            $allBookings = $this->resource->getAllBookings();
            foreach ($allBookings as $booking) {
                if ($booking instanceof Booking) {
                    $booking->loadMissing('services');
                    foreach ($booking->services as $service) {
                        // Use base_price (without VAT) and vat_amount separately
                        $basePrice = (float) ($service->base_price ?? 0);
                        $vatAmount = (float) ($service->vat_amount ?? 0);

                        // If base_price not set, derive from final_price
                        if ($basePrice == 0 && $service->final_price) {
                            $basePrice = (float) $service->final_price / (1 + $vatRate);
                            $vatAmount = (float) $service->final_price - $basePrice;
                        }

                        $subtotal += $basePrice;
                        $tax += $vatAmount;
                        $quantity += 1;
                    }
                }
            }
        }

        $addressString = null;
        if ($shippingAddress) {
            $parts = array_filter([
                $shippingAddress->address,
                $shippingAddress->additional_address,
                $shippingAddress->city,
                $shippingAddress->country?->name ?? ($shippingAddress->country_id ? 'Country #' . $shippingAddress->country_id : null),
                $shippingAddress->zip_code,
            ]);
            $addressString = $parts ? implode(', ', $parts) : null;
        }

        $deliveryStatuses = [];
        if ($this->type === 'ecommerce') {
            $statuses = [
                'ordered' => $this->created_at,
                'out_of_delivery' => null,
                'delivered' => null,
                'canceled' => null,
            ];

            if ($this->delivery_status) {
                $found = false;
                foreach (['ordered', 'out_of_delivery', 'delivered', 'canceled'] as $status) {
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
                    'status' => 'out_of_delivery',
                    'label' => 'Out of Delivery',
                    'date' => $statuses['out_of_delivery']?->format('D, d F Y'),
                    'checked' => $statuses['out_of_delivery'] !== null,
                ],
                [
                    'status' => 'delivered',
                    'label' => 'Delivered',
                    'date' => $statuses['delivered']?->format('D, d F Y'),
                    'checked' => $statuses['delivered'] !== null,
                ],
                [
                    'status' => 'canceled',
                    'label' => 'Canceled',
                    'date' => $statuses['canceled']?->format('D, d F Y'),
                    'checked' => $statuses['canceled'] !== null,
                ],
            ];
        }

        $orderDiscountType = null;
        $orderDiscountValue = null;
        $orderDiscountLabel = null;
        $orderDiscountAmount = null;

        if ($this->type === 'booking' && $this->relationLoaded('orderable')) {
            // Get discount info from all batch bookings
            $allBookings = $this->resource->getAllBookings();
            foreach ($allBookings as $booking) {
                if ($booking instanceof Booking) {
                    // Use first booking's discount type/value/label as they should be same across batch
                    if (!$orderDiscountType && $booking->discount_type && $booking->discount_type !== 'none') {
                        $orderDiscountType = $booking->discount_type;
                        $orderDiscountValue = $booking->discount_value;
                        $orderDiscountLabel = $booking->discount_label;
                    }
                }
            }

            // Calculate discount amount: (subtotal + tax) - order total
            // This accounts for batch-level discounts
            if ($orderDiscountType && $orderDiscountValue) {
                $expectedTotal = $subtotal + $tax;
                $actualTotal = (float) $this->amount;
                if ($expectedTotal > $actualTotal) {
                    $orderDiscountAmount = round($expectedTotal - $actualTotal, 2);
                }
            }
        }

        // Derive payment status from order status
        // Map order status to payment status
        $paymentStatus = match($this->status) {
            'pending' => 'pending',
            'pending_payment' => 'pending',
            'paid' => 'paid',
            'refunded' => 'refunded',
            'cancelled' => 'cancelled',
            'fulfilled' => 'paid',
            'return_requested' => 'paid',
            'return_approved' => 'refunded',
            'return_rejected' => 'paid',
            default => $this->status,
        };

        return [
            'id'        => $this->id,
            'reference' => $this->reference ?? "#{$this->id}",
            'type'      => $this->type,
            'status'    => $this->status,
            'deliveryStatus' => $this->delivery_status,
            'paymentStatus' => $paymentStatus,
            'amount'   => (string) $this->amount,
            'currency' => $this->currency ?? 'AED',
            'discount_type' => $orderDiscountType,
            'discount_value' => $orderDiscountValue ? (float) $orderDiscountValue : null,
            'discount_label' => $orderDiscountLabel,
            'discount_amount' => $orderDiscountAmount ? (float) $orderDiscountAmount : null,
            'booking' => $this->when($this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof Booking, function () {
                $booking = $this->orderable;
                $booking->loadMissing('services.bookable', 'master', 'cancelledBy');
                return new BookingResource($booking);
            }),
            'bookings' => $this->when($this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof Booking, function () {
                $allBookings = $this->resource->getAllBookings();
                return BookingResource::collection($allBookings);
            }),
            'isBatchOrder' => $this->when($this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof Booking, function () {
                return !empty($this->orderable->batch_id);
            }),
            'batchId' => $this->when($this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof Booking, function () {
                return $this->orderable->batch_id;
            }),
            'items' => $this->when($this->type === 'ecommerce' && $this->relationLoaded('items'), function () {
                if (!$this->items->every(fn($item) => $item->relationLoaded('product'))) {
                    $this->items->loadMissing('product.files');
                }
                return OrderItemResource::collection($this->items);
            }, function () {
                if ($this->type === 'gift_card') {
                    $giftCardName = $this->meta['gift_card_name'] ?? 'Gift Card';
                    $recipientName = $this->meta['recipient_name'] ?? '';
                    return [[
                        'id' => $this->meta['gift_card_id'] ?? 0,
                        'name' => $giftCardName,
                        'quantity' => 1,
                        'unitPrice' => (string) $this->amount,
                        'subtotal' => (string) $this->amount,
                        'finalPrice' => (string) $this->amount,
                        'tax' => '0',
                        'type' => 'gift_card',
                        'recipientName' => $recipientName,
                    ]];
                }

                if ($this->type === 'service_package' || $this->type === 'service package') {
                    $packageName = $this->meta['service_package_name'] ?? null;
                    $packageId = $this->meta['service_package_id'] ?? null;
                    if (!$packageName && $this->relationLoaded('orderable') && $this->orderable) {
                        $packageName = $this->orderable->name ?? null;
                        $packageId = $packageId ?? ($this->orderable->id ?? null);
                    }
                    return [[
                        'id' => $packageId ?? 0,
                        'name' => $packageName ?? 'Service Package',
                        'quantity' => 1,
                        'unitPrice' => (string) $this->amount,
                        'subtotal' => (string) $this->amount,
                        'finalPrice' => (string) $this->amount,
                        'tax' => '0',
                        'type' => 'service_package',
                    ]];
                }
                if ($this->type === 'booking' && $this->relationLoaded('orderable')) {
                    // Get all bookings (including batch bookings)
                    $allBookings = $this->resource->getAllBookings();
                    $allServices = collect();

                    foreach ($allBookings as $booking) {
                        if ($booking instanceof Booking) {
                            $booking->loadMissing('services.bookable');
                            foreach ($booking->services as $service) {
                                $servicePrice = (float) ($service->final_price ?? $service->price ?? 0);
                                $vatRate = 0.05;
                                // Tax is added on top (tax-exclusive pricing)
                                $serviceTax = $servicePrice * $vatRate;

                                $serviceImage = null;
                                $bookable = $service->bookable;
                                if ($bookable && isset($bookable->image) && $bookable->image) {
                                    $serviceImage = asset('storage/' . $bookable->image);
                                }

                                $resolvedName = $bookable?->name ?? 'Unknown Service';
                                if ($bookable instanceof \App\Models\SubServiceItem) {
                                    $bookable->loadMissing('subService');
                                    $parentName = $bookable->subService?->name;
                                    if ($parentName) {
                                        $resolvedName = $parentName . ' — ' . $bookable->name;
                                    }
                                } elseif ($bookable instanceof \App\Models\SubServiceItemVariant) {
                                    $bookable->loadMissing('subServiceItem.subService');
                                    $variantParent = $bookable->subServiceItem;
                                    $grandparent = $variantParent?->subService;
                                    $parts = array_filter([
                                        $grandparent?->name,
                                        $variantParent?->name,
                                        $bookable->name,
                                    ]);
                                    if (count($parts) > 0) {
                                        $resolvedName = implode(' — ', $parts);
                                    }
                                }

                                $allServices->push([
                                    'id' => $service->id,
                                    'productId' => $service->bookable_id ?? $service->id,
                                    'name' => $resolvedName,
                                    'image' => $serviceImage,
                                    'quantity' => 1,
                                    'unitPrice' => (string) $servicePrice,
                                    'subtotal' => (string) $servicePrice,
                                    'finalPrice' => (string) $servicePrice,
                                    'tax' => (string) round($serviceTax, 2),
                                    'type' => 'service',
                                    'bookingReference' => $booking->reference,
                                    'startTime' => $service->start_time,
                                    'endTime' => $service->end_time,
                                ]);
                            }
                        }
                    }

                    return $allServices->all();
                }
                return [];
            }),
            'meta' => $this->meta,
            'createdAt' => $this->created_at,
            'paidAt' => $this->paid_at,
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
                'phone' => $customerPhone,
            ],
            'client' => [
                'id' => $this->user_id,
                'name' => $clientName,
                'email' => $clientEmail,
                'phone' => $clientPhone,
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
                    'country' => $this->shippingAddress->country ? [
                        'id' => $this->shippingAddress->country->id,
                        'name' => $this->shippingAddress->country->name,
                        'nameAr' => $this->shippingAddress->country->name_ar,
                        'code' => $this->shippingAddress->country->code,
                    ] : null,
                    'zipCode' => $this->shippingAddress->zip_code,
                ];
            }),
            'quantity' => $quantity > 0 ? $quantity : 1,
            'price' => (string) round($subtotal, 2),
            'subtotal' => (string) round($subtotal, 2),
            'tax' => (string) round($tax, 2),
            'total' => (string) round($subtotal + $tax - ($orderDiscountAmount ?? 0) - (float) (($this->meta ?? [])['gift_card_amount'] ?? 0), 2),
            'giftCardCode' => ($this->meta ?? [])['gift_card_code'] ?? null,
            'giftCardAmount' => !empty($this->meta['gift_card_amount']) ? (float) $this->meta['gift_card_amount'] : null,
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
            'statusHistory' => $this->whenLoaded('statusHistory', function () {
                return $this->statusHistory->map(function ($history) {
                    return [
                        'status' => $history->status,
                        'timestamp' => $history->created_at?->toIso8601String(),
                        'note' => $history->note,
                    ];
                })->all();
            }),
            'returnRequest' => $this->whenLoaded('orderReturn', function () {
                if (!$this->orderReturn) {
                    return null;
                }
                return [
                    'id' => $this->orderReturn->id,
                    'status' => $this->orderReturn->status,
                    'reason' => $this->orderReturn->reason,
                    'createdAt' => $this->orderReturn->created_at,
                    'adminNotes' => $this->orderReturn->admin_notes,
                ];
            }),
        ];
    }
}


