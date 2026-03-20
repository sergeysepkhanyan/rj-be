<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (!$key) {
            return $next($request);
        }

        // Check if this key was already used
        $existing = DB::table('idempotency_keys')
            ->where('key', $key)
            ->first();

        if ($existing) {
            return response()->json(
                json_decode($existing->response_body, true),
                $existing->response_status
            );
        }

        $response = $next($request);

        // Store the response for this key
        try {
            DB::table('idempotency_keys')->insert([
                'key' => $key,
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'created_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate key — another concurrent request won the race. That's fine.
        }

        return $response;
    }
}
