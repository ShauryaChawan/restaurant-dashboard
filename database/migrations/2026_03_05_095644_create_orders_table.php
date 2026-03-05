<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
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
            $table->tinyInteger('status')->default(2);
            $table->timestamps();

            // --- Indexes --------------------------------------------------------------------------------
            // Composite index: covers the most common analytics query pattern
            // (filter by restaurant AND date range simultaneously)
            $table->index(['restaurant_id', 'ordered_at'], 'idx_orders_restaurant_date');

            // Individual indexes for single-column filter queries
            $table->index('ordered_at', 'idx_orders_date');
            $table->index('status', 'idx_orders_status');
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
