<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentLocation extends Model
{
    protected $fillable = [
        'agent_id', 'latitude', 'longitude', 'accuracy',
        'battery_level', 'is_online', 'last_seen_at'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy' => 'decimal:2',
        'is_online' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Update or create the agent's live location
     */
    public static function updatePosition(int $agentId, float $lat, float $lng, ?float $accuracy = null, ?int $battery = null): self
    {
        return static::updateOrCreate(
            ['agent_id' => $agentId],
            [
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy' => $accuracy,
                'battery_level' => $battery,
                'is_online' => true,
                'last_seen_at' => now(),
            ]
        );
    }
}
