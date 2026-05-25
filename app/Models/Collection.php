<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Collection extends Model
{
    protected $fillable = [
        'business_id', 'agent_id', 'amount', 'payment_method', 
        'receipt_number', 'gps_lat', 'gps_lng', 'offline_sync_id', 'collected_at'
    ];

    protected $casts = [
        'collected_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->receipt_number)) {
                $model->receipt_number = 'REC-' . strtoupper(Str::random(8)) . '-' . date('Ymd');
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function reconciliation()
    {
        return $this->hasOne(Reconciliation::class);
    }
}
