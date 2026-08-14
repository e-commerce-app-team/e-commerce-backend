<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'password',
        'profile_photo',
        'role', // super_admin, users_admin, orders_admin, products_admin
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    // ============================================================
    // 🔥 العلاقات (للإحصائيات فقط)
    // ============================================================

    /**
     * جلب جميع الإعلانات (للإحصائيات)
     */
    public function allAds()
    {
        return $this->hasMany(Ad::class, 'admin_id');
    }

    /**
     * جلب الإعلانات المعلقة (قيد المراجعة)
     */
    public function pendingAds()
    {
        return $this->allAds()->where('status', 'pending');
    }

    /**
     * جلب الإعلانات النشطة
     */
    public function activeAds()
    {
        return $this->allAds()->where('status', 'active');
    }

    /**
     * جلب الإعلانات المرفوضة
     */
    public function rejectedAds()
    {
        return $this->allAds()->where('status', 'rejected');
    }

    /**
     * جلب الإعلانات المنتهية
     */
    public function expiredAds()
    {
        return $this->allAds()->where('status', 'expired');
    }

    // ============================================================
    // 📌 دوال مساعدة للصلاحيات
    // ============================================================

    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function isUsersAdmin()
    {
        return $this->role === 'users_admin';
    }

    public function isOrdersAdmin()
    {
        return $this->role === 'orders_admin';
    }

    public function isProductsAdmin()
    {
        return $this->role === 'products_admin';
    }

    public function canManageAds()
    {
        return in_array($this->role, ['super_admin', 'users_admin']);
    }
}