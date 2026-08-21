<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletDepositRequest extends Model
{
    use HasFactory;

    protected $table = 'wallet_deposit_requests';

    protected $fillable = [
        'user_id', 'amount', 'payment_method', 'reference', 'status',
        'admin_note', 'reviewed_at', 'reviewed_by_admin_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }
}
