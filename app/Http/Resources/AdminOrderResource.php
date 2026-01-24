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
        [$productName, $productId, $quantity] = $this->resolveProductInfo();
        $address = $this->formatAddress();
        [$customerName, $customerEmail, $customerPhone] = $this->resolveCustomerInfo();
        [$paymentId, $paymentMethod, $paymentMethodLast4, $paymentMethodBrand] = $this->resolvePaymentInfo();
        $displayStatus = $this->displayStatus();
        $paymentStatus = $this->paymentStatus();

        return [
            'id' => $this->id,
            'paymentId' => $paymentId,
            'productName' => $productName,
            'productId' => $productId,
            'paymentMethod' => $paymentMethod,
            'paymentMethodLast4' => $paymentMethodLast4,
            'paymentMethodBrand' => $paymentMethodBrand,
            'price' => (string) $this->amount,
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
        ];
    }

    /** @return array{0: ?string, 1: ?int, 2: int} [productName, productId, quantity] */
    protected function resolveProductInfo(): array
    {
        $productName = null;
        $productId = null;
        $quantity = 0;

        if ($this->type === 'ecommerce') {
            $this->ensureItemsLoaded();
            if ($this->items->isNotEmpty()) {
                $first = $this->items->first();
                $productName = $first->product?->name;
                $productId = $first->product_id;
                $quantity = (int) $this->items->sum('quantity');
            }
        } elseif ($this->type === 'booking' && $this->orderable instanceof Booking) {
            $this->ensureBookingServicesLoaded();
            $booking = $this->orderable;
            if ($booking->services->isNotEmpty()) {
                $first = $booking->services->first();
                $productName = $first->bookable?->name;
                $productId = $first->bookable_id;
                $quantity = $booking->services->count();
            }
        }

        return [$productName, $productId, $quantity];
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
        $parts = array_filter([$a->address, $a->city, $a->state]);

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

        $meta = $this->meta ?? [];
        if (!$customerName && isset($meta['customer_name'])) {
            $customerName = $meta['customer_name'];
        }
        if (!$customerEmail && isset($meta['customer_email'])) {
            $customerEmail = $meta['customer_email'];
        }
        if (!$customerPhone && isset($meta['customer_phone'])) {
            $customerPhone = $meta['customer_phone'];
        }

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
            default => (string) $this->status,
        };
    }
}
