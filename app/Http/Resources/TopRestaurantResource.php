<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * TopRestaurantResource
 *
 * Extends RestaurantResource with two computed fields produced by
 * the top-restaurants analytics query:
 *   - total_revenue: SUM(orders.order_amount) — cast to float
 *   - total_orders:  COUNT(orders.id)         — cast to int
 *
 * MySQL returns aggregate values as strings in PHP, so explicit
 * casts here ensure the JSON always contains proper numbers.
 */
class TopRestaurantResource extends RestaurantResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'total_revenue' => round((float) $this->total_revenue, 2),
            'total_orders' => (int) $this->total_orders,
        ]);
    }
}
