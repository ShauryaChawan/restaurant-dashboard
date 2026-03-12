<?php

namespace App\Services;

use App\Http\Resources\RestaurantResource;
use App\Http\Resources\TopRestaurantResource;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AnalyticsService
 *
 * Owns all analytics aggregation logic.
 * All methods are cache-aware with dynamic TTL:
 *   - Historical date ranges  → 60 min TTL (data will not change)
 *   - Today or recent ranges  → 5 min TTL  (data may still be updating)
 *
 * Cache key convention: analytics_{action}_{md5(serialized params)}
 * Cache driver: file (swap to redis via CACHE_STORE=redis — zero code change)
 *
 * Important column notes:
 *   - Amount is stored as `order_amount` (decimal 10,2)
 *   - Hour is NOT stored — always derived via HOUR(ordered_at)
 *   - Status is a tinyInteger cast to OrderStatus enum by Eloquent
 */
class AnalyticsService
{
    /** TTL for historical (past-only) date ranges — 60 minutes */
    private const TTL_HISTORICAL = 3600;

    /** TTL for ranges that include today — 5 minutes */
    private const TTL_RECENT = 300;

    /** Maximum orders per page */
    private const MAX_PER_PAGE = 50;

    // ----------------------------------------
    // Public API
    // ----------------------------------------

    /**
     * Get per-restaurant analytics for a given date range.
     *
     * Returns:
     *   - daily_orders   : order count per calendar day
     *   - daily_revenue  : sum of order_amount per calendar day
     *   - avg_order_value: overall AOV across the range
     *   - peak_hours     : peak order hour per calendar day (via HOUR(ordered_at))
     *
     * @param  string  $startDate  Y-m-d
     * @param  string  $endDate  Y-m-d
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getRestaurantAnalytics(int $restaurantId, string $startDate, string $endDate): array
    {
        // Throws ModelNotFoundException → caught by global exception handler → 404
        $restaurant = Restaurant::findOrFail($restaurantId);

        $params = compact('restaurantId', 'startDate', 'endDate');
        $cacheKey = 'analytics_restaurant_'.md5(serialize($params));
        $ttl = $this->resolveTtl($startDate, $endDate);

        return Cache::remember($cacheKey, $ttl, function () use ($restaurant, $startDate, $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // Base query — cloned for each sub-aggregation so builders don't interfere
            $base = $restaurant->orders()->whereBetween('ordered_at', [$start, $end]);

            return [
                'restaurant' => new RestaurantResource($restaurant),
                'daily_orders' => $this->getDailyOrderCounts(clone $base),
                'daily_revenue' => $this->getDailyRevenue(clone $base),
                'avg_order_value' => $this->getAverageOrderValue(clone $base),
                'peak_hours' => $this->getPeakHoursPerDay(clone $base),
            ];
        });
    }

    /**
     * Get the top N restaurants by total revenue for a given date range.
     *
     * Default limit is 3 (per spec).
     *
     * @param  string  $startDate  Y-m-d
     * @param  string  $endDate  Y-m-d
     */
    public function getTopRestaurants(string $startDate, string $endDate, int $limit = 3): Collection
    {
        $params = compact('startDate', 'endDate', 'limit');
        $cacheKey = 'analytics_top_restaurants_'.md5(serialize($params));
        $ttl = $this->resolveTtl($startDate, $endDate);

        if (App::environment('testing')) {
            return $this->queryTopRestaurants($startDate, $endDate, $limit);
        }

        return Cache::remember($cacheKey, $ttl, function () use ($startDate, $endDate, $limit) {
            return $this->queryTopRestaurants($startDate, $endDate, $limit);
        });

    }

    /**
     * Get a paginated, filtered list of orders.
     *
     * Supported filters:
     *   restaurant_id — integer
     *   start_date    — Y-m-d  (filters ordered_at >= start of day)
     *   end_date      — Y-m-d  (filters ordered_at <= end of day)
     *   min_amount    — float  (filters order_amount >=)
     *   max_amount    — float  (filters order_amount <=)
     *   hour_from     — 0–23   (filters HOUR(ordered_at) >=)
     *   hour_to       — 0–23   (filters HOUR(ordered_at) <=)
     *   per_page      — integer (max 50, default 15)
     *
     * Hour filtering uses HOUR(ordered_at) — no dedicated hour column exists.
     *
     * Note: Orders list is NOT cached — paginated + multi-filter queries
     * would create an unbounded number of cache entries.
     */
    public function getOrders(array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), self::MAX_PER_PAGE);

        $query = Order::query()
            ->select('orders.*')
            ->with('restaurant:id,name')
            ->when(
                isset($filters['restaurant_id']),
                fn ($q) => $q->where('orders.restaurant_id', $filters['restaurant_id'])
            )
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('orders.status', $filters['status'])
            )
            ->when(
                isset($filters['start_date']),
                fn ($q) => $q->where('orders.ordered_at', '>=', Carbon::parse($filters['start_date'])->startOfDay())
            )
            ->when(
                isset($filters['end_date']),
                fn ($q) => $q->where('orders.ordered_at', '<=', Carbon::parse($filters['end_date'])->endOfDay())
            )
            ->when(
                isset($filters['min_amount']),
                fn ($q) => $q->where('orders.order_amount', '>=', (float) $filters['min_amount'])
            )
            ->when(
                isset($filters['max_amount']),
                fn ($q) => $q->where('orders.order_amount', '<=', (float) $filters['max_amount'])
            )
            ->when(
                isset($filters['hour_from']),
                fn ($q) => $q->whereRaw('HOUR(orders.ordered_at) >= ?', [(int) $filters['hour_from']])
            )
            ->when(
                isset($filters['hour_to']),
                fn ($q) => $q->whereRaw('HOUR(orders.ordered_at) <= ?', [(int) $filters['hour_to']])
            );

        $sortBy = $filters['sort_by'] ?? 'date';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        if ($sortBy === 'restaurant') {
            $query->join('restaurants', 'orders.restaurant_id', '=', 'restaurants.id')
                  ->orderBy('restaurants.name', $sortDir);
        } else {
            $sortColumn = match ($sortBy) {
                'id' => 'orders.id',
                'amount' => 'orders.order_amount',
                'status' => 'orders.status',
                'hour' => DB::raw('HOUR(orders.ordered_at)'),
                'date', 'default' => 'orders.ordered_at',
            };
            
            // For date with same day, we also probably want to fallback to id
            $query->orderBy($sortColumn, $sortDir);
        }

        // Add a secondary sort by ID to ensure stable pagination
        if ($sortBy !== 'id') {
            $query->orderBy('orders.id', 'desc');
        }

        return $query->paginate($perPage);
    }

    // ----------------------------------------
    // Private Aggregation Helpers
    // ----------------------------------------

    /**
     * Count orders grouped by calendar day.
     *
     * Returns: [['date' => 'YYYY-MM-DD', 'count' => int], ...]
     */
    private function getDailyOrderCounts($query): Collection
    {
        return $query
            ->select(
                DB::raw('DATE(ordered_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DATE(ordered_at)'))
            ->orderBy(DB::raw('DATE(ordered_at)'))
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'count' => (int) $row->count,
            ]);
    }

    /**
     * Sum order_amount grouped by calendar day.
     *
     * Returns: [['date' => 'YYYY-MM-DD', 'revenue' => float], ...]
     */
    private function getDailyRevenue($query): Collection
    {
        return $query
            ->select(
                DB::raw('DATE(ordered_at) as date'),
                DB::raw('SUM(order_amount) as revenue')
            )
            ->groupBy(DB::raw('DATE(ordered_at)'))
            ->orderBy(DB::raw('DATE(ordered_at)'))
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'revenue' => round((float) $row->revenue, 2),
            ]);
    }

    /**
     * Calculate average order_amount across the entire date range.
     *
     * Returns: float (rounded to 2 decimal places), or 0.0 if no orders.
     */
    private function getAverageOrderValue($query): float
    {
        $avg = $query->avg('order_amount');

        return round((float) ($avg ?? 0), 2);
    }

    /**
     * Find the hour with the most orders for each calendar day.
     *
     * Hour is derived from ordered_at using HOUR() — no dedicated column.
     *
     * Strategy: fetch all day+hour combos with their order counts,
     * then group in PHP and keep the top-count row per date.
     *
     * Returns: [['date' => 'YYYY-MM-DD', 'peak_hour' => int, 'order_count' => int], ...]
     */
    private function getPeakHoursPerDay($query): Collection
    {
        $rows = $query
            ->select(
                DB::raw('DATE(ordered_at) as date'),
                DB::raw('HOUR(ordered_at) as hour'),
                DB::raw('COUNT(*) as order_count')
            )
            ->groupBy(DB::raw('DATE(ordered_at)'), DB::raw('HOUR(ordered_at)'))
            ->orderBy(DB::raw('DATE(ordered_at)'))
            ->orderByDesc('order_count')
            ->get();

        // Group by date, take first row per date (highest order_count due to orderByDesc above)
        return $rows
            ->groupBy('date')
            ->map(fn ($dayRows) => [
                'date' => $dayRows->first()->date,
                'peak_hour' => (int) $dayRows->first()->hour,
                'order_count' => (int) $dayRows->first()->order_count,
            ])
            ->values();
    }

    // ----------------------------------------
    // Cache TTL Strategy
    // ----------------------------------------

    /**
     * Resolve cache TTL based on whether the date range includes today.
     *
     * Historical ranges (end date fully in the past) → 60 min.
     * Ranges that touch today or the future           → 5 min.
     *
     * Rationale: historical data is immutable; today's data may still
     * be receiving new orders and should not be stale for long.
     */
    private function resolveTtl(string $startDate, string $endDate): int
    {
        $end = Carbon::parse($endDate)->endOfDay();
        $isHistorical = $end->isPast() && ! $end->isToday();

        return $isHistorical ? self::TTL_HISTORICAL : self::TTL_RECENT;
    }

    private function queryTopRestaurants(string $startDate, string $endDate, int $limit)
    {
        return Restaurant::query()
            ->select(
                'restaurants.id',
                'restaurants.name',
                'restaurants.cuisine',
                'restaurants.location',
                'restaurants.rating'
            )
            ->selectRaw('SUM(orders.order_amount) as total_revenue')
            ->selectRaw('COUNT(orders.id) as total_orders')
            ->join('orders', 'restaurants.id', '=', 'orders.restaurant_id')
            ->whereBetween('orders.ordered_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->groupBy(
                'restaurants.id',
                'restaurants.name',
                'restaurants.cuisine',
                'restaurants.location',
                'restaurants.rating'
            )
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => new TopRestaurantResource($r));
    }
}
