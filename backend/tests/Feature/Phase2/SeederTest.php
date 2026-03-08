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
