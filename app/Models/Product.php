<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'name', 'description', 'images', 'video_url',
        'original_price', 'offer_price', 'offer_expires_at', 'sku', 'quantity',
        'alert_threshold', 'weight', 'length', 'width', 'height', 'status'
    ];

    // تحويل الحقل إلى مصفوفة تلقائياً
    protected $casts = [
        'images' => 'array',
        'offer_expires_at' => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}