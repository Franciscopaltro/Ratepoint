<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalAccessToken extends Model
{
    protected $fillable = [
        'user_id', 'name', 'token', 'abilities', 'last_used_at', 'expires_at'
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Find a token record by its plain text value
     */
    public static function findByToken(string $plainToken): ?self
    {
        $hashed = hash('sha256', $plainToken);
        return static::where('token', $hashed)->first();
    }

    /**
     * Check if token has expired
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    /**
     * Check if token has a specific ability
     */
    public function hasAbility(string $ability): bool
    {
        $abilities = json_decode($this->abilities, true) ?? [];
        return in_array('*', $abilities) || in_array($ability, $abilities);
    }

    /**
     * Mark this token as recently used
     */
    public function markAsUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
