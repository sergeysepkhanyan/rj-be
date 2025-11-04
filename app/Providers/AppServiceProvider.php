<?php

namespace App\Providers;

use App\Repositories\Interfaces\ServiceRepositoryInterface;
use App\Repositories\Interfaces\SubServiceItemRepositoryInterface;
use App\Repositories\Interfaces\SubServiceItemVariantRepositoryInterface;
use App\Repositories\Interfaces\SubServiceRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\UserRoleRepositoryInterface;
use App\Repositories\ServiceManagerRepository;
use App\Repositories\SubServiceItemManagerRepository;
use App\Repositories\SubServiceItemVariantManagerRepository;
use App\Repositories\SubServiceManagerRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserRoleRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRoleRepositoryInterface::class, UserRoleRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceManagerRepository::class);
        $this->app->bind(SubServiceRepositoryInterface::class, SubServiceManagerRepository::class);
        $this->app->bind(SubServiceItemRepositoryInterface::class, SubServiceItemManagerRepository::class);
        $this->app->bind(SubServiceItemVariantRepositoryInterface::class, SubServiceItemVariantManagerRepository::class);


    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
