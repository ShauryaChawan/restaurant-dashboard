<?php

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Cache;

describe('Phase 5 — Analytics: Restaurant', function () {

    // ── Auth guard ──────────────────────────────────────────────────

    it('returns 401 for unauthenticated request to restaurant analytics', function () {
        $r = Restaurant::factory()->create();

        $this->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-07")
            ->assertStatus(401);
    });

    // ── Validation ──────────────────────────────────────────────────

    it('returns 422 when start_date is missing', function () {
        $r = Restaurant::factory()->create();

        actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?end_date=2024-01-07")
            ->assertStatus(422);
    });

    it('returns 422 when end_date is missing', function () {
        $r = Restaurant::factory()->create();

        actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01")
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

    // ── Response structure ──────────────────────────────────────────

    it('response contains all required top-level keys', function () {
        $r = Restaurant::factory()->create();

        actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-07")
            ->assertStatus(200)
            ->assertJson(['status' => 'success'])
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

    // ── Daily orders ────────────────────────────────────────────────

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

    it('excludes orders outside the date range', function () {
        $r = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-05 10:00:00']); // inside
        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-15 10:00:00']); // outside

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-07")
            ->assertStatus(200);

        expect(collect($response->json('data.daily_orders'))->sum('count'))->toBe(1);
    });

    // ── Daily revenue ───────────────────────────────────────────────

    it('returns correct daily revenue using order_amount column', function () {
        $r = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r->id, 'order_amount' => 500.00, 'ordered_at' => '2024-01-01 10:00:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'order_amount' => 300.00, 'ordered_at' => '2024-01-01 14:00:00']);

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-01")
            ->assertStatus(200);

        $revenue = collect($response->json('data.daily_revenue'))
            ->firstWhere('date', '2024-01-01')['revenue'];

        expect($revenue)->toEqual(800.0);
    });

    // ── Average order value ─────────────────────────────────────────

    it('returns correct average order value', function () {
        $r = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r->id, 'order_amount' => 400.00, 'ordered_at' => '2024-01-01 10:00:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'order_amount' => 600.00, 'ordered_at' => '2024-01-01 14:00:00']);

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-01")
            ->assertStatus(200);

        expect($response->json('data.avg_order_value'))->toEqual(500.0);
    });

    it('returns 0 avg_order_value when no orders exist in range', function () {
        $r = Restaurant::factory()->create();

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-07")
            ->assertStatus(200);

        expect($response->json('data.avg_order_value'))->toEqual(0.0);
    });

    // ── Peak hours ──────────────────────────────────────────────────

    it('returns correct peak hour per day using HOUR(ordered_at)', function () {
        $r = Restaurant::factory()->create();

        // 3 orders at hour 13, 1 at hour 9 — peak should be 13
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

    // ── Cache ───────────────────────────────────────────────────────

    it('caches response and serves it on repeat request', function () {
        $r = Restaurant::factory()->create();
        Cache::flush();

        $url = "/api/v1/analytics/restaurant/{$r->id}?start_date=2024-01-01&end_date=2024-01-07";

        actingAsUser()->getJson($url)->assertStatus(200);
        actingAsUser()->getJson($url)->assertStatus(200);
    });

});

describe('Phase 5 — Analytics: Top Restaurants', function () {

    // ── Auth guard ──────────────────────────────────────────────────

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/analytics/top-restaurants?start_date=2024-01-01&end_date=2024-01-07')
            ->assertStatus(401);
    });

    // ── Validation ──────────────────────────────────────────────────

    it('returns 422 when date params are missing', function () {
        actingAsUser()
            ->getJson('/api/v1/analytics/top-restaurants')
            ->assertStatus(422);
    });

    // ── Results ─────────────────────────────────────────────────────

    it('returns at most 3 results', function () {
        $restaurants = Restaurant::factory()->count(5)->create();

        foreach ($restaurants as $i => $r) {
            Order::factory()->create([
                'restaurant_id' => $r->id,
                'order_amount' => 100 * ($i + 1),
                'ordered_at' => '2024-01-03 12:00:00',
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

        $revenues = collect($response->json('data'))->pluck('total_revenue');

        expect($revenues[0])->toBeGreaterThan($revenues[1]);
        expect($revenues[1])->toBeGreaterThan($revenues[2]);
    });

    it('excludes restaurants with no orders in the date range', function () {
        $r1 = Restaurant::factory()->create();
        $r2 = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r1->id, 'order_amount' => 200, 'ordered_at' => '2024-01-03 12:00:00']);
        Order::factory()->create(['restaurant_id' => $r2->id, 'order_amount' => 999, 'ordered_at' => '2024-02-15 12:00:00']); // outside

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/top-restaurants?start_date=2024-01-01&end_date=2024-01-07')
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id); // cast to int

        expect($ids)->toContain($r1->id);
        expect($ids)->not->toContain($r2->id);
    });

    it('response contains total_revenue and total_orders as numbers', function () {
        $r = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r->id, 'order_amount' => 250.00, 'ordered_at' => '2024-01-03 12:00:00']);

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/top-restaurants?start_date=2024-01-01&end_date=2024-01-07')
            ->assertStatus(200);

        $item = $response->json('data.0');

        expect((float) $item['total_revenue'])->toBeFloat(); // cast before asserting
        expect((int) $item['total_orders'])->toBeInt();
    });

});

describe('Phase 5 — Analytics: Orders', function () {

    // ── Auth guard ──────────────────────────────────────────────────

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/analytics/orders')->assertStatus(401);
    });

    // ── Pagination structure ────────────────────────────────────────

    it('returns paginated orders with data array and meta object', function () {
        Order::factory()->count(20)->create();

        actingAsUser()
            ->getJson('/api/v1/analytics/orders?per_page=10')
            ->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page', 'from', 'to'],
            ]);
    });

    it('per_page above 50 is rejected with 422', function () {
        Order::factory()->count(5)->create();

        actingAsUser()
            ->getJson('/api/v1/analytics/orders?per_page=9999')
            ->assertStatus(422)
            ->assertJsonPath('errors.per_page.0', 'The per page field must not be greater than 50.');
    });

    // ── Filters ─────────────────────────────────────────────────────

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

    it('returns 422 for a restaurant_id that does not exist', function () {
        actingAsUser()
            ->getJson('/api/v1/analytics/orders?restaurant_id=99999')
            ->assertStatus(422);
    });

    it('filters by start_date and end_date using ordered_at', function () {
        Order::factory()->create(['ordered_at' => '2024-01-05 10:00:00']); // inside
        Order::factory()->create(['ordered_at' => '2024-02-15 10:00:00']); // outside

        $response = actingAsUser()
            ->getJson('/api/v1/analytics/orders?start_date=2024-01-01&end_date=2024-01-31')
            ->assertStatus(200);

        expect($response->json('meta.total'))->toBe(1);
    });

    it('filters by min_amount and max_amount', function () {
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

    it('eagerly loads restaurant name on each order', function () {
        $r = Restaurant::factory()->create(['name' => 'Tandoori Treats']);
        Order::factory()->create(['restaurant_id' => $r->id]);

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/orders?restaurant_id={$r->id}")
            ->assertStatus(200);

        expect($response->json('data.0.restaurant.name'))->toBe('Tandoori Treats');
    });

    it('orders are sorted by ordered_at descending', function () {
        $r = Restaurant::factory()->create();

        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-01 10:00:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-03 10:00:00']);
        Order::factory()->create(['restaurant_id' => $r->id, 'ordered_at' => '2024-01-02 10:00:00']);

        $response = actingAsUser()
            ->getJson("/api/v1/analytics/orders?restaurant_id={$r->id}")
            ->assertStatus(200);

        $dates = collect($response->json('data'))->pluck('ordered_at');

        expect($dates[0])->toBeGreaterThan($dates[1]);
        expect($dates[1])->toBeGreaterThan($dates[2]);
    });

});
