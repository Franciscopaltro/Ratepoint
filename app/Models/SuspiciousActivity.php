<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuspiciousActivity extends Model
{
    protected $fillable = ['type', 'related_id', 'description', 'severity', 'status'];

    public static function log($type, $related_id, $description, $severity = 'medium')
    {
        return self::create([
            'type' => $type,
            'related_id' => $related_id,
            'description' => $description,
            'severity' => $severity,
            'status' => 'open'
        ]);
    }
}
