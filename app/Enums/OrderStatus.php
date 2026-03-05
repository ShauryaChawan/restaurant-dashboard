<?php

namespace App\Enums;

/**
 * OrderStatus Enum
 *
 * Integer-backed enum representing the lifecycle of an order.
 * The integer value is what gets stored in the `status` tinyint column.
 *
 * Values:
 *   0 = failed      — order could not be processed
 *   1 = completed   — order successfully fulfilled (default)
 *   2 = pending     — order placed, awaiting confirmation
 *   3 = in-progress — order confirmed, being prepared
 */
enum OrderStatus: int
{
    case Failed = 0;
    case Completed = 1;
    case Pending = 2;
    case InProgress = 3;

    /**
     * Return a human-readable label for the status.
     * Useful for API responses and frontend display.
     */
    public function label(): string
    {
        return match ($this) {
            OrderStatus::Failed => 'Failed',
            OrderStatus::Completed => 'Completed',
            OrderStatus::Pending => 'Pending',
            OrderStatus::InProgress => 'In Progress',
        };
    }

    /**
     * Return all statuses as a key-value array.
     * Useful for filter dropdowns and API documentation.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(
                fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
                self::cases()
            ),
            'label',
            'value'
        );
    }
}
