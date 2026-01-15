<?php

namespace App\Http\Middleware;

use App\Services\ApiResponse;
use Closure;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtOptionalMiddleware
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->header('token');
    
        if (! $token) {
            $authHeader = $request->header('Authorization');
            if (is_string($authHeader) && str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
            }
        }
        if (!$token) {
            return $next($request);
        }

        try {
            $user = JWTAuth::setToken($token)->authenticate();
            if ($user) {
                Auth::setUser($user);
            }
        } catch (TokenExpiredException $e) {
            return ApiResponse::error(
                ['auth' => [__('auth.token_expired')]],
                __('auth.unauthorized'),
                401
            );
        } catch (TokenInvalidException $e) {
            return ApiResponse::error(
                ['auth' => [__('auth.token_invalid')]],
                __('auth.unauthorized'),
                401
            );
        } catch (JWTException $e) {
            return ApiResponse::error(
                ['auth' => [__('auth.token_missing')]],
                __('auth.unauthorized'),
                401
            );
        }

        return $next($request);
    }
}
