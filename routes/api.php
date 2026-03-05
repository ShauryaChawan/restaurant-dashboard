<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\RestaurantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Public Auth Routes ──────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // ── Protected Routes (Sanctum) ──────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Restaurants
        Route::get('/restaurants', [RestaurantController::class, 'index']);
        Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);

        // Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('/restaurant/{id}', [AnalyticsController::class, 'restaurant']);
            Route::get('/top-restaurants', [AnalyticsController::class, 'topRestaurants']);
            Route::get('/orders', [AnalyticsController::class, 'orders']);
        });
    });
});
