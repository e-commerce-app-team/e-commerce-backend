<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    // الحقول المسموح بتعبئتها
    protected $fillable = [
        'user_id',
        'counterparty_user_id',
        'order_id',
        'type',
        'direction',
        'status',
        'reference',
        'account_type',
        'amount',
        'description',
    ];

    // علاقة السجل بالمستخدم
    // كل عملية تنتمي لمستخدم واحد
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function counterparty()
    {
        return $this->belongsTo(User::class, 'counterparty_user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
