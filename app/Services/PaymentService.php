<?php

namespace App\Services;

use App\Integrations\Stripe\StripeClient;
use App\Integrations\Tabby\TabbyClient;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected PaymentRepositoryInterface $paymentRepository,
        protected TabbyClient $tabbyClient,
        protected StripeClient $stripeClient,
    ) {}

    /**
     * Creates Payment row + Tabby session, returns updated Payment.
     */
    public function startTabbyCheckout(Order $order, Booking $booking): Payment
    {
        $payment = $this->paymentRepository->create([
            'order_id' => $order->id,
            'provider' => 'tabby',
            'flow' => 'redirect',
            'amount' => $order->amount,
            'currency' => $order->currency,
            'status' => 'created',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $payload = [
            'merchant_code' => config('tabby.merchant_code'),
            'lang' => 'en',
            'merchant_urls' => [
                'success' => config('tabby.urls.success'),
                'cancel'  => config('tabby.urls.cancel'),
                'failure' => config('tabby.urls.failure'),
            ],
            'payment' => [
                'amount' => (string) $order->amount,
                'currency' => $order->currency,
                'description' => "Booking #{$booking->id}",
                'buyer' => [
                    'name'  => $booking->customer_name,
                    'email' => $booking->customer_email,
                    'phone' => $booking->customer_phone,
                ],
                'order' => [
                    'reference_id' => $order->reference ?? (string) $order->id,
                    'items' => [
                        [
                            'title' => 'Booking',
                            'quantity' => 1,
                            'unit_price' => (string) $order->amount,
                            'category' => 'services',
                        ],
                    ],
                ],
            ],
        ];

        $res = $this->tabbyClient->createSession($payload);
        $tabbyPaymentId = data_get($res, 'payment.id');
        $sessionId = data_get($res, 'id');
        $checkoutUrl = data_get($res, 'configuration.available_products.installments.0.web_url');

        return $this->paymentRepository->update($payment, [
            'external_id' => $tabbyPaymentId,
            'session_id' => $sessionId,
            'checkout_url' => $checkoutUrl,
            'status' => strtolower((string) data_get($res, 'status', 'created')),
            'raw' => $res,
        ]);
    }

    /**
     * Creates Payment row + Stripe PaymentIntent, returns updated Payment.
     */
    public function startStripePaymentIntent(Order $order, Booking $booking): Payment
    {
        $idempotencyKey = (string) Str::uuid();
        $payment = $this->paymentRepository->create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'flow' => 'token_charge',
            'amount' => $order->amount,
            'currency' => $order->currency,
            'status' => 'created',
            'idempotency_key' => $idempotencyKey,
        ]);

        $amountMinor = (int) round(((float) $order->amount) * 100);
        $payload = [
            'amount' => $amountMinor,
            'currency' => strtolower($order->currency ?? 'AED'),
            'description' => "Booking #{$booking->id}",
            'payment_method_types[]' => 'card',
            'receipt_email' => $booking->customer_email,
            'metadata[order_id]' => (string) $order->id,
            'metadata[booking_id]' => (string) $booking->id,
            'metadata[reference]' => (string) ($order->reference ?? $order->id),
        ];

        $res = $this->stripeClient->createPaymentIntent($payload, $idempotencyKey);
        $paymentIntentId = data_get($res, 'id');
        $status = (string) data_get($res, 'status', 'requires_payment_method');

        $mappedStatus = match ($status) {
            'succeeded' => 'paid',
            'canceled' => 'canceled',
            'processing' => 'pending',
            default => 'pending',
        };

        return $this->paymentRepository->update($payment, [
            'external_id' => $paymentIntentId,
            'status' => $mappedStatus,
            'raw' => $res,
        ]);
    }
}

