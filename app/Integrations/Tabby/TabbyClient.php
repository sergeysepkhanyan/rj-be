<?php

namespace App\Integrations\Tabby;

use App\Services\Http\ExternalRequestLogger;
use Illuminate\Support\Facades\Http;

class TabbyClient
{
    public function createSession(array $payload): array
    {
        return ExternalRequestLogger::log('tabby', 'create_session', $payload, function () use ($payload) {
            return Http::baseUrl(config('tabby.base_url'))
                ->acceptJson()
                ->withToken(config('tabby.secret_key'))
                ->post('/api/v2/checkout', $payload)
                ->throw()
                ->json();
        });
    }

    public function retrievePayment(string $paymentId): array
    {
        $request = ['payment_id' => $paymentId];
        return ExternalRequestLogger::log('tabby', 'retrieve_payment', $request, function () use ($paymentId) {
            return Http::baseUrl(config('tabby.base_url'))
                ->acceptJson()
                ->withToken(config('tabby.secret_key'))
                ->get("/api/v2/payments/{$paymentId}")
                ->throw()
                ->json();
        });
    }

    public function capture(string $paymentId, array $payload): array
    {
        $request = array_merge(['payment_id' => $paymentId], $payload);
        return ExternalRequestLogger::log('tabby', 'capture', $request, function () use ($paymentId, $payload) {
            return Http::baseUrl(config('tabby.base_url'))
                ->acceptJson()
                ->withToken(config('tabby.secret_key'))
                ->post("/api/v2/payments/{$paymentId}/captures", $payload)
                ->throw()
                ->json();
        });
    }
}

