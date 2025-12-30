<?php

namespace App\Integrations\Tabby;

use Illuminate\Support\Facades\Http;

class TabbyClient
{
    public function createSession(array $payload): array
    {
        return Http::baseUrl(config('tabby.base_url'))
            ->acceptJson()
            ->withToken(config('tabby.secret_key'))
            ->post('/api/v2/checkout', $payload)
            ->throw()
            ->json();
    }

    public function retrievePayment(string $paymentId): array
    {
        return Http::baseUrl(config('tabby.base_url'))
            ->acceptJson()
            ->withToken(config('tabby.secret_key'))
            ->get("/api/v2/payments/{$paymentId}")
            ->throw()
            ->json();
    }

    public function capture(string $paymentId, array $payload): array
    {
        return Http::baseUrl(config('tabby.base_url'))
            ->acceptJson()
            ->withToken(config('tabby.secret_key'))
            ->post("/api/v2/payments/{$paymentId}/captures", $payload)
            ->throw()
            ->json();
    }
}

