<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $restaurants = Restaurant::all();

        if ($restaurants->isEmpty()) {
            $this->command->warn('No restaurants found. Please run RestaurantSeeder first.');
            return;
        }

        // Generate 500 orders and assign them randomly to the existing 50 restaurants
        Order::factory()
            ->count(500)
            ->recycle($restaurants)
            ->create();
    }
}
