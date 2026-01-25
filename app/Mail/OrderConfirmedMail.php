<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(
        public Order $order,
        public string $recipientEmail,
    ) {}

    public function build(): OrderConfirmedMail
    {
        try {
            $order = $this->order->load([
                'items.product',
                'shippingAddress',
                'billingAddress',
                'latestPayment.paymentMethod',
                'user',
            ]);

            $orderData = [
                'id' => $order->id,
                'reference' => $order->reference,
                'type' => $order->type,
                'status' => $order->status,
                'amount' => (string) $order->amount,
                'currency' => $order->currency ?? 'AED',
                'createdAt' => $order->created_at?->format('Y-m-d H:i:s'),
                'paidAt' => $order->paid_at?->format('Y-m-d H:i:s'),
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'productId' => $item->product_id,
                        'productName' => $item->product?->name,
                        'skuId' => $item->product?->sku_id,
                        'quantity' => (int) $item->quantity,
                        'unitPrice' => (string) $item->unit_price,
                        'subtotal' => (string) $item->subtotal,
                        'currency' => $item->currency ?? 'AED',
                    ];
                })->all(),
                'shippingAddress' => $order->shippingAddress ? [
                    'name' => $order->shippingAddress->name,
                    'lastName' => $order->shippingAddress->last_name,
                    'mobile' => $order->shippingAddress->mobile,
                    'address' => $order->shippingAddress->address,
                    'additionalAddress' => $order->shippingAddress->additional_address,
                    'city' => $order->shippingAddress->city,
                    'state' => $order->shippingAddress->state,
                    'zipCode' => $order->shippingAddress->zip_code,
                ] : null,
                'billingAddress' => $order->billingAddress ? [
                    'name' => $order->billingAddress->name,
                    'lastName' => $order->billingAddress->last_name,
                    'mobile' => $order->billingAddress->mobile,
                    'address' => $order->billingAddress->address,
                    'additionalAddress' => $order->billingAddress->additional_address,
                    'city' => $order->billingAddress->city,
                    'state' => $order->billingAddress->state,
                    'zipCode' => $order->billingAddress->zip_code,
                ] : null,
                'paymentMethod' => $order->latestPayment?->paymentMethod ? [
                    'type' => $order->latestPayment->paymentMethod->type,
                    'brand' => $order->latestPayment->paymentMethod->brand,
                    'last4' => $order->latestPayment->paymentMethod->last4,
                ] : ($order->latestPayment ? [
                    'provider' => $order->latestPayment->provider,
                    'type' => 'card',
                ] : null),
            ];

            $orderData = $this->stripMissingValues($orderData);

            $subject = 'Order Confirmed #' . ($orderData['reference'] ?? $order->reference ?? $order->id);

            return $this->to($this->recipientEmail)
                ->subject($subject)
                ->from(config('mail.from.address'), config('mail.from.name'))
                ->view('emails.order-confirmed')
                ->text('emails.order-confirmed-text')
                ->with(['order' => $orderData]);
        } catch (\Throwable $e) {
            Log::error('[order][email] OrderConfirmedMail build failed', [
                'order_id' => $this->order->id,
                'recipient' => $this->recipientEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
