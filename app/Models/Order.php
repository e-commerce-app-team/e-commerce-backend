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
        'estimated_delivery_date', // مضاف 🌟
        'shipped_at',              // مضاف 🌟
        'delivered_at',            // مضاف 🌟
        'status_timeline'
    ];

    protected $casts = [
        'status_timeline' => 'array',
        'total_price' => 'decimal:2',
        'shipped_at' => 'datetime',            // مضاف لعمل كاست تلقائي 🌟
        'delivered_at' => 'datetime',          // مضاف لعمل كاست تلقائي 🌟
        'estimated_delivery_date' => 'datetime' // مضاف لعمل كاست تلقائي 🌟
    ];

    // العلاقة مع المشتري
    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // العلاقة مع البائع
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
        return $this->belongsToMany(Product::class, 'order_product')
            ->withPivot('quantity', 'price')
            ->withTimestamps();
    }
}