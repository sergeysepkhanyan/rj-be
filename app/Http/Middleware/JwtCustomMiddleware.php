<?php
namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Services\ApiResponse;

class JwtCustomMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return ApiResponse::error(['general' => ['Token expired']], 'Unauthorized', 401);
        } catch (TokenInvalidException $e) {
            return ApiResponse::error(['general' => ['Token invalid']], 'Unauthorized', 401);
        } catch (JWTException $e) {
            return ApiResponse::error(['general' => ['Token not provided']], 'Unauthorized', 401);
        }

        return $next($request);
    }
}

