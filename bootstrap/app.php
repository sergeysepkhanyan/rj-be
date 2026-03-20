<?php

use App\Http\Middleware\JwtCustomMiddleware;
use App\Http\Middleware\JwtOptionalMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\RoleMiddleware;

use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

use App\Services\ApiResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'jwt.custom' => JwtCustomMiddleware::class,
            'jwt.optional' => JwtOptionalMiddleware::class,
            'cors.custom' => \App\Http\Middleware\Cors::class,
            'set.locale' => \App\Http\Middleware\SetLocale::class,
            'idempotency' => \App\Http\Middleware\IdempotencyMiddleware::class,
        ]);
        $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->append(\App\Http\Middleware\Cors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    $e->errors(),
                    __('validation.failed'),
                    422
                );
            }
            return null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(null, __('messages.unauthenticated'), 401);
            }
            return null;
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(null, __('messages.forbidden'), 403);
            }
            return null;
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(null, __('messages.resource_not_found'), 404);
            }
            return null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(null, __('messages.endpoint_not_found'), 404);
            }
            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (!($request->is('api/*') || $request->expectsJson())) {
                return null;
            }
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            $message = app()->isProduction()
                ? __('messages.something_went_wrong')
                : ($e->getMessage() ?: __('messages.server_error'));

            return ApiResponse::error(null, $message, $status);
        });
    })
    ->create();

