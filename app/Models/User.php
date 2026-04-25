<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'profile_photo',
        'id_card_photo',
        'commercial_record_photo',
        'company_name',
        'commercial_registration_number',
        'category',
        'min_order_quantity',
        'warehouse_address',
        'status',
        'role',
        'payout_method',
        'payout_account',
        'wallet_pin'
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wallet_pin' => 'hashed'
        ];
    }


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

    // داخل كلاس User
    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class);
    }

    // لجلب كل الحركات المالية الخاصة بهذا المستخدم
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function vendorOrders()
    {
        return $this->hasMany(Order::class, 'vendor_id');
    }

    // 2. علاقة المشتري مع الطلبات التي قام بشرائها
    public function buyerOrders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }
}
