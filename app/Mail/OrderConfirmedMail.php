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

            // Try full template first, fallback to simple on error
            try {
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

                // Use OrderResource to format order data
                $payload = (new OrderResource($order))->resolve();
                $payload = $this->stripMissingValues($payload);

                // Transform items: OrderItemResource uses 'name', template expects 'productName'
                if (!empty($payload['items']) && is_array($payload['items'])) {
                    $payload['items'] = array_map(function ($item) {
                        // Ensure productName exists (template requirement)
                        if (isset($item['name']) && !isset($item['productName'])) {
                            $item['productName'] = $item['name'];
                        }
                        // Ensure all required fields exist with defaults
                        return [
                            'id' => $item['id'] ?? null,
                            'productId' => $item['productId'] ?? null,
                            'productName' => $item['productName'] ?? $item['name'] ?? 'Product',
                            'skuId' => $item['skuId'] ?? null,
                            'quantity' => $item['quantity'] ?? 1,
                            'unitPrice' => $item['unitPrice'] ?? '0',
                            'subtotal' => $item['subtotal'] ?? '0',
                        ];
                    }, $payload['items']);
                } else {
                    $payload['items'] = [];
                }

                // Format createdAt as string (template expects string, not Carbon)
                if (isset($payload['createdAt'])) {
                    if ($payload['createdAt'] instanceof \Carbon\Carbon) {
                        $payload['createdAt'] = $payload['createdAt']->format('Y-m-d H:i:s');
                    } elseif (isset($payload['purchaseDateTime'])) {
                        $payload['createdAt'] = $payload['purchaseDateTime'];
                    } else {
                        $payload['createdAt'] = $order->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');
                    }
                } else {
                    $payload['createdAt'] = $order->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');
                }

                // Format paidAt if exists
                if (isset($payload['paidAt']) && $payload['paidAt'] instanceof \Carbon\Carbon) {
                    $payload['paidAt'] = $payload['paidAt']->format('Y-m-d H:i:s');
                }

                // Extract payment method info from latestPayment for template
                $paymentMethod = null;
                if ($order->latestPayment) {
                    $paymentRaw = $order->latestPayment->raw ?? [];
                    
                    // Try multiple paths to find payment method details
                    $pmId = data_get($paymentRaw, 'payment_method');
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
                    
                    // Fallback: try payment_method object directly
                    if (!$paymentMethod && is_array($pmId)) {
                        $paymentMethod = [
                            'provider' => $order->latestPayment->provider ?? 'card',
                            'brand' => data_get($pmId, 'card.brand'),
                            'last4' => data_get($pmId, 'card.last4'),
                        ];
                    }
                    
                    // Final fallback: just provider name
                    if (!$paymentMethod) {
                        $paymentMethod = [
                            'provider' => $order->latestPayment->provider ?? 'card',
                        ];
                    }
                }

                // Add paymentMethod to payload for template
                $payload['paymentMethod'] = $paymentMethod;

                // Ensure shippingAddress and billingAddress are arrays (not objects)
                if (isset($payload['shippingAddress']) && is_object($payload['shippingAddress'])) {
                    $payload['shippingAddress'] = (array) $payload['shippingAddress'];
                }
                if (isset($payload['billingAddress']) && is_object($payload['billingAddress'])) {
                    $payload['billingAddress'] = (array) $payload['billingAddress'];
                }

                Log::info('[order][email][build] Order data prepared', [
                    'order_id' => $order->id,
                    'has_items' => !empty($payload['items']),
                    'items_count' => count($payload['items'] ?? []),
                    'has_shipping_address' => !empty($payload['shippingAddress']),
                    'has_billing_address' => !empty($payload['billingAddress']),
                    'has_payment_method' => !is_null($paymentMethod),
                    'createdAt' => $payload['createdAt'] ?? null,
                ]);

                $reference = $payload['reference'] ?? ('#' . ($payload['id'] ?? $order->id));
                $subject = 'Order Confirmed #' . $reference;

                Log::info('[order][email][build] Building email with full template', [
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

                Log::info('[order][email][build] Email built successfully with full template', [
                    'order_id' => $order->id,
                    'recipient' => $this->recipientEmail,
                ]);

                return $mail;
            } catch (\Throwable $templateError) {
                // Fallback to simple template if full template fails
                Log::warning('[order][email][build] Full template failed, falling back to simple template', [
                    'order_id' => $this->order->id,
                    'error' => $templateError->getMessage(),
                    'trace' => $templateError->getTraceAsString(),
                ]);

                // Use simple template with minimal data
                $order = $this->order->load(['items.product']);
                $orderData = [
                    'id' => $order->id,
                    'reference' => $order->reference ?? 'ORD-' . $order->id,
                    'amount' => (string) $order->amount,
                    'currency' => $order->currency ?? 'AED',
                    'createdAt' => $order->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                ];

                $subject = 'Order Confirmed #' . ($orderData['reference'] ?? $order->id);

                Log::info('[order][email][build] Building email with simple template', [
                    'order_id' => $order->id,
                    'subject' => $subject,
                    'recipient' => $this->recipientEmail,
                ]);

                $mail = $this->to($this->recipientEmail)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'))
                    ->view('emails.order-confirmed-simple')
                    ->text('emails.order-confirmed-simple-text')
                    ->with(['order' => $orderData]);

                Log::info('[order][email][build] Email built successfully with simple template', [
                    'order_id' => $order->id,
                    'recipient' => $this->recipientEmail,
                ]);

                return $mail;
            }
        } catch (\Throwable $e) {
            Log::error('[order][email][build] OrderConfirmedMail build failed completely', [
                'order_id' => $this->order->id,
                'recipient' => $this->recipientEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
