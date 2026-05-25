<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    protected $fillable = [
        'name', 'email', 'password', 'role', 'zone_id',
        'phone_number', 'is_active', 'device_token',
        'last_login_at', 'last_login_ip'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // ─── Relationships ──────────────────────────────────

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'agent_id');
    }

    public function location(): HasOne
    {
        return $this->hasOne(AgentLocation::class, 'agent_id');
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(PersonalAccessToken::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }

    // ─── API Token Helpers ──────────────────────────────

    /**
     * Create a new API token for the user
     */
    public function createApiToken(string $name = 'mobile-app', array $abilities = ['*']): string
    {
        $plainToken = Str::random(64);

        $this->apiTokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainToken),
            'abilities' => json_encode($abilities),
        ]);

        return $plainToken;
    }

    /**
     * Revoke all API tokens for this user
     */
    public function revokeAllTokens(): void
    {
        $this->apiTokens()->delete();
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopeAgents($query)
    {
        return $query->whereIn('role', ['field_agent', 'agent']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ─── Helpers ────────────────────────────────────────

    public function isAgent(): bool
    {
        return in_array($this->role, ['field_agent', 'agent']);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'finance_officer', 'supervisor']);
    }
}
