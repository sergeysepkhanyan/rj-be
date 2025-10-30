<?php

use App\Http\Controllers\API\Admin\ServicesController as AdminServicesController;
use App\Http\Controllers\API\Admin\SubServicesController as AdminSubServicesController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\ResetPasswordController;
use App\Http\Controllers\API\Content\PageContentController;
use App\Http\Controllers\API\UsersController;
use Illuminate\Support\Facades\Route;

Route::prefix('content')->group(function () {
    Route::get('/home', [PageContentController::class, 'home']);
    Route::get('/about', [PageContentController::class, 'about']);
    Route::get('/contact', [PageContentController::class, 'contact']);
    Route::get('/blog', [PageContentController::class, 'blog']);
    Route::get('/store', [PageContentController::class, 'store']);
    Route::get('/general', [PageContentController::class, 'general']);
});

Route::post('signup', [AuthController::class, 'signup']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('jwt.custom')->group(function () {
    Route::patch('/user/details', [UsersController::class, 'updateDetails']);
    Route::patch('/user/change-password', [UsersController::class, 'changePassword']);
    Route::get('me', function () {
        return auth()->user();
    });
});
Route::post('/password/forgot', [ResetPasswordController::class, 'forgot']);
Route::post('/password/reset', [ResetPasswordController::class, 'reset']);


Route::middleware(['jwt.custom', 'role:superadmin'])->group(function () {
    Route::get('/admin/services', [AdminServicesController::class, 'index']);
    Route::post('/admin/services', [AdminServicesController::class, 'store']);
    Route::put('/admin/services/{service}', [AdminServicesController::class, 'update']);
    Route::post('/admin/sub-services', [AdminSubServicesController::class, 'store']);
    Route::put('/admin/sub-services/{subService}', [AdminSubServicesController::class, 'update']);
});

