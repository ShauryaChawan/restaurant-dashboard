<?php

namespace App\Http\Controllers\Api\V1;

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
     * GET /api/v1/restaurants/{id}
     *
     * Returns a single restaurant.
     * ModelNotFoundException is caught by the global exception handler
     * and returns a 404 JSON response automatically.
     */
    public function show(int $id): JsonResponse
    {
        $restaurant = $this->restaurantService->findById($id);

        return $this->success($restaurant, 'Restaurant fetched successfully.');
    }
}
