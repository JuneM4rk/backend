<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PENDING_PICKUP = 'pending_pickup';
    const STATUS_RENTED = 'rented';
    const STATUS_PENDING_RETURN = 'pending_return';
    const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'user_id',
        'atv_id',
        'status',
        'start_time',
        'end_time',
        'total_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'total_price' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the rental.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the ATV for the rental.
     */
    public function atv()
    {
        return $this->belongsTo(Atv::class);
    }

    /**
     * Calculate total price based on duration and ATV daily rate.
     */
    public function calculateTotalPrice(): float
    {
        $days = $this->start_time->diffInDays($this->end_time);
        // Minimum 1 day
        $days = max(1, $days);
        return $days * $this->atv->daily_price;
    }

    /**
     * Check if rental is active (not returned or denied).
     */
    public function isActive(): bool
    {
        return !in_array($this->status, [self::STATUS_RETURNED, self::STATUS_DENIED, self::STATUS_CANCELLED]);
    }

    /**
     * Get rental duration in days (inclusive).
     * For example: Dec 13 to Dec 14 = 2 days (Dec 13 AND Dec 14).
     */
    public function getDurationDaysAttribute(): int
    {
        // Use diffInDays and add 1 for inclusive counting (Dec 13 to Dec 14 = 2 days)
        $days = $this->start_time->copy()->startOfDay()->diffInDays($this->end_time->copy()->startOfDay()) + 1;
        return max(1, $days);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter active rentals.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_RETURNED, self::STATUS_DENIED, self::STATUS_CANCELLED]);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get all valid statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_DENIED,
            self::STATUS_CANCELLED,
            self::STATUS_PENDING_PICKUP,
            self::STATUS_RENTED,
            self::STATUS_PENDING_RETURN,
            self::STATUS_RETURNED,
        ];
    }

    /**
     * Get status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_DENIED => 'Denied',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_PENDING_PICKUP => 'Pending Pickup',
            self::STATUS_RENTED => 'Currently Rented',
            self::STATUS_PENDING_RETURN => 'Pending Return',
            self::STATUS_RETURNED => 'Returned',
            default => ucfirst($this->status),
        };
    }
}