<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     * @param mixed ...$roles
     * @return JsonResponse|mixed
     */

    public function handle(Request $request, \Closure $next, ...$roles): mixed
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }
        if ($user instanceof User) {
            $user->loadMissing('role');
            $roleName = $user->role?->slug;
        } else {
            $roleName = $user->role ?? null;
        }

        if (!$roleName || !in_array($roleName, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied.'
            ], 403);
        }

        return $next($request);
    }

}

