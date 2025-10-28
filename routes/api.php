<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Content\PageContentController;
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

Route::middleware('auth:api')->group(function () {
    Route::get('me', function () {
        return auth()->user();
    });
});


