<?php

namespace App\Traits;

use App\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filterable Trait
 *
 * Add this trait to any Eloquent model to enable the filter() scope.
 * Usage: Restaurant::query()->filter($restaurantFilter)->paginate(15);
 */
trait Filterable
{
    /**
     * Scope: apply a QueryFilter to the current builder.
     */
    public function scopeFilter(Builder $builder, QueryFilter $filter): Builder
    {
        return $filter->apply($builder);
    }
}
