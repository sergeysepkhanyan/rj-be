<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\ApiResponse;

class ExceptionServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot(): void
    {
        $this->app->bind(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            function ($app) {
                return new class($app) extends \Illuminate\Foundation\Exceptions\Handler {
                    public function render($request, \Throwable $e)
                    {
                        if ($e instanceof ModelNotFoundException) {
                            return ApiResponse::error(
                                ['message' => 'Resource not found'],
                                'Not Found',
                                404
                            );
                        }

                        return parent::render($request, $e);
                    }
                };
            }
        );
    }
}


