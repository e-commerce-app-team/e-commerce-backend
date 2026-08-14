<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'address',
        'lat',
        'lng',
        'phone',
        'manager_name',
        'working_hours',
        'is_active',
        'product_count'
    ];

    protected $casts = [
        'working_hours' => 'array',
        'is_active' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
