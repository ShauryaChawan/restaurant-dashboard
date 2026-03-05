<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;

describe('Phase 2 — Models & Relationships', function () {

    it('can create a restaurant via factory', function () {
        $restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
            'location' => 'Mumbai',
            'cuisine' => 'Indian',
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
        $order = Order::factory()->create(['restaurant_id' => $restaurant->id]);

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
