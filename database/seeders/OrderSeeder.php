<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * OrderSeeder
 *
 * Reads orders.json and inserts all 200 orders.
 *
 * ID Remapping:
 *   The JSON uses restaurant IDs 101-104.
 *   We build a map from JSON id → DB id by querying the
 *   restaurants table in insertion order after RestaurantSeeder runs.
 *
 * Status Assignment:
 *   Each order is assigned a random OrderStatus.
 *   Distribution is weighted to reflect realistic data:
 *     - Completed  (1): 60% — most orders succeed
 *     - Pending    (2): 20% — some awaiting confirmation
 *     - InProgress (3): 15% — some being prepared
 *     - Failed     (0):  5% — few failures
 */
class OrderSeeder extends Seeder
{
    /**
     * Weighted status pool for random assignment.
     * Array values are OrderStatus cases repeated by weight.
     *
     * @var array<int, OrderStatus>
     */
    private function buildStatusPool(): array
    {
        return array_merge(
            array_fill(0, 60, OrderStatus::Completed),  // 60%
            array_fill(0, 20, OrderStatus::Pending),    // 20%
            array_fill(0, 15, OrderStatus::InProgress), // 15%
            array_fill(0, 5, OrderStatus::Failed)      //  5%
        );
    }

    /**
     * Build a lookup map from JSON restaurant IDs to DB restaurant IDs.
     * Relies on RestaurantSeeder having run first and inserted in JSON order.
     *
     * Result:
     *   [ 101 => 1, 102 => 2, 103 => 3, 104 => 4 ]
     */
    private function buildRestaurantIdMap(): array
    {
        // JSON IDs in the same order as restaurants.json
        $jsonIds = [101, 102, 103, 104];

        // DB restaurants ordered by id (insertion order)
        $dbIds = Restaurant::orderBy('id')->pluck('id')->toArray();

        if (count($jsonIds) !== count($dbIds)) {
            throw new \RuntimeException(
                'Restaurant count mismatch. Run RestaurantSeeder before OrderSeeder.'
            );
        }

        // Map JSON id => DB id
        return array_combine($jsonIds, $dbIds);
    }

    public function run(): void
    {
        $json = File::get(database_path('data/orders.json'));
        $orders = json_decode($json, true);

        $idMap = $this->buildRestaurantIdMap();
        $statusPool = $this->buildStatusPool();
        $poolSize = count($statusPool);

        $rows = [];

        foreach ($orders as $data) {
            // Resolve JSON restaurant_id to actual DB id
            $dbRestaurantId = $idMap[$data['restaurant_id']] ?? null;

            if (! $dbRestaurantId) {
                $this->command->warn(
                    "⚠️  Skipping order id {$data['id']}: unknown restaurant_id {$data['restaurant_id']}"
                );

                continue;
            }

            // Pick a random status from the weighted pool
            $status = $statusPool[array_rand(array_fill(0, $poolSize, null))];

            $now = now();

            $rows[] = [
                'restaurant_id' => $dbRestaurantId,
                'order_amount' => $data['order_amount'],
                'ordered_at' => $data['order_time'],  // ISO datetime string maps directly
                'status' => $status->value,        // store the integer (0-3)
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Chunk insert for performance (avoids 200 individual INSERT queries)
        foreach (array_chunk($rows, 50) as $chunk) {
            Order::insert($chunk);
        }

        // $this->command->info('✅ Orders seeded: '.count($rows).' records');
    }
}
