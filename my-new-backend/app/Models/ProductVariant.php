<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'attributes', 'sku', 'price', 'quantity', 'image_url', 'is_active'
    ];

    protected $casts = [
        'attributes' => 'array', // ليتحول الجيسون تلقائياً إلى مصفوفة في الـ API
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}