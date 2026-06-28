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
        'store_logo',
        'store_name',
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
            'balance' => 'decimal:2'
        ];
    }

    // الـ Helper Methods للتحقق من الصلاحيات
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
    // علاقة جلب الأقسام الداخلية التي أنشأها هذا التاجر لمتجره
    public function departments()
    {
        return $this->hasMany(Department::class, 'seller_id');
    }

    // ربط مجال عمل التجر بالتصنيف العام للسيستم
    public function globalCategory()
    {
        return $this->belongsTo(Category::class, 'category');
    }

    // العلاقات (Relationships)
    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class);
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    // الطلبات التي يقوم هذا المستخدم ببيعها (إذا كان بائعاً)
    public function sales()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    // الطلبات التي قام هذا المستخدم بشرائها (إذا كان مشترياً)
    public function purchases()
    {
        return $this->hasMany(Order::class, 'user_id');
    }


    // المنتجات التي يمتلكها البائع (تمت إضافتها)
    public function products()
    {
        return $this->hasMany(Product::class, 'user_id');
    }
}
