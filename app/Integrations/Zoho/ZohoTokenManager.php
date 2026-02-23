<?php

namespace App\Integrations\Zoho;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoTokenManager
{
    private const CACHE_KEY = 'zoho_access_token';

    /**
     * Token TTL is set slightly below Zoho's 3600s expiry
     * to avoid edge-case failures near expiration.
     */
    private const TOKEN_TTL_SECONDS = 3500;

    /**
     * Returns a valid Zoho access token, refreshing it from
     * Zoho's OAuth2 endpoint when the cached one has expired.
     */
    public function getAccessToken(): string
    {
        return Cache::remember(self::CACHE_KEY, self::TOKEN_TTL_SECONDS, function () {
            return $this->fetchNewAccessToken();
        });
    }

    /**
     * Forces a token refresh regardless of cache state.
     * Useful after a 401 response from the Zoho API.
     */
    public function forceRefresh(): string
    {
        Cache::forget(self::CACHE_KEY);

        return $this->getAccessToken();
    }

    private function fetchNewAccessToken(): string
    {
        Log::channel('payments')->info('[zoho][token_refresh] Fetching new access token');

        $response = Http::asForm()
            ->post(config('zoho.accounts_url') . '/oauth/v2/token', [
                'grant_type'    => 'refresh_token',
                'client_id'     => config('zoho.client_id'),
                'client_secret' => config('zoho.client_secret'),
                'refresh_token' => config('zoho.refresh_token'),
            ])
            ->throw()
            ->json();

        $accessToken = data_get($response, 'access_token');

        if (empty($accessToken)) {
            Log::channel('payments')->error('[zoho][token_refresh] FAILED - no access_token in response', [
                'response' => $response,
            ]);

            throw new RuntimeException('Zoho token refresh failed: no access_token in response.');
        }

        Log::channel('payments')->info('[zoho][token_refresh] SUCCESS');

        return $accessToken;
    }
}
