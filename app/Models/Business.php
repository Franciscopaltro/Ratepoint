<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = [
        'name', 'owner_name', 'gps_lat', 'gps_lng', 'zone_id', 
        'structure_type', 'levy_type', 'fee_amount', 'status'
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function collections()
    {
        return $this->hasMany(Collection::class);
    }
}
