<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Create a new personal access token for the user.
     * Override to store plain text token and return same token (no ID prefix).
     */
    public function createToken(string $name, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null)
    {
        $plainTextToken = Str::random(40);

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => $plainTextToken, // Store plain text instead of hash
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        // Return same token as stored in DB (no ID prefix)
        return new NewAccessToken($token, $plainTextToken);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'role',
        'is_verified',
        'avatar',
        'address',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the rentals for the user.
     */
    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is manager.
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Check if user is customer.
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }
}
