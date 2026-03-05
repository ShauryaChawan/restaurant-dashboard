<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'location' => $this->faker->city(),
            'cuisine' => $this->faker->randomElement(['Indian', 'Italian', 'Japanese', 'American', 'Chinese']),
            'rating' => $this->faker->randomFloat(1, 1.0, 5.0),
        ];
    }
}
