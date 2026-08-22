<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'seller_id',
        'product_id',
        'type',
        'title',
        'title_ar',
        'title_en',
        'description',
        'description_ar',
        'description_en',
        'image_url',
        'link',
        'duration',
        'price',
        'starts_at',
        'expires_at',
        'status',
        'views_count',
        'clicks_count',
        'admin_notes'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    // ============================================================
    // 📌 العلاقات
    // ============================================================

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function views()
    {
        return $this->hasMany(AdView::class);
    }

    /**
     * الأدمن الذي قام بالموافقة أو الرفض
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    // ============================================================
    // 📌 التحقق من الحالة
    // ============================================================

    public function isActive()
    {
        return $this->status === 'active' &&
            ($this->starts_at ? $this->starts_at <= now() : true) &&
            ($this->expires_at ? $this->expires_at >= now() : true);
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function isExpired()
    {
        return $this->status === 'expired' ||
            ($this->expires_at && $this->expires_at < now());
    }

    // ============================================================
    // 📌 دوال مساعدة للإحصائيات
    // ============================================================

    public function incrementViews()
    {
        $this->increment('views_count');
    }

    public function incrementClicks()
    {
        $this->increment('clicks_count');
    }

    public function getPriceForDuration()
    {
        $prices = [
            '1_day' => 5000,
            '3_days' => 12000,
            '1_week' => 25000,
            '1_month' => 80000,
        ];

        return $prices[$this->duration] ?? 0;
    }

    public function getDurationLabel()
    {
        $labels = [
            '1_day' => 'يوم واحد',
            '3_days' => '3 أيام',
            '1_week' => 'أسبوع',
            '1_month' => 'شهر',
        ];

        return $labels[$this->duration] ?? $this->duration;
    }

    public function getTypeLabel()
    {
        $labels = [
            'banner' => 'بانر رئيسي',
            'promoted_product' => 'منتج معزز',
            'featured_store' => 'متجر مميز',
            'paid_notification' => 'إشعار مدفوع',
        ];

        return $labels[$this->type] ?? $this->type;
    }

    public function getTypeIcon()
    {
        $icons = [
            'banner' => '📢',
            'promoted_product' => '⭐',
            'featured_store' => '🏪',
            'paid_notification' => '🔔',
        ];

        return $icons[$this->type] ?? '📌';
    }

    // ============================================================
    // 📌 النطاقات (Scopes)
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere('expires_at', '<', now());
    }

    public function scopeForSeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
