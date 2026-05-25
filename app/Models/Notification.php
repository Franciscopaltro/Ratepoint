<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'sender_id', 'recipient_id', 'recipient_role',
        'title', 'message', 'type', 'priority', 'data', 'read_at'
    ];

    protected $casts = [
        'data' => 'json',
        'read_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Check if notification has been read
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Scope: unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: for a specific user (direct or role-based broadcast)
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('recipient_id', $user->id)
              ->orWhere('recipient_role', $user->role)
              ->orWhereNull('recipient_id'); // broadcast to all
        });
    }

    /**
     * Send a notification to a specific user
     */
    public static function sendTo(int $recipientId, string $title, string $message, string $type = 'info', ?int $senderId = null, array $data = []): self
    {
        return static::create([
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'data' => !empty($data) ? $data : null,
        ]);
    }

    /**
     * Broadcast a notification to all users with a given role
     */
    public static function broadcastToRole(string $role, string $title, string $message, string $type = 'info', ?int $senderId = null): self
    {
        return static::create([
            'sender_id' => $senderId,
            'recipient_role' => $role,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);
    }
}
