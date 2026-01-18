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
}
