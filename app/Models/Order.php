<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order Model
 *
 * Represents a single customer order.
 * Status is cast to the OrderStatus enum automatically by Eloquent.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property float $order_amount
 * @property \Carbon\Carbon $ordered_at
 * @property OrderStatus $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'restaurant_id',
        'order_amount',
        'ordered_at',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * ordered_at is cast to datetime so Carbon methods are available.
     * status is cast to the OrderStatus backed enum automatically.
     */
    protected $casts = [
        'ordered_at' => 'datetime',
        'order_amount' => 'decimal:2',
        'status' => OrderStatus::class,
    ];

    // ── Relationships ──────────────────────────────────────────────────

    /**
     * An order belongs to a restaurant.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
