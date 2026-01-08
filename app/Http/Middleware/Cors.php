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
            ? ['*']
            : config('app.frontend_urls', []);

        $isAllowed = $isLocal || in_array($origin, $allowedOrigins, true);

        if ($request->isMethod('OPTIONS')) {
            return $this->preflightResponse($isAllowed, $origin, !$isLocal);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($isAllowed) {
            $response->headers->set(
                'Access-Control-Allow-Origin',
                $isLocal ? '*' : $origin
            );
            $response->headers->set('Vary', 'Origin');
            $response->headers->set(
                'Access-Control-Allow-Methods',
                'GET, POST, PUT, PATCH, DELETE, OPTIONS'
            );
            $response->headers->set(
                'Access-Control-Allow-Headers',
                'Content-Type, Authorization, X-Requested-With'
            );

            if (!$isLocal) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
        }

        $response->headers->set('X-Cors-Middleware', 'custom');
        $response->headers->set('X-Cors-IsLocal', $isLocal ? '1' : '0');
        $response->headers->set('X-Cors-Origin', $origin ?? 'null');
        $response->headers->set('X-Cors-Allowed', $isAllowed ? '1' : '0');


        return $response;
    }

    private function preflightResponse(bool $isAllowed, ?string $origin, bool $withCredentials): Response
    {
        $response = response('', 204);

        if ($isAllowed) {
            $response->headers->set(
                'Access-Control-Allow-Origin',
                $withCredentials ? $origin : '*'
            );
            $response->headers->set('Vary', 'Origin');
            $response->headers->set(
                'Access-Control-Allow-Methods',
                'GET, POST, PUT, PATCH, DELETE, OPTIONS'
            );
            $response->headers->set(
                'Access-Control-Allow-Headers',
                'Content-Type, Authorization, X-Requested-With'
            );

            if ($withCredentials) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
        }

        return $response;
    }
}

