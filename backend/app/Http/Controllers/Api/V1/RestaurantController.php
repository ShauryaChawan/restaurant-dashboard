<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Restaurant;
use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantController extends ApiController
{
    public function __construct(
        protected RestaurantService $restaurantService
    ) {}

    /**
     * GET /api/v1/restaurants
     *
     * Returns a paginated, filtered, sorted list of restaurants.
     * Caching is handled entirely within RestaurantService.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurants = $this->restaurantService->getPaginated($request);

        return $this->success($restaurants, 'Restaurants fetched successfully.');
    }

    /**
     * GET /api/v1/restaurants/{restaurant}
     *
     * Returns a single restaurant.
     * Uses route model binding to resolve the Restaurant model.
     */
    public function show(Restaurant $restaurant): JsonResponse
    {
        return $this->success($restaurant, 'Restaurant fetched successfully.');
    }
}
