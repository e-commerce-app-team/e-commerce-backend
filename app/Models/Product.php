<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'description',
        'images',
        'video_url',
        'original_price',
        'wholesale_price', // تم الإضافة
        'offer_price',
        'offer_expires_at',
        'sku',
        'quantity',
        'min_wholesale_qty', // تم الإضافة
        'warehouse_stock',   // تم الإضافة
        'alert_threshold',
        'weight',
        'length',
        'width',
        'height',
        'status',
        'is_free_shipping',  // تم الإضافة
        'sales_count',
        'department_id',
        // 🔥 حقول الضريبة (المضافة حديثاً)
        'tax_exempt',
        'tax_exempt_reason',
    ];

    // تحويل الحقل إلى مصفوفة تلقائياً
    protected $casts = [
        'images'          => 'array',
        'offer_expires_at'=> 'datetime',
        'sales_count'     => 'integer',
        'warehouse_stock' => 'array',  // سيقوم لارافيل بتحويل مصفوفة المخازن إلى JSON تلقائياً
        'is_free_shipping'=> 'boolean',
        // 🔥 حقول الضريبة
        'tax_exempt'      => 'boolean',
    ];

    // ============================================================
    // 📌 العلاقات
    // ============================================================

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_product')
            ->withPivot('quantity', 'price')
            ->withTimestamps();
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // ============================================================
    // 🔥 دوال مساعدة للضريبة
    // ============================================================

    /**
     * جلب النسبة الضريبية الفعلية للمنتج:
     * - إذا كان المنتج معفى من الضريبة → 0%
     * - وإلا → نسبة التصنيف (الافتراضي 5%)
     */
    public function effectiveTaxRate(): float
    {
        if ($this->tax_exempt) {
            return 0.0;
        }
        return (float) ($this->category?->tax_rate ?? 5.0);
    }

    /**
     * جلب وصف النسبة الضريبية
     */
    public function taxLabel(): string
    {
        if ($this->tax_exempt) {
            return 'معفى من الضريبة';
        }
        return $this->category?->tax_label ?? ('ضريبة مبيعات ' . $this->effectiveTaxRate() . '%');
    }

    public function store()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }
}