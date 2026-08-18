<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
    ];

    public function store()
    {
        return $this->belongsTo(User::class, 'store_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
