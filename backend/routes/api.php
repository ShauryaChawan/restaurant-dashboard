<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\RestaurantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->as('api.v1.')
    ->group(function () {

        // --- Public Auth Routes ----------------------------------------
        Route::prefix('auth')
            ->as('auth.')
            ->group(function () {
                Route::middleware('throttle:60,1')
                    ->post('/register', [AuthController::class, 'register'])
                    ->name('register');

                Route::middleware('throttle:60,1')
                    ->post('/login', [AuthController::class, 'login'])
                    ->name('login');
            });

        // --- Protected Routes (Sanctum) ----------------------------------------
        Route::middleware('auth:sanctum')->group(function () {

            Route::post('/auth/logout', [AuthController::class, 'logout'])
                ->name('auth.logout');

            Route::get('/auth/me', [AuthController::class, 'me'])
                ->name('auth.me');

            // Restaurants
            Route::prefix('restaurants')
                ->as('restaurants.')
                ->group(function () {

                    Route::get('/', [RestaurantController::class, 'index'])
                        ->name('index');

                    Route::get('/{restaurant}', [RestaurantController::class, 'show'])
                        ->name('show');
                });

            // Analytics
            Route::prefix('analytics')
                ->as('analytics.')
                ->group(function () {

                    Route::get('/restaurant/{restaurant}', [AnalyticsController::class, 'restaurant'])
                        ->name('restaurant');

                    Route::get('/top-restaurants', [AnalyticsController::class, 'topRestaurants'])
                        ->name('top-restaurants');

                    Route::get('/orders', [AnalyticsController::class, 'orders'])
                        ->name('orders');
                });
        });
    });
