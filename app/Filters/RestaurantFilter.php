<?php

namespace App\Filters;

/**
 * RestaurantFilter
 *
 * Handles all query filtering for the Restaurant listing endpoint.
 * Each public method name maps directly to a query parameter key.
 * Methods are called automatically by the QueryFilter base class.
 *
 * Supported params:
 *   search    — partial match on name, cuisine, location
 *   cuisine   — exact match on cuisine
 *   location  — exact match on location
 *   rating    — minimum rating (e.g. rating=4 returns 4.0 and above)
 *   sort_by   — column to sort by (whitelisted)
 *   sort_dir  — asc|desc (defaults to asc)
 */
class RestaurantFilter extends QueryFilter
{
    /**
     * Global search across name, cuisine, and location.
     * Case-insensitive partial match.
     */
    public function search(string $value): void
    {
        $this->builder->where(function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%")
                ->orWhere('cuisine', 'like', "%{$value}%")
                ->orWhere('location', 'like', "%{$value}%");
        });
    }

    /**
     * Filter by exact cuisine type.
     * Example: ?cuisine=Japanese
     */
    public function cuisine(string $value): void
    {
        $this->builder->where('cuisine', $value);
    }

    /**
     * Filter by exact location.
     * Example: ?location=Mumbai
     */
    public function location(string $value): void
    {
        $this->builder->where('location', $value);
    }

    /**
     * Filter by minimum rating.
     * Example: ?rating=4 returns restaurants with rating >= 4.0
     */
    public function rating(string $value): void
    {
        $this->builder->where('rating', '>=', (float) $value);
    }

    /**
     * Sort by a whitelisted column.
     * Example: ?sort_by=name&sort_dir=desc
     *
     * sort_dir defaults to 'asc' if not provided or invalid.
     */
    public function sort_by(string $value): void
    {
        $allowed = ['name', 'cuisine', 'location', 'rating', 'created_at'];
        $dir = $this->request->query('sort_dir', 'asc');
        $dir = in_array(strtolower($dir), ['asc', 'desc']) ? strtolower($dir) : 'asc';

        if (in_array($value, $allowed)) {
            $this->builder->orderBy($value, $dir);
        }
    }
}
