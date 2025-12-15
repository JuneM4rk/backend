<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Generate a new verification token for an email.
     */
    public static function generateFor(string $email): self
    {
        // Delete any existing tokens for this email
        self::where('email', $email)->delete();

        // Create new token
        return self::create([
            'email' => $email,
            'token' => bin2hex(random_bytes(32)),
            'created_at' => now(),
        ]);
    }

    /**
     * Check if the token is expired (24 hours).
     */
    public function isExpired(): bool
    {
        return $this->created_at->addHours(24)->isPast();
    }
}

