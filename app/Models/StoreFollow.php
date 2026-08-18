<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreFollow extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',
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
