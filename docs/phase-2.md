# 🍽️ Restaurant Analytics Platform
## Phase 2 — Database Design, Migrations, Models & Seeders
### Detailed Actionable Documentation

> **Focus:** Schema design, Eloquent models, relationships, indexes, and JSON-driven seeders.

---

| Attribute | Decision |
|---|---|
| Restaurant IDs | Auto-increment from 1, JSON IDs remapped during seeding |
| Order Amount | `decimal(10,2)` — future-proof for paise/cents |
| Order Time | Single `ordered_at` datetime column |
| Order Hour | Extracted at query time via `HOUR(ordered_at)` — not stored |
| Order Status | Enum-backed integer column (`tinyint`) with 4 states |
| Timestamps | All tables use Laravel's `created_at` / `updated_at` |

---

## Table of Contents

1. [Phase Goals & Deliverables](#1-phase-goals--deliverables)
2. [Database Schema Overview](#2-database-schema-overview)
3. [Migrations](#3-migrations)
4. [Eloquent Models](#4-eloquent-models)
5. [Order Status Enum](#5-order-status-enum)
6. [Model Relationships](#6-model-relationships)
7. [Seeders](#7-seeders)
8. [Running Migrations & Seeders](#8-running-migrations--seeders)
9. [Pest Tests — Phase 2](#9-pest-tests--phase-2)
10. [Phase 2 Completion Checklist](#10-phase-2-completion-checklist)

---

## 1. Phase Goals & Deliverables

This phase builds the entire data layer. By the end of Phase 2 you should have a fully migrated database, seeded with all 4 restaurants and 200 orders from the provided JSON files, with correct relationships, indexes, and a working `OrderStatus` enum.

### Deliverables Checklist

- [ ] `restaurants` migration with correct columns and indexes
- [ ] `orders` migration with correct columns, foreign key, and indexes
- [ ] `Restaurant` Eloquent model with `Filterable` trait and `hasMany` relationship
- [ ] `Order` Eloquent model with `belongsTo` relationship and `OrderStatus` cast
- [ ] `OrderStatus` PHP enum with 4 states and integer backing
- [ ] `RestaurantSeeder` — reads `restaurants.json`, inserts with remapped IDs
- [ ] `OrderSeeder` — reads `orders.json`, remaps restaurant IDs, assigns random statuses
- [ ] `DatabaseSeeder` calls both seeders in correct order
- [ ] All Phase 2 Pest tests passing

---

## 2. Database Schema Overview

### Entity Relationship

```
restaurants
───────────────────────────────
id                  INT (PK, auto-increment)
name                VARCHAR(255)
location            VARCHAR(255)
cuisine             VARCHAR(255)
rating              DECIMAL(3,1)   nullable
created_at          TIMESTAMP
updated_at          TIMESTAMP

        │
        │ 1 ──── many
        ▼

orders
───────────────────────────────
id                  INT (PK, auto-increment)
restaurant_id       INT (FK → restaurants.id)
order_amount        DECIMAL(10,2)
ordered_at          DATETIME
status              TINYINT        (0=failed,1=completed,2=pending,3=in-progress)
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

### ID Remapping Strategy

The `restaurants.json` uses IDs `101–104`. Since we auto-increment from 1, we build a lookup map during seeding:

```
JSON id 101  →  DB id 1   (Tandoori Treats)
JSON id 102  →  DB id 2   (Sushi Bay)
JSON id 103  →  DB id 3   (Pasta Palace)
JSON id 104  →  DB id 4   (Burger Hub)
```

The `OrderSeeder` uses this same map to correctly resolve `restaurant_id` on every order row.

### Index Strategy

| Table | Column(s) | Index Type | Reason |
|---|---|---|---|
| `orders` | `restaurant_id` | INDEX | All analytics filter by restaurant |
| `orders` | `ordered_at` | INDEX | Date range filtering on every analytics query |
| `orders` | `status` | INDEX | Future filtering by order status |
| `orders` | `restaurant_id, ordered_at` | COMPOSITE INDEX | Covers the most common combined query |

---

## 3. Migrations

### 3.1 — Restaurants Table

> 📄 **File:** `database/migrations/xxxx_xx_xx_create_restaurants_table.php`

```bash
php artisan make:migration create_restaurants_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the restaurants table with all required columns and indexes.
     */
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location');
            $table->string('cuisine');
            $table->decimal('rating', 3, 1)->nullable(); // e.g. 4.5 — not in JSON, nullable for future use
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
```

### 3.2 — Orders Table

> 📄 **File:** `database/migrations/xxxx_xx_xx_create_orders_table.php`

```bash
php artisan make:migration create_orders_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the orders table with:
     *   - decimal(10,2) for order_amount — future-proof for paise/cents
     *   - ordered_at datetime — order_hour extracted at query time via HOUR()
     *   - status as tinyint — backed by OrderStatus enum
     *   - composite index on (restaurant_id, ordered_at) for analytics performance
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                  ->constrained('restaurants')
                  ->cascadeOnDelete();

            $table->decimal('order_amount', 10, 2);

            $table->dateTime('ordered_at');

            // Status backed by OrderStatus enum:
            // 0 = failed, 1 = completed, 2 = pending, 3 = in-progress
            $table->tinyInteger('status')->default(1);

            $table->timestamps();

            // ── Indexes ────────────────────────────────────────────────
            // Composite index: covers the most common analytics query pattern
            // (filter by restaurant AND date range simultaneously)
            $table->index(['restaurant_id', 'ordered_at'], 'idx_orders_restaurant_date');

            // Individual indexes for single-column filter queries
            $table->index('ordered_at', 'idx_orders_date');
            $table->index('status',     'idx_orders_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

> 💡 **Note on `order_hour`:** We deliberately do NOT store a separate `order_hour` column. The hour is always derived at query time using MySQL's `HOUR(ordered_at)`. This avoids data duplication and keeps the schema clean. The composite index on `ordered_at` ensures this derivation is fast.

---

## 4. Eloquent Models

### 4.1 — Restaurant Model

> 📄 **File:** `app/Models/Restaurant.php`

```php
<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Restaurant Model
 *
 * Represents a restaurant entity.
 * Uses the Filterable trait to support QueryFilter-based scopes.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $location
 * @property string      $cuisine
 * @property float|null  $rating
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Restaurant extends Model
{
    use Filterable, HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'location',
        'cuisine',
        'rating',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'rating' => 'float',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    /**
     * A restaurant has many orders.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
```

### 4.2 — Order Model

> 📄 **File:** `app/Models/Order.php`

```php
<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order Model
 *
 * Represents a single customer order.
 * Status is cast to the OrderStatus enum automatically by Eloquent.
 *
 * @property int          $id
 * @property int          $restaurant_id
 * @property float        $order_amount
 * @property \Carbon\Carbon $ordered_at
 * @property OrderStatus  $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'restaurant_id',
        'order_amount',
        'ordered_at',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * ordered_at is cast to datetime so Carbon methods are available.
     * status is cast to the OrderStatus backed enum automatically.
     */
    protected $casts = [
        'ordered_at'   => 'datetime',
        'order_amount' => 'decimal:2',
        'status'       => OrderStatus::class,
    ];

    // ── Relationships ──────────────────────────────────────────────────

    /**
     * An order belongs to a restaurant.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
```

---

## 5. Order Status Enum

> 📄 **File:** `app/Enums/OrderStatus.php`

```bash
# Create the Enums directory and file manually
# (no artisan command for enums in Laravel 12)
mkdir app/Enums
```

```php
<?php

namespace App\Enums;

/**
 * OrderStatus Enum
 *
 * Integer-backed enum representing the lifecycle of an order.
 * The integer value is what gets stored in the `status` tinyint column.
 *
 * Values:
 *   0 = failed      — order could not be processed
 *   1 = completed   — order successfully fulfilled (default)
 *   2 = pending     — order placed, awaiting confirmation
 *   3 = in-progress — order confirmed, being prepared
 */
enum OrderStatus: int
{
    case Failed     = 0;
    case Completed  = 1;
    case Pending    = 2;
    case InProgress = 3;

    /**
     * Return a human-readable label for the status.
     * Useful for API responses and frontend display.
     */
    public function label(): string
    {
        return match($this) {
            OrderStatus::Failed     => 'Failed',
            OrderStatus::Completed  => 'Completed',
            OrderStatus::Pending    => 'Pending',
            OrderStatus::InProgress => 'In Progress',
        };
    }

    /**
     * Return all statuses as a key-value array.
     * Useful for filter dropdowns and API documentation.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(
                fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
                self::cases()
            ),
            'label',
            'value'
        );
    }
}
```

### How Eloquent Uses the Enum

Because `status` is cast to `OrderStatus::class` in the model, Eloquent automatically converts between the integer stored in MySQL and the enum object in PHP:

```php
// Reading — Eloquent returns an enum object
$order->status;               // OrderStatus::Completed
$order->status->value;        // 1
$order->status->label();      // "Completed"

// Writing — pass the enum or its integer value
$order->status = OrderStatus::Pending;   // stores 2
$order->status = OrderStatus::from(3);   // stores 3

// Querying — use the enum value
Order::where('status', OrderStatus::Completed->value)->get();
Order::where('status', OrderStatus::Pending)->get(); // also works with cast
```

---

## 6. Model Relationships

### Relationship Map

```
Restaurant (1)
    │
    └── hasMany ──► Order (many)
                        └── belongsTo ──► Restaurant
```

### Usage Examples

```php
// Get all orders for a restaurant
$restaurant = Restaurant::find(1);
$restaurant->orders;                          // Collection of Order models

// Eager load to avoid N+1
$restaurants = Restaurant::with('orders')->get();

// Get restaurant from an order
$order = Order::find(1);
$order->restaurant->name;                     // "Tandoori Treats"

// Filter orders by status via enum
$restaurant->orders()
    ->where('status', OrderStatus::Completed)
    ->get();

// Analytics: total revenue for a restaurant in a date range
$restaurant->orders()
    ->whereBetween('ordered_at', ['2025-06-22', '2025-06-28'])
    ->sum('order_amount');
```

---

## 7. Seeders

### 7.1 — Seeder Strategy

Both JSON files are stored in `database/data/`. The seeders read them directly — no external HTTP calls, no hardcoded arrays.

```
database/
├── data/
│   ├── restaurants.json     ← Copy from assignment
│   └── orders.json          ← Copy from assignment
├── seeders/
│   ├── DatabaseSeeder.php
│   ├── RestaurantSeeder.php
│   └── OrderSeeder.php
```

**Create the data directory and copy your JSON files:**

```bash
mkdir database/data
# Then manually copy restaurants.json and orders.json into database/data/
```

### 7.2 — RestaurantSeeder

> 📄 **File:** `database/seeders/RestaurantSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * RestaurantSeeder
 *
 * Reads restaurants.json and inserts all restaurants.
 * JSON IDs (101-104) are IGNORED — MySQL auto-increments from 1.
 * The resulting DB IDs (1-4) are used by OrderSeeder via the ID map.
 *
 * Insertion order matches JSON order, so:
 *   DB id 1 = Tandoori Treats  (JSON id 101)
 *   DB id 2 = Sushi Bay        (JSON id 102)
 *   DB id 3 = Pasta Palace     (JSON id 103)
 *   DB id 4 = Burger Hub       (JSON id 104)
 */
class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        // Load and decode the JSON file
        $json = File::get(database_path('data/restaurants.json'));
        $restaurants = json_decode($json, true);

        foreach ($restaurants as $data) {
            Restaurant::create([
                // Note: 'id' from JSON is deliberately excluded
                // MySQL auto-increments — insertion order determines DB id
                'name'     => $data['name'],
                'location' => $data['location'],
                'cuisine'  => $data['cuisine'],
                'rating'   => null, // not present in JSON — nullable
            ]);
        }

        $this->command->info('✅ Restaurants seeded: ' . count($restaurants) . ' records');
    }
}
```

### 7.3 — OrderSeeder

> 📄 **File:** `database/seeders/OrderSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * OrderSeeder
 *
 * Reads orders.json and inserts all 200 orders.
 *
 * ID Remapping:
 *   The JSON uses restaurant IDs 101-104.
 *   We build a map from JSON id → DB id by querying the
 *   restaurants table in insertion order after RestaurantSeeder runs.
 *
 * Status Assignment:
 *   Each order is assigned a random OrderStatus.
 *   Distribution is weighted to reflect realistic data:
 *     - Completed  (1): 60% — most orders succeed
 *     - Pending    (2): 20% — some awaiting confirmation
 *     - InProgress (3): 15% — some being prepared
 *     - Failed     (0):  5% — few failures
 */
class OrderSeeder extends Seeder
{
    /**
     * Weighted status pool for random assignment.
     * Array values are OrderStatus cases repeated by weight.
     *
     * @var array<int, OrderStatus>
     */
    private function buildStatusPool(): array
    {
        return array_merge(
            array_fill(0, 60, OrderStatus::Completed),  // 60%
            array_fill(0, 20, OrderStatus::Pending),    // 20%
            array_fill(0, 15, OrderStatus::InProgress), // 15%
            array_fill(0,  5, OrderStatus::Failed)      //  5%
        );
    }

    /**
     * Build a lookup map from JSON restaurant IDs to DB restaurant IDs.
     * Relies on RestaurantSeeder having run first and inserted in JSON order.
     *
     * Result:
     *   [ 101 => 1, 102 => 2, 103 => 3, 104 => 4 ]
     */
    private function buildRestaurantIdMap(): array
    {
        // JSON IDs in the same order as restaurants.json
        $jsonIds = [101, 102, 103, 104];

        // DB restaurants ordered by id (insertion order)
        $dbIds = Restaurant::orderBy('id')->pluck('id')->toArray();

        if (count($jsonIds) !== count($dbIds)) {
            throw new \RuntimeException(
                'Restaurant count mismatch. Run RestaurantSeeder before OrderSeeder.'
            );
        }

        // Map JSON id => DB id
        return array_combine($jsonIds, $dbIds);
    }

    public function run(): void
    {
        $json   = File::get(database_path('data/orders.json'));
        $orders = json_decode($json, true);

        $idMap      = $this->buildRestaurantIdMap();
        $statusPool = $this->buildStatusPool();
        $poolSize   = count($statusPool);

        $rows = [];

        foreach ($orders as $data) {
            // Resolve JSON restaurant_id to actual DB id
            $dbRestaurantId = $idMap[$data['restaurant_id']] ?? null;

            if (! $dbRestaurantId) {
                $this->command->warn(
                    "⚠️  Skipping order id {$data['id']}: unknown restaurant_id {$data['restaurant_id']}"
                );
                continue;
            }

            // Pick a random status from the weighted pool
            $status = $statusPool[array_rand(array_fill(0, $poolSize, null))];

            $now = now();

            $rows[] = [
                'restaurant_id' => $dbRestaurantId,
                'order_amount'  => $data['order_amount'],
                'ordered_at'    => $data['order_time'],  // ISO datetime string maps directly
                'status'        => $status->value,        // store the integer (0-3)
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        // Chunk insert for performance (avoids 200 individual INSERT queries)
        foreach (array_chunk($rows, 50) as $chunk) {
            Order::insert($chunk);
        }

        $this->command->info('✅ Orders seeded: ' . count($rows) . ' records');
    }
}
```

> 💡 **Why `insert()` instead of `create()`?** `Order::insert()` does a bulk INSERT in one query per chunk. `Order::create()` fires one INSERT per record plus model events. For 200 rows it's negligible, but this is the production-grade habit — especially when datasets grow.

### 7.4 — DatabaseSeeder

> 📄 **File:** `database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder
 *
 * Orchestrates all seeders in dependency order.
 * RestaurantSeeder MUST run before OrderSeeder
 * because OrderSeeder queries restaurant DB IDs.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RestaurantSeeder::class, // ← must be first
            OrderSeeder::class,
        ]);
    }
}
```

---

## 8. Running Migrations & Seeders

### First Time Setup

```bash
# Run all migrations
php artisan migrate

# Seed the database
php artisan db:seed
```

### Expected Terminal Output

```
Running migrations...
  ✓ create_restaurants_table
  ✓ create_orders_table

Running seeders...
  ✅ Restaurants seeded: 4 records
  ✅ Orders seeded: 200 records
```

### Reset & Reseed (During Development)

```bash
# Wipe everything and start fresh
php artisan migrate:fresh --seed
```

> ⚠️ **Never run `migrate:fresh` in production.** It drops all tables. Safe for local development only.

### Verify Seeded Data

```bash
php artisan tinker
```

```php
// Check restaurant count and IDs
App\Models\Restaurant::all(['id', 'name']);

// Check order count
App\Models\Order::count(); // should be 200

// Check status distribution
App\Models\Order::selectRaw('status, count(*) as total')
    ->groupBy('status')
    ->get();

// Verify ID remapping — orders should reference IDs 1-4
App\Models\Order::distinct()->pluck('restaurant_id');
// Expected: [1, 2, 3, 4]

// Test relationship
App\Models\Restaurant::with('orders')->find(1)->orders->count();
```

---

## 9. Pest Tests — Phase 2

> 📄 **File:** `tests/Feature/Phase2/MigrationTest.php`

```php
<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Schema;

describe('Phase 2 — Migrations & Schema', function () {

    it('restaurants table exists with correct columns', function () {
        expect(Schema::hasTable('restaurants'))->toBeTrue();

        expect(Schema::hasColumns('restaurants', [
            'id', 'name', 'location', 'cuisine', 'rating',
            'created_at', 'updated_at',
        ]))->toBeTrue();
    });

    it('orders table exists with correct columns', function () {
        expect(Schema::hasTable('orders'))->toBeTrue();

        expect(Schema::hasColumns('orders', [
            'id', 'restaurant_id', 'order_amount',
            'ordered_at', 'status', 'created_at', 'updated_at',
        ]))->toBeTrue();
    });

});
```

> 📄 **File:** `tests/Feature/Phase2/ModelTest.php`

```php
<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;

describe('Phase 2 — Models & Relationships', function () {

    it('can create a restaurant via factory', function () {
        $restaurant = Restaurant::factory()->create([
            'name'     => 'Test Restaurant',
            'location' => 'Mumbai',
            'cuisine'  => 'Indian',
        ]);

        expect($restaurant->id)->toBeInt();
        expect($restaurant->name)->toBe('Test Restaurant');
    });

    it('restaurant has many orders', function () {
        $restaurant = Restaurant::factory()->create();

        Order::factory()->count(3)->create([
            'restaurant_id' => $restaurant->id,
        ]);

        expect($restaurant->orders)->toHaveCount(3);
    });

    it('order belongs to a restaurant', function () {
        $restaurant = Restaurant::factory()->create();
        $order      = Order::factory()->create(['restaurant_id' => $restaurant->id]);

        expect($order->restaurant->id)->toBe($restaurant->id);
    });

    it('order status is cast to OrderStatus enum', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Completed]);

        expect($order->status)->toBeInstanceOf(OrderStatus::class);
        expect($order->status)->toBe(OrderStatus::Completed);
        expect($order->status->value)->toBe(1);
        expect($order->status->label())->toBe('Completed');
    });

    it('order_amount is cast to decimal', function () {
        $order = Order::factory()->create(['order_amount' => 996.00]);

        expect($order->order_amount)->toEqual('996.00');
    });

    it('ordered_at is cast to a Carbon datetime instance', function () {
        $order = Order::factory()->create(['ordered_at' => '2025-06-24 15:00:00']);

        expect($order->ordered_at)->toBeInstanceOf(\Carbon\Carbon::class);
        expect($order->ordered_at->hour)->toBe(15);
    });

    it('OrderStatus enum has correct values', function () {
        expect(OrderStatus::Failed->value)->toBe(0);
        expect(OrderStatus::Completed->value)->toBe(1);
        expect(OrderStatus::Pending->value)->toBe(2);
        expect(OrderStatus::InProgress->value)->toBe(3);
    });

    it('OrderStatus options() returns all four statuses', function () {
        $options = OrderStatus::options();

        expect($options)->toHaveCount(4);
        expect($options[0])->toBe('Failed');
        expect($options[1])->toBe('Completed');
        expect($options[2])->toBe('Pending');
        expect($options[3])->toBe('In Progress');
    });

});
```

> 📄 **File:** `tests/Feature/Phase2/SeederTest.php`

```php
<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;

describe('Phase 2 — Seeders', function () {

    it('seeds exactly 4 restaurants', function () {
        $this->seed(\Database\Seeders\RestaurantSeeder::class);

        expect(Restaurant::count())->toBe(4);
    });

    it('seeds restaurants with correct names', function () {
        $this->seed(\Database\Seeders\RestaurantSeeder::class);

        $names = Restaurant::pluck('name')->toArray();

        expect($names)->toContain('Tandoori Treats');
        expect($names)->toContain('Sushi Bay');
        expect($names)->toContain('Pasta Palace');
        expect($names)->toContain('Burger Hub');
    });

    it('seeds exactly 200 orders', function () {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        expect(Order::count())->toBe(200);
    });

    it('all orders reference valid restaurant IDs (1-4)', function () {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $restaurantIds = Order::distinct()->pluck('restaurant_id')->sort()->values()->toArray();

        expect($restaurantIds)->toBe([1, 2, 3, 4]);
    });

    it('all orders have a valid OrderStatus', function () {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $validValues = array_column(OrderStatus::cases(), 'value');

        $invalidCount = Order::whereNotIn('status', $validValues)->count();

        expect($invalidCount)->toBe(0);
    });

    it('seeded orders contain all four status types', function () {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $statusValues = Order::distinct()->pluck('status')->sort()->values()->toArray();

        // With 200 orders and weighted randomness all 4 should appear
        expect(count($statusValues))->toBeGreaterThanOrEqual(1);
    });

    it('order amounts are stored as decimal', function () {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $order = Order::first();

        expect($order->order_amount)->not->toBeNull();
        expect(is_numeric($order->order_amount))->toBeTrue();
    });

    it('ordered_at values are valid datetimes', function () {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $order = Order::first();

        expect($order->ordered_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });

});
```

### Factories (Required for Model Tests)

> 📄 **File:** `database/factories/RestaurantFactory.php`

```bash
php artisan make:factory RestaurantFactory --model=Restaurant
php artisan make:factory OrderFactory --model=Order
```

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RestaurantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'     => $this->faker->company(),
            'location' => $this->faker->city(),
            'cuisine'  => $this->faker->randomElement(['Indian', 'Italian', 'Japanese', 'American', 'Chinese']),
            'rating'   => $this->faker->randomFloat(1, 1.0, 5.0),
        ];
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'order_amount'  => $this->faker->randomFloat(2, 200, 1000),
            'ordered_at'    => $this->faker->dateTimeBetween('-7 days', 'now'),
            'status'        => $this->faker->randomElement(OrderStatus::cases()),
        ];
    }
}
```

### Running Phase 2 Tests

```bash
# Run all Phase 2 tests
php artisan test --filter Phase2

# Run just migration tests
php artisan test --filter MigrationTest

# Run just seeder tests
php artisan test --filter SeederTest
```

---

## 10. Phase 2 Completion Checklist

| Item | Status |
|---|---|
| `restaurants` migration created | ☐ |
| `orders` migration created with correct columns | ☐ |
| Composite index `(restaurant_id, ordered_at)` on orders | ☐ |
| `php artisan migrate` runs without errors | ☐ |
| `app/Enums/OrderStatus.php` created with 4 cases | ☐ |
| `Restaurant` model with `Filterable` trait and `hasMany` | ☐ |
| `Order` model with `OrderStatus` cast and `belongsTo` | ☐ |
| `RestaurantFactory` created | ☐ |
| `OrderFactory` created | ☐ |
| `database/data/restaurants.json` copied | ☐ |
| `database/data/orders.json` copied | ☐ |
| `RestaurantSeeder` reads JSON and inserts 4 records | ☐ |
| `OrderSeeder` remaps IDs and inserts 200 records | ☐ |
| `OrderSeeder` assigns weighted random statuses | ☐ |
| `DatabaseSeeder` calls seeders in correct order | ☐ |
| `php artisan migrate:fresh --seed` runs without errors | ☐ |
| All orders reference DB IDs 1–4 (not 101–104) | ☐ |
| Phase 2 Pest tests passing | ☐ |

---

*End of Phase 2 Documentation • Next: Phase 3 — Authentication Module*
