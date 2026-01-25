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

        // Include customer if user has stripe_customer_id (allows using saved payment methods)
        if ($booking->user_id) {
            // Load the client (user) relationship if not already loaded
            if (!$booking->relationLoaded('client')) {
                $booking->load('client');
            }
            $user = $booking->client;
            if ($user && $user->stripe_customer_id) {
                $payload['customer'] = $user->stripe_customer_id;
            }
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

    public function startStripePaymentIntentForOrder(
        Order $order,
        ?string $customerEmail = null,
        array $metadata = [],
        ?string $customerId = null,
        ?string $paymentMethodId = null
    ): Payment
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

        if ($paymentMethodId) {
            if ($customerId) {
                try {
                    $pm = $this->stripeClient->retrievePaymentMethod($paymentMethodId);
                    
                    // Check if payment method is attached to a different customer
                    if (isset($pm['customer']) && $pm['customer'] && $pm['customer'] !== $customerId) {
                        throw new HttpResponseException(
                            \App\Services\ApiResponse::error(
                                ['paymentMethod' => [__('validation.payment_method.already_attached_to_another_customer')]],
                                __('validation.payment_method.already_attached'),
                                422
                            )
                        );
                    }

                    // Verify the payment method belongs to the user in our database
                    if ($order->user_id) {
                        $order->load('user');
                        $user = $order->user;
                        if ($user) {
                            $localPaymentMethod = \App\Models\PaymentMethod::query()
                                ->where('token', $paymentMethodId)
                                ->where('user_id', $user->id)
                                ->where('provider', 'stripe')
                                ->first();

                            if (!$localPaymentMethod) {
                                throw new HttpResponseException(
                                    \App\Services\ApiResponse::error(
                                        ['paymentMethod' => [__('validation.payment_method.already_attached_to_another_customer')]],
                                        __('validation.payment_method.already_attached'),
                                        422
                                    )
                                );
                            }
                        }
                    }
                    
                    // Only attach if not already attached to this customer
                    // If already attached, we can use it directly in the payment intent
                    if (!isset($pm['customer']) || $pm['customer'] !== $customerId) {
                        try {
                            $this->stripeClient->attachPaymentMethod($paymentMethodId, $customerId);
                        } catch (\Illuminate\Http\Client\RequestException $attachException) {
                            $errorBody = $attachException->response?->json();
                            $errorMessage = ($errorBody && isset($errorBody['error']['message']))
                                ? $errorBody['error']['message']
                                : ($attachException->getMessage() ?: 'Unknown error');
                            $errorMessageLower = strtolower($errorMessage);
                            
                            // If payment method was previously used, it's already attached or can't be attached
                            // Check if it's actually attached to our customer now
                            $pmCheck = $this->stripeClient->retrievePaymentMethod($paymentMethodId);
                            if (isset($pmCheck['customer']) && $pmCheck['customer'] === $customerId) {
                                // It's already attached, continue
                            } elseif (
                                stripos($errorMessageLower, 'previously used') !== false ||
                                stripos($errorMessageLower, 'cannot be reused') !== false
                            ) {
                                // Payment method was used in a previous payment intent without being attached
                                // We can't use it for a new payment intent - user needs to use a new payment method
                                throw new HttpResponseException(
                                    \App\Services\ApiResponse::error(
                                        ['paymentMethod' => [__('validation.payment_method.cannot_be_reused')]],
                                        __('validation.payment_method.cannot_be_reused'),
                                        422
                                    )
                                );
                            } else {
                                // Some other error, rethrow
                                throw $attachException;
                            }
                        }
                    }
                    // If payment method is already attached to this customer, we can use it directly
                } catch (HttpResponseException $e) {
                    throw $e;
                } catch (\Illuminate\Http\Client\RequestException $e) {
                    $errorBody = $e->response?->json();
                    $errorMessage = ($errorBody && isset($errorBody['error']['message']))
                        ? $errorBody['error']['message']
                        : ($e->getMessage() ?: 'Unknown error');
                    $errorMessageLower = strtolower($errorMessage);
                    
                    if (
                        stripos($errorMessageLower, 'previously used') !== false ||
                        stripos($errorMessageLower, 'detach') !== false ||
                        stripos($errorMessageLower, 'cannot be reused') !== false
                    ) {
                        throw new HttpResponseException(
                            \App\Services\ApiResponse::error(
                                ['paymentMethod' => [__('validation.payment_method.cannot_be_reused')]],
                                __('validation.payment_method.cannot_be_reused'),
                                422
                            )
                        );
                    }
                    throw $e;
                }
            } else {
                try {
                    $pm = $this->stripeClient->retrievePaymentMethod($paymentMethodId);
                    
                    if (isset($pm['customer']) && $pm['customer']) {
                        try {
                            $this->stripeClient->detachPaymentMethod($paymentMethodId);
                        } catch (\Illuminate\Http\Client\RequestException $detachException) {
                            throw new HttpResponseException(
                                \App\Services\ApiResponse::error(
                                    ['paymentMethod' => [__('validation.payment_method.cannot_be_reused')]],
                                    __('validation.payment_method.cannot_be_reused'),
                                    422
                                )
                            );
                        }
                    }
                } catch (HttpResponseException $e) {
                    throw $e;
                } catch (\Illuminate\Http\Client\RequestException $e) {
                    $errorBody = $e->response?->json();
                    $errorMessage = ($errorBody && isset($errorBody['error']['message']))
                        ? $errorBody['error']['message']
                        : ($e->getMessage() ?: 'Unknown error');
                    $errorMessageLower = strtolower($errorMessage);
                    
                    if (
                        stripos($errorMessageLower, 'previously used') !== false ||
                        stripos($errorMessageLower, 'detach') !== false ||
                        stripos($errorMessageLower, 'cannot be reused') !== false ||
                        stripos($errorMessageLower, 'no such payment_method') !== false
                    ) {
                        throw new HttpResponseException(
                            \App\Services\ApiResponse::error(
                                ['paymentMethod' => [__('validation.payment_method.cannot_be_reused')]],
                                __('validation.payment_method.cannot_be_reused'),
                                422
                            )
                        );
                    }
                }
            }
            
            $payload['payment_method'] = $paymentMethodId;
        }

        if ($customerId) {
            $payload['customer'] = $customerId;
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

