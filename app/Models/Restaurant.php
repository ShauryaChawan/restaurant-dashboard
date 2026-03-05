<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Restaurant Model
 *
 * Represents a restaurant entity.
 * Uses the Filterable trait to support QueryFilter-based scopes.
 *
 * @property int $id
 * @property string $name
 * @property string $location
 * @property string $cuisine
 * @property float|null $rating
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Restaurant extends Model
{
    use Filterable, HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'location',
        'cuisine',
        'rating',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'rating' => 'float',
    ];

    // --- Relationships ----------------------------------------------

    /**
     * A restaurant has many orders.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
