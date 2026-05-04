<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'seller_id', // تأكد من تغييرها هنا أيضاً
        'total_price',
        'status',
        'payment_method'
    ];

    // العلاقة مع المشتري
    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // العلاقة مع البائع (نستخدم seller_id)
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}

