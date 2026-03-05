<?php

namespace App\Services;

use App\Filters\RestaurantFilter;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RestaurantService
{
    /**
     * Cache TTL in seconds for paginated restaurant listings.
     * 10 minutes — restaurant data rarely changes.
     */
    private const CACHE_TTL = 600;

    /**
     * Maximum allowed per_page value.
     * Prevents clients from dumping the entire table in one request.
     */
    private const MAX_PER_PAGE = 50;

    /**
     * Global cache toggle driven by CACHE_ENABLED in .env.
     * Set CACHE_ENABLED=false to disable caching in local/test environments.
     */
    private function isCacheEnabled(): bool
    {
        return (bool) config('app.cache_enabled', true);
    }

    /**
     * Build a deterministic cache key from the request query parameters.
     *
     * Keys are sorted before hashing so that:
     *   ?search=sushi&cuisine=Japanese
     *   ?cuisine=Japanese&search=sushi
     * ...produce the same cache key.
     */
    private function buildCacheKey(Request $request): string
    {
        $params = $request->query();
        ksort($params);

        return 'restaurants_index_'.md5(serialize($params));
    }

    /**
     * Return a paginated, filtered, sorted, and cached restaurant list.
     *
     * @param  Request  $request  The incoming HTTP request.
     */
    public function getPaginated(Request $request)
    {
        $perPage = min(
            (int) $request->query('per_page', 10),
            self::MAX_PER_PAGE
        );

        if ($this->isCacheEnabled()) {
            return Cache::remember(
                $this->buildCacheKey($request),
                self::CACHE_TTL,
                fn () => $this->queryPaginated($request, $perPage)
            );
        }

        return $this->queryPaginated($request, $perPage);
    }

    /**
     * Execute the actual Eloquent query with filters applied.
     * Called by getPaginated() — either directly or via cache callback.
     */
    private function queryPaginated(Request $request, int $perPage): LengthAwarePaginator
    {
        $filter = new RestaurantFilter($request);

        return Restaurant::query()
            ->filter($filter)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($restaurant) => new RestaurantResource($restaurant));
    }

    /**
     * Find a single restaurant by primary key.
     * Throws ModelNotFoundException (→ 404) if not found.
     * The global exception handler in bootstrap/app.php catches this.
     *
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findById(int $id): RestaurantResource
    {
        return new RestaurantResource(Restaurant::findOrFail($id));
    }
}
