<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'rating',
        'comment',
    ];

    // علاقة التقييم بالمتجر
    public function store()
    {
        return $this->belongsTo(User::class, 'store_id');
    }

    // علاقة التقييم بالمشتري
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
