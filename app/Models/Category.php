<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

  protected $fillable = [
        'parent_id', 'name', 'slug', 'image_url', 'icon_url', 'order_position', 'is_visible'
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    // علاقة جلب القسم الأب مباشرة
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // علاقة جلب المستوى التالي من الأقسام الفرعية فقط
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order_position', 'asc');
    }

    // السحر: علاقة عودية تجلب الشجرة كاملة مهما تعمقت (أبناء، أحفاد...)
    public function recursiveChildren()
    {
        return $this->children()->with('recursiveChildren');
    }

    // علاقة ربط القسم بالمنتجات
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // حساب عدد منتجات القسم تلقائياً في الـ API
    protected $appends = ['products_count'];

    public function getProductsCountAttribute(): int
    {
        return $this->products()->count();
    }
}
