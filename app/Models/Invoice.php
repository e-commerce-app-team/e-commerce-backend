<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'invoice_number',
        'type',              // 'order' (wholesale فقط) | 'commission' (للجميع)
        'subtotal',
        'vat_amount',
        'commission_amount',
        'total',
        'seller_name',
        'seller_tax_number',
        'seller_cr',
        'line_items',        // JSON: تفصيل ضريبة كل منتج
        'status',            // 'issued' | 'cancelled' | 'refunded'
        'notes',
        'pdf_path',
    ];

    protected $casts = [
        'line_items'         => 'array',
        'subtotal'           => 'decimal:2',
        'vat_amount'         => 'decimal:2',
        'commission_amount'  => 'decimal:2',
        'total'              => 'decimal:2',
    ];

    // ============================================================
    // 📌 العلاقات
    // ============================================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // ============================================================
    // 📌 Helpers
    // ============================================================

    public function isOrderInvoice(): bool
    {
        return $this->type === 'order';
    }

    public function isCommissionInvoice(): bool
    {
        return $this->type === 'commission';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}