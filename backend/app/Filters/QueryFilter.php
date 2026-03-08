<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * QueryFilter
 *
 * Abstract base class for all Eloquent query filters.
 * Each filter class extends this and defines individual filter methods.
 * Method names map directly to request query param names.
 *
 * Usage: $filter->apply($builder) — called automatically by Filterable trait.
 */
abstract class QueryFilter
{
    protected Builder $builder;

    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Attach the filter to a given Eloquent builder.
     * Called automatically by the Filterable trait.
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        // Loop through each query param and call matching method if it exists
        foreach ($this->filters() as $name => $value) {
            if (method_exists($this, $name) && filled($value)) {
                $this->$name($value);
            }
        }

        return $this->builder;
    }

    /**
     * Return all active filters from the request.
     * Only includes params that are present and non-empty.
     */
    protected function filters(): array
    {
        return array_filter($this->request->all(), fn ($value) => filled($value));
    }
}
