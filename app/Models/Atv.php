<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Atv extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'serial_number',
        'daily_price',
        'status',
        'image',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'daily_price' => 'decimal:2',
        ];
    }

    /**
     * Get the rentals for the ATV.
     */
    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Check if ATV is available for rental.
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Check if ATV is currently rented.
     */
    public function isRented(): bool
    {
        return $this->status === 'rented';
    }

    /**
     * Check if ATV is under maintenance.
     */
    public function isUnderMaintenance(): bool
    {
        return $this->status === 'maintenance';
    }

    /**
     * Check if ATV has active rentals (not returned or denied).
     */
    public function hasActiveRentals(): bool
    {
        return $this->rentals()
            ->whereNotIn('status', ['returned', 'denied'])
            ->exists();
    }

    /**
     * Get the image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    /**
     * Scope to filter available ATVs.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope to search ATVs by name or type.
     */
    public function scopeSearch($query, ?string $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }
        return $query;
    }
}

