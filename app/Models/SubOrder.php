<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubOrder extends Model
{
    protected $fillable = [
        'order_id',    // ربط بالطلب الرئيسي
        'seller_id',   // التاجر الخاص بهذا الطلب الفرعي
        'total'        // المبلغ المطلوب دفعه لهذا التاجر
    ];

    // علاقة للعودة للطلب الرئيسي
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function orderItems()
{
    return $this->hasMany(OrderItem::class);
}
}
