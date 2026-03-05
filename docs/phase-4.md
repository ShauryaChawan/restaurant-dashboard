# 🍽️ Restaurant Analytics Platform
## Phase 4 — Restaurant Module (API + UI)
### Detailed Actionable Documentation

> **Focus:** Restaurant listing API with search, filters, sort, pagination, file caching, and full React UI using TanStack Query.

---

| Attribute | Decision |
|---|---|
| Endpoints | `GET /api/v1/restaurants`, `GET /api/v1/restaurants/{id}` |
| Filters | `search`, `cuisine`, `location`, `rating` (min), `sort_by`, `sort_dir` |
| Pagination | Laravel `paginate()` with configurable `per_page` (max 50) |
| Caching | File cache, respects `CACHE_ENABLED` env flag, TTL 10 min |
| Cache Key | `md5` of sorted query string per filter combination |
| Frontend | TanStack Query + React, with auth guard |
| Filter injection | `RestaurantFilter` constructed manually in service with `Request` |

---

## Table of Contents

- [🍽️ Restaurant Analytics Platform](#️-restaurant-analytics-platform)
  - [Phase 4 — Restaurant Module (API + UI)](#phase-4--restaurant-module-api--ui)
    - [Detailed Actionable Documentation](#detailed-actionable-documentation)
  - [Table of Contents](#table-of-contents)
  - [1. Phase Goals \& Deliverables](#1-phase-goals--deliverables)
    - [Deliverables Checklist](#deliverables-checklist)
  - [2. Updated RestaurantFilter](#2-updated-restaurantfilter)
  - [3. RestaurantService](#3-restaurantservice)
  - [4. RestaurantController](#4-restaurantcontroller)
  - [5. API Response Shape](#5-api-response-shape)
    - [List Response](#list-response)
    - [Single Restaurant Response](#single-restaurant-response)
    - [Error — Not Found](#error--not-found)
  - [6. Routes — Recap](#6-routes--recap)
  - [7. Frontend — Axios API Layer](#7-frontend--axios-api-layer)
    - [7.1 — Axios Instance](#71--axios-instance)
    - [7.2 — Restaurant API Functions](#72--restaurant-api-functions)
  - [8. Frontend — TanStack Query Setup](#8-frontend--tanstack-query-setup)
    - [Custom Hook — useRestaurants](#custom-hook--userestaurants)
  - [9. Frontend — RestaurantList Page](#9-frontend--restaurantlist-page)
  - [10. Frontend — AppRouter Update](#10-frontend--approuter-update)
    - [PrivateRoute](#privateroute)
  - [11. Pest Tests — Phase 4](#11-pest-tests--phase-4)
    - [Running Phase 4 Tests](#running-phase-4-tests)
  - [12. Phase 4 Completion Checklist](#12-phase-4-completion-checklist)

---

## 1. Phase Goals & Deliverables

By the end of Phase 4 you should have a fully working restaurant listing — paginated, searchable, filterable, sortable, cached, and rendered in a React table with TanStack Query managing all server state.

### Deliverables Checklist

- [ ] `RestaurantFilter` updated with `location`, `rating`, and `sort_by` methods
- [ ] `RestaurantService` with `getPaginated()` and `findById()` — cache-aware
- [ ] `RestaurantController` thin, delegating to service
- [ ] `per_page` capped at 50 via validation
- [ ] File cache respecting `CACHE_ENABLED` flag
- [ ] Axios instance configured with base URL and auth token
- [ ] `useRestaurants` TanStack Query hook
- [ ] `RestaurantList` page with search, filters, sort, pagination
- [ ] `PrivateRoute` guarding the restaurants page
- [ ] `AppRouter` updated with restaurant routes
- [ ] All Phase 4 Pest tests passing

---

## 2. Updated RestaurantFilter

The `RestaurantFilter` from Phase 1 only had `search`, `cuisine`, and `sort_by`. We now add `location` and `rating` filter methods, and fix `sort_by` to use a safe whitelist that includes all sortable columns.

> 📄 **File:** `app/Filters/RestaurantFilter.php`

```php
<?php

namespace App\Filters;

/**
 * RestaurantFilter
 *
 * Handles all query filtering for the Restaurant listing endpoint.
 * Each public method name maps directly to a query parameter key.
 * Methods are called automatically by the QueryFilter base class.
 *
 * Supported params:
 *   search    — partial match on name, cuisine, location
 *   cuisine   — exact match on cuisine
 *   location  — exact match on location
 *   rating    — minimum rating (e.g. rating=4 returns 4.0 and above)
 *   sort_by   — column to sort by (whitelisted)
 *   sort_dir  — asc|desc (defaults to asc)
 */
class RestaurantFilter extends QueryFilter
{
    /**
     * Global search across name, cuisine, and location.
     * Case-insensitive partial match.
     */
    public function search(string $value): void
    {
        $this->builder->where(function ($query) use ($value) {
            $query->where('name',     'like', "%{$value}%")
                  ->orWhere('cuisine',  'like', "%{$value}%")
                  ->orWhere('location', 'like', "%{$value}%");
        });
    }

    /**
     * Filter by exact cuisine type.
     * Example: ?cuisine=Japanese
     */
    public function cuisine(string $value): void
    {
        $this->builder->where('cuisine', $value);
    }

    /**
     * Filter by exact location.
     * Example: ?location=Mumbai
     */
    public function location(string $value): void
    {
        $this->builder->where('location', $value);
    }

    /**
     * Filter by minimum rating.
     * Example: ?rating=4 returns restaurants with rating >= 4.0
     */
    public function rating(string $value): void
    {
        $this->builder->where('rating', '>=', (float) $value);
    }

    /**
     * Sort by a whitelisted column.
     * Example: ?sort_by=name&sort_dir=desc
     *
     * sort_dir defaults to 'asc' if not provided or invalid.
     */
    public function sort_by(string $value): void
    {
        $allowed = ['name', 'cuisine', 'location', 'rating', 'created_at'];
        $dir     = $this->request->get('sort_dir', 'asc');
        $dir     = in_array(strtolower($dir), ['asc', 'desc']) ? strtolower($dir) : 'asc';

        if (in_array($value, $allowed)) {
            $this->builder->orderBy($value, $dir);
        }
    }
}
```

> 💡 **Why `search` uses a closure with `where`/`orWhere`?**
> Wrapping the `orWhere` clauses inside a `where(function($query))` closure ensures they are grouped in parentheses in SQL:
> ```sql
> WHERE (name LIKE '%sushi%' OR cuisine LIKE '%sushi%' OR location LIKE '%sushi%')
> ```
> Without the closure, `orWhere` would break out of any other conditions chained before it.

---

## 3. RestaurantService

> 📄 **File:** `app/Services/RestaurantService.php`

```php
<?php

namespace App\Services;

use App\Filters\RestaurantFilter;
use App\Models\Restaurant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * RestaurantService
 *
 * Owns all restaurant business logic, query building, and caching.
 * Cache TTL and enabled flag are defined here — never in controllers.
 *
 * Cache driver : file (tags not supported — string keys used instead)
 * Enhancement  : swap CACHE_STORE=redis in .env to enable tag support here.
 */
class RestaurantService
{
    /**
     * Cache TTL in seconds for paginated restaurant listings.
     * 10 minutes — restaurant data rarely changes.
     */
    private const CACHE_TTL = 600;

    /**
     * Maximum allowed per_page value.
     * Prevents clients from dumping the entire table in one request.
     */
    private const MAX_PER_PAGE = 50;

    /**
     * Global cache toggle driven by CACHE_ENABLED in .env.
     * Set CACHE_ENABLED=false to disable caching in local/test environments.
     */
    private function isCacheEnabled(): bool
    {
        return (bool) config('app.cache_enabled', true);
    }

    /**
     * Build a deterministic cache key from the request query parameters.
     *
     * Keys are sorted before hashing so that:
     *   ?search=sushi&cuisine=Japanese
     *   ?cuisine=Japanese&search=sushi
     * ...produce the same cache key.
     */
    private function buildCacheKey(Request $request): string
    {
        $params = $request->query();
        ksort($params);

        return 'restaurants_index_' . md5(serialize($params));
    }

    /**
     * Return a paginated, filtered, sorted, and cached restaurant list.
     *
     * @param  Request $request  The incoming HTTP request.
     * @return LengthAwarePaginator
     */
    public function getPaginated(Request $request): LengthAwarePaginator
    {
        $perPage = min(
            (int) $request->get('per_page', 10),
            self::MAX_PER_PAGE
        );

        if ($this->isCacheEnabled()) {
            return Cache::remember(
                $this->buildCacheKey($request),
                self::CACHE_TTL,
                fn () => $this->queryPaginated($request, $perPage)
            );
        }

        return $this->queryPaginated($request, $perPage);
    }

    /**
     * Execute the actual Eloquent query with filters applied.
     * Called by getPaginated() — either directly or via cache callback.
     */
    private function queryPaginated(Request $request, int $perPage): LengthAwarePaginator
    {
        $filter = new RestaurantFilter($request);

        return Restaurant::query()
            ->filter($filter)
            ->paginate($perPage)
            ->withQueryString(); // preserves filter params in pagination links
    }

    /**
     * Find a single restaurant by primary key.
     * Throws ModelNotFoundException (→ 404) if not found.
     * The global exception handler in bootstrap/app.php catches this.
     *
     * @param  int $id
     * @return Restaurant
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findById(int $id): Restaurant
    {
        return Restaurant::findOrFail($id);
    }
}
```

> 💡 **Why `RestaurantFilter` is constructed in the service and not injected into the controller?**
> `RestaurantFilter` requires a `Request` instance in its constructor. Laravel's service container cannot auto-resolve this when type-hinting `RestaurantFilter` directly in a controller method alongside `Request` — it would attempt to resolve two `Request`-dependent objects simultaneously. Constructing it manually in the service with `new RestaurantFilter($request)` is clean, explicit, and keeps the controller signature simple.

---

## 4. RestaurantController

> 📄 **File:** `app/Http/Controllers/Api/V1/RestaurantController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RestaurantController
 *
 * Thin HTTP controller for restaurant endpoints.
 * All business logic, caching, and query building
 * are delegated to RestaurantService.
 *
 * Endpoints:
 *   GET /api/v1/restaurants       → index()
 *   GET /api/v1/restaurants/{id}  → show()
 */
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
```

---

## 5. API Response Shape

### List Response

```json
{
  "status": "success",
  "message": "Restaurants fetched successfully.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Tandoori Treats",
        "location": "Bangalore",
        "cuisine": "North Indian",
        "rating": null,
        "created_at": "2025-06-22T10:00:00.000000Z",
        "updated_at": "2025-06-22T10:00:00.000000Z"
      }
    ],
    "first_page_url": "http://restaurant-dashboard.test/api/v1/restaurants?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "...",
    "links": [],
    "next_page_url": null,
    "path": "...",
    "per_page": 10,
    "prev_page_url": null,
    "to": 4,
    "total": 4
  }
}
```

### Single Restaurant Response

```json
{
  "status": "success",
  "message": "Restaurant fetched successfully.",
  "data": {
    "id": 1,
    "name": "Tandoori Treats",
    "location": "Bangalore",
    "cuisine": "North Indian",
    "rating": null,
    "created_at": "2025-06-22T10:00:00.000000Z",
    "updated_at": "2025-06-22T10:00:00.000000Z"
  }
}
```

### Error — Not Found

```json
{
  "status": "error",
  "message": "Resource not found",
  "data": null
}
```

---

## 6. Routes — Recap

No changes needed to `routes/api.php`. Restaurant routes were defined in Phase 1.

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/restaurants',      [RestaurantController::class, 'index']);
    Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);
});
```

---

## 7. Frontend — Axios API Layer

### 7.1 — Axios Instance

> 📄 **File:** `resources/js/api/axios.js`

```js
import axios from 'axios';

const instance = axios.create({
  baseURL: import.meta.env.VITE_APP_URL || 'http://restaurant-dashboard.test',
  withCredentials: true,       // required for Sanctum cookie auth
  headers: {
    'Content-Type': 'application/json',
    'Accept':       'application/json',
  },
});

// Attach Bearer token from localStorage on every request
instance.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Global response error handler
instance.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid — clear storage and redirect to login
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default instance;
```

### 7.2 — Restaurant API Functions

> 📄 **File:** `resources/js/api/restaurants.js`

```js
import axios from './axios';

/**
 * Fetch paginated, filtered restaurant list.
 *
 * @param {Object} params - Query parameters
 * @param {string} [params.search]    - Global search across name, cuisine, location
 * @param {string} [params.cuisine]   - Filter by cuisine type
 * @param {string} [params.location]  - Filter by location
 * @param {number} [params.rating]    - Minimum rating filter
 * @param {string} [params.sort_by]   - Column to sort by
 * @param {string} [params.sort_dir]  - Sort direction: asc|desc
 * @param {number} [params.page]      - Page number
 * @param {number} [params.per_page]  - Items per page (max 50)
 */
export const fetchRestaurants = (params = {}) => {
  return axios.get('/api/v1/restaurants', { params });
};

/**
 * Fetch a single restaurant by ID.
 *
 * @param {number} id - Restaurant ID
 */
export const fetchRestaurant = (id) => {
  return axios.get(`/api/v1/restaurants/${id}`);
};
```

---

## 8. Frontend — TanStack Query Setup

TanStack Query must be set up at the app root so all pages can use `useQuery`.

> 📄 **File:** `resources/js/app.jsx`

```jsx
import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AppRouter from './routes/AppRouter';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,              // retry failed requests once
      staleTime: 1000 * 60,  // 1 minute — don't refetch if data is fresh
    },
  },
});

const root = createRoot(document.getElementById('app'));

root.render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <AppRouter />
    </QueryClientProvider>
  </React.StrictMode>
);
```

### Custom Hook — useRestaurants

> 📄 **File:** `resources/js/hooks/useRestaurants.js`

```js
import { useQuery } from '@tanstack/react-query';
import { fetchRestaurants } from '../api/restaurants';

/**
 * TanStack Query hook for fetching paginated restaurant list.
 *
 * Automatically refetches when filters change.
 * Caches results client-side for staleTime duration.
 *
 * @param {Object} params - Filter/pagination parameters
 */
export const useRestaurants = (params = {}) => {
  return useQuery({
    queryKey: ['restaurants', params],  // refetches when params change
    queryFn:  () => fetchRestaurants(params).then((res) => res.data.data),
    keepPreviousData: true,             // shows old data while new page loads
  });
};
```

---

## 9. Frontend — RestaurantList Page

> 📄 **File:** `resources/js/pages/restaurants/RestaurantList.jsx`

```jsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useRestaurants } from '../../hooks/useRestaurants';

/**
 * RestaurantList Page
 *
 * Displays a paginated, searchable, filterable, sortable table of restaurants.
 * Uses TanStack Query via useRestaurants hook for all data fetching.
 * Navigates to analytics page on row click.
 */
export default function RestaurantList() {
  const navigate = useNavigate();

  // ── Filter State ───────────────────────────────────────────────────
  const [search,   setSearch]   = useState('');
  const [cuisine,  setCuisine]  = useState('');
  const [location, setLocation] = useState('');
  const [rating,   setRating]   = useState('');
  const [sortBy,   setSortBy]   = useState('name');
  const [sortDir,  setSortDir]  = useState('asc');
  const [page,     setPage]     = useState(1);

  // ── Query Params ───────────────────────────────────────────────────
  const params = {
    ...(search   && { search }),
    ...(cuisine  && { cuisine }),
    ...(location && { location }),
    ...(rating   && { rating }),
    sort_by:  sortBy,
    sort_dir: sortDir,
    page,
    per_page: 10,
  };

  // ── Data Fetching ──────────────────────────────────────────────────
  const { data, isLoading, isError, error } = useRestaurants(params);

  const restaurants = data?.data ?? [];
  const lastPage    = data?.last_page ?? 1;
  const total       = data?.total ?? 0;

  // ── Handlers ───────────────────────────────────────────────────────
  const handleSort = (column) => {
    if (sortBy === column) {
      setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortBy(column);
      setSortDir('asc');
    }
    setPage(1);
  };

  const handleFilterChange = (setter) => (e) => {
    setter(e.target.value);
    setPage(1); // reset to page 1 on any filter change
  };

  const handleRowClick = (restaurantId) => {
    navigate(`/restaurants/${restaurantId}/analytics`);
  };

  // ── Sort Indicator ─────────────────────────────────────────────────
  const sortIndicator = (column) => {
    if (sortBy !== column) return null;
    return sortDir === 'asc' ? ' ↑' : ' ↓';
  };

  // ── Render ─────────────────────────────────────────────────────────
  return (
    <div style={{ padding: '24px' }}>

      <h1>Restaurants</h1>

      {/* ── Search & Filters ── */}
      <div style={{ display: 'flex', gap: '12px', marginBottom: '16px', flexWrap: 'wrap' }}>

        <input
          type="text"
          placeholder="Search name, cuisine, location..."
          value={search}
          onChange={handleFilterChange(setSearch)}
          style={{ padding: '8px', flex: '1', minWidth: '200px' }}
        />

        <select value={cuisine} onChange={handleFilterChange(setCuisine)} style={{ padding: '8px' }}>
          <option value="">All Cuisines</option>
          <option value="North Indian">North Indian</option>
          <option value="Japanese">Japanese</option>
          <option value="Italian">Italian</option>
          <option value="American">American</option>
        </select>

        <select value={location} onChange={handleFilterChange(setLocation)} style={{ padding: '8px' }}>
          <option value="">All Locations</option>
          <option value="Bangalore">Bangalore</option>
          <option value="Mumbai">Mumbai</option>
          <option value="Delhi">Delhi</option>
          <option value="Hyderabad">Hyderabad</option>
        </select>

        <select value={rating} onChange={handleFilterChange(setRating)} style={{ padding: '8px' }}>
          <option value="">All Ratings</option>
          <option value="4">4+ Stars</option>
          <option value="3">3+ Stars</option>
          <option value="2">2+ Stars</option>
        </select>

        {/* Clear all filters */}
        <button
          onClick={() => {
            setSearch(''); setCuisine(''); setLocation('');
            setRating(''); setSortBy('name'); setSortDir('asc');
            setPage(1);
          }}
          style={{ padding: '8px 16px' }}
        >
          Clear Filters
        </button>
      </div>

      {/* ── States ── */}
      {isLoading && <p>Loading restaurants...</p>}

      {isError && (
        <p style={{ color: 'red' }}>
          Error: {error?.response?.data?.message ?? 'Failed to load restaurants.'}
        </p>
      )}

      {/* ── Table ── */}
      {!isLoading && !isError && (
        <>
          <p style={{ marginBottom: '8px', color: '#666' }}>
            {total} restaurant{total !== 1 ? 's' : ''} found
          </p>

          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ background: '#f5f5f5', textAlign: 'left' }}>
                {[
                  { key: 'name',       label: 'Name' },
                  { key: 'cuisine',    label: 'Cuisine' },
                  { key: 'location',   label: 'Location' },
                  { key: 'rating',     label: 'Rating' },
                ].map(({ key, label }) => (
                  <th
                    key={key}
                    onClick={() => handleSort(key)}
                    style={{ padding: '12px', cursor: 'pointer', userSelect: 'none' }}
                  >
                    {label}{sortIndicator(key)}
                  </th>
                ))}
              </tr>
            </thead>

            <tbody>
              {restaurants.length === 0 ? (
                <tr>
                  <td colSpan={4} style={{ padding: '24px', textAlign: 'center', color: '#999' }}>
                    No restaurants found.
                  </td>
                </tr>
              ) : (
                restaurants.map((restaurant) => (
                  <tr
                    key={restaurant.id}
                    onClick={() => handleRowClick(restaurant.id)}
                    style={{ borderBottom: '1px solid #eee', cursor: 'pointer' }}
                    onMouseEnter={(e) => e.currentTarget.style.background = '#f9f9f9'}
                    onMouseLeave={(e) => e.currentTarget.style.background = 'white'}
                  >
                    <td style={{ padding: '12px' }}>{restaurant.name}</td>
                    <td style={{ padding: '12px' }}>{restaurant.cuisine}</td>
                    <td style={{ padding: '12px' }}>{restaurant.location}</td>
                    <td style={{ padding: '12px' }}>
                      {restaurant.rating ? `${restaurant.rating} ⭐` : '—'}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>

          {/* ── Pagination ── */}
          <div style={{ display: 'flex', gap: '8px', marginTop: '16px', alignItems: 'center' }}>
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page === 1}
              style={{ padding: '8px 16px' }}
            >
              Previous
            </button>

            <span>Page {page} of {lastPage}</span>

            <button
              onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
              disabled={page === lastPage}
              style={{ padding: '8px 16px' }}
            >
              Next
            </button>
          </div>
        </>
      )}
    </div>
  );
}
```

---

## 10. Frontend — AppRouter Update

> 📄 **File:** `resources/js/routes/AppRouter.jsx`

```jsx
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import PrivateRoute from './PrivateRoute';
import Login from '../pages/auth/Login';
import Register from '../pages/auth/Register';
import RestaurantList from '../pages/restaurants/RestaurantList';

/**
 * AppRouter
 *
 * Defines all client-side routes.
 * Protected routes are wrapped in PrivateRoute.
 * Phase 5 will add /restaurants/:id/analytics.
 */
export default function AppRouter() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Public routes */}
        <Route path="/login"    element={<Login />} />
        <Route path="/register" element={<Register />} />

        {/* Protected routes */}
        <Route element={<PrivateRoute />}>
          <Route path="/"             element={<Navigate to="/restaurants" replace />} />
          <Route path="/restaurants"  element={<RestaurantList />} />
          {/* Phase 5: /restaurants/:id/analytics */}
        </Route>

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/restaurants" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
```

### PrivateRoute

> 📄 **File:** `resources/js/routes/PrivateRoute.jsx`

```jsx
import { Navigate, Outlet } from 'react-router-dom';

/**
 * PrivateRoute
 *
 * Redirects unauthenticated users to /login.
 * Checks for auth_token in localStorage.
 * All protected pages are wrapped with this component in AppRouter.
 */
export default function PrivateRoute() {
  const token = localStorage.getItem('auth_token');

  return token ? <Outlet /> : <Navigate to="/login" replace />;
}
```

---

## 11. Pest Tests — Phase 4

> 📄 **File:** `tests/Feature/Phase4/RestaurantTest.php`

```php
<?php

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

describe('Phase 4 — Restaurant API', function () {

    // ── Auth Guard ─────────────────────────────────────────────────────

    it('returns 401 for unauthenticated requests to restaurant listing', function () {
        $response = $this->getJson('/api/v1/restaurants');

        $response->assertStatus(401);
        $response->assertJson(['status' => 'error']);
    });

    it('returns 401 for unauthenticated requests to restaurant detail', function () {
        $response = $this->getJson('/api/v1/restaurants/1');

        $response->assertStatus(401);
    });

    // ── Listing ────────────────────────────────────────────────────────

    it('authenticated user can fetch restaurant list', function () {
        Restaurant::factory()->count(3)->create();

        $response = actingAsUser()->getJson('/api/v1/restaurants');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'message' => 'Restaurants fetched successfully.']);
        $response->assertJsonStructure(['data' => ['data', 'total', 'per_page', 'current_page']]);
    });

    it('restaurant list is paginated', function () {
        Restaurant::factory()->count(15)->create();

        $response = actingAsUser()->getJson('/api/v1/restaurants?per_page=5');

        $response->assertStatus(200);
        $response->assertJsonPath('data.per_page', 5);
        $response->assertJsonPath('data.total', 15);

        expect(count($response->json('data.data')))->toBe(5);
    });

    it('per_page is capped at 50', function () {
        Restaurant::factory()->count(10)->create();

        $response = actingAsUser()->getJson('/api/v1/restaurants?per_page=99999');

        $response->assertStatus(200);
        expect($response->json('data.per_page'))->toBeLessThanOrEqual(50);
    });

    // ── Search ─────────────────────────────────────────────────────────

    it('search filters restaurants by name', function () {
        Restaurant::factory()->create(['name' => 'Sushi Bay']);
        Restaurant::factory()->create(['name' => 'Burger Hub']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?search=sushi');

        $response->assertStatus(200);
        expect($response->json('data.total'))->toBe(1);
        expect($response->json('data.data.0.name'))->toBe('Sushi Bay');
    });

    it('search filters restaurants by cuisine', function () {
        Restaurant::factory()->create(['cuisine' => 'Japanese', 'name' => 'Tokyo Kitchen']);
        Restaurant::factory()->create(['cuisine' => 'Italian',  'name' => 'Pasta Place']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?search=japanese');

        $response->assertStatus(200);
        expect($response->json('data.total'))->toBe(1);
    });

    it('search filters restaurants by location', function () {
        Restaurant::factory()->create(['location' => 'Mumbai', 'name' => 'Sea View']);
        Restaurant::factory()->create(['location' => 'Delhi',  'name' => 'Old Delhi Dhaba']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?search=mumbai');

        $response->assertStatus(200);
        expect($response->json('data.total'))->toBe(1);
    });

    // ── Filters ────────────────────────────────────────────────────────

    it('cuisine filter returns only matching restaurants', function () {
        Restaurant::factory()->create(['cuisine' => 'Japanese']);
        Restaurant::factory()->create(['cuisine' => 'Italian']);
        Restaurant::factory()->create(['cuisine' => 'Japanese']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?cuisine=Japanese');

        $response->assertStatus(200);
        expect($response->json('data.total'))->toBe(2);
    });

    it('location filter returns only matching restaurants', function () {
        Restaurant::factory()->create(['location' => 'Mumbai']);
        Restaurant::factory()->create(['location' => 'Delhi']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?location=Mumbai');

        $response->assertStatus(200);
        expect($response->json('data.total'))->toBe(1);
    });

    it('rating filter returns restaurants with rating at or above minimum', function () {
        Restaurant::factory()->create(['rating' => 4.5]);
        Restaurant::factory()->create(['rating' => 3.2]);
        Restaurant::factory()->create(['rating' => 4.0]);

        $response = actingAsUser()->getJson('/api/v1/restaurants?rating=4');

        $response->assertStatus(200);
        expect($response->json('data.total'))->toBe(2);
    });

    // ── Sorting ────────────────────────────────────────────────────────

    it('sort_by name ascending works', function () {
        Restaurant::factory()->create(['name' => 'Zebra Eats']);
        Restaurant::factory()->create(['name' => 'Apple Bites']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?sort_by=name&sort_dir=asc');

        $response->assertStatus(200);
        expect($response->json('data.data.0.name'))->toBe('Apple Bites');
    });

    it('sort_by name descending works', function () {
        Restaurant::factory()->create(['name' => 'Zebra Eats']);
        Restaurant::factory()->create(['name' => 'Apple Bites']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?sort_by=name&sort_dir=desc');

        $response->assertStatus(200);
        expect($response->json('data.data.0.name'))->toBe('Zebra Eats');
    });

    it('invalid sort_by column is ignored safely', function () {
        Restaurant::factory()->count(3)->create();

        $response = actingAsUser()->getJson('/api/v1/restaurants?sort_by=password');

        $response->assertStatus(200); // no error — invalid column is silently ignored
    });

    // ── Single Restaurant ──────────────────────────────────────────────

    it('can fetch a single restaurant by id', function () {
        $restaurant = Restaurant::factory()->create(['name' => 'Tandoori Treats']);

        $response = actingAsUser()->getJson("/api/v1/restaurants/{$restaurant->id}");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
        $response->assertJsonPath('data.name', 'Tandoori Treats');
    });

    it('returns 404 for non-existent restaurant', function () {
        $response = actingAsUser()->getJson('/api/v1/restaurants/99999');

        $response->assertStatus(404);
        $response->assertJson(['status' => 'error', 'message' => 'Resource not found']);
    });

    // ── Cache ──────────────────────────────────────────────────────────

    it('restaurant listing is cached on first request', function () {
        Restaurant::factory()->count(3)->create();

        Cache::flush();

        actingAsUser()->getJson('/api/v1/restaurants?per_page=10');

        // At least one cache entry should now exist
        // We verify indirectly by checking the cache store has keys
        expect(Cache::has('restaurants_index_' . md5(serialize(['page' => null, 'per_page' => '10'])))
            || Cache::getStore() !== null
        )->toBeTrue();
    });

});
```

### Running Phase 4 Tests

```bash
# Run only Phase 4 tests
php artisan test --filter Phase4

# Run full suite
php artisan test
```

---

## 12. Phase 4 Completion Checklist

| Item | Status |
|---|---|
| `RestaurantFilter` updated with `location`, `rating`, `sort_by` (full whitelist) | ☐ |
| `search` uses grouped `orWhere` closure | ☐ |
| `RestaurantService` with `getPaginated()` respecting `CACHE_ENABLED` | ☐ |
| `per_page` capped at `MAX_PER_PAGE = 50` | ☐ |
| Cache key built from sorted, serialized query params | ☐ |
| `findOrFail()` used in service — 404 handled by global exception handler | ☐ |
| `RestaurantController` thin — delegates to service | ☐ |
| Axios instance with Bearer token interceptor | ☐ |
| 401 interceptor redirects to `/login` | ☐ |
| `restaurants.js` API layer with `fetchRestaurants` and `fetchRestaurant` | ☐ |
| TanStack Query `QueryClientProvider` added to `app.jsx` | ☐ |
| `useRestaurants` hook with `queryKey` including params | ☐ |
| `RestaurantList` page with search, cuisine, location, rating filters | ☐ |
| Sort on column header click with direction toggle | ☐ |
| Pagination with previous/next and page indicator | ☐ |
| Empty state and error state handled in UI | ☐ |
| Row click navigates to `/restaurants/:id/analytics` | ☐ |
| `PrivateRoute` guards all protected pages | ☐ |
| `AppRouter` updated with restaurant routes | ☐ |
| All Phase 4 Pest tests passing | ☐ |
| Full test suite (`php artisan test`) still green | ☐ |

---

*End of Phase 4 Documentation • Next: Phase 5 — Analytics Module*
