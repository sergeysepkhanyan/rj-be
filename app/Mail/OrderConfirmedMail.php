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

            // Load order with all relations needed (same pattern as BookingConfirmedMail)
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

            // Transform items array to match template expectations
            // Template expects 'productName' but OrderItemResource returns 'name'
            if (!empty($payload['items']) && is_array($payload['items'])) {
                $payload['items'] = array_map(function ($item) {
                    if (isset($item['name']) && !isset($item['productName'])) {
                        $item['productName'] = $item['name'];
                    }
                    return $item;
                }, $payload['items']);
            }

            // Format dates as strings (template expects strings)
            if (isset($payload['createdAt']) && $payload['createdAt'] instanceof \Carbon\Carbon) {
                $payload['createdAt'] = $payload['createdAt']->format('Y-m-d H:i:s');
            }
            if (isset($payload['paidAt']) && $payload['paidAt'] instanceof \Carbon\Carbon) {
                $payload['paidAt'] = $payload['paidAt']->format('Y-m-d H:i:s');
            }

            // Extract payment method info for template
            $paymentMethod = null;
            if ($order->latestPayment) {
                $paymentRaw = $order->latestPayment->raw ?? [];
                $charges = data_get($paymentRaw, 'charges.data', []);
                
                if (!empty($charges) && is_array($charges)) {
                    $firstCharge = $charges[0] ?? [];
                    $pmDetails = data_get($firstCharge, 'payment_method_details.card', []);
                    
                    if (!empty($pmDetails)) {
                        $paymentMethod = [
                            'provider' => $order->latestPayment->provider ?? 'card',
                            'brand' => data_get($pmDetails, 'brand'),
                            'last4' => data_get($pmDetails, 'last4'),
                        ];
                    }
                }
                
                if (!$paymentMethod) {
                    $paymentMethod = [
                        'provider' => $order->latestPayment->provider ?? 'card',
                    ];
                }
            }
            $payload['paymentMethod'] = $paymentMethod;

            Log::info('[order][email][build] Order data prepared', [
                'order_id' => $order->id,
                'has_items' => !empty($payload['items']),
                'items_count' => count($payload['items'] ?? []),
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
