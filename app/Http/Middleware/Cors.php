<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isWebhookEndpoint($request)) {
            return $next($request);
        }

        $origin  = $request->headers->get('Origin');
        $isLocal = app()->environment('local');

        $allowedOrigins = $isLocal ? [] : $this->allowedOrigins();

        $isAllowed = $isLocal || ($origin && in_array($origin, $allowedOrigins, true));

        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
            return $this->applyCors($request, $response, $origin, $isLocal, $isAllowed);
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->applyCors($request, $response, $origin, $isLocal, $isAllowed);
    }

    private function isWebhookEndpoint(Request $request): bool
    {
        $path = $request->path();
        return str_starts_with($path, 'api/webhooks/')
            || str_contains($path, '/webhooks/stripe')
            || str_contains($path, '/webhooks/tabby')
            || str_contains($path, 'webhooks/stripe')
            || str_contains($path, 'webhooks/tabby');
    }

    private function applyCors(
        Request $request,
        Response $response,
        ?string $origin,
        bool $isLocal,
        bool $isAllowed
    ): Response {
        $this->appendVary($response, 'Origin');

        foreach ([
                     'Access-Control-Allow-Origin',
                     'Access-Control-Allow-Credentials',
                     'Access-Control-Allow-Methods',
                     'Access-Control-Allow-Headers',
                     'Access-Control-Max-Age',
                 ] as $header) {
            $response->headers->remove($header);
        }

        if (!$origin || !$isAllowed) {
            return $response;
        }

        $response->headers->set(
            'Access-Control-Allow-Headers',
            $request->headers->get('Access-Control-Request-Headers')
                ?: 'Origin, Content-Type, Accept, Authorization, X-Requested-With'
        );

        $response->headers->set(
            'Access-Control-Allow-Methods',
            'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        );

        $response->headers->set('Access-Control-Max-Age', '86400');

        if ($isLocal) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function allowedOrigins(): array
    {
        $cfg = config('app.frontend_urls', []);

        if (!is_array($cfg)) {
            $cfg = [];
        }

        $normalized = array_map(fn ($v) => rtrim(trim((string) $v), '/'), $cfg);

        return array_values(array_unique(array_filter($normalized)));
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
