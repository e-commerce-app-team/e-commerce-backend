<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'discount_amount',
        'order_total_before_discount',
        'order_total_after_discount'
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'order_total_before_discount' => 'decimal:2',
        'order_total_after_discount' => 'decimal:2',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}