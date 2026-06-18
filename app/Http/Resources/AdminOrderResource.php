<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin table view resource for orders.
 * Outputs a flat structure for the admin orders list (id, productName, paymentStatus, etc.).
 *
 * @property mixed $id
 * @property mixed $type
 * @property mixed $status
 * @property mixed $reference
 * @property mixed $amount
 * @property mixed $created_at
 * @property mixed $meta
 * @property mixed $user
 * @property mixed $items
 * @property mixed $orderable
 * @property mixed $shippingAddress
 * @property mixed $delivery_status
 * @property mixed $latestPayment
 * @method relationLoaded(string $string)
 * @method loadMissing(string $string)
 */
class AdminOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        [$productName, $productId, $quantity, $productImage] = $this->resolveProductInfo();
        $address = $this->formatAddress();
        [$customerName, $customerEmail, $customerPhone] = $this->resolveCustomerInfo();
        [$paymentId, $paymentMethod, $paymentMethodLast4, $paymentMethodBrand] = $this->resolvePaymentInfo();
        $displayStatus = $this->displayStatus();
        $paymentStatus = $this->paymentStatus();
        [$subtotal, $tax] = $this->calculateSubtotalAndTax();
        [$discountType, $discountValue, $discountLabel, $discountAmount] = $this->calculateDiscount($subtotal, $tax);

        return [
            'id' => $this->id,
            'paymentId' => $paymentId,
            'productName' => $productName,
            'productId' => $productId,
            'productImage' => $productImage,
            'paymentMethod' => $paymentMethod,
            'paymentMethodLast4' => $paymentMethodLast4,
            'paymentMethodBrand' => $paymentMethodBrand,
            'price' => (string) $subtotal,
            'subtotal' => (string) $subtotal,
            'tax' => (string) $tax,
            'total' => (string) round($subtotal + $tax - ($discountAmount ?? 0) - (float) ($this->resolveGiftCardAmount() ?? 0), 2),
            'amount' => (string) $this->amount,
            'quantity' => $quantity,
            'address' => $address,
            'date' => $this->created_at?->format('d. M Y'),
            'status' => $displayStatus,
            'paymentStatus' => $paymentStatus,
            'type' => $this->type,
            'reference' => $this->reference,
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
            'customerPhone' => $customerPhone,
            'deliveryStatus' => $this->delivery_status,
            'currency' => $this->currency ?? 'AED',
            'discountType' => $discountType,
            'discountValue' => $discountValue,
            'discountLabel' => $discountLabel,
            'discountAmount' => $discountAmount,
            // Booking payment details (tip, gift card, payment method)
            'tipAmount' => $this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof \App\Models\Booking
                ? (float) ($this->orderable->tip_amount ?? 0) : 0,
            'paidPaymentMethod' => $this->resolvePaidPaymentMethod(),
            'giftCardCode' => ($this->meta['gift_card_code'] ?? null)
                ?? ($this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof \App\Models\Booking
                    ? $this->orderable->gift_card_code
                    : null),
            'giftCardAmount' => $this->resolveGiftCardAmount(),
            'isPackageBooking' => (bool) ($this->meta['is_package_booking'] ?? (
                $this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof \App\Models\Booking
                    ? $this->orderable->is_package_booking
                    : false
            )),
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

    /** @return array{0: float, 1: float} [subtotal, tax] */
    protected function calculateSubtotalAndTax(): array
    {
        $vatRate = 0.05;
        $subtotal = 0.0;
        $tax = 0.0;

        if ($this->type === 'ecommerce') {
            $this->ensureItemsLoaded();
            if ($this->items->isNotEmpty()) {
                foreach ($this->items as $item) {
                    $itemSubtotal = (float) $item->subtotal;
                    $subtotal += $itemSubtotal;
                    $tax += $itemSubtotal * $vatRate;
                }
            } else {
                // Items not loaded - calculate from stored amount
                $totalAmount = (float) $this->amount;
                $subtotal = round($totalAmount / (1 + $vatRate), 2);
                $tax = round($totalAmount - $subtotal, 2);
            }
        } elseif ($this->type === 'gift_card') {
            // Gift cards: stored amount is the base price; tax is added on top.
            $subtotal = (float) $this->amount;
            $tax = round($subtotal * $vatRate, 2);
        } elseif ($this->type === 'service_package') {
            // Service packages store the gross amount charged (base + VAT);
            // derive the tax-exclusive subtotal back out of it.
            $total = (float) $this->amount;
            $subtotal = round($total / (1 + $vatRate), 2);
            $tax = round($total - $subtotal, 2);
        } elseif ($this->type === 'booking' && $this->orderable instanceof Booking) {
            // Get all bookings (including batch bookings)
            $allBookings = $this->resource->getAllBookings();
            $hasServices = false;

            foreach ($allBookings as $booking) {
                $booking->loadMissing('services.bookable');
                if ($booking->services->isNotEmpty()) {
                    $hasServices = true;
                    foreach ($booking->services as $service) {
                        $basePrice = (float) ($service->base_price ?? 0);
                        $vatAmount = (float) ($service->vat_amount ?? 0);
                        $subtotal += $basePrice;
                        $tax += $vatAmount;
                    }
                }
            }

            if (!$hasServices) {
                // Services not loaded - calculate from stored amount
                $totalAmount = (float) $this->amount;
                $subtotal = round($totalAmount / (1 + $vatRate), 2);
                $tax = round($totalAmount - $subtotal, 2);
            }
        }

        return [round($subtotal, 2), round($tax, 2)];
    }

    /** @return array{0: ?string, 1: ?int, 2: int, 3: ?string} [productName, productId, quantity, productImage] */
    protected function resolveProductInfo(): array
    {
        $productName = null;
        $productId = null;
        $quantity = 0;
        $productImage = null;

        if ($this->type === 'gift_card') {
            $productName = $this->meta['gift_card_name'] ?? 'Gift Card';
            $productId = $this->meta['gift_card_id'] ?? null;
            $quantity = 1;
        } elseif ($this->type === 'ecommerce') {
            $this->ensureItemsLoaded();
            if ($this->items->isNotEmpty()) {
                $first = $this->items->first();
                $productName = $first->product?->name;
                $productId = $first->product_id;
                $quantity = (int) $this->items->sum('quantity');
                // Get product image
                if ($first->product) {
                    $first->product->loadMissing('files');
                    $firstFile = $first->product->files->first();
                    if ($firstFile) {
                        $productImage = asset('storage/' . $firstFile->path);
                    }
                }
            }
        } elseif ($this->type === 'service_package') {
            $productName = $this->meta['service_package_name'] ?? 'Service Package';
            $productId = $this->meta['service_package_id'] ?? null;
            $quantity = 1;
        } elseif ($this->type === 'booking' && $this->orderable instanceof Booking) {
            // Get all bookings (including batch bookings)
            $allBookings = $this->resource->getAllBookings();
            $serviceNames = [];
            $totalServices = 0;

            foreach ($allBookings as $booking) {
                $booking->loadMissing('services.bookable');
                foreach ($booking->services as $service) {
                    $serviceName = $service->bookable?->name;
                    if ($serviceName) {
                        $serviceNames[] = $serviceName;
                    }
                    if (!$productId) {
                        $productId = $service->bookable_id;
                    }
                    $totalServices++;
                }
            }

            // Show all service names joined, or first one if too long
            if (count($serviceNames) > 0) {
                $productName = count($serviceNames) > 2
                    ? $serviceNames[0] . ' +' . (count($serviceNames) - 1) . ' more'
                    : implode(', ', $serviceNames);
            }
            $quantity = $totalServices;
        }

        return [$productName, $productId, $quantity, $productImage];
    }

    protected function ensureItemsLoaded(): void
    {
        if (!$this->relationLoaded('items') || !$this->items->every(fn ($i) => $i->relationLoaded('product'))) {
            $this->loadMissing('items.product');
        }
    }

    protected function ensureBookingServicesLoaded(): void
    {
        if (!$this->relationLoaded('orderable')) {
            return;
        }
        $booking = $this->orderable;
        if (!$booking instanceof Booking) {
            return;
        }
        if (!$booking->relationLoaded('services') || !$booking->services->every(fn ($s) => $s->relationLoaded('bookable'))) {
            $booking->loadMissing('services.bookable');
        }
    }

    protected function formatAddress(): ?string
    {
        if (!$this->relationLoaded('shippingAddress') || !$this->shippingAddress) {
            return null;
        }
        $a = $this->shippingAddress;

        // Build full name
        $fullName = trim(($a->name ?? '') . ' ' . ($a->last_name ?? ''));

        $parts = array_filter([
            $fullName ?: null,
            $a->address,
            $a->city,
            $a->country?->name ?? ($a->country_id ? 'Country #' . $a->country_id : null),
            $a->zip_code,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} [customerName, customerEmail, customerPhone] */
    protected function resolveCustomerInfo(): array
    {
        $customerName = null;
        $customerEmail = null;
        $customerPhone = null;

        if ($this->relationLoaded('user') && $this->user) {
            $customerName = trim(($this->user->name ?? '') . ' ' . ($this->user->last_name ?? ''));
            $customerEmail = $this->user->email ?? null;
            $customerPhone = $this->user->mobile ?? null;
        }

        // For booking orders, prefer the booking's customer info over the order's user (which may be the admin)
        if ($this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof \App\Models\Booking) {
            $booking = $this->orderable;
            if ($booking->customer_name) {
                $customerName = $booking->customer_name;
            }
            if ($booking->customer_email) {
                $customerEmail = $booking->customer_email;
            }
            if ($booking->customer_phone) {
                $customerPhone = $booking->customer_phone;
            }
        }

        $meta = $this->meta ?? [];
        // For gift cards, prefer recipient name as the customer
        if ($this->type === 'gift_card' && !empty($meta['recipient_name'])) {
            $customerName = $meta['recipient_name'];
        } elseif (!$customerName && isset($meta['customer_name'])) {
            $customerName = $meta['customer_name'];
        }
        if (!$customerEmail && isset($meta['customer_email'])) {
            $customerEmail = $meta['customer_email'];
        }
        if (!$customerPhone && isset($meta['customer_phone'])) {
            $customerPhone = $meta['customer_phone'];
        }

        $customerName = trim((string) $customerName) !== '' ? $customerName : 'Guest';

        return [$customerName, $customerEmail, $customerPhone];
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string} [paymentId, paymentMethod, last4, brand] */
    protected function resolvePaymentInfo(): array
    {
        $paymentId = null;
        $paymentMethod = null;
        $paymentMethodLast4 = null;
        $paymentMethodBrand = null;

        if (!$this->relationLoaded('latestPayment') || !$this->latestPayment) {
            $metaMethod = $this->meta['payment_method'] ?? null;
            if ($metaMethod) {
                $paymentId = $this->reference ?? "#{$this->id}";
                $paymentMethod = ucfirst($metaMethod);
            }
            return [$paymentId, $paymentMethod, $paymentMethodLast4, $paymentMethodBrand];
        }

        $payment = $this->latestPayment;
        $paymentId = $payment->external_id ?: ($this->reference ?? "#{$this->id}");

        if ($payment->relationLoaded('paymentMethod') && $payment->paymentMethod) {
            $paymentMethodLast4 = $payment->paymentMethod->last4;
            $paymentMethodBrand = $payment->paymentMethod->brand;
        } elseif ($payment->provider === 'stripe' && $payment->raw) {
            $raw = $payment->raw;
            $charge = data_get($raw, 'charges.data.0');
            if ($charge) {
                $paymentMethodLast4 = data_get($charge, 'payment_method_details.card.last4');
                $paymentMethodBrand = data_get($charge, 'payment_method_details.card.brand');
            }
            if (!$paymentMethodLast4) {
                $pm = data_get($raw, 'payment_method');
                if (is_array($pm)) {
                    $paymentMethodLast4 = data_get($pm, 'card.last4');
                    $paymentMethodBrand = data_get($pm, 'card.brand');
                }
            }
        }

        if ($paymentMethodLast4) {
            $brand = $paymentMethodBrand ? ucfirst($paymentMethodBrand) : 'Card';
            $paymentMethod = "{$brand} ...{$paymentMethodLast4}";
        } else {
            $paymentMethod = $payment->provider === 'stripe' ? 'Card' : ucfirst($payment->provider ?? 'Unknown');
        }

        return [$paymentId, $paymentMethod, $paymentMethodLast4, $paymentMethodBrand];
    }

    protected function displayStatus(): string
    {
        $status = $this->delivery_status ?? $this->status;

        return match ($status) {
            'delivered', 'fulfilled' => 'Delivered',
            'paid' => 'In Process',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    protected function paymentStatus(): string
    {
        return match ($this->status) {
            'pending' => 'pending',
            'pending_payment' => 'pending',
            'paid' => 'paid',
            'refunded' => 'refunded',
            'cancelled' => 'cancelled',
            'fulfilled' => 'paid',
            'return_requested' => 'return_requested',
            'return_approved' => 'return_approved',
            'return_rejected' => 'return_rejected',
            default => (string) $this->status,
        };
    }

    /** @return array{0: ?string, 1: ?float, 2: ?string, 3: ?float} [discountType, discountValue, discountLabel, discountAmount] */
    protected function calculateDiscount(float $subtotal, float $tax): array
    {
        $discountType = null;
        $discountValue = null;
        $discountLabel = null;
        $discountAmount = null;

        if ($this->type === 'booking' && $this->orderable instanceof Booking) {
            $allBookings = $this->resource->getAllBookings();
            foreach ($allBookings as $booking) {
                if ($booking instanceof Booking) {
                    if (!$discountType && $booking->discount_type && $booking->discount_type !== 'none') {
                        $discountType = $booking->discount_type;
                        $discountValue = (float) $booking->discount_value;
                        $discountLabel = $booking->discount_label;
                    }
                }
            }

            if ($discountType && $discountValue) {
                $expectedTotal = $subtotal + $tax;
                $actualTotal = (float) $this->amount;
                if ($expectedTotal > $actualTotal) {
                    $discountAmount = round($expectedTotal - $actualTotal, 2);
                }
            }
        }

        // For ecommerce/in-store orders, get discount from meta
        if (!$discountType && $this->meta) {
            $meta = $this->meta;
            if (!empty($meta['discount_type']) && $meta['discount_type'] !== 'none') {
                $discountType = $meta['discount_type'];
                $discountValue = isset($meta['discount_value']) ? (float) $meta['discount_value'] : null;
                $discountLabel = $meta['discount_label'] ?? null;
                $discountAmount = isset($meta['discount_amount']) ? (float) $meta['discount_amount'] : null;
            }
        }

        return [$discountType, $discountValue, $discountLabel, $discountAmount];
    }

    protected function resolveTipAmount(): float
    {
        if ($this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof Booking) {
            return (float) ($this->orderable->tip_amount ?? 0);
        }
        return 0.0;
    }

    protected function resolveGiftCardAmount(): ?float
    {
        // First check order meta (set by online checkout flows)
        if (!empty($this->meta) && !empty($this->meta['gift_card_amount'])) {
            return (float) $this->meta['gift_card_amount'];
        }

        // For booking orders, look up from GiftCardUsage if booking has a gift card code
        if ($this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof Booking) {
            $giftCardCode = $this->orderable->gift_card_code;
            if ($giftCardCode) {
                $usage = \App\Models\GiftCardUsage::where('used_for_type', 'booking')
                    ->where('used_for_id', $this->orderable->id)
                    ->first();
                if ($usage) {
                    return (float) $usage->amount_used;
                }
            }
        }

        return null;
    }

    protected function resolvePaidPaymentMethod(): ?string
    {
        $method = null;

        if ($this->type === 'booking' && $this->relationLoaded('orderable') && $this->orderable instanceof Booking) {
            $method = $this->orderable->paid_payment_method;
        } else {
            $method = $this->meta['payment_method'] ?? null;
        }

        // If method is already set (e.g. "gift_card,cash"), return as-is
        if ($method) {
            return $method;
        }

        // Fallback for online-paid bookings: if gift card fully covers the order, show "gift_card"
        $giftCardAmount = $this->resolveGiftCardAmount();
        if ($giftCardAmount && $giftCardAmount > 0) {
            [$subtotal, $tax] = $this->calculateSubtotalAndTax();
            [$discountType, $discountValue, $discountLabel, $discountAmount] = $this->calculateDiscount($subtotal, $tax);
            $expectedTotal = $subtotal + $tax - ($discountAmount ?? 0);
            if ($giftCardAmount >= $expectedTotal) {
                return 'gift_card';
            }
        }

        return $method;
    }
}
