<?php

namespace App\Services;

use App\Integrations\Stripe\StripeClient;
use App\Integrations\Tabby\TabbyClient;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            'canceled' => 'cancelled',
            default => 'pending',
        };

        return $this->paymentRepository->update($payment, [
            'external_id' => $paymentIntentId,
            'status' => $mappedStatus,
            'raw' => $res,
        ]);
    }

    public function startStripePaymentIntentForOrder(Order $order, ?string $customerEmail = null, array $metadata = []): Payment
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
            'description' => "Order #{$order->id}",
            'payment_method_types[]' => 'card',
            'metadata[order_id]' => (string) $order->id,
            'metadata[reference]' => (string) ($order->reference ?? $order->id),
        ];

        if ($customerEmail) {
            $payload['receipt_email'] = $customerEmail;
        }

        foreach ($metadata as $key => $value) {
            $payload["metadata[{$key}]"] = (string) $value;
        }

        $res = $this->stripeClient->createPaymentIntent($payload, $idempotencyKey);
        $paymentIntentId = data_get($res, 'id');
        $status = (string) data_get($res, 'status', 'requires_payment_method');

        $mappedStatus = match ($status) {
            'succeeded' => 'paid',
            'canceled' => 'cancelled',
            default => 'pending',
        };

        return $this->paymentRepository->update($payment, [
            'external_id' => $paymentIntentId,
            'status' => $mappedStatus,
            'raw' => $res,
        ]);
    }

    public function refundOrderPayment(Order $order, array $meta = []): array
    {
        $payment = $order->latestPayment;
        if (!$payment) {
            throw new HttpResponseException(
                ApiResponse::error(['payment' => ['Payment not found']], 'Payment not found', 404)
            );
        }

        return match ($payment->provider) {
            'stripe' => $this->refundStripePayment($payment, $meta),
            default => throw new HttpResponseException(
                ApiResponse::error(['payment' => ['Refund not supported']], 'Refund not supported', 400)
            ),
        };
    }

    protected function refundStripePayment(Payment $payment, array $meta = []): array
    {
        $paymentIntentId = $payment->external_id;
        if (!$paymentIntentId) {
            throw new HttpResponseException(
                ApiResponse::error(['payment' => ['Missing payment intent id']], 'Missing payment intent id', 400)
            );
        }

        $payload = [
            'payment_intent' => $paymentIntentId,
        ];

        foreach ($meta as $key => $value) {
            $payload["metadata[{$key}]"] = (string) $value;
        }

        $refund = $this->stripeClient->createRefund($payload, (string) Str::uuid());

        $raw = $payment->raw ?? [];
        $raw['refund'] = $refund;

        $this->paymentRepository->update($payment, [
            'raw' => $raw,
        ]);

        return $refund;
    }
}

