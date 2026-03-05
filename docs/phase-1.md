# 🍽️ Restaurant Analytics Platform
## Phase 1 — Project Scaffolding & Environment Setup
### Detailed Actionable Documentation

> **Stack:** Laravel 12 + Sanctum + React (Vite) + MySQL + File Cache + Pest

---

| Attribute | Value |
|---|---|
| Laravel Version | 12.x (latest stable) |
| PHP Version | 8.3+ |
| Node Version | 20.x LTS |
| Frontend | React 18 via Vite (inside `resources/js`) |
| Auth | Laravel Sanctum (SPA cookie tokens) |
| Cache Driver | File (`CACHE_STORE=file`) |
| Database | MySQL 8.0+ |
| Testing | Pest PHP |
| Code Style | Laravel Pint (PHP) + ESLint/Prettier (JS) |
| API Prefix | `/api/v1` |

---

## Table of Contents

- [🍽️ Restaurant Analytics Platform](#️-restaurant-analytics-platform)
  - [Phase 1 — Project Scaffolding \& Environment Setup](#phase-1--project-scaffolding--environment-setup)
    - [Detailed Actionable Documentation](#detailed-actionable-documentation)
  - [Table of Contents](#table-of-contents)
  - [1. Phase Goals \& Deliverables](#1-phase-goals--deliverables)
    - [Deliverables Checklist](#deliverables-checklist)
  - [2. Folder Structure](#2-folder-structure)
    - [Backend Structure (`app/`)](#backend-structure-app)
    - [Frontend Structure (`resources/js/`)](#frontend-structure-resourcesjs)
    - [Routes \& Config Files](#routes--config-files)
  - [3. Step-by-Step Setup](#3-step-by-step-setup)
    - [Step 1 — Create Laravel 12 Project](#step-1--create-laravel-12-project)
    - [Step 2 — Install PHP Dependencies](#step-2--install-php-dependencies)
    - [Step 3 — Install and Configure React via Vite](#step-3--install-and-configure-react-via-vite)
    - [Step 4 — Configure Vite for React](#step-4--configure-vite-for-react)
    - [Step 5 — `.env` Configuration](#step-5--env-configuration)
    - [Step 6 — Publish Sanctum \& Generate App Key](#step-6--publish-sanctum--generate-app-key)
  - [4. ApiController — Standard Response Methods](#4-apicontroller--standard-response-methods)
    - [Standard Response Shape](#standard-response-shape)
  - [5. QueryFilter — Abstract Base Filter Class](#5-queryfilter--abstract-base-filter-class)
    - [Example: RestaurantFilter extends QueryFilter](#example-restaurantfilter-extends-queryfilter)
  - [6. Global Exception Handler](#6-global-exception-handler)
  - [7. API Route Structure](#7-api-route-structure)
  - [8. Sanctum \& CORS Configuration](#8-sanctum--cors-configuration)
    - [Sanctum Config](#sanctum-config)
    - [CORS Config](#cors-config)
  - [9. Code Quality Configuration](#9-code-quality-configuration)
    - [Laravel Pint (PHP)](#laravel-pint-php)
    - [ESLint + Prettier (JS/React)](#eslint--prettier-jsreact)
  - [10. Pest — Test Setup \& Phase 1 Smoke Tests](#10-pest--test-setup--phase-1-smoke-tests)
    - [Pest Configuration](#pest-configuration)
    - [Phase 1 Smoke Tests](#phase-1-smoke-tests)
    - [Running Tests](#running-tests)
  - [11. Local Setup — Full Quickstart](#11-local-setup--full-quickstart)
  - [12. Phase 1 Completion Checklist](#12-phase-1-completion-checklist)

---

## 1. Phase Goals & Deliverables

This phase establishes the complete foundation of the project. Every subsequent phase depends on what is built here. By the end of Phase 1, you should have a running Laravel 12 application with React integrated, a working Sanctum setup, a consistent API response structure, a dedicated `QueryFilter` base class, and Pest configured and green.

### Deliverables Checklist

- [ ] Laravel 12 project scaffolded and running
- [ ] React 18 configured via Vite inside `resources/js`
- [ ] MySQL database connected and `.env` configured
- [ ] File-based caching configured (`CACHE_STORE=file`)
- [ ] Laravel Sanctum installed for SPA auth
- [ ] CORS configured for same-domain SPA
- [ ] API versioning structure under `/api/v1`
- [ ] `ApiController` base class with standard response methods
- [ ] `QueryFilter.php` abstract base class for all filters
- [ ] `Filterable` trait on models
- [ ] `RestaurantFilter` extending `QueryFilter`
- [ ] Model-Controller-Service folder structure enforced
- [ ] Global exception handler returning JSON error responses
- [ ] Laravel Pint + ESLint + Prettier configured
- [ ] Pest installed with base `TestCase` and Phase 1 smoke tests passing

> 🔮 **Enhancement note:** Redis driver swap is a one-line `.env` change once infra is ready — `CACHE_STORE=redis`. Zero code changes required.

---

## 2. Folder Structure

The project follows a strict **Model-Controller-Service (MCS)** pattern. All business logic lives in Service classes, controllers are thin and only handle HTTP concerns, and models define relationships and scopes only.

### Backend Structure (`app/`)

```
app/
├── Console/
├── Exceptions/
│   └── Handler.php                   ← Global JSON error handler
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           ├── ApiController.php  ← Base: success(), error(), notFound()
│   │           ├── Auth/
│   │           │   └── AuthController.php
│   │           ├── RestaurantController.php
│   │           └── AnalyticsController.php
│   ├── Requests/
│   │   ├── Auth/
│   │   │   ├── LoginRequest.php
│   │   │   └── RegisterRequest.php
│   │   └── Restaurant/
│   │       └── RestaurantFilterRequest.php
│   └── Middleware/
├── Models/
│   ├── User.php
│   ├── Restaurant.php
│   └── Order.php
├── Services/
│   ├── AuthService.php
│   ├── RestaurantService.php
│   └── AnalyticsService.php
├── Filters/
│   ├── QueryFilter.php                ← Abstract base filter class
│   └── RestaurantFilter.php           ← Extends QueryFilter
└── Traits/
    └── Filterable.php                 ← Model trait to apply filters
```

### Frontend Structure (`resources/js/`)

```
resources/js/
├── api/
│   └── axios.js                       ← Configured Axios instance
├── components/
│   ├── ui/                            ← Reusable UI components
│   └── layout/
│       ├── AppLayout.jsx
│       └── AuthLayout.jsx
├── context/
│   └── AuthContext.jsx                ← Global auth state
├── hooks/
│   └── useAuth.js                     ← Auth hook
├── pages/
│   ├── auth/
│   │   ├── Login.jsx
│   │   └── Register.jsx
│   ├── Dashboard.jsx
│   └── restaurants/
│       ├── RestaurantList.jsx
│       └── RestaurantAnalytics.jsx
├── routes/
│   ├── AppRouter.jsx                  ← Route definitions
│   └── PrivateRoute.jsx               ← Auth-protected wrapper
├── app.jsx                            ← React root
└── bootstrap.js
```

### Routes & Config Files

```
routes/
├── api.php                            ← All /api/v1 routes
└── web.php                            ← Catch-all for SPA

config/
├── sanctum.php
├── cors.php
└── cache.php
```

---

## 3. Step-by-Step Setup

### Step 1 — Create Laravel 12 Project

```bash
composer create-project laravel/laravel restaurant-analytics
cd restaurant-analytics
```

### Step 2 — Install PHP Dependencies

```bash
# Laravel Sanctum for SPA auth
composer require laravel/sanctum

# Laravel Pint for code style
composer require laravel/pint --dev

# Pest for testing
composer require pestphp/pest --dev --with-all-dependencies
composer require pestphp/pest-plugin-laravel --dev
./vendor/bin/pest --init
```

### Step 3 — Install and Configure React via Vite

```bash
npm install
npm install react react-dom
npm install @vitejs/plugin-react --save-dev

# Additional frontend dependencies
npm install axios react-router-dom @tanstack/react-query recharts
npm install tailwindcss @tailwindcss/vite --save-dev

# Code quality
npm install eslint prettier eslint-plugin-react --save-dev
```

### Step 4 — Configure Vite for React

> 📄 **File:** `vite.config.js`

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.jsx'], refresh: true }),
        react(),
        tailwindcss(),
    ],
});
```

### Step 5 — `.env` Configuration

```env
APP_NAME="Restaurant Analytics"
APP_ENV=local
APP_KEY=   # auto-set by php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=restaurant_analytics
DB_USERNAME=root
DB_PASSWORD=your_password

# Cache — File (default)
CACHE_STORE=file

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DRIVER=cookie
SESSION_DOMAIN=localhost
```

> 🔮 **Enhancement:** To switch to Redis later, change `CACHE_STORE=redis` and add `REDIS_HOST`, `REDIS_PORT`. Zero code changes required.

### Step 6 — Publish Sanctum & Generate App Key

```bash
php artisan key:generate
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

---

## 4. ApiController — Standard Response Methods

All controllers extend this base class. It enforces a consistent JSON response contract across every endpoint in the application. Never return raw arrays or `response()` calls from child controllers — always use these methods.

> 📄 **File:** `app/Http/Controllers/Api/V1/ApiController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * ApiController
 *
 * Base controller for all API endpoints.
 * Provides a consistent JSON response structure across the application.
 * All V1 controllers MUST extend this class.
 */
abstract class ApiController extends Controller
{
    /**
     * Return a 200 success response.
     *
     * @param  mixed  $data     The payload to return.
     * @param  string $message  Human-readable success message.
     * @param  int    $code     HTTP status code.
     */
    protected function success(mixed $data = null, string $message = 'Success', int $code = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Return a 201 created response.
     *
     * @param  mixed  $data
     * @param  string $message
     */
    protected function created(mixed $data = null, string $message = 'Resource created'): JsonResponse
    {
        return $this->success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Return a 404 not found response.
     *
     * @param  string $message
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'data'    => null,
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Return a 422 validation error response.
     *
     * @param  mixed  $errors   Validation error bag.
     * @param  string $message
     */
    protected function validationError(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Return a generic error response.
     *
     * @param  string $message
     * @param  int    $code     HTTP status code.
     * @param  mixed  $errors   Optional additional error context.
     */
    protected function error(string $message = 'Something went wrong', int $code = Response::HTTP_INTERNAL_SERVER_ERROR, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }

    /**
     * Return a 401 unauthorized response.
     *
     * @param  string $message
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a 403 forbidden response.
     *
     * @param  string $message
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, Response::HTTP_FORBIDDEN);
    }
}
```

The key change is importing `Illuminate\Http\Response` and replacing all raw integers with its constants:

| Before | After |
|---|---|
| `200` | `Response::HTTP_OK` |
| `201` | `Response::HTTP_CREATED` |
| `404` | `Response::HTTP_NOT_FOUND` |
| `422` | `Response::HTTP_UNPROCESSABLE_ENTITY` |
| `500` | `Response::HTTP_INTERNAL_SERVER_ERROR` |
| `401` | `Response::HTTP_UNAUTHORIZED` |
| `403` | `Response::HTTP_FORBIDDEN` |

This is the idiomatic Laravel/Symfony way — self-documenting, no magic numbers, and your IDE can autocomplete the full list of HTTP constants directly from `Response::HTTP_*`.

### Standard Response Shape

Every response from the API follows this contract:

```json
// Success
{
  "status":  "success",
  "message": "Restaurants fetched",
  "data":    { }
}

// Error / Not Found
{
  "status":  "error",
  "message": "Resource not found",
  "data":    null
}

// Validation
{
  "status":  "error",
  "message": "Validation failed",
  "errors":  { "email": ["The email field is required."] }
}
```

---

## 5. QueryFilter — Abstract Base Filter Class

The `QueryFilter` pattern decouples filter logic from controllers and models. Every filter class extends `QueryFilter`. A `Filterable` trait on the model allows calling `.filter($filter)` directly on Eloquent query builders.

> 📄 **File:** `app/Filters/QueryFilter.php`

```php
<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * QueryFilter
 *
 * Abstract base class for all Eloquent query filters.
 * Each filter class extends this and defines individual filter methods.
 * Method names map directly to request query param names.
 *
 * Usage: $filter->apply($builder) — called automatically by Filterable trait.
 */
abstract class QueryFilter
{
    protected Builder $builder;
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Attach the filter to a given Eloquent builder.
     * Called automatically by the Filterable trait.
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        // Loop through each query param and call matching method if it exists
        foreach ($this->filters() as $name => $value) {
            if (method_exists($this, $name) && filled($value)) {
                $this->$name($value);
            }
        }

        return $this->builder;
    }

    /**
     * Return all active filters from the request.
     * Only includes params that are present and non-empty.
     */
    protected function filters(): array
    {
        return array_filter($this->request->all(), fn ($value) => filled($value));
    }
}
```

> 📄 **File:** `app/Traits/Filterable.php`

```php
<?php

namespace App\Traits;

use App\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filterable Trait
 *
 * Add this trait to any Eloquent model to enable the filter() scope.
 * Usage: Restaurant::query()->filter($restaurantFilter)->paginate(15);
 */
trait Filterable
{
    /**
     * Scope: apply a QueryFilter to the current builder.
     */
    public function scopeFilter(Builder $builder, QueryFilter $filter): Builder
    {
        return $filter->apply($builder);
    }
}
```

### Example: RestaurantFilter extends QueryFilter

> 📄 **File:** `app/Filters/RestaurantFilter.php`

```php
<?php

namespace App\Filters;

/**
 * RestaurantFilter
 *
 * Handles search, sort, and filter logic for the Restaurant listing.
 * Each public method name corresponds to a query param key.
 *
 * Supported params: search, cuisine, sort_by, sort_dir
 */
class RestaurantFilter extends QueryFilter
{
    /**
     * Filter: search restaurants by name (case-insensitive partial match).
     */
    public function search(string $value): void
    {
        $this->builder->where('name', 'like', "%{$value}%");
    }

    /**
     * Filter: filter by cuisine type.
     */
    public function cuisine(string $value): void
    {
        $this->builder->where('cuisine', $value);
    }

    /**
     * Sort: sort by a given column (whitelist enforced).
     */
    public function sort_by(string $value): void
    {
        $allowed = ['name', 'rating', 'created_at'];
        $dir     = $this->request->get('sort_dir', 'asc');

        if (in_array($value, $allowed)) {
            $this->builder->orderBy($value, $dir === 'desc' ? 'desc' : 'asc');
        }
    }
}
```

---

## 6. Global Exception Handler

Laravel 12 uses `bootstrap/app.php` for exception handling. All unhandled exceptions return a structured JSON response — no HTML error pages leak through the API.

> 📄 **File:** `bootstrap/app.php` — `withExceptions()` block

```php
->withExceptions(function (Exceptions $exceptions) {

    // Handle model not found (route model binding failures)
    $exceptions->render(function (ModelNotFoundException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Resource not found',
                'data'    => null,
            ], 404);
        }
    });

    // Handle auth failures
    $exceptions->render(function (AuthenticationException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthenticated',
                'data'    => null,
            ], 401);
        }
    });

    // Handle validation (FormRequest failures)
    $exceptions->render(function (ValidationException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }
    });

})
```

---

## 7. API Route Structure

> 📄 **File:** `routes/api.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\RestaurantController;
use App\Http\Controllers\Api\V1\AnalyticsController;

Route::prefix('v1')->group(function () {

    // ── Public Auth Routes ----------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login',    [AuthController::class, 'login']);
    });

    // ── Protected Routes (Sanctum) ----------------------------------------
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me',      [AuthController::class, 'me']);

        // Restaurants
        Route::get('/restaurants',      [RestaurantController::class, 'index']);
        Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);

        // Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('/restaurant/{id}', [AnalyticsController::class, 'restaurant']);
            Route::get('/top-restaurants', [AnalyticsController::class, 'topRestaurants']);
            Route::get('/orders',          [AnalyticsController::class, 'orders']);
        });
    });
});
```

Also update `routes/web.php` to serve the SPA for all non-API routes:

```php
// routes/web.php
Route::get('/{any}', function () {
    return view('app'); // blade view that loads Vite/React
})->where('any', '.*');
```

---

## 8. Sanctum & CORS Configuration

### Sanctum Config

> 📄 **File:** `config/sanctum.php` — `stateful` key

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000',
    Str::contains(env('APP_URL', ''), 'https://')
        ? ',' . parse_url(env('APP_URL', ''), PHP_URL_HOST)
        : ''
))),
```

### CORS Config

> 📄 **File:** `config/cors.php`

```php
return [
    'paths'                => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'      => ['*'],
    'allowed_origins'      => [env('APP_URL', 'http://localhost:5173')],
    'allowed_origins_patterns' => [],
    'allowed_headers'      => ['*'],
    'exposed_headers'      => [],
    'max_age'              => 0,
    'supports_credentials' => true,  // Required for Sanctum cookie auth
];
```

---

## 9. Code Quality Configuration

### Laravel Pint (PHP)

> 📄 **File:** `pint.json`

```json
{
    "preset": "laravel",
    "rules": {
        "single_quote":                true,
        "array_syntax":                { "syntax": "short" },
        "ordered_imports":             { "sort_algorithm": "alpha" },
        "no_unused_imports":           true,
        "trailing_comma_in_multiline": true
    }
}
```

Run Pint:

```bash
./vendor/bin/pint
```

### ESLint + Prettier (JS/React)

> 📄 **File:** `.prettierrc`

```json
{
  "semi":          true,
  "singleQuote":   true,
  "tabWidth":      2,
  "trailingComma": "es5",
  "printWidth":    100
}
```

---

## 10. Pest — Test Setup & Phase 1 Smoke Tests

Pest is configured as the sole test runner. All tests use the Laravel plugin for access to artisan helpers, `RefreshDatabase`, and `actingAs()`.

### Pest Configuration

> 📄 **File:** `tests/Pest.php`

```php
<?php

// Assign RefreshDatabase trait to all Feature tests automatically
uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

// Helper: create and authenticate a user
function actingAsUser(): Tests\TestCase
{
    $user = \App\Models\User::factory()->create();
    return test()->actingAs($user);
}
```

### Phase 1 Smoke Tests

> 📄 **File:** `tests/Feature/Phase1/ScaffoldingTest.php`

```php
<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

describe('Phase 1 — Scaffolding & Environment', function () {

    it('connects to the database successfully', function () {
        expect(fn () => DB::connection()->getPdo())->not->toThrow(Exception::class);
    });

    it('uses file cache driver', function () {
        expect(config('cache.default'))->toBe('file');
    });

    it('can write to and read from the file cache', function () {
        Cache::put('smoke_test', 'ok', 60);
        expect(Cache::get('smoke_test'))->toBe('ok');
        Cache::forget('smoke_test');
    });

    it('api v1 prefix is registered', function () {
        $routes = collect(Route::getRoutes()->getRoutesByMethod()['POST'] ?? [])
            ->keys()
            ->filter(fn ($r) => str_starts_with($r, 'api/v1'));
        expect($routes)->not->toBeEmpty();
    });

    it('returns 404 JSON for unknown api routes', function () {
        $response = $this->getJson('/api/v1/nonexistent-route');
        $response->assertStatus(404);
        $response->assertJsonStructure(['status', 'message', 'data']);
    });

    it('returns 401 JSON for unauthenticated requests to protected routes', function () {
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
        $response->assertJson(['status' => 'error', 'message' => 'Unauthenticated']);
    });

});
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run only Phase 1 tests
php artisan test --filter Phase1

# Run with coverage (requires Xdebug)
php artisan test --coverage
```

---

## 11. Local Setup — Full Quickstart

| Step | Command / Action |
|---|---|
| 1. Clone & install PHP deps | `composer install` |
| 2. Install Node deps | `npm install` |
| 3. Copy env file | `cp .env.example .env` |
| 4. Generate app key | `php artisan key:generate` |
| 5. Create MySQL database | `CREATE DATABASE restaurant_analytics;` |
| 6. Run migrations | `php artisan migrate` |
| 7. Seed mock data (Phase 2+) | `php artisan db:seed` |
| 8. Start Laravel backend | `php artisan serve` (port 8000) |
| 9. Start React frontend | `npm run dev` (port 5173) |
| 10. Run test suite | `php artisan test` |

> 💡 **Note:** Both `php artisan serve` and `npm run dev` must run simultaneously in separate terminals. The React app at `:5173` proxies API calls to Laravel at `:8000`.

---

## 12. Phase 1 Completion Checklist

| Item | Status |
|---|---|
| Laravel 12 project created | ☐ |
| React 18 + Vite configured in `resources/js` | ☐ |
| MySQL connected + `.env` configured | ☐ |
| File cache configured (`CACHE_STORE=file`) | ☐ |
| Sanctum installed and published | ☐ |
| CORS configured for `localhost:5173` | ☐ |
| API versioning under `/api/v1` | ☐ |
| `ApiController` with all response methods | ☐ |
| `QueryFilter.php` abstract base class | ☐ |
| `Filterable` trait on models | ☐ |
| `RestaurantFilter` extending `QueryFilter` | ☐ |
| MCS folder structure enforced | ☐ |
| Global exception handler returning JSON | ☐ |
| Laravel Pint configured | ☐ |
| ESLint + Prettier configured | ☐ |
| Pest installed + `Pest.php` configured | ☐ |
| Phase 1 smoke tests passing | ☐ |

---

*End of Phase 1 Documentation • Next: Phase 2 — Database Design & Migrations*
