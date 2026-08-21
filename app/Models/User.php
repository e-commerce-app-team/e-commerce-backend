<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;
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
        'permissions',
        'seller_id',
        'balance',
        'locked_balance',
        'wallet_qr_token',
        'payout_method',
        'payout_account',
        'wallet_pin',
        'wallet_qr_token',
        // ─── OTP & 2FA ────────────────────────────────────────────────
        'otp_code',
        'otp_expires_at',
        'email_verified_at',
        'phone_verified_at',
        'two_factor_enabled',
        'two_factor_method',
        'fcm_token',
        'shipping_settings',
        'pickup_enabled',
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
            'phone_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'wallet_pin' => 'hashed',
            'balance' => 'decimal:2',
            'locked_balance' => 'decimal:2',
            'working_hours' => 'array',
            'social_links' => 'array',
            'permissions' => 'array',
            'two_factor_enabled' => 'boolean',
            'shipping_settings' => 'array',
            'pickup_enabled' => 'boolean',
        ];
    }

    public function buyerAddresses()
    {
        return $this->hasMany(BuyerAddress::class);
    }

    // ─── OTP & 2FA Helpers ──────────────────────────────────────────────────
    public function isOtpValid(string $code): bool
    {
        return $this->otp_code === $code
            && $this->otp_expires_at
            && $this->otp_expires_at->isFuture();
    }

    public function clearOtp(): void
    {
        $this->update(['otp_code' => null, 'otp_expires_at' => null]);
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

    public function walletDepositRequests()
    {
        return $this->hasMany(WalletDepositRequest::class);
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

    public function storeReviews()
    {
        return $this->hasMany(StoreReview::class, 'store_id');
    }

    /**
     * المتابعون لهذا المتجر (المستخدمون الذين يتابعون هذا البائع)
     */
    public function storeFollowers()
    {
        // Existing database snapshots use store_id for the followed seller.
        return $this->hasMany(StoreFollow::class, 'store_id', 'id');
    }
    public function followedStores()
    {
        return $this->belongsToMany(User::class, 'store_follows', 'user_id', 'store_id')
            ->withTimestamps();
    }

    /**
     * الطلبات التي قام بها المستخدم كمشتري
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    /**
     * الطلبات التي قام بها المستخدم كبائع
     */
    public function sellerOrders()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }
    // ============================================================
    // 🔥 علاقات الموظفين (Staff Management)
    // ============================================================

    public function staffMembers()
    {
        return $this->hasMany(User::class, 'seller_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
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

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    // المتاجر التي يتابعها المشتري
    public function followingStores()
    {
        return $table = $this->belongsToMany(User::class, 'store_follows', 'user_id', 'seller_id')
            ->withPivot('followed_at');
    }

    public function notifications()
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function notificationPreferences()
    {
        return $this->hasMany(NotificationPreference::class);
    }


}
