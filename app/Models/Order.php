<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'seller_id',
        'total_price',
        'status',
        'payment_method',
        'payment_status',
        'shipping_address_title',
        'shipping_address_details',
        'customer_notes',
        'status_timeline'
    ];

    protected $casts = [
        'status_timeline' => 'array', // يحول الـ JSON تلقائياً لمصفوفة PHP والعكس
        'total_price' => 'decimal:2'
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

    public function products()
    {
        // هنا نربط الطلب بالمنتجات عبر جدول وسيط (مثلاً order_product)
        // مع جلب حقول الكمية والسعر من الجدول الوسيط باستخدام withPivot
        return $this->belongsToMany(Product::class, 'order_product')
            ->withPivot('quantity', 'price')
            ->withTimestamps();
    }
}

