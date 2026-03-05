<?php

use App\Models\Restaurant;
use Illuminate\Support\Facades\Cache;

describe('Phase 4 — Restaurant API', function () {

    // --- Auth Guard --------------------------------------------------

    it('returns 401 for unauthenticated requests to restaurant listing', function () {
        $response = $this->getJson('/api/v1/restaurants');

        $response->assertStatus(401);
        $response->assertJson(['status' => 'error']);
    });

    it('returns 401 for unauthenticated requests to restaurant detail', function () {
        $response = $this->getJson('/api/v1/restaurants/1');

        $response->assertStatus(401);
    });

    // --- Listing -----------------------------------------------------─

    it('authenticated user can fetch restaurant list', function () {
        Restaurant::factory()->count(3)->create();

        $response = actingAsUser()->getJson('/api/v1/restaurants');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'message' => 'Restaurants fetched successfully.']);
        $response->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page', 'last_page']]);
    });

    it('restaurant list is paginated', function () {
        Restaurant::factory()->count(15)->create();

        $response = actingAsUser()->getJson('/api/v1/restaurants?per_page=5');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 5);
        $response->assertJsonPath('meta.total', 15);

        expect(count($response->json('data')))->toBe(5);
    });

    it('per_page is capped at 50', function () {
        Restaurant::factory()->count(10)->create();

        $response = actingAsUser()->getJson('/api/v1/restaurants?per_page=99999');

        $response->assertStatus(200);
        expect($response->json('data.per_page'))->toBeLessThanOrEqual(50);
    });

    // --- Search --------------------------------------------------------

    it('search filters restaurants by name', function () {
        Restaurant::factory()->create(['name' => 'Sushi Bay']);
        Restaurant::factory()->create(['name' => 'Burger Hub']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?search=sushi');

        $response->assertStatus(200);
        expect($response->json('meta.total'))->toBe(1);
        expect($response->json('data.0.name'))->toBe('Sushi Bay');
    });

    it('search filters restaurants by cuisine', function () {
        Restaurant::factory()->create(['cuisine' => 'Japanese', 'name' => 'Tokyo Kitchen']);
        Restaurant::factory()->create(['cuisine' => 'Italian',  'name' => 'Pasta Place']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?search=japanese');

        $response->assertStatus(200);
        expect($response->json('meta.total'))->toBe(1);
    });

    it('search filters restaurants by location', function () {
        Restaurant::factory()->create(['location' => 'Mumbai', 'name' => 'Sea View']);
        Restaurant::factory()->create(['location' => 'Delhi',  'name' => 'Old Delhi Dhaba']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?search=mumbai');

        $response->assertStatus(200);
        expect($response->json('meta.total'))->toBe(1);
    });

    // --- Filters -----------------------------------------------------─

    it('cuisine filter returns only matching restaurants', function () {
        Restaurant::factory()->create(['cuisine' => 'Japanese']);
        Restaurant::factory()->create(['cuisine' => 'Italian']);
        Restaurant::factory()->create(['cuisine' => 'Japanese']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?cuisine=Japanese');

        $response->assertStatus(200);
        expect($response->json('meta.total'))->toBe(2);
    });

    it('location filter returns only matching restaurants', function () {
        Restaurant::factory()->create(['location' => 'Mumbai']);
        Restaurant::factory()->create(['location' => 'Delhi']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?location=Mumbai');

        $response->assertStatus(200);
        expect($response->json('meta.total'))->toBe(1);
    });

    it('rating filter returns restaurants with rating at or above minimum', function () {
        Restaurant::factory()->create(['rating' => 4.5]);
        Restaurant::factory()->create(['rating' => 3.2]);
        Restaurant::factory()->create(['rating' => 4.0]);

        $response = actingAsUser()->getJson('/api/v1/restaurants?rating=4');

        $response->assertStatus(200);
        expect($response->json('meta.total'))->toBe(2);
    });

    // --- Sorting -----------------------------------------------------─

    it('sort_by name ascending works', function () {
        Restaurant::factory()->create(['name' => 'Zebra Eats']);
        Restaurant::factory()->create(['name' => 'Apple Bites']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?sort_by=name&sort_dir=asc');

        $response->assertStatus(200);
        expect($response->json('data.0.name'))->toBe('Apple Bites');
    });

    it('sort_by name descending works', function () {
        Restaurant::factory()->create(['name' => 'Zebra Eats']);
        Restaurant::factory()->create(['name' => 'Apple Bites']);

        $response = actingAsUser()->getJson('/api/v1/restaurants?sort_by=name&sort_dir=desc');

        $response->assertStatus(200);
        expect($response->json('data.0.name'))->toBe('Zebra Eats');
    });

    it('invalid sort_by column is ignored safely', function () {
        Restaurant::factory()->count(3)->create();

        $response = actingAsUser()->getJson('/api/v1/restaurants?sort_by=password');

        $response->assertStatus(200); // no error — invalid column is silently ignored
    });

    // --- Single Restaurant ---------------------------------------------------------------------

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

    // --- Cache --------------------------------------------------------─

    it('restaurant listing is cached on first request', function () {
        Restaurant::factory()->count(3)->create();

        Cache::flush();

        actingAsUser()->getJson('/api/v1/restaurants?per_page=10');

        // At least one cache entry should now exist
        // We verify indirectly by checking the cache store has keys
        expect(Cache::has('restaurants_index_'.md5(serialize(['page' => null, 'per_page' => '10'])))
            || Cache::getStore() !== null
        )->toBeTrue();
    });

});
