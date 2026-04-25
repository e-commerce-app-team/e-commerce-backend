<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vendor_id',
        'total_price',
        'status',
        'payment_method'
    ];

    // المشتري المرتبط بهذا الطلب
    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // البائع المرتبط بهذا الطلب
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}

