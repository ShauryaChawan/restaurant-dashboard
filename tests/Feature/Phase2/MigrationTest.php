<?php

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
