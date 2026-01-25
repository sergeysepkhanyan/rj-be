<?php

namespace App\Mail;

use App\Models\Order;
use App\Http\Resources\OrderResource;
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
            Log::info('[order][email][build] Starting email build', [
                'order_id' => $this->order->id,
                'recipient_email' => $this->recipientEmail,
            ]);

            // Load order with all relations needed for full template
            $order = $this->order->load([
                'items.product.files',
                'shippingAddress',
                'billingAddress',
                'latestPayment',
                'user',
            ]);

            Log::info('[order][email][build] Order loaded', [
                'order_id' => $order->id,
                'items_count' => $order->items->count(),
                'has_shipping_address' => !is_null($order->shippingAddress),
                'has_billing_address' => !is_null($order->billingAddress),
                'has_payment' => !is_null($order->latestPayment),
            ]);

            // Use OrderResource to format order data (same pattern as BookingConfirmedMail)
            $payload = (new OrderResource($order))->resolve();
            $payload = $this->stripMissingValues($payload);

            // Transform items to match template expectations (productName instead of name)
            if (!empty($payload['items']) && is_array($payload['items'])) {
                $payload['items'] = array_map(function ($item) {
                    if (isset($item['name']) && !isset($item['productName'])) {
                        $item['productName'] = $item['name'];
                    }
                    return $item;
                }, $payload['items']);
            }

            // Extract payment method info from latestPayment for template
            $paymentMethod = null;
            if ($order->latestPayment) {
                $paymentRaw = $order->latestPayment->raw ?? [];
                $paymentMethodData = data_get($paymentRaw, 'payment_method');
                
                if (is_string($paymentMethodData)) {
                    // Payment method is just an ID, try to get details from raw data
                    $paymentMethod = [
                        'provider' => $order->latestPayment->provider ?? 'card',
                        'last4' => data_get($paymentRaw, 'charges.data.0.payment_method_details.card.last4'),
                        'brand' => data_get($paymentRaw, 'charges.data.0.payment_method_details.card.brand'),
                    ];
                } elseif (is_array($paymentMethodData)) {
                    // Payment method is an object
                    $paymentMethod = [
                        'provider' => $order->latestPayment->provider ?? 'card',
                        'last4' => data_get($paymentMethodData, 'card.last4'),
                        'brand' => data_get($paymentMethodData, 'card.brand'),
                    ];
                } else {
                    // Fallback to provider name
                    $paymentMethod = [
                        'provider' => $order->latestPayment->provider ?? 'card',
                    ];
                }
            }

            // Add paymentMethod to payload for template
            $payload['paymentMethod'] = $paymentMethod;

            Log::info('[order][email][build] Order data prepared', [
                'order_id' => $order->id,
                'has_items' => !empty($payload['items']),
                'has_shipping_address' => !empty($payload['shippingAddress']),
                'has_billing_address' => !empty($payload['billingAddress']),
                'has_payment_method' => !is_null($paymentMethod),
            ]);

            $reference = $payload['reference'] ?? ('#' . ($payload['id'] ?? $order->id));
            $subject = 'Order Confirmed #' . $reference;

            Log::info('[order][email][build] Building email', [
                'order_id' => $order->id,
                'subject' => $subject,
                'recipient' => $this->recipientEmail,
                'template' => 'emails.order-confirmed',
            ]);

            $mail = $this->to($this->recipientEmail)
                ->subject($subject)
                ->from(config('mail.from.address'), config('mail.from.name'))
                ->view('emails.order-confirmed')
                ->text('emails.order-confirmed-text')
                ->with(['order' => $payload]);

            Log::info('[order][email][build] Email built successfully', [
                'order_id' => $order->id,
                'recipient' => $this->recipientEmail,
            ]);

            return $mail;
        } catch (\Throwable $e) {
            Log::error('[order][email][build] OrderConfirmedMail build failed', [
                'order_id' => $this->order->id,
                'recipient' => $this->recipientEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
