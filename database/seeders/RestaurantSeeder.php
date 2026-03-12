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
                'name' => $data['name'],
                'location' => $data['location'],
                'cuisine' => $data['cuisine'],
                'rating' => mt_rand(0, 50) / 10,
            ]);
        }

        // $this->command->info('✅ Restaurants seeded: '.count($restaurants).' records');
    }
}
