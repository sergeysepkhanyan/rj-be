<?php

namespace App\Integrations\Zoho;

use App\Services\Http\ExternalRequestLogger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ZohoClient
{
    public function __construct(
        protected ZohoTokenManager $tokenManager,
    ) {}

    /**
     * Returns a pre-configured HTTP client with a valid Bearer token.
     * On a 401 response, forces a token refresh and retries once.
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl(config('zoho.api_base_url'))
            ->acceptJson()
            ->withToken($this->tokenManager->getAccessToken());
    }

    /**
     * Executes a Zoho API call wrapped in logging.
     * Automatically retries once with a fresh token on 401 Unauthorized.
     */
    protected function call(string $action, array $requestPayload, callable $callback): array
    {
        return ExternalRequestLogger::log('zoho', $action, $requestPayload, function () use ($action, $requestPayload, $callback) {
            try {
                return $callback($this->http());
            } catch (\Illuminate\Http\Client\RequestException $e) {
                if ($e->response->status() === 401) {
                    // Token may have expired mid-request — force refresh and retry once
                    $this->tokenManager->forceRefresh();

                    return $callback($this->http());
                }

                throw $e;
            }
        });
    }
}
