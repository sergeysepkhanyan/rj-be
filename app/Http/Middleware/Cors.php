<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $isLocal = app()->environment('local');

        $allowedOrigin = $isLocal
            ? '*'
            : config('app.frontend_url');

        $allowCredentials = !$isLocal;

        if ($request->getMethod() === 'OPTIONS') {
            $resp = response('', 204)
                ->header('Access-Control-Allow-Origin', $allowedOrigin)
                ->header('Vary', 'Origin')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

            if ($allowCredentials) {
                $resp->header('Access-Control-Allow-Credentials', 'true');
            }

            return $resp;
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        if ($allowCredentials) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}

