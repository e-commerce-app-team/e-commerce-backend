<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    // الحقول المسموح بإدخالها وتعديلها تلقائياً
    protected $fillable = [
        'user_id',
        'invoice_number',
        'subtotal',
        'vat_amount',
        'total',
        'pdf_path',
    ];

    /**
     * علاقة الفاتورة بالمستخدم (تاجر الجملة)
     * كل فاتورة تنتمي إلى مستخدم واحد من نوع wholesale
     */
    /*    public function user()
       {
           return $this->belongsTo(User::class);
       } */

    // جلب كافة الفواتير الخاصة بالتاجر (Wholesale)
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'user_id');
    }
}