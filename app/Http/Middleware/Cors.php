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

        $allowedOrigins = $isLocal
            ? []
            : config('app.frontend_urls', []);

        $isAllowed = $isLocal || ($origin && in_array($origin, $allowedOrigins, true));

        if ($request->isMethod('OPTIONS')) {
            return $this->preflightResponse($isAllowed, $origin, $isLocal);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($isAllowed) {
            $this->applyHeaders($response, $origin, $isLocal);
        }

        return $response;
    }

    private function preflightResponse(bool $isAllowed, ?string $origin, bool $isLocal): Response
    {
        $response = response('', 204);

        if ($isAllowed) {
            $this->applyHeaders($response, $origin, $isLocal);
        }

        return $response;
    }

    private function applyHeaders(Response $response, ?string $origin, bool $isLocal): void
    {
        if (!$isLocal && !$origin) {
            return;
        }

        if ($isLocal) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }

}


