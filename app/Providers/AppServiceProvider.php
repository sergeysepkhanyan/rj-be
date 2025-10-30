<?php

namespace App\Providers;

use App\Repositories\Interfaces\ServiceRepositoryInterface;
use App\Repositories\Interfaces\SubServiceRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\ServiceManagerRepository;
use App\Repositories\SubServiceManagerRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceManagerRepository::class);
        $this->app->bind(SubServiceRepositoryInterface::class, SubServiceManagerRepository::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
