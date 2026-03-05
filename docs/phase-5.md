# 🍽️ Restaurant Analytics Platform
## Phase 5 — Auth UI + Analytics Module (API + Frontend)
### Detailed Actionable Documentation

> **Focus:** Login & Register pages, Analytics backend (3 endpoints), Analytics frontend (charts, filters, orders table), and full Pest test coverage. This phase completes the entire application.

---

| Attribute | Decision |
|---|---|
| New Endpoints | `GET /api/v1/analytics/restaurant/{id}`, `GET /api/v1/analytics/top-restaurants`, `GET /api/v1/analytics/orders` |
| Auth UI | Login + Register pages, `localStorage` + Bearer token |
| Charts | Recharts — LineChart (daily orders), BarChart (daily revenue), stat card (AOV), BarChart (peak hour) |
| Aggregation | All logic in `AnalyticsService` — controllers stay thin |
| Caching | File cache, TTL 60 min for historical ranges / 5 min for today |
| Cache Key | `analytics_` prefix + `md5` of serialized params |
| Column names | `order_amount` (not `amount`), no `hour` column — hour derived via `HOUR(ordered_at)` |
| Hour filter | Frontend sends `hour_from` + `hour_to` range; backend uses `HOUR(ordered_at)` |
| Status | `OrderStatus` int-backed enum — label shown via frontend lookup map |
| Pagination shape | `ApiController::success()` with `LengthAwarePaginator` outputs `data` array + `meta` object |

---

## Table of Contents

- [🍽️ Restaurant Analytics Platform](#️-restaurant-analytics-platform)
  - [Phase 5 — Auth UI + Analytics Module (API + Frontend)](#phase-5--auth-ui--analytics-module-api--frontend)
    - [Detailed Actionable Documentation](#detailed-actionable-documentation)
  - [Table of Contents](#table-of-contents)
  - [1. Phase Goals \& Deliverables](#1-phase-goals--deliverables)
    - [Deliverables Checklist](#deliverables-checklist)
  - [2. Key Schema Facts to Remember](#2-key-schema-facts-to-remember)
  - [3. AnalyticsService](#3-analyticsservice)
  - [4. AnalyticsController](#4-analyticscontroller)
  - [5. API Routes — Recap](#5-api-routes--recap)
  - [6. API Response Shapes](#6-api-response-shapes)
    - [Restaurant Analytics — plain `success()` (not paginated)](#restaurant-analytics--plain-success-not-paginated)
    - [Top Restaurants — plain `success()` (not paginated)](#top-restaurants--plain-success-not-paginated)
    - [Orders List — `LengthAwarePaginator` passed to plain `success()`](#orders-list--lengthawarepaginator-passed-to-plain-success)
  - [7. Frontend — Auth Pages](#7-frontend--auth-pages)
    - [7.1 — AuthContext \& useAuth Hook](#71--authcontext--useauth-hook)
    - [7.2 — Login Page](#72--login-page)
    - [7.3 — Register Page](#73--register-page)
    - [7.4 — app.jsx Update](#74--appjsx-update)
    - [7.5 — AppRouter Update](#75--approuter-update)
    - [7.6 — PrivateRoute Update](#76--privateroute-update)
  - [8. Frontend — API Layer (Analytics)](#8-frontend--api-layer-analytics)
  - [9. Frontend — Analytics Hooks](#9-frontend--analytics-hooks)
  - [10. Frontend — Dashboard Page (Top 3)](#10-frontend--dashboard-page-top-3)
  - [11. Frontend — Restaurant Analytics Page](#11-frontend--restaurant-analytics-page)
    - [11.1 — Chart Components](#111--chart-components)
    - [11.2 — Orders Table Component](#112--orders-table-component)
    - [11.3 — Analytics Page Assembly](#113--analytics-page-assembly)
  - [12. Frontend — Folder Structure After Phase 5](#12-frontend--folder-structure-after-phase-5)
  - [13. Pest Tests — Phase 5](#13-pest-tests--phase-5)
    - [Running Phase 5 Tests](#running-phase-5-tests)
  - [14. Phase 5 Completion Checklist](#14-phase-5-completion-checklist)

---

## 1. Phase Goals & Deliverables

By the end of Phase 5 the application is fully complete and usable end-to-end:
- A user can register, log in, and log out
- They can browse the restaurant list (Phase 4)
- Click a restaurant to see analytics — charts, stats, and a filtered orders table
- Visit the dashboard for a global Top 3 view with date range filters

### Deliverables Checklist

- [ ] `AnalyticsService` with all aggregation methods
- [ ] `AnalyticsController` with 3 endpoints
- [ ] Analytics routes already in `api.php` — confirm `AnalyticsController` is imported
- [ ] `AuthContext` + `useAuth` hook
- [ ] `Login.jsx` page — form, validation, token storage, redirect
- [ ] `Register.jsx` page — form, Laravel 422 errors mapped, redirect to login
- [ ] `app.jsx` updated to wrap with `AuthProvider`
- [ ] `AppRouter.jsx` updated with all new routes
- [ ] `PrivateRoute.jsx` updated to use `useAuth`
- [ ] `analytics.js` API layer
- [ ] `useRestaurantAnalytics`, `useTopRestaurants`, `useOrders` hooks
- [ ] `Dashboard.jsx` — Top 3 widget + date range filter
- [ ] Chart components: `DailyOrdersChart`, `DailyRevenueChart`, `AovCard`, `PeakHourChart`
- [ ] `OrdersTable.jsx` — paginated, with date + hour range + amount range filters
- [ ] `RestaurantAnalytics.jsx` — charts + orders table assembled
- [ ] All Phase 5 Pest tests passing
- [ ] Full test suite still green

---

## 2. Key Schema Facts to Remember

Read this before touching any backend code. Every query in `AnalyticsService` must match these exactly.

| Fact | Detail |
|---|---|
| Amount column | `order_amount` — `decimal(10,2)` — **not** `amount` |
| Datetime column | `ordered_at` — single datetime column |
| Hour extraction | No `hour` column. Always use `HOUR(ordered_at)` in raw SQL |
| Status column | `status` — `tinyInteger`, Eloquent casts to `OrderStatus` enum |
| Status values | 0 = Failed, 1 = Completed, 2 = Pending, 3 = In Progress |
| Pagination response | `ApiController::success(LengthAwarePaginator)` → `data[]` + `meta{}` (not `data.data`) |

---

## 3. AnalyticsService

> 📄 **File:** `app/Services/AnalyticsService.php`

```php
<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    // ─────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Get per-restaurant analytics for a given date range.
     *
     * Returns:
     *   - daily_orders   : order count per calendar day
     *   - daily_revenue  : sum of order_amount per calendar day
     *   - avg_order_value: overall AOV across the range
     *   - peak_hours     : peak order hour per calendar day (via HOUR(ordered_at))
     *
     * @param  int    $restaurantId
     * @param  string $startDate  Y-m-d
     * @param  string $endDate    Y-m-d
     * @return array
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getRestaurantAnalytics(int $restaurantId, string $startDate, string $endDate): array
    {
        // Throws ModelNotFoundException → caught by global exception handler → 404
        $restaurant = Restaurant::findOrFail($restaurantId);

        $params   = compact('restaurantId', 'startDate', 'endDate');
        $cacheKey = 'analytics_restaurant_' . md5(serialize($params));
        $ttl      = $this->resolveTtl($startDate, $endDate);

        return Cache::remember($cacheKey, $ttl, function () use ($restaurant, $startDate, $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end   = Carbon::parse($endDate)->endOfDay();

            // Base query — cloned for each sub-aggregation so builders don't interfere
            $base = $restaurant->orders()->whereBetween('ordered_at', [$start, $end]);

            return [
                'restaurant'      => [
                    'id'       => $restaurant->id,
                    'name'     => $restaurant->name,
                    'cuisine'  => $restaurant->cuisine,
                    'location' => $restaurant->location,
                    'rating'   => $restaurant->rating,
                ],
                'daily_orders'    => $this->getDailyOrderCounts(clone $base),
                'daily_revenue'   => $this->getDailyRevenue(clone $base),
                'avg_order_value' => $this->getAverageOrderValue(clone $base),
                'peak_hours'      => $this->getPeakHoursPerDay(clone $base),
            ];
        });
    }

    /**
     * Get the top N restaurants by total revenue for a given date range.
     *
     * Default limit is 3 (per spec).
     *
     * @param  string $startDate  Y-m-d
     * @param  string $endDate    Y-m-d
     * @param  int    $limit
     * @return Collection
     */
    public function getTopRestaurants(string $startDate, string $endDate, int $limit = 3): Collection
    {
        $params   = compact('startDate', 'endDate', 'limit');
        $cacheKey = 'analytics_top_restaurants_' . md5(serialize($params));
        $ttl      = $this->resolveTtl($startDate, $endDate);

        return Cache::remember($cacheKey, $ttl, function () use ($startDate, $endDate, $limit) {
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
                ->get();
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
     *
     * @param  array $filters
     * @return LengthAwarePaginator
     */
    public function getOrders(array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), self::MAX_PER_PAGE);

        return Order::query()
            ->with('restaurant:id,name')
            ->when(
                isset($filters['restaurant_id']),
                fn ($q) => $q->where('restaurant_id', $filters['restaurant_id'])
            )
            ->when(
                isset($filters['start_date']),
                fn ($q) => $q->where('ordered_at', '>=', Carbon::parse($filters['start_date'])->startOfDay())
            )
            ->when(
                isset($filters['end_date']),
                fn ($q) => $q->where('ordered_at', '<=', Carbon::parse($filters['end_date'])->endOfDay())
            )
            ->when(
                isset($filters['min_amount']),
                fn ($q) => $q->where('order_amount', '>=', (float) $filters['min_amount'])
            )
            ->when(
                isset($filters['max_amount']),
                fn ($q) => $q->where('order_amount', '<=', (float) $filters['max_amount'])
            )
            ->when(
                isset($filters['hour_from']),
                fn ($q) => $q->whereRaw('HOUR(ordered_at) >= ?', [(int) $filters['hour_from']])
            )
            ->when(
                isset($filters['hour_to']),
                fn ($q) => $q->whereRaw('HOUR(ordered_at) <= ?', [(int) $filters['hour_to']])
            )
            ->orderBy('ordered_at', 'desc')
            ->paginate($perPage);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private Aggregation Helpers
    // ─────────────────────────────────────────────────────────────────────

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
                'date'  => $row->date,
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
                'date'    => $row->date,
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
                'date'        => $dayRows->first()->date,
                'peak_hour'   => (int) $dayRows->first()->hour,
                'order_count' => (int) $dayRows->first()->order_count,
            ])
            ->values();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cache TTL Strategy
    // ─────────────────────────────────────────────────────────────────────

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
        $end          = Carbon::parse($endDate)->endOfDay();
        $isHistorical = $end->isPast() && ! $end->isToday();

        return $isHistorical ? self::TTL_HISTORICAL : self::TTL_RECENT;
    }
}
```

---

## 4. AnalyticsController

> 📄 **File:** `app/Http/Controllers/Api/V1/AnalyticsController.php`

```php
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
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
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
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
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
            'start_date'    => ['nullable', 'date_format:Y-m-d'],
            'end_date'      => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'min_amount'    => ['nullable', 'numeric', 'min:0'],
            'max_amount'    => ['nullable', 'numeric', 'min:0'],
            'hour_from'     => ['nullable', 'integer', 'min:0', 'max:23'],
            'hour_to'       => ['nullable', 'integer', 'min:0', 'max:23'],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $this->analyticsService->getOrders($validated);

        return $this->success($paginator, 'Orders fetched successfully.');
    }
}
```

---

## 5. API Routes — Recap

Your `routes/api.php` already has the analytics routes. Confirm `AnalyticsController` is imported at the top:

```php
use App\Http\Controllers\Api\V1\AnalyticsController;
```

The routes block should match:

```php
Route::prefix('analytics')->group(function () {
    Route::get('/restaurant/{id}', [AnalyticsController::class, 'restaurant']);
    Route::get('/top-restaurants', [AnalyticsController::class, 'topRestaurants']);
    Route::get('/orders',          [AnalyticsController::class, 'orders']);
});
```

No other changes to `api.php` are needed.

---

## 6. API Response Shapes

### Restaurant Analytics — plain `success()` (not paginated)

```json
{
  "status": "success",
  "message": "Restaurant analytics fetched successfully.",
  "data": {
    "restaurant": { "id": 1, "name": "Tandoori Treats", "cuisine": "Indian", "location": "Mumbai", "rating": null },
    "daily_orders":    [{ "date": "2024-01-01", "count": 12 }],
    "daily_revenue":   [{ "date": "2024-01-01", "revenue": 4820.50 }],
    "avg_order_value": 387.25,
    "peak_hours":      [{ "date": "2024-01-01", "peak_hour": 13, "order_count": 5 }]
  }
}
```

### Top Restaurants — plain `success()` (not paginated)

```json
{
  "status": "success",
  "message": "Top restaurants fetched successfully.",
  "data": [
    { "id": 2, "name": "Burger Hub", "cuisine": "American", "location": "Delhi", "rating": null, "total_revenue": "98420.00", "total_orders": 87 }
  ]
}
```

### Orders List — `LengthAwarePaginator` passed to plain `success()`

`ApiController::success()` does `'data' => $data` with no special handling. When `$data` is a `LengthAwarePaginator`, Laravel serializes it to its standard JSON form — items land in `data.data`, and pagination fields (`total`, `last_page`, `per_page`, `current_page`, `from`, `to`) sit alongside as `data.total`, `data.last_page`, etc. **There is no separate `meta` object.** This is the same shape as the restaurant listing in Phase 4.

```json
{
  "status": "success",
  "message": "Orders fetched successfully.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 42,
        "restaurant_id": 1,
        "order_amount": "450.00",
        "status": 1,
        "ordered_at": "2024-01-05T13:00:00.000000Z",
        "created_at": "2024-01-05T13:00:00.000000Z",
        "updated_at": "2024-01-05T13:00:00.000000Z",
        "restaurant": { "id": 1, "name": "Tandoori Treats" }
      }
    ],
    "first_page_url": "...",
    "from": 1,
    "last_page": 14,
    "last_page_url": "...",
    "links": [],
    "next_page_url": "...",
    "path": "...",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 200
  }
}
```

> 💡 **Status in JSON:** Eloquent serializes `OrderStatus` as its raw integer value (e.g. `1`). The frontend maps integers to labels using a local lookup object — see `OrdersTable.jsx` in Section 11.2.
>
> | Value | Label |
> |---|---|
> | 0 | Failed |
> | 1 | Completed |
> | 2 | Pending |
> | 3 | In Progress |

---

## 7. Frontend — Auth Pages

### 7.1 — AuthContext & useAuth Hook

> 📄 **New file:** `resources/js/context/AuthContext.jsx`

```jsx
import { createContext, useContext, useState, useCallback } from 'react';

const AuthContext = createContext(null);

/**
 * AuthProvider
 *
 * Wraps the entire app. Reads token from localStorage on mount
 * so auth state survives a page refresh.
 */
export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem('auth_token'));
  const [user, setUser]   = useState(null);

  /**
   * Store token in state + localStorage.
   * Called after a successful login response.
   */
  const login = useCallback((newToken, userData = null) => {
    localStorage.setItem('auth_token', newToken);
    setToken(newToken);
    setUser(userData);
  }, []);

  /**
   * Remove token from state + localStorage.
   * Called on logout or when a 401 is intercepted by Axios.
   */
  const logout = useCallback(() => {
    localStorage.removeItem('auth_token');
    setToken(null);
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ token, user, isAuthenticated: Boolean(token), login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

/**
 * useAuth — consume auth context from any component.
 *
 * Usage:
 *   const { isAuthenticated, login, logout, token } = useAuth();
 */
export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within <AuthProvider>');
  return ctx;
}
```

### 7.2 — Login Page

> 📄 **New file:** `resources/js/pages/auth/Login.jsx`

```jsx
import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import api from '../../api/axios';

export default function Login() {
  const navigate       = useNavigate();
  const [searchParams] = useSearchParams();
  const { login }      = useAuth();

  const [form, setForm]               = useState({ email: '', password: '' });
  const [errors, setErrors]           = useState({});
  const [serverError, setServerError] = useState('');
  const [loading, setLoading]         = useState(false);

  // Show success banner if redirected here after registration
  const justRegistered = searchParams.get('registered') === '1';

  function handleChange(e) {
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }));
    setErrors(prev => ({ ...prev, [e.target.name]: '' }));
  }

  function validate() {
    const errs = {};
    if (!form.email)    errs.email    = 'Email is required.';
    if (!form.password) errs.password = 'Password is required.';
    return errs;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setServerError('');
    const errs = validate();
    if (Object.keys(errs).length) { setErrors(errs); return; }

    setLoading(true);
    try {
      const res = await api.post('/auth/login', form);
      // Response shape: { status, message, data: { token, user } }
      login(res.data.data.token, res.data.data.user);
      navigate('/dashboard');
    } catch (err) {
      setServerError(err.response?.data?.message ?? 'Login failed. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="w-full max-w-sm">

        <div className="mb-8 text-center">
          <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">Restaurant Analytics</h1>
          <p className="text-sm text-gray-500 mt-1">Sign in to your account</p>
        </div>

        <div className="bg-white border border-gray-200 rounded-xl p-8 shadow-sm">

          {justRegistered && (
            <div className="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
              Account created — you can now sign in.
            </div>
          )}

          {serverError && (
            <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
              {serverError}
            </div>
          )}

          <form onSubmit={handleSubmit} noValidate>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input
                type="email" name="email" value={form.email} onChange={handleChange}
                placeholder="you@example.com"
                className={`w-full rounded-lg border px-3 py-2 text-sm outline-none transition
                  focus:ring-2 focus:ring-gray-900 focus:border-transparent
                  ${errors.email ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'}`}
              />
              {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
            </div>

            <div className="mb-6">
              <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
              <input
                type="password" name="password" value={form.password} onChange={handleChange}
                placeholder="••••••••"
                className={`w-full rounded-lg border px-3 py-2 text-sm outline-none transition
                  focus:ring-2 focus:ring-gray-900 focus:border-transparent
                  ${errors.password ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'}`}
              />
              {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
            </div>

            <button
              type="submit" disabled={loading}
              className="w-full rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white
                hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2
                disabled:opacity-50 disabled:cursor-not-allowed transition"
            >
              {loading ? 'Signing in…' : 'Sign in'}
            </button>
          </form>
        </div>

        <p className="mt-4 text-center text-sm text-gray-500">
          Don't have an account?{' '}
          <Link to="/register" className="text-gray-900 font-medium hover:underline">Register</Link>
        </p>

      </div>
    </div>
  );
}
```

### 7.3 — Register Page

> 📄 **New file:** `resources/js/pages/auth/Register.jsx`

```jsx
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../../api/axios';

export default function Register() {
  const navigate = useNavigate();

  const [form, setForm] = useState({
    username: '', email: '', password: '', password_confirmation: '',
  });
  const [errors, setErrors]           = useState({});
  const [serverError, setServerError] = useState('');
  const [loading, setLoading]         = useState(false);

  function handleChange(e) {
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }));
    setErrors(prev => ({ ...prev, [e.target.name]: '' }));
  }

  function validate() {
    const errs = {};
    if (!form.username) errs.username = 'Username is required.';
    if (!form.email)    errs.email    = 'Email is required.';
    if (!form.password) errs.password = 'Password is required.';
    else if (form.password.length < 8) errs.password = 'Password must be at least 8 characters.';
    if (form.password !== form.password_confirmation)
      errs.password_confirmation = 'Passwords do not match.';
    return errs;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setServerError('');
    const errs = validate();
    if (Object.keys(errs).length) { setErrors(errs); return; }

    setLoading(true);
    try {
      await api.post('/auth/register', form);
      navigate('/login?registered=1');
    } catch (err) {
      if (err.response?.status === 422) {
        // Map Laravel validation errors: { field: ['message'] } → { field: 'message' }
        const laravelErrors = err.response.data?.errors ?? {};
        const mapped = {};
        Object.keys(laravelErrors).forEach(key => { mapped[key] = laravelErrors[key][0]; });
        setErrors(mapped);
      } else {
        setServerError(err.response?.data?.message ?? 'Registration failed. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  }

  const fields = [
    { name: 'username',              label: 'Username',         type: 'text',     placeholder: 'johndoe' },
    { name: 'email',                 label: 'Email',            type: 'email',    placeholder: 'you@example.com' },
    { name: 'password',              label: 'Password',         type: 'password', placeholder: '••••••••' },
    { name: 'password_confirmation', label: 'Confirm Password', type: 'password', placeholder: '••••••••' },
  ];

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="w-full max-w-sm">

        <div className="mb-8 text-center">
          <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">Create an account</h1>
          <p className="text-sm text-gray-500 mt-1">Get started with Restaurant Analytics</p>
        </div>

        <div className="bg-white border border-gray-200 rounded-xl p-8 shadow-sm">
          {serverError && (
            <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
              {serverError}
            </div>
          )}

          <form onSubmit={handleSubmit} noValidate>
            {fields.map(field => (
              <div key={field.name} className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-1">{field.label}</label>
                <input
                  type={field.type} name={field.name}
                  value={form[field.name]} onChange={handleChange}
                  placeholder={field.placeholder}
                  className={`w-full rounded-lg border px-3 py-2 text-sm outline-none transition
                    focus:ring-2 focus:ring-gray-900 focus:border-transparent
                    ${errors[field.name] ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'}`}
                />
                {errors[field.name] && (
                  <p className="mt-1 text-xs text-red-600">{errors[field.name]}</p>
                )}
              </div>
            ))}

            <button
              type="submit" disabled={loading}
              className="mt-2 w-full rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white
                hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2
                disabled:opacity-50 disabled:cursor-not-allowed transition"
            >
              {loading ? 'Creating account…' : 'Create account'}
            </button>
          </form>
        </div>

        <p className="mt-4 text-center text-sm text-gray-500">
          Already have an account?{' '}
          <Link to="/login" className="text-gray-900 font-medium hover:underline">Sign in</Link>
        </p>
      </div>
    </div>
  );
}
```

### 7.4 — app.jsx Update

Add `AuthProvider` around `AppRouter`. `QueryClientProvider` already exists from Phase 4.

> 📄 **Update:** `resources/js/app.jsx`

```jsx
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './context/AuthContext';
import AppRouter from './routes/AppRouter';
import '../css/app.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 1000 * 60 * 5, retry: 1 },
  },
});

createRoot(document.getElementById('app')).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <AppRouter />
      </AuthProvider>
    </QueryClientProvider>
  </StrictMode>
);
```

### 7.5 — AppRouter Update

> 📄 **Update:** `resources/js/routes/AppRouter.jsx`

```jsx
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import PrivateRoute        from './PrivateRoute';
import Login               from '../pages/auth/Login';
import Register            from '../pages/auth/Register';
import Dashboard           from '../pages/dashboard/Dashboard';
import RestaurantList      from '../pages/restaurants/RestaurantList';
import RestaurantAnalytics from '../pages/analytics/RestaurantAnalytics';

export default function AppRouter() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Public */}
        <Route path="/login"    element={<Login />} />
        <Route path="/register" element={<Register />} />

        {/* Protected */}
        <Route element={<PrivateRoute />}>
          <Route path="/dashboard"                 element={<Dashboard />} />
          <Route path="/restaurants"               element={<RestaurantList />} />
          <Route path="/restaurants/:id/analytics" element={<RestaurantAnalytics />} />
        </Route>

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
```

### 7.6 — PrivateRoute Update

> 📄 **Update:** `resources/js/routes/PrivateRoute.jsx`

```jsx
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function PrivateRoute() {
  const { isAuthenticated } = useAuth();
  return isAuthenticated ? <Outlet /> : <Navigate to="/login" replace />;
}
```

---

## 8. Frontend — API Layer (Analytics)

> 📄 **New file:** `resources/js/api/analytics.js`

```js
import api from './axios';

/**
 * Fetch analytics for a single restaurant.
 *
 * @param {number} restaurantId
 * @param {string} startDate — 'YYYY-MM-DD'
 * @param {string} endDate   — 'YYYY-MM-DD'
 * @returns {Promise<object>} — the `data` key from the API response
 */
export function fetchRestaurantAnalytics(restaurantId, startDate, endDate) {
  return api
    .get(`/analytics/restaurant/${restaurantId}`, {
      params: { start_date: startDate, end_date: endDate },
    })
    .then(res => res.data.data);
}

/**
 * Fetch top 3 restaurants by revenue.
 *
 * @param {string} startDate — 'YYYY-MM-DD'
 * @param {string} endDate   — 'YYYY-MM-DD'
 * @returns {Promise<Array>}
 */
export function fetchTopRestaurants(startDate, endDate) {
  return api
    .get('/analytics/top-restaurants', {
      params: { start_date: startDate, end_date: endDate },
    })
    .then(res => res.data.data);
}

/**
 * Fetch paginated, filtered orders list.
 *
 * The ApiController returns paginated orders as:
 *   { status, message, data: [...items], meta: { current_page, per_page, total, last_page, from, to } }
 *
 * This function returns { data, meta } directly so hooks can destructure cleanly.
 *
 * @param {object} filters — {
 *   restaurant_id?, start_date?, end_date?,
 *   min_amount?, max_amount?,
 *   hour_from?, hour_to?,
 *   page?, per_page?
 * }
 * @returns {Promise<{ data: Array, meta: object }>}
 */
export function fetchOrders(filters = {}) {
  // Strip undefined/null/empty so they don't appear as blank query params
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v !== undefined && v !== null && v !== '')
  );
  return api.get('/analytics/orders', { params }).then(res => ({
    data: res.data.data,
    meta: res.data.meta,
  }));
}
```

---

## 9. Frontend — Analytics Hooks

> 📄 **New file:** `resources/js/hooks/useRestaurantAnalytics.js`

```js
import { useQuery } from '@tanstack/react-query';
import { fetchRestaurantAnalytics } from '../api/analytics';

export function useRestaurantAnalytics(restaurantId, startDate, endDate) {
  return useQuery({
    queryKey: ['analytics', 'restaurant', restaurantId, startDate, endDate],
    queryFn:  () => fetchRestaurantAnalytics(restaurantId, startDate, endDate),
    enabled:  Boolean(restaurantId && startDate && endDate),
    staleTime: 1000 * 60 * 5,
  });
}
```

> 📄 **New file:** `resources/js/hooks/useTopRestaurants.js`

```js
import { useQuery } from '@tanstack/react-query';
import { fetchTopRestaurants } from '../api/analytics';

export function useTopRestaurants(startDate, endDate) {
  return useQuery({
    queryKey: ['analytics', 'top-restaurants', startDate, endDate],
    queryFn:  () => fetchTopRestaurants(startDate, endDate),
    enabled:  Boolean(startDate && endDate),
    staleTime: 1000 * 60 * 5,
  });
}
```

> 📄 **New file:** `resources/js/hooks/useOrders.js`

```js
import { useQuery } from '@tanstack/react-query';
import { fetchOrders } from '../api/analytics';

export function useOrders(filters) {
  return useQuery({
    queryKey:        ['orders', filters],
    queryFn:         () => fetchOrders(filters),
    placeholderData: (prev) => prev, // TanStack v5 — keeps previous page while loading next
    staleTime:       1000 * 60 * 2,
  });
}

// Note: if you're on TanStack Query v4, replace placeholderData with: keepPreviousData: true
// Check version: npm list @tanstack/react-query
```

---

## 10. Frontend — Dashboard Page (Top 3)

> 📄 **New file:** `resources/js/pages/dashboard/Dashboard.jsx`

```jsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { useTopRestaurants } from '../../hooks/useTopRestaurants';
import api from '../../api/axios';

function defaultDates() {
  const end   = new Date();
  const start = new Date();
  start.setDate(start.getDate() - 6);
  return {
    startDate: start.toISOString().split('T')[0],
    endDate:   end.toISOString().split('T')[0],
  };
}

export default function Dashboard() {
  const navigate   = useNavigate();
  const { logout } = useAuth();

  const [dates, setDates]         = useState(defaultDates());
  const [dateInput, setDateInput] = useState(dates);

  const { data: topRestaurants, isLoading, isError } = useTopRestaurants(
    dates.startDate,
    dates.endDate
  );

  async function handleLogout() {
    try { await api.post('/auth/logout'); } catch (_) { /* ignore */ }
    logout();
    navigate('/login');
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-900">Restaurant Analytics</h1>
        <div className="flex items-center gap-4">
          <button onClick={() => navigate('/restaurants')}
            className="text-sm text-gray-600 hover:text-gray-900 transition">
            Restaurants
          </button>
          <button onClick={handleLogout}
            className="text-sm text-red-500 hover:text-red-700 transition">
            Logout
          </button>
        </div>
      </header>

      <main className="max-w-5xl mx-auto px-6 py-10">
        {/* Heading + date filter */}
        <div className="mb-8 flex flex-col sm:flex-row sm:items-end gap-4">
          <div>
            <h2 className="text-xl font-semibold text-gray-900">Dashboard</h2>
            <p className="text-sm text-gray-500 mt-0.5">Top 3 restaurants by revenue</p>
          </div>
          <div className="sm:ml-auto flex flex-wrap items-end gap-3">
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">From</label>
              <input type="date" value={dateInput.startDate}
                onChange={e => setDateInput(p => ({ ...p, startDate: e.target.value }))}
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none
                  focus:ring-2 focus:ring-gray-900 focus:border-transparent" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">To</label>
              <input type="date" value={dateInput.endDate}
                onChange={e => setDateInput(p => ({ ...p, endDate: e.target.value }))}
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none
                  focus:ring-2 focus:ring-gray-900 focus:border-transparent" />
            </div>
            <button onClick={() => setDates(dateInput)}
              className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white
                hover:bg-gray-700 transition">
              Apply
            </button>
          </div>
        </div>

        {/* Loading */}
        {isLoading && (
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
            {[1, 2, 3].map(i => (
              <div key={i} className="bg-white rounded-xl border border-gray-200 p-6 h-44 animate-pulse" />
            ))}
          </div>
        )}

        {/* Error */}
        {isError && (
          <div className="rounded-xl bg-red-50 border border-red-200 px-6 py-4 text-sm text-red-700">
            Failed to load top restaurants. Please try again.
          </div>
        )}

        {/* Empty */}
        {!isLoading && !isError && topRestaurants?.length === 0 && (
          <div className="rounded-xl bg-gray-50 border border-gray-200 px-6 py-10 text-center text-sm text-gray-500">
            No orders found for the selected date range.
          </div>
        )}

        {/* Top 3 cards */}
        {!isLoading && !isError && topRestaurants?.length > 0 && (
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
            {topRestaurants.map((r, idx) => (
              <div key={r.id}
                onClick={() => navigate(`/restaurants/${r.id}/analytics`)}
                className="bg-white rounded-xl border border-gray-200 p-6 cursor-pointer
                  hover:shadow-md hover:border-gray-300 transition group"
              >
                <div className="flex items-center justify-between mb-1">
                  <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">#{idx + 1}</span>
                  <span className="text-xs text-gray-400">{r.cuisine}</span>
                </div>
                <h3 className="text-base font-semibold text-gray-900 group-hover:text-gray-700 mt-1 truncate">
                  {r.name}
                </h3>
                <p className="text-xs text-gray-500 mb-4">{r.location}</p>
                <div className="border-t border-gray-100 pt-4 space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Revenue</span>
                    <span className="font-semibold text-gray-900">
                      ₹{Number(r.total_revenue).toLocaleString('en-IN', { maximumFractionDigits: 0 })}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Orders</span>
                    <span className="font-medium text-gray-700">{r.total_orders}</span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        <div className="mt-10 text-center">
          <button onClick={() => navigate('/restaurants')}
            className="text-sm text-gray-400 hover:text-gray-700 underline transition">
            View all restaurants →
          </button>
        </div>
      </main>
    </div>
  );
}
```

---

## 11. Frontend — Restaurant Analytics Page

### 11.1 — Chart Components

Create folder `resources/js/components/charts/`.

> 📄 **New file:** `resources/js/components/charts/DailyOrdersChart.jsx`

```jsx
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function DailyOrdersChart({ data = [] }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5">
      <h3 className="text-sm font-semibold text-gray-700 mb-4">Daily Order Count</h3>
      {data.length === 0 ? (
        <p className="text-sm text-gray-400 text-center py-8">No data for this range</p>
      ) : (
        <ResponsiveContainer width="100%" height={220}>
          <LineChart data={data} margin={{ top: 5, right: 10, left: -10, bottom: 5 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#6b7280' }} tickFormatter={d => d.slice(5)} />
            <YAxis tick={{ fontSize: 11, fill: '#6b7280' }} allowDecimals={false} />
            <Tooltip
              contentStyle={{ fontSize: 12, borderRadius: '8px', border: '1px solid #e5e7eb' }}
              labelFormatter={d => `Date: ${d}`}
              formatter={v => [v, 'Orders']}
            />
            <Line type="monotone" dataKey="count" stroke="#111827" strokeWidth={2}
              dot={{ r: 3, fill: '#111827' }} activeDot={{ r: 5 }} />
          </LineChart>
        </ResponsiveContainer>
      )}
    </div>
  );
}
```

> 📄 **New file:** `resources/js/components/charts/DailyRevenueChart.jsx`

```jsx
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function DailyRevenueChart({ data = [] }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5">
      <h3 className="text-sm font-semibold text-gray-700 mb-4">Daily Revenue</h3>
      {data.length === 0 ? (
        <p className="text-sm text-gray-400 text-center py-8">No data for this range</p>
      ) : (
        <ResponsiveContainer width="100%" height={220}>
          <BarChart data={data} margin={{ top: 5, right: 10, left: -10, bottom: 5 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#6b7280' }} tickFormatter={d => d.slice(5)} />
            <YAxis tick={{ fontSize: 11, fill: '#6b7280' }} tickFormatter={v => `₹${(v/1000).toFixed(0)}k`} />
            <Tooltip
              contentStyle={{ fontSize: 12, borderRadius: '8px', border: '1px solid #e5e7eb' }}
              labelFormatter={d => `Date: ${d}`}
              formatter={v => [`₹${Number(v).toLocaleString('en-IN')}`, 'Revenue']}
            />
            <Bar dataKey="revenue" fill="#111827" radius={[4, 4, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      )}
    </div>
  );
}
```

> 📄 **New file:** `resources/js/components/charts/AovCard.jsx`

```jsx
export default function AovCard({ value = 0 }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5 flex flex-col justify-between">
      <h3 className="text-sm font-semibold text-gray-700">Avg. Order Value</h3>
      <div className="mt-4">
        <span className="text-3xl font-bold text-gray-900">
          ₹{Number(value).toLocaleString('en-IN', { maximumFractionDigits: 2 })}
        </span>
        <p className="text-xs text-gray-400 mt-1">across selected date range</p>
      </div>
    </div>
  );
}
```

> 📄 **New file:** `resources/js/components/charts/PeakHourChart.jsx`

```jsx
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts';

export default function PeakHourChart({ data = [] }) {
  const maxCount = Math.max(...data.map(d => d.order_count), 0);

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5">
      <h3 className="text-sm font-semibold text-gray-700 mb-4">Peak Order Hour per Day</h3>
      {data.length === 0 ? (
        <p className="text-sm text-gray-400 text-center py-8">No data for this range</p>
      ) : (
        <ResponsiveContainer width="100%" height={220}>
          <BarChart data={data} margin={{ top: 5, right: 10, left: -10, bottom: 5 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#6b7280' }} tickFormatter={d => d.slice(5)} />
            <YAxis domain={[0, 23]} tick={{ fontSize: 11, fill: '#6b7280' }} tickFormatter={h => `${h}:00`} />
            <Tooltip
              contentStyle={{ fontSize: 12, borderRadius: '8px', border: '1px solid #e5e7eb' }}
              labelFormatter={d => `Date: ${d}`}
              formatter={(v, _n, props) => [`${v}:00  (${props.payload.order_count} orders)`, 'Peak Hour']}
            />
            <Bar dataKey="peak_hour" radius={[4, 4, 0, 0]}>
              {data.map((entry, idx) => (
                <Cell key={idx} fill={entry.order_count === maxCount ? '#111827' : '#d1d5db'} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      )}
    </div>
  );
}
```

### 11.2 — Orders Table Component

Key points specific to your schema:
- Response is `{ data: [...], meta: {...} }` — already unwrapped by `fetchOrders`
- `status` arrives as an integer — mapped to label via `STATUS_LABELS`
- No `hour` column — hour displayed by parsing `ordered_at` with `getUTCHours()`
- Filters use `hour_from` / `hour_to` (ranges), not a single `hour`

> 📄 **New file:** `resources/js/components/OrdersTable.jsx`

```jsx
import { useState } from 'react';
import { useOrders } from '../hooks/useOrders';

/**
 * Maps OrderStatus integer values to human-readable labels.
 * Matches the PHP OrderStatus enum exactly:
 *   0 = Failed, 1 = Completed, 2 = Pending, 3 = In Progress
 */
const STATUS_LABELS = {
  0: 'Failed',
  1: 'Completed',
  2: 'Pending',
  3: 'In Progress',
};

const STATUS_STYLES = {
  0: 'bg-red-50 text-red-700 border-red-200',
  1: 'bg-green-50 text-green-700 border-green-200',
  2: 'bg-yellow-50 text-yellow-700 border-yellow-200',
  3: 'bg-blue-50 text-blue-700 border-blue-200',
};

/**
 * OrdersTable
 *
 * Paginated orders table with date range, amount range, and hour range filters.
 *
 * @param {{ lockedRestaurantId?: number }} props
 *   Pass when rendered inside a per-restaurant analytics page.
 *   The restaurant_id filter will be sent on every request and is not editable.
 */
export default function OrdersTable({ lockedRestaurantId }) {
  const [filters, setFilters] = useState({
    restaurant_id: lockedRestaurantId ?? '',
    start_date: '', end_date: '',
    min_amount: '', max_amount: '',
    hour_from:  '', hour_to: '',
    page: 1, per_page: 15,
  });

  const { data, isLoading, isError } = useOrders(filters);

  // fetchOrders returns { data: [...items], meta: {...} }
  const orders   = data?.data   ?? [];
  const meta     = data?.meta   ?? {};
  const total    = meta.total    ?? 0;
  const lastPage = meta.last_page ?? 1;

  function setFilter(key, value) {
    setFilters(prev => ({ ...prev, [key]: value, page: 1 }));
  }

  function setPage(page) {
    setFilters(prev => ({ ...prev, page }));
  }

  function resetFilters() {
    setFilters({
      restaurant_id: lockedRestaurantId ?? '',
      start_date: '', end_date: '',
      min_amount: '', max_amount: '',
      hour_from: '',  hour_to: '',
      page: 1, per_page: 15,
    });
  }

  return (
    <div className="bg-white rounded-xl border border-gray-200">

      {/* Filter bar */}
      <div className="p-4 border-b border-gray-100 flex flex-wrap gap-3 items-end">

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Date From</label>
          <input type="date" value={filters.start_date}
            onChange={e => setFilter('start_date', e.target.value)}
            className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent" />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Date To</label>
          <input type="date" value={filters.end_date}
            onChange={e => setFilter('end_date', e.target.value)}
            className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent" />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Min Amount</label>
          <input type="number" min={0} value={filters.min_amount} placeholder="0"
            onChange={e => setFilter('min_amount', e.target.value)}
            className="w-24 rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent" />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Max Amount</label>
          <input type="number" min={0} value={filters.max_amount} placeholder="∞"
            onChange={e => setFilter('max_amount', e.target.value)}
            className="w-24 rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent" />
        </div>

        {/* Hour range — backend uses HOUR(ordered_at) */}
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Hour From</label>
          <input type="number" min={0} max={23} value={filters.hour_from} placeholder="0"
            onChange={e => setFilter('hour_from', e.target.value)}
            className="w-20 rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent" />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Hour To</label>
          <input type="number" min={0} max={23} value={filters.hour_to} placeholder="23"
            onChange={e => setFilter('hour_to', e.target.value)}
            className="w-20 rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent" />
        </div>

        <button onClick={resetFilters}
          className="text-xs text-gray-400 hover:text-gray-700 underline transition self-end pb-1.5">
          Reset
        </button>
      </div>

      {/* Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 bg-gray-50 text-left">
              {['#', 'Restaurant', 'Amount', 'Status', 'Date', 'Hour'].map(h => (
                <th key={h} className="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-50">
            {isLoading && (
              <tr><td colSpan={6} className="text-center py-12 text-sm text-gray-400">Loading…</td></tr>
            )}
            {isError && (
              <tr><td colSpan={6} className="text-center py-12 text-sm text-red-500">Failed to load orders.</td></tr>
            )}
            {!isLoading && !isError && orders.length === 0 && (
              <tr><td colSpan={6} className="text-center py-12 text-sm text-gray-400">No orders match your filters.</td></tr>
            )}
            {orders.map(order => {
              // Hour is not a column — derive from ordered_at on the frontend
              const dt          = order.ordered_at ? new Date(order.ordered_at) : null;
              const hourDisplay = dt ? `${dt.getUTCHours()}:00` : '—';
              const dateDisplay = dt ? dt.toLocaleDateString('en-IN', { timeZone: 'UTC' }) : '—';

              return (
                <tr key={order.id} className="hover:bg-gray-50 transition">
                  <td className="px-4 py-3 text-gray-500 font-mono text-xs">{order.id}</td>
                  <td className="px-4 py-3 text-gray-800">{order.restaurant?.name ?? '—'}</td>
                  <td className="px-4 py-3 font-medium text-gray-900">
                    ₹{Number(order.order_amount).toLocaleString('en-IN', { maximumFractionDigits: 2 })}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium
                      ${STATUS_STYLES[order.status] ?? 'bg-gray-50 text-gray-600 border-gray-200'}`}>
                      {STATUS_LABELS[order.status] ?? `Status ${order.status}`}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-600 text-xs">{dateDisplay}</td>
                  <td className="px-4 py-3 text-gray-600">{hourDisplay}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Pagination — reads from meta object */}
      {!isLoading && total > 0 && (
        <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
          <span className="text-xs text-gray-500">
            {meta.from}–{meta.to} of {total} order{total !== 1 ? 's' : ''}
          </span>
          <div className="flex items-center gap-2">
            <button onClick={() => setPage(filters.page - 1)} disabled={filters.page <= 1}
              className="rounded px-3 py-1 text-xs border border-gray-200 text-gray-600
                hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
              ← Prev
            </button>
            <span className="text-xs text-gray-500">Page {filters.page} of {lastPage}</span>
            <button onClick={() => setPage(filters.page + 1)} disabled={filters.page >= lastPage}
              className="rounded px-3 py-1 text-xs border border-gray-200 text-gray-600
                hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
              Next →
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
```

### 11.3 — Analytics Page Assembly

> 📄 **New file:** `resources/js/pages/analytics/RestaurantAnalytics.jsx`

```jsx
import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useRestaurantAnalytics } from '../../hooks/useRestaurantAnalytics';
import DailyOrdersChart  from '../../components/charts/DailyOrdersChart';
import DailyRevenueChart from '../../components/charts/DailyRevenueChart';
import AovCard           from '../../components/charts/AovCard';
import PeakHourChart     from '../../components/charts/PeakHourChart';
import OrdersTable       from '../../components/OrdersTable';

function defaultDates() {
  const end   = new Date();
  const start = new Date();
  start.setDate(start.getDate() - 6);
  return {
    startDate: start.toISOString().split('T')[0],
    endDate:   end.toISOString().split('T')[0],
  };
}

export default function RestaurantAnalytics() {
  const { id }   = useParams();
  const navigate = useNavigate();

  const [dates, setDates]         = useState(defaultDates());
  const [dateInput, setDateInput] = useState(dates);

  const { data, isLoading, isError } = useRestaurantAnalytics(
    Number(id),
    dates.startDate,
    dates.endDate
  );

  if (isError) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <p className="text-red-600 font-medium mb-2">Restaurant not found or failed to load.</p>
          <button onClick={() => navigate('/restaurants')}
            className="text-sm text-gray-500 underline">
            ← Back to restaurants
          </button>
        </div>
      </div>
    );
  }

  const restaurant = data?.restaurant;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Breadcrumb nav */}
      <header className="bg-white border-b border-gray-200 px-6 py-4 flex items-center gap-3">
        <button onClick={() => navigate('/restaurants')}
          className="text-sm text-gray-500 hover:text-gray-900 transition">
          ← Restaurants
        </button>
        <span className="text-gray-300">/</span>
        <span className="text-sm font-medium text-gray-900">
          {isLoading ? '…' : restaurant?.name}
        </span>
      </header>

      <main className="max-w-6xl mx-auto px-6 py-10">
        {/* Header + date filter */}
        <div className="mb-8 flex flex-col sm:flex-row sm:items-end gap-4">
          <div>
            {isLoading ? (
              <div className="h-6 w-48 bg-gray-200 rounded animate-pulse" />
            ) : (
              <>
                <h2 className="text-xl font-semibold text-gray-900">{restaurant?.name}</h2>
                <p className="text-sm text-gray-500 mt-0.5">
                  {restaurant?.cuisine} · {restaurant?.location}
                  {restaurant?.rating ? ` · ⭐ ${restaurant.rating}` : ''}
                </p>
              </>
            )}
          </div>

          <div className="sm:ml-auto flex flex-wrap items-end gap-3">
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">From</label>
              <input type="date" value={dateInput.startDate}
                onChange={e => setDateInput(p => ({ ...p, startDate: e.target.value }))}
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none
                  focus:ring-2 focus:ring-gray-900 focus:border-transparent" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">To</label>
              <input type="date" value={dateInput.endDate}
                onChange={e => setDateInput(p => ({ ...p, endDate: e.target.value }))}
                className="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none
                  focus:ring-2 focus:ring-gray-900 focus:border-transparent" />
            </div>
            <button onClick={() => setDates(dateInput)}
              className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white
                hover:bg-gray-700 transition">
              Apply
            </button>
          </div>
        </div>

        {/* Loading skeleton */}
        {isLoading && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
            {[1, 2, 3, 4].map(i => (
              <div key={i} className="bg-white rounded-xl border border-gray-200 p-5 h-56 animate-pulse" />
            ))}
          </div>
        )}

        {/* Charts + orders table */}
        {!isLoading && data && (
          <>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
              <DailyOrdersChart  data={data.daily_orders} />
              <DailyRevenueChart data={data.daily_revenue} />
              <AovCard           value={data.avg_order_value} />
              <PeakHourChart     data={data.peak_hours} />
            </div>

            <div>
              <h3 className="text-base font-semibold text-gray-900 mb-3">Orders</h3>
              {/* lockedRestaurantId locks the restaurant_id filter silently */}
              <OrdersTable lockedRestaurantId={Number(id)} />
            </div>
          </>
        )}
      </main>
    </div>
  );
}
```

---

## 12. Frontend — Folder Structure After Phase 5

```
resources/js/
├── api/
│   ├── axios.js                       ← existing (Phase 4)
│   ├── restaurants.js                 ← existing (Phase 4)
│   └── analytics.js                   ← NEW
├── context/
│   └── AuthContext.jsx                ← NEW
├── components/
│   ├── charts/
│   │   ├── DailyOrdersChart.jsx       ← NEW
│   │   ├── DailyRevenueChart.jsx      ← NEW
│   │   ├── AovCard.jsx                ← NEW
│   │   └── PeakHourChart.jsx          ← NEW
│   └── OrdersTable.jsx                ← NEW
├── hooks/
│   ├── useRestaurants.js              ← existing (Phase 4)
│   ├── useRestaurantAnalytics.js      ← NEW
│   ├── useTopRestaurants.js           ← NEW
│   └── useOrders.js                   ← NEW
├── pages/
│   ├── auth/
│   │   ├── Login.jsx                  ← NEW
│   │   └── Register.jsx               ← NEW
│   ├── dashboard/
│   │   └── Dashboard.jsx              ← NEW
│   ├── restaurants/
│   │   └── RestaurantList.jsx         ← existing (Phase 4)
│   └── analytics/
│       └── RestaurantAnalytics.jsx    ← NEW
├── routes/
│   ├── AppRouter.jsx                  ← UPDATED
│   └── PrivateRoute.jsx               ← UPDATED
└── app.jsx                            ← UPDATED
```

---

## 13. Pest Tests — Phase 5

> 📄 **New file:** `tests/Feature/Phase5/AnalyticsTest.php`

```php
<?php

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Cache;

describe('Analytics — Restaurant', function () {

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/analytics/restaurant/1?start_date=2024-01-01&end_date=2024-01-07')
            ->assertStatus(401);
    });

    it('returns 422 when start_date is missing', function () {
        $r = Restaurant::factory()->create();
        actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?end_date=2024-01-07")
            ->assertStatus(422);
    });

    it('returns 422 when end_date is before start_date', function () {
        $r = Restaurant::factory()->create();
        actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-10&end_date=2024-01-01")
            ->assertStatus(422);
    });

    it('returns 404 for a non-existent restaurant', function () {
        actingAsUser()
            ->getJson('/api/v1/analytics/restaurant/99999?start_date=2024-01-01&end_date=2024-01-07')
            ->assertStatus(404);
    });

    it('response contains all required keys', function () {
        $r = Restaurant::factory()->create();
        actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-07")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'restaurant' => ['id', 'name', 'cuisine', 'location', 'rating'],
                    'daily_orders',
                    'daily_revenue',
                    'avg_order_value',
                    'peak_hours',
                ],
            ]);
    });

    it('returns correct daily order count per day', function () {
        $r = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-01 10:00:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-01 15:00:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-02 09:00:00']);

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-02")
            ->assertStatus(200);

        $dailyOrders = collect($response->json('data.daily_orders'));
        expect($dailyOrders->firstWhere('date', '2024-01-01')['count'])->toBe(2);
        expect($dailyOrders->firstWhere('date', '2024-01-02')['count'])->toBe(1);
    });

    it('returns correct daily revenue using order_amount column', function () {
        $r = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r->id, 'order_amount' => 500.00, 'ordered_at' => '2024-01-01 10:00:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'order_amount' => 300.00, 'ordered_at' => '2024-01-01 14:00:00']);

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-01")
            ->assertStatus(200);

        $revenue = collect($response->json('data.daily_revenue'))->firstWhere('date', '2024-01-01')['revenue'];
        expect($revenue)->toBe(800.0);
    });

    it('returns correct average order value', function () {
        $r = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r->id, 'order_amount' => 400.00, 'ordered_at' => '2024-01-01 10:00:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'order_amount' => 600.00, 'ordered_at' => '2024-01-01 14:00:00']);

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-01")
            ->assertStatus(200);

        expect($response->json('data.avg_order_value'))->toBe(500.0);
    });

    it('returns correct peak hour per day using HOUR(ordered_at)', function () {
        $r = Restaurant::factory()->create();

        // 3 orders at hour 13, 1 at hour 9 — peak_hour should be 13
        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-01 13:00:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-01 13:30:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-01 13:45:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-01 09:00:00']);

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-01")
            ->assertStatus(200);

        $peak = collect($response->json('data.peak_hours'))->firstWhere('date', '2024-01-01');
        expect($peak['peak_hour'])->toBe(13);
        expect($peak['order_count'])->toBe(3);
    });

    it('excludes orders outside the date range', function () {
        $r = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-05 10:00:00']); // inside
        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-15 10:00:00']); // outside

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-07")
            ->assertStatus(200);

        expect(collect($response->json('data.daily_orders'))->sum('count'))->toBe(1);
    });

    it('analytics response is served from cache on repeat request', function () {
        $r = Restaurant::factory()->create();
        Cache::flush();

        $url = "/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-07";
        actingAsUser()->getJson($url)->assertStatus(200);
        actingAsUser()->getJson($url)->assertStatus(200);
    });
});

describe('Analytics — Top Restaurants', function () {

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/analytics/top-restaurants?start_date=2024-01-01&end_date=2024-01-07')
            ->assertStatus(401);
    });

    it('returns exactly 3 results when more than 3 restaurants have orders', function () {
        $restaurants = Restaurant::factory()->count(5)->create();

        foreach ($restaurants as $i => $r) {
            Order::factory()->create([
                'restaurant_id' => $r->id,
                'order_amount'  => 100 * ($i + 1),
                'ordered_at'    => '2024-01-03 12:00:00',
            ]);
        }

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/top-restaurants?start_date=2024-01-01&end_date=2024-01-07')
            ->assertStatus(200);

        expect(count($response->json('data')))->toBe(3);
    });

    it('results are ordered by total_revenue descending', function () {
        $r1 = Restaurant::factory()->create();
        $r2 = Restaurant::factory()->create();
        $r3 = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r1->id, 'order_amount' => 100, 'ordered_at' => '2024-01-03 12:00:00']);
        Order::factory()->create(['restaurant_id' => $r2->id, 'order_amount' => 500, 'ordered_at' => '2024-01-03 12:00:00']);
        Order::factory()->create(['restaurant_id' => $r3->id, 'order_amount' => 300, 'ordered_at' => '2024-01-03 12:00:00']);

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/top-restaurants?start_date=2024-01-01&end_date=2024-01-07')
            ->assertStatus(200);

        $revenues = collect($response->json('data'))->pluck('total_revenue')->map(fn ($v) => (float) $v);
        expect($revenues[0])->toBeGreaterThan($revenues[1]);
        expect($revenues[1])->toBeGreaterThan($revenues[2]);
    });

    it('excludes restaurants with no orders in the date range', function () {
        $r1 = Restaurant::factory()->create();
        $r2 = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r1->id, 'order_amount' => 200, 'ordered_at' => '2024-01-03 12:00:00']);
        Order::factory()->create(['restaurant_id' => $r2->id, 'order_amount' => 999, 'ordered_at' => '2024-02-15 12:00:00']);

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/top-restaurants?start_date=2024-01-01&end_date=2024-01-07')
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($r1->id);
        expect($ids)->not->toContain($r2->id);
    });
});

describe('Analytics — Orders', function () {

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/analytics/orders')->assertStatus(401);
    });

    it('returns paginated orders with data array and meta object', function () {
        Order::factory()->count(20)->create();

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/orders?per_page=10')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page', 'from', 'to'],
            ]);

        expect(count($response->json('data')))->toBeLessThanOrEqual(10);
    });

    it('filters by restaurant_id', function () {
        $r1 = Restaurant::factory()->create();
        $r2 = Restaurant::factory()->create();

        Order::factory()->count(3)->create(['restaurant_id' => $r1->id]);
        Order::factory()->count(2)->create(['restaurant_id' => $r2->id]);

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/orders?restaurant_id={$r1->id}")
            ->assertStatus(200);

        expect($response->json('meta.total'))->toBe(3);
    });

    it('filters by date range using ordered_at', function () {
        Order::factory()->create(['ordered_at' => '2024-01-05 10:00:00']);
        Order::factory()->create(['ordered_at' => '2024-02-15 10:00:00']); // outside range

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/orders?start_date=2024-01-01&end_date=2024-01-31')
            ->assertStatus(200);

        expect($response->json('meta.total'))->toBe(1);
    });

    it('filters by min and max order_amount', function () {
        Order::factory()->create(['order_amount' => 100]);
        Order::factory()->create(['order_amount' => 500]);
        Order::factory()->create(['order_amount' => 1000]);

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/orders?min_amount=200&max_amount=600')
            ->assertStatus(200);

        expect($response->json('meta.total'))->toBe(1);
    });

    it('filters by hour_from and hour_to using HOUR(ordered_at)', function () {
        Order::factory()->create(['ordered_at' => '2024-01-05 08:00:00']); // hour 8  — excluded
        Order::factory()->create(['ordered_at' => '2024-01-05 13:00:00']); // hour 13 — included
        Order::factory()->create(['ordered_at' => '2024-01-05 15:00:00']); // hour 15 — included
        Order::factory()->create(['ordered_at' => '2024-01-05 22:00:00']); // hour 22 — excluded

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/orders?hour_from=12&hour_to=18')
            ->assertStatus(200);

        expect($response->json('meta.total'))->toBe(2);
    });

    it('per_page is capped at 50', function () {
        Order::factory()->count(5)->create();

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/orders?per_page=9999')
            ->assertStatus(200);

        expect($response->json('meta.per_page'))->toBeLessThanOrEqual(50);
    });

    it('returns 422 for a restaurant_id that does not exist', function () {
        actingAsUser()
            ->getJson('/api/v1/analytics/orders?restaurant_id=99999')
            ->assertStatus(422);
    });

    it('eagerly loads restaurant name on each order', function () {
        $r = Restaurant::factory()->create(['name' => 'Tandoori Treats']);
        Order::factory()->create(['restaurant_id' => $r->id]);

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/orders?restaurant_id={$r->id}")
            ->assertStatus(200);

        expect($response->json('data.0.restaurant.name'))->toBe('Tandoori Treats');
    });
});
```

### Running Phase 5 Tests

```bash
# Run only Phase 5
php artisan test --filter Phase5

# Full suite
php artisan test
```

---

## 14. Phase 5 Completion Checklist

| Item | Status |
|---|---|
| `AnalyticsService` created | ☐ |
| All queries use `order_amount` — not `amount` | ☐ |
| All hour logic uses `HOUR(ordered_at)` — no `hour` column referenced anywhere | ☐ |
| `getRestaurantAnalytics()` returns all 5 keys including `peak_hours` | ☐ |
| `getTopRestaurants()` joins on `order_amount`, orders by `total_revenue` desc | ☐ |
| `getOrders()` filters by `hour_from`/`hour_to` via `whereRaw('HOUR(ordered_at)')` | ☐ |
| Cache TTL resolves to 3600 for historical, 300 for today | ☐ |
| `AnalyticsController` created with all 3 methods | ☐ |
| `orders()` passes `LengthAwarePaginator` to `$this->success()` → `meta` key present | ☐ |
| `AnalyticsController` imported in `api.php` | ☐ |
| `AuthContext.jsx` + `useAuth()` created | ☐ |
| `Login.jsx` calls `/auth/login`, stores token, redirects to `/dashboard` | ☐ |
| `Register.jsx` maps Laravel 422 errors per-field, redirects to `/login?registered=1` | ☐ |
| `app.jsx` wraps `AppRouter` with `AuthProvider` | ☐ |
| `AppRouter.jsx` has all 5 routes wired correctly | ☐ |
| `PrivateRoute.jsx` uses `useAuth().isAuthenticated` | ☐ |
| `analytics.js` — `fetchOrders` returns `{ data, meta }` shape | ☐ |
| `useOrders` hook uses `placeholderData` (v5) or `keepPreviousData` (v4) | ☐ |
| `OrdersTable.jsx` — `STATUS_LABELS` maps 0/1/2/3 correctly | ☐ |
| `OrdersTable.jsx` — hour shown by parsing `ordered_at` with `getUTCHours()` | ☐ |
| `OrdersTable.jsx` — pagination reads `meta.total` and `meta.last_page` | ☐ |
| All 4 chart components created in `components/charts/` | ☐ |
| `RestaurantAnalytics.jsx` passes `lockedRestaurantId={Number(id)}` to `OrdersTable` | ☐ |
| All Phase 5 Pest tests passing | ☐ |
| Full test suite (`php artisan test`) still green | ☐ |

---

*End of Phase 5 Documentation — Application Complete*
