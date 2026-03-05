# 🏗️ Application Overview

**Stack:** Laravel 12 + Sanctum + File Cache + React (Vite, inside `resources/js`) + MySQL + Pest

**Repo structure:** Monorepo — single Laravel project, React lives in `resources/js`, compiled via Vite.

---

## 📦 Phases

---

### **Phase 1 — Project Scaffolding & Environment Setup**
> *Foundation everything else builds on*

- Laravel 12 fresh install
- Vite + React configured inside `resources/js`
- MySQL database setup + `.env` configuration
- **File-based cache** configured (`CACHE_DRIVER=file` in `.env`)
- Laravel Sanctum installed & configured for SPA auth
- CORS policy configured (for SPA cookie-based auth)
- Base folder structure defined (API versioning under `api/v1`)
- Global exception handler + API response helper (consistent JSON responses)
- `pint` for Laravel code style, ESLint + Prettier for frontend
- **Pest** installed and configured as the default test runner

> 🔮 *Enhancement note: Redis driver swap is a one-line `.env` change once infra is ready*

---

### **Phase 2 — Database Design & Migrations**
> *Schema, relationships, and indexes*

- `users` table (Sanctum-ready)
- `restaurants` table (id, name, cuisine, location, rating, etc.)
- `orders` table (id, restaurant_id, amount, status, ordered_at, hour, etc.)
- Strategic **indexes** on `restaurant_id`, `ordered_at`, `amount` for query performance
- Relationships defined on Eloquent models
- **Laravel Seeders** — parse `restaurants.json` + `orders.json` and seed DB
- Factory stubs (for Pest test data generation)

**Pest Tests — Phase 2**
- Migration integrity checks (all tables + columns exist)
- Seeder runs without errors
- Model relationship assertions (`Restaurant` hasMany `Order`)
- Factory generates valid model instances

---

### **Phase 3 — Authentication Module**
> *Sanctum SPA auth — register, login, logout*

**Backend**
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- Form Request validation classes
- Auth middleware protecting all dashboard routes

**Frontend**
- `/login` and `/register` pages
- Axios instance with CSRF + cookie handling
- Auth context + `useAuth` hook
- Protected route wrapper (`<PrivateRoute />`)
- Persist auth state across refresh

**Pest Tests — Phase 3**
```
✔ a user can register with valid credentials
✔ registration fails with duplicate email
✔ registration fails with invalid payload
✔ a user can login with correct credentials
✔ login fails with wrong password
✔ an authenticated user can fetch their profile (/me)
✔ an unauthenticated request to /me returns 401
✔ a user can logout and token is invalidated
✔ protected routes reject unauthenticated requests
```

---

### **Phase 4 — Restaurant Module (API + UI)**
> *Listing, search, sort, filter*

**Backend**
- `GET /api/v1/restaurants` — paginated, with search / sort / filter query params
- `GET /api/v1/restaurants/{id}` — single restaurant detail
- Service class: `RestaurantService`
- **File cache** on restaurant listing (cache key includes all query params, e.g. `restaurants_search_cuisine_page`)
- Cache invalidation strategy documented per endpoint
- Query scopes on `Restaurant` model (`searchable`, `sortable`, `filterable`)

**Frontend**
- `/restaurants` page — data table with columns: name, cuisine, location, rating
- Search bar (by name)
- Sort controls (by name, rating)
- Filter panel (by cuisine, location)
- Pagination controls
- Click row → navigate to restaurant analytics

**Pest Tests — Phase 4**
```
✔ authenticated user can fetch paginated restaurant list
✔ unauthenticated request to restaurants returns 401
✔ search by name returns correct results
✔ filter by cuisine returns correct results
✔ sort by rating returns correctly ordered results
✔ pagination returns correct page size and meta
✔ fetching a single restaurant returns correct data
✔ fetching a non-existent restaurant returns 404
✔ restaurant listing response is served from file cache on repeat request
```

---

### **Phase 5 — Analytics & Dashboard Module (API + UI)**
> *Core of the assignment — heaviest phase*

**Backend**
- `GET /api/v1/analytics/restaurant/{id}` — order trends for a date range
  - Daily order count
  - Daily revenue
  - Average order value
  - Peak order hour per day
- `GET /api/v1/analytics/top-restaurants` — top 3 by revenue for a date range
- `GET /api/v1/analytics/orders` — paginated order list with filters (restaurant, date range, amount range, hour range)
- Dedicated `AnalyticsService` — all aggregation logic lives here, not in controllers
- **File cache** per query fingerprint (`md5` of serialized params as cache key)
- Cache TTL strategy documented:
  - Historical date ranges → longer TTL (60 min)
  - Recent/today ranges → shorter TTL (5 min)

**Frontend**
- `/dashboard` — global overview (top 3 restaurants widget + global filters)
- `/restaurants/{id}/analytics` — per-restaurant deep dive
- Charts (via **Recharts**):
  - Line chart — daily order count
  - Bar chart — daily revenue
  - Stat card — average order value
  - Bar/highlight chart — peak order hour
- Global filter panel: date range picker, amount range slider, hour range selector
- Orders table (paginated) below charts
- Loading skeletons + error states on all data-fetching components

**Pest Tests — Phase 5**
```
✔ restaurant analytics returns correct daily order count
✔ restaurant analytics returns correct daily revenue
✔ restaurant analytics returns correct average order value
✔ restaurant analytics returns correct peak order hour per day
✔ analytics returns 404 for non-existent restaurant
✔ analytics respects date range filter
✔ top 3 restaurants returns exactly 3 results
✔ top 3 is ordered by revenue descending
✔ top 3 respects date range filter
✔ orders list is paginated correctly
✔ orders filter by restaurant works
✔ orders filter by date range works
✔ orders filter by amount range works
✔ orders filter by hour range works
✔ analytics response is served from file cache on repeat request
✔ different date range params generate different cache entries
```

---

### **Phase 6 — Performance & Optimization**
> *What separates a good submission from a great one*

- File cache layer audit across all analytics + restaurant endpoints
- Cache key naming convention documented (`module_action_paramhash`)
- DB query optimization — `EXPLAIN` analysis, eager loading, avoiding N+1
- API response compression (gzip via middleware)
- React: `useMemo` / `useCallback` on expensive chart computations
- **TanStack Query** for data fetching — client-side caching, background refetch, stale time config
- Debounced search inputs (300ms)
- Pagination on all list endpoints

> 🔮 *Redis enhancement path: swap `CACHE_DRIVER=file` → `CACHE_DRIVER=redis`, add Redis config — zero code changes needed*

**Pest Tests — Phase 6**
```
✔ repeated identical API requests return cached response within TTL
✔ cache is bypassed correctly after TTL expiry
✔ N+1 queries are not present on restaurant listing (assert query count)
✔ analytics aggregation query count is within acceptable threshold
```

---

### **Phase 7 — Code Quality & Documentation**
> *Makes the interviewer trust the codebase*

- PHPDoc blocks on all service methods
- API documentation — **Postman collection** exported with all endpoints, example payloads, and responses
- Inline comments on non-obvious logic (cache key strategy, aggregation logic, TTL decisions)
- ESLint + Prettier enforced across `resources/js`
- `README.md` — full local setup guide:
  - Prerequisites (PHP 8.3, Node 20, MySQL, Composer)
  - `.env` setup (with annotated example)
  - `composer install && php artisan migrate --seed`
  - `php artisan serve` + `npm run dev`
  - `php artisan test` to run full Pest suite
  - Cache driver note + Redis upgrade path
- Folder structure documentation (backend + frontend)

---

### **Phase 8 — Testing Consolidation & CI Readiness**
> *Demonstrates engineering maturity*

- Full **Pest** test suite review — all phases consolidated
- `TestCase` base class setup with `RefreshDatabase` trait
- Shared test helpers: `actingAsUser()`, `createRestaurantWithOrders()` helpers
- `DatabaseSeeder` usable in test environment via `seed()` in tests
- Feature test coverage summary per module:

| Module | Tests |
|---|---|
| Auth | 9 |
| Restaurants | 9 |
| Analytics | 16 |
| Cache / Performance | 4 |
| **Total** | **38+** |

- GitHub Actions-ready `ci.yml` stub (runs `php artisan test` on push)

---

## 🗺️ User Flow Summary

```
Register / Login
      ↓
Dashboard (Top 3 Restaurants + Global Filters)
      ↓
Restaurant List (search / sort / filter / paginate)
      ↓
Restaurant Detail → Analytics View
  ├── Date range picker
  ├── Charts (daily orders, revenue, AOV, peak hour)
  └── Orders table (filtered + paginated)
      ↓
Logout
```

---

## 🔮 Enhancement Backlog (Post-Submission)
| Enhancement | Effort |
|---|---|
| Swap file cache → Redis | Minimal (`.env` change + config) |
| Rate limiting on API endpoints | Low |
| Export orders to CSV | Medium |
| Role-based access (admin vs viewer) | Medium |
| Real-time order updates (Laravel Echo + Pusher) | High |

---