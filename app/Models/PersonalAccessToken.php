<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Find the token instance matching the given token.
     * Override to find by plain text token directly.
     */
    public static function findToken($token)
    {
        // Find by exact plain text token match
        return static::where('token', $token)->first();
    }
}

