<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AnalyticsController
 *
 * Handles all analytics endpoints.
 * All business logic lives in AnalyticsService.
 * This controller only validates input, delegates, and returns responses.
 */
class AnalyticsController extends ApiController
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    /**
     * GET /api/v1/analytics/restaurant/{id}
     *
     * Returns daily orders, daily revenue, AOV, and peak hour per day
     * for the given restaurant within the requested date range.
     *
     * Query params:
     *   start_date (required) — Y-m-d
     *   end_date   (required) — Y-m-d, must be >= start_date
     */
    public function restaurant(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $data = $this->analyticsService->getRestaurantAnalytics(
            $id,
            $validated['start_date'],
            $validated['end_date']
        );

        return $this->success($data, 'Restaurant analytics fetched successfully.');
    }

    /**
     * GET /api/v1/analytics/top-restaurants
     *
     * Returns the top 3 restaurants by total revenue for the date range.
     *
     * Query params:
     *   start_date (required) — Y-m-d
     *   end_date   (required) — Y-m-d, must be >= start_date
     */
    public function topRestaurants(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $data = $this->analyticsService->getTopRestaurants(
            $validated['start_date'],
            $validated['end_date']
        );

        return $this->success($data, 'Top restaurants fetched successfully.');
    }

    /**
     * GET /api/v1/analytics/orders
     *
     * Returns a paginated, filtered list of orders.
     * Passing a LengthAwarePaginator to success() automatically
     * formats the response with data[] + meta{} (see ApiController).
     *
     * Query params (all optional):
     *   restaurant_id — integer, must exist in restaurants table
     *   start_date    — Y-m-d
     *   end_date      — Y-m-d, must be >= start_date if provided
     *   min_amount    — numeric >= 0
     *   max_amount    — numeric >= 0
     *   hour_from     — integer 0–23
     *   hour_to       — integer 0–23
     *   per_page      — integer 1–50 (default 15)
     */
    public function orders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'hour_from' => ['nullable', 'integer', 'min:0', 'max:23'],
            'hour_to' => ['nullable', 'integer', 'min:0', 'max:23'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $this->analyticsService->getOrders($validated);

        return $this->success($paginator, 'Orders fetched successfully.');
    }
}
