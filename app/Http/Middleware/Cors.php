<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $isLocal = app()->environment('local');

        $allowedOrigins = $isLocal ? [] : $this->allowedOrigins();

        $isAllowed = $isLocal || ($origin && in_array($origin, $allowedOrigins, true));

        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
            return $this->applyCors($request, $response, $origin, $isLocal, $isAllowed, $allowedOrigins);
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->applyCors($request, $response, $origin, $isLocal, $isAllowed, $allowedOrigins);
    }

    private function applyCors(
        Request $request,
        Response $response,
        ?string $origin,
        bool $isLocal,
        bool $isAllowed,
        array $allowedOrigins
    ): Response {
        // Debug (TEMP)
        $response->headers->set('X-Cors-Env', app()->environment());
        $response->headers->set('X-Cors-IsLocal', $isLocal ? '1' : '0');
        $response->headers->set('X-Cors-Origin', $origin ?: '(none)');
        $response->headers->set('X-Cors-Allowed', $isAllowed ? '1' : '0');
        $response->headers->set('X-Cors-Allowlist', $isLocal ? '(local=*)' : implode(',', $allowedOrigins));

        // Always vary on Origin for caches
        $this->appendVary($response, 'Origin');

        // IMPORTANT: wipe existing CORS headers so nothing else "wins"
        $response->headers->remove('Access-Control-Allow-Origin');
        $response->headers->remove('Access-Control-Allow-Credentials');
        $response->headers->remove('Access-Control-Allow-Methods');
        $response->headers->remove('Access-Control-Allow-Headers');
        $response->headers->remove('Access-Control-Max-Age');

        // If not a CORS request, don’t add ACAO at all
        if (!$origin) {
            return $response;
        }

        // If origin not allowed, return with no ACAO
        if (!$isAllowed) {
            return $response;
        }

        // Shared headers (allowed request)
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set(
            'Access-Control-Allow-Headers',
            $request->headers->get('Access-Control-Request-Headers')
                ?: 'Origin, Content-Type, Accept, Authorization, X-Requested-With'
        );
        $response->headers->set('Access-Control-Max-Age', '86400');

        if ($isLocal) {
            // Local: allow *, NEVER credentials with *
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->remove('Access-Control-Allow-Credentials');
        } else {
            // Production: echo exact origin + credentials OK
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function allowedOrigins(): array
    {
        // Your required allowlist
        $hardcoded = [
            'https://rjbeautylounge.com',
            'https://www.rjbeautylounge.com',
        ];

        // Also support config('app.frontend_urls') if you use it
        $cfg = config('app.frontend_urls', []);
        if (is_string($cfg)) {
            $cfg = preg_split('/[\s,]+/', $cfg, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (!is_array($cfg)) {
            $cfg = [];
        }

        $all = array_merge($hardcoded, $cfg);

        // Normalize
        $all = array_values(array_unique(array_filter(array_map(function ($v) {
            $v = trim((string)$v);
            return $v !== '' ? rtrim($v, '/') : null;
        }, $all))));

        return $all;
    }

    private function appendVary(Response $response, string $value): void
    {
        $existing = $response->headers->get('Vary');
        if (!$existing) {
            $response->headers->set('Vary', $value);
            return;
        }

        $parts = array_map('trim', explode(',', $existing));
        if (!in_array($value, $parts, true)) {
            $parts[] = $value;
            $response->headers->set('Vary', implode(', ', $parts));
        }
    }
}


