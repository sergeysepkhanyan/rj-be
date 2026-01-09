<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language')
            ?? $request->header('X-Locale')
            ?? $request->query('lang');

        $locale = is_string($locale) ? strtolower(trim($locale)) : 'en';
        $locale = explode(',', $locale)[0];
        $locale = explode('-', $locale)[0];

        if (!in_array($locale, ['ar', 'en'], true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        app()->instance('api_locale', $locale);

        return $next($request);
    }
}

