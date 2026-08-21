<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubOrder extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'total',
        'escrow_amount',
        'commission_rate_snapshot',
        'platform_commission',
        'seller_net_amount',
        'shipping_method',
        'shipping_label',
        'shipping_cost',
        'shipping_approved',
        'shipping_approved_at',
        'estimated_delivery',
        'coupon_id',
        'discount_amount',
        'status',
        'shipment_state',
        'escrow_release_at',
        'escrow_released_at',
        'delivery_confirmed_at',
        'delivery_confirmation_type',
        'auto_release_days',
    ];

    protected $casts = [
        'total'           => 'decimal:2',
        'shipping_cost'   => 'decimal:2',
        'shipping_approved' => 'boolean',
        'shipping_approved_at' => 'datetime',
        'discount_amount' => 'decimal:2',
        'escrow_amount' => 'decimal:2',
        'commission_rate_snapshot' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'seller_net_amount' => 'decimal:2',
        'escrow_release_at' => 'datetime',
        'escrow_released_at' => 'datetime',
        'delivery_confirmed_at' => 'datetime',
        'auto_release_days' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
