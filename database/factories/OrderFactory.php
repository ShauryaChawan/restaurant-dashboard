<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'order_amount' => $this->faker->randomFloat(2, 200, 1000),
            'ordered_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'status' => $this->faker->randomElement(OrderStatus::cases()),
        ];
    }
}
