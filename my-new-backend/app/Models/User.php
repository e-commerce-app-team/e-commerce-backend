<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'profile_photo',
        'id_card_photo',
        'commercial_record_photo',
        'store_name',
        'store_description',
        'store_logo',
        'store_cover_photo',
        'working_hours',
        'return_policy',
        'store_email',
        'social_links',
        'latitude',
        'longitude',
        'detailed_address',
        'commercial_registration_number',
        'tax_number',
        'category',
        'status',
        'role',
        'balance',
        'payout_method',
        'payout_account',
        'wallet_pin'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'wallet_pin',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wallet_pin' => 'hashed',
            'balance' => 'decimal:2',
            'working_hours' => 'array',
            'social_links' => 'array',
        ];
    }

    // ============================================================
    // 📌 Helper Methods
    // ============================================================
    public function isVendor()
    {
        return $this->role === 'vendor';
    }

    public function isWholesale()
    {
        return $this->role === 'wholesale';
    }

    public function isBuyer()
    {
        return $this->role === 'buyer';
    }

    public function isSeller()
    {
        return in_array($this->role, ['vendor', 'wholesale']);
    }

    // ============================================================
    // 📌 العلاقات الموجودة
    // ============================================================
    public function departments()
    {
        return $this->hasMany(Department::class, 'seller_id');
    }

    public function globalCategory()
    {
        return $this->belongsTo(Category::class, 'category');
    }

    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function sales()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function purchases()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'user_id');
    }

    // ============================================================
    // 🔥 علاقات الكوبونات
    // ============================================================

    public function coupons()
    {
        return $this->hasMany(Coupon::class, 'seller_id');
    }

    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class, 'user_id');
    }

    public function usedCoupons()
    {
        return $this->belongsToMany(Coupon::class, 'coupon_usages', 'user_id', 'coupon_id')
            ->withPivot('order_id', 'discount_amount', 'order_total_before_discount', 'order_total_after_discount')
            ->withTimestamps();
    }

    // ============================================================
    // 🔥 علاقات الإعلانات (المضافة حديثاً)
    // ============================================================

    /**
     * الإعلانات التي أنشأها هذا التاجر
     */
    public function ads()
    {
        return $this->hasMany(Ad::class, 'seller_id');
    }

    /**
     * مشاهدات الإعلانات لهذا المستخدم
     */
    public function adViews()
    {
        return $this->hasMany(AdView::class);
    }

    // ============================================================
    // 📌 دوال مساعدة للكوبونات
    // ============================================================

    public function hasUsedCoupon($couponId)
    {
        return $this->couponUsages()
            ->where('coupon_id', $couponId)
            ->exists();
    }

    public function getTotalDiscountFromCoupons()
    {
        return $this->couponUsages()->sum('discount_amount');
    }

    public function getCouponUsageCount()
    {
        return $this->couponUsages()->count();
    }

    // ============================================================
    // 📌 دوال مساعدة للإعلانات
    // ============================================================

    /**
     * جلب إجمالي المبلغ المنفق على الإعلانات
     */
    public function getTotalAdSpent()
    {
        return $this->ads()->sum('price');
    }

    /**
     * جلب عدد الإعلانات النشطة
     */
    public function getActiveAdsCount()
    {
        return $this->ads()->active()->count();
    }

    /**
     * جلب عدد الإعلانات قيد المراجعة
     */
    public function getPendingAdsCount()
    {
        return $this->ads()->pending()->count();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}