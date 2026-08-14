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
    public function Items()
{
    return $this->hasMany(OrderItem::class);
}
public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id'); // أو Store::class حسب مشروعك
    }
}
