<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'seller_id',
        'code',
        'title',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'usage_limit_per_user',
        'starts_at',
        'expires_at',
        'apply_to_all_products',
        'product_ids',
        'is_active'
    ];

    protected $casts = [
        'product_ids' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isValid($userId = null, $orderTotal = null, $productIds = [])
    {
        if (!$this->is_active) {
            return ['valid' => false, 'message' => 'Coupon is not active.'];
        }

        if ($this->starts_at && $this->starts_at > now()) {
            return ['valid' => false, 'message' => 'Coupon is not active yet.'];
        }

        if ($this->expires_at && $this->expires_at < now()) {
            return ['valid' => false, 'message' => 'Coupon has expired.'];
        }

        if ($this->min_order_amount && $orderTotal < $this->min_order_amount) {
            return ['valid' => false, 'message' => "Minimum order amount is {$this->min_order_amount} SAR."];
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return ['valid' => false, 'message' => 'Coupon has reached its usage limit.'];
        }

        if ($this->usage_limit_per_user === 'once' && $userId) {
            $usedBefore = CouponUsage::where('coupon_id', $this->id)
                ->where('user_id', $userId)
                ->exists();

            if ($usedBefore) {
                return ['valid' => false, 'message' => 'You have already used this coupon.'];
            }
        }

        if (!$this->apply_to_all_products && !empty($this->product_ids) && !empty($productIds)) {
            $validProducts = array_intersect($productIds, $this->product_ids);
            if (empty($validProducts)) {
                return ['valid' => false, 'message' => 'Coupon does not apply to products in your cart.'];
            }
        }

        return ['valid' => true];
    }

    public function calculateDiscount($orderTotal)
    {
        switch ($this->type) {
            case 'percentage':
                return round(($this->value / 100) * $orderTotal, 2);
            case 'fixed':
                return min($this->value, $orderTotal);
            case 'free_shipping':
                return 0;
            default:
                return 0;
        }
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeForSeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }
}