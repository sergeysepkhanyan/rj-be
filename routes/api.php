<?php

use App\Http\Controllers\API\Admin\ReferralsController;
use App\Http\Controllers\API\ProductsController;
use App\Http\Controllers\API\Admin\ProductsController as AdminProductsController;
use App\Http\Controllers\API\Admin\ServicesController as AdminServicesController;
use App\Http\Controllers\API\Admin\SubServicesController as AdminSubServicesController;
use App\Http\Controllers\API\ServicesController as ServicesController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\ResetPasswordController;
use App\Http\Controllers\API\Content\PageContentController;
use App\Http\Controllers\API\FilesController;
use App\Http\Controllers\API\UsersController;
use App\Http\Controllers\API\Admin\StaffController;
use App\Http\Controllers\API\WeekdaysController;
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

    Route::post('image/upload', [FilesController::class, 'upload']);
    Route::post('image/upload-multiple', [FilesController::class, 'uploadMultiple']);

    Route::patch('/user/details', [UsersController::class, 'updateDetails']);
    Route::patch('/user/change-password', [UsersController::class, 'changePassword']);
    Route::get('me', function () {
        return auth()->user();
    });

    Route::get('/products', [ProductsController::class, 'index']);
});
Route::post('/password/forgot', [ResetPasswordController::class, 'forgot']);
Route::post('/password/reset', [ResetPasswordController::class, 'reset']);


Route::middleware(['jwt.custom', 'role:superadmin,admin'])->group(function () {

    Route::post('/admin/services', [AdminServicesController::class, 'store']);
    Route::put('/admin/services/{service}', [AdminServicesController::class, 'update']);

    Route::post('/admin/sub-services', [AdminSubServicesController::class, 'store']);
    Route::put('/admin/sub-services/{subService}', [AdminSubServicesController::class, 'update']);

    Route::get('/admin/staff', [StaffController::class, 'index']);
    Route::post('/admin/staff/create', [StaffController::class, 'store']);
    Route::put('/admin/staff/update/{id}', [StaffController::class, 'update']);
    Route::delete('/admin/staff/delete/{id}', [StaffController::class, 'destroy']);;
    Route::post('/admin/staff/create-many', [StaffController::class, 'createMany']);
    Route::post('/admin/staff/update-many', [StaffController::class, 'updateMany']);
    Route::patch('/admin/staff/add-referral/{id}', [StaffController::class, 'addReferral']);

    Route::post('/admin/product/create', [AdminProductsController::class, 'store']);

    Route::get('/referrals', [ReferralsController::class, 'index']);
});

Route::get('/services', [ServicesController::class, 'index']);
Route::get('/weekdays', [WeekdaysController::class, 'index']);
