<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuyerAddress extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'details',
        'latitude',
        'longitude',
        'driver_notes',
        'is_default',
    ];

    protected $casts = [
        'latitude'    => 'decimal:8',
        'longitude'   => 'decimal:8',
        'is_default'  => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
