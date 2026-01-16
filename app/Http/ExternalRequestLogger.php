<?php

namespace App\Services\Http;

use Illuminate\Support\Facades\Log;
use Throwable;

class ExternalRequestLogger
{
    public static function log(
        string $provider,
        string $action,
        array $request,
        callable $callback,
        array $context = []
    ) {
        $start = microtime(true);

        try {
            $response = $callback();

            Log::channel('payments')->info("[$provider][$action] SUCCESS", [
                'duration_ms' => round((microtime(true) - $start) * 1000),
                'request' => self::sanitize($request),
                'response' => self::sanitize($response),
                'context' => $context,
            ]);

            return $response;
        } catch (Throwable $e) {
            Log::channel('payments')->error("[$provider][$action] FAILED", [
                'duration_ms' => round((microtime(true) - $start) * 1000),
                'request' => self::sanitize($request),
                'error' => $e->getMessage(),
                'context' => $context,
            ]);

            throw $e;
        }
    }

    private static function sanitize(array $data): array
    {
        return collect($data)->map(function ($value, $key) {
            if (str_contains($key, 'token') || str_contains($key, 'secret')) {
                return '***';
            }
            return $value;
        })->toArray();
    }
}

