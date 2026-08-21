<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'sub_order_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price',
        'total_price',
        // Legacy order rows used `price`; keep it assignable for compatibility.
        'price',
    ];

    // علاقة عكسية: كل item ينتمي لـ subOrder معين
    public function subOrder()
    {
        return $this->belongsTo(SubOrder::class);
    }

    // علاقة: كل item مرتبط بـ product معين
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
