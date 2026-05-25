<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $fillable = ['name', 'boundary_coords'];
    protected $casts = ['boundary_coords' => 'json'];

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }

    public function agents()
    {
        return $this->hasMany(User::class);
    }
}
