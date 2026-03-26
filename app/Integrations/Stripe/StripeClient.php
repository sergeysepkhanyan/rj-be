<?php

namespace App\Integrations\Stripe;

use App\Services\Http\ExternalRequestLogger;
use Illuminate\Support\Facades\Http;

class StripeClient
{
    public function createPaymentIntent(array $payload, ?string $idempotencyKey = null): array
    {
        return ExternalRequestLogger::log('stripe', 'create_payment_intent', $payload, function () use ($payload, $idempotencyKey) {
            $request = Http::baseUrl(config('stripe.base_url'))
                ->acceptJson()
                ->withToken(config('stripe.secret_key'))
                ->asForm();

            if ($idempotencyKey) {
                $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
            }

            return $request
                ->post('/v1/payment_intents', $payload)
                ->throw()
                ->json();
        }, [
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        $request = ['payment_intent_id' => $paymentIntentId];
        return ExternalRequestLogger::log('stripe', 'retrieve_payment_intent', $request, function () use ($paymentIntentId) {
            return Http::baseUrl(config('stripe.base_url'))
                ->acceptJson()
                ->withToken(config('stripe.secret_key'))
                ->get("/v1/payment_intents/{$paymentIntentId}")
                ->throw()
                ->json();
        });
    }

    public function createRefund(array $payload, ?string $idempotencyKey = null): array
    {
        return ExternalRequestLogger::log('stripe', 'create_refund', $payload, function () use ($payload, $idempotencyKey) {
            $request = Http::baseUrl(config('stripe.base_url'))
                ->acceptJson()
                ->withToken(config('stripe.secret_key'))
                ->asForm();

            if ($idempotencyKey) {
                $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
            }

            return $request
                ->post('/v1/refunds', $payload)
                ->throw()
                ->json();
        }, [
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function createCustomer(array $payload): array
    {
        return ExternalRequestLogger::log('stripe', 'create_customer', $payload, function () use ($payload) {
            return Http::baseUrl(config('stripe.base_url'))
                ->acceptJson()
                ->withToken(config('stripe.secret_key'))
                ->asForm()
                ->post('/v1/customers', $payload)
                ->throw()
                ->json();
        });
    }

    public function retrievePaymentMethod(string $paymentMethodId): array
    {
        $request = ['payment_method_id' => $paymentMethodId];
        return ExternalRequestLogger::log('stripe', 'retrieve_payment_method', $request, function () use ($paymentMethodId) {
            return Http::baseUrl(config('stripe.base_url'))
                ->acceptJson()
                ->withToken(config('stripe.secret_key'))
                ->get("/v1/payment_methods/{$paymentMethodId}")
                ->throw()
                ->json();
        });
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array
    {
        $payload = ['customer' => $customerId];
        return ExternalRequestLogger::log('stripe', 'attach_payment_method', $payload, function () use ($paymentMethodId, $customerId) {
            $response = Http::baseUrl(config('stripe.base_url'))
                ->acceptJson()
                ->withToken(config('stripe.secret_key'))
                ->asForm()
                ->post("/v1/payment_methods/{$paymentMethodId}/attach", [
                    'customer' => $customerId,
                ]);

            if ($response->failed()) {
                throw new \Illuminate\Http\Client\RequestException($response);
            }

            return $response->json();
        });
    }

    public function updateCustomerDefaultPaymentMethod(string $customerId, string $paymentMethodId): array
    {
        $payload = ['invoice_settings[default_payment_method]' => $paymentMethodId];
        return ExternalRequestLogger::log('stripe', 'update_customer_default_payment_method', $payload, function () use ($customerId, $paymentMethodId) {
            return Http::baseUrl(config('stripe.base_url'))
                ->acceptJson()
                ->withToken(config('stripe.secret_key'))
                ->asForm()
                ->post("/v1/customers/{$customerId}", [
                    'invoice_settings[default_payment_method]' => $paymentMethodId,
                ])
                ->throw()
                ->json();
        });
    }

    public function updatePaymentIntent(string $paymentIntentId, array $payload): array
    {
        return ExternalRequestLogger::log('stripe', 'update_payment_intent', $payload, function () use ($paymentIntentId, $payload) {
            return Http::baseUrl(config('stripe.base_url'))
                ->acceptJson()
                ->withToken(config('stripe.secret_key'))
                ->asForm()
                ->post("/v1/payment_intents/{$paymentIntentId}", $payload)
                ->throw()
                ->json();
        });
    }

    public function detachPaymentMethod(string $paymentMethodId): array
    {
        $request = ['payment_method_id' => $paymentMethodId];
        return ExternalRequestLogger::log('stripe', 'detach_payment_method', $request, function () use ($paymentMethodId) {
            return Http::baseUrl(config('stripe.base_url'))
                ->acceptJson()
                ->withToken(config('stripe.secret_key'))
                ->asForm()
                ->post("/v1/payment_methods/{$paymentMethodId}/detach")
                ->throw()
                ->json();
        });
    }
}
