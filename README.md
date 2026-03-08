# 🏗️ Application Overview

**Stack:** Laravel 12 API + Next.js (React) Frontend + Sanctum + MySQL + Pest + Scramble (API Docs)

**Repo structure:**
- `backend/`: Laravel 12 API providing data and authentication logic.
- `client/`: Standalone Next.js 16 (App Router) frontend application.

---

- [🏗️ Application Overview](#️-application-overview)
  - [� Getting Started](#-getting-started)
    - [Prerequisites](#prerequisites)
    - [Environment Setup](#environment-setup)
    - [Database Setup](#database-setup)
    - [Installation](#installation)
    - [Running the Project](#running-the-project)
    - [Running Tests](#running-tests)
    - [Additional Commands](#additional-commands)
    - [Troubleshooting](#troubleshooting)
  - [�📦 Phases](#-phases)
    - [**Phase 1 — Project Scaffolding \& Environment Setup**](#phase-1--project-scaffolding--environment-setup)
    - [**Phase 2 — Database Design \& Migrations**](#phase-2--database-design--migrations)
    - [**Phase 3 — Authentication Module**](#phase-3--authentication-module)
    - [**Phase 4 — Restaurant Module (API + UI)**](#phase-4--restaurant-module-api--ui)
    - [**Phase 5 — Analytics \& Dashboard Module (API + UI)**](#phase-5--analytics--dashboard-module-api--ui)
  - [🗺️ User Flow Summary](#️-user-flow-summary)


---

## � Getting Started

### Prerequisites

Before you begin, ensure you have the following installed on your system:

- **PHP 8.3 or higher** - Check with: `php --version`
- **Composer** - PHP dependency manager
- **Node.js 20 or higher** - Check with: `node --version`
- **npm** - Node package manager (comes with Node.js)
- **MySQL 8.0 or higher** - Database server

### Environment Setup

1. **Verify PHP Version:**
   ```bash
   php --version
   ```
   Should show PHP 8.3.x or higher.

2. **Verify Node.js Version:**
   ```bash
   node --version
   ```
   Should show v20.x.x or higher.

3. **Verify npm Version:**
   ```bash
   npm --version
   ```
   Should show 10.x.x or higher.

### Database Setup

Create two MySQL databases - one for the main application and one for testing:

```sql
-- Create main database
CREATE DATABASE restaurant_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create testing database
CREATE DATABASE restaurant_dashboard_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Optional: Create a database user (recommended for security)
CREATE USER 'restaurant_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON restaurant_dashboard.* TO 'restaurant_user'@'localhost';
GRANT ALL PRIVILEGES ON restaurant_dashboard_testing.* TO 'restaurant_user'@'localhost';
FLUSH PRIVILEGES;
```

### Installation

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd restaurant-dashboard
   ```

2. **Backend Setup:**
   ```bash
   cd backend
   composer install
   ```

3. **Frontend Setup:**
   ```bash
   cd ../client
   npm install
   ```

4. **Environment Configuration for Backend:**
   ```bash
   cd backend
   cp .env.example .env
   ```

   Edit `.env` file and configure the following:
   ```env
   APP_NAME="Restaurant Analytics Dashboard"
   APP_ENV=local
   APP_KEY=base64:your_app_key_here
   APP_DEBUG=true
   APP_URL=http://localhost:8000
   FRONTEND_URL=http://localhost:3000

   # Database Configuration
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=restaurant_dashboard
   DB_USERNAME=your_mysql_username
   DB_PASSWORD=your_mysql_password

   # Testing Database (separate from main DB)
   DB_TESTING_DATABASE=restaurant_dashboard_testing

   # Cache Configuration (File-based)
   CACHE_DRIVER=file

   # Sanctum Configuration
   SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
   ```

5. **Generate Application Key & Build Database:**
   ```bash
   php artisan key:generate
   php artisan migrate:fresh --seed
   ```

6. **Environment Configuration for Frontend:**
   ```bash
   cd ../client
   cp env.example .env.local
   ```
   Ensure `.env.local` has: `NEXT_PUBLIC_API_URL=http://localhost:8000`

### Running the Project

1. **Start the Laravel Backend API:**
   ```bash
   cd backend
   php artisan serve
   ```
   - API will be available at: `http://localhost:8000`
   - **Interactive API Documentation (Scramble):** `http://localhost:8000/docs/api`

2. **Start the Next.js Frontend Development Server:**
   ```bash
   cd client
   npm run dev
   ```
   - Frontend will be available at: `http://localhost:3000`

3. **Access the Application:**
   - Open your browser and navigate to `http://localhost:3000`
   - Log in with default seeded credentials (`test@example.com` / `password`), or register a new account!

### Running Tests

The project uses **Pest** as the testing framework. Run the full test suite:

```bash
php artisan test
```

Or run specific test groups:
```bash
# Run only feature tests
php artisan test --testsuite=Feature
php artisan test --filter=Phase1
php artisan test --filter=Phase2
php artisan test --filter=Phase3
php artisan test --filter=Phase4
php artisan test --filter=Phase5

# Run only unit tests
php artisan test --testsuite=Unit

# Run tests with coverage
php artisan test --coverage
```

### Additional Commands

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Run database migrations (without seeders)
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Run only seeders (after migrations exist)
php artisan db:seed

# Check Laravel/PHP code style
./vendor/bin/pint --test

# Fix Laravel/PHP code style
./vendor/bin/pint

# Check frontend code style
npm run format
```

### Troubleshooting

**Common Issues:**

1. **Port 8000 already in use:**
   ```bash
   # Use a different port
   php artisan serve --port=8001
   ```

2. **Port 3000 already in use:**
   ```bash
   # Use a different port
   npm run dev -- --port 3001
   ```

3. **Database connection issues:**
   - Verify MySQL is running
   - Check database credentials in `.env`
   - Ensure databases exist

4. **CORS issues:**
   - Make sure Sanctum domains are configured correctly in `.env`
   - Clear config cache: `php artisan config:clear`

5. **Cache issues:**
   - Clear all caches: `php artisan cache:clear && php artisan config:clear`

---

## �📦 Phases

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

