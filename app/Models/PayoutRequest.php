<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutRequest extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'amount',
        'payout_method',
        'payout_account',
        'status',
        'admin_notes'
    ];

    // علاقة الطلب بصاحبه (البائع)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
