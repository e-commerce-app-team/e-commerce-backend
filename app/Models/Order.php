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
        'estimated_delivery_date',
        'shipped_at',
        'delivered_at',
        'status_timeline',
        // 🔥 إضافة حقل الكوبون
        'coupon_id',
        'discount_amount',
        'address_id',
        'shipping_lat',
        'shipping_lng',
        // 🧾 حقول النظام الضريبي والعمولة
        'subtotal_before_tax',
        'tax_amount',
        'tax_breakdown',
        'platform_commission',
        'commission_rate_snapshot',
    ];

    protected $casts = [
        'status_timeline' => 'array',
        'tax_breakdown'   => 'array',
        'total_price'     => 'decimal:2',
        'subtotal_before_tax' => 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'commission_rate_snapshot' => 'decimal:2',
        'shipped_at'     => 'datetime',
        'delivered_at'   => 'datetime',
        'estimated_delivery_date' => 'datetime',
        'discount_amount' => 'decimal:2',
    ];

    // ============================================================
    // 📌 العلاقات
    // ============================================================

    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

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

    // ============================================================
    // 🔥 علاقات الكوبونات (المضافة حديثاً)
    // ============================================================

    /**
     * الكوبون المستخدم في هذا الطلب
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * سجل استخدام الكوبون لهذا الطلب
     */
    public function couponUsage()
    {
        return $this->hasOne(CouponUsage::class, 'order_id');
    }

    // ============================================================
    // 📌 دوال مساعدة
    // ============================================================

    /**
     * التحقق مما إذا كان الطلب يستخدم كوبون
     */
    public function hasCoupon()
    {
        return !is_null($this->coupon_id);
    }

    /**
     * حساب السعر النهائي بعد الخصم
     */
    public function getFinalPrice()
    {
        return $this->total_price - ($this->discount_amount ?? 0);
    }

    /**
     * جلب قيمة الخصم المطبقة
     */
    public function getDiscountAmount()
    {
        return $this->discount_amount ?? 0;
    }

    public function subOrders()
{
    return $this->hasMany(SubOrder::class);
}
}