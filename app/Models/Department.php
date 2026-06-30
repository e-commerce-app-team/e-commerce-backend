<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'parent_id', // أضف الحقل هنا
        'name',
        'slug',
        'image_url',
        'icon_url',
        'order_position',
        'is_visible'
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    // علاقة جلب القسم الأب مباشرة
    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    // علاقة جلب المستوى التالي من الأقسام الفرعية فقط للتاجر
    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id')->orderBy('order_position', 'asc');
    }

    // علاقة عودية تجلب الشجرة كاملة لأقسام هذا التاجر مهما تعمقت
    public function recursiveChildren()
    {
        return $this->children()->with('recursiveChildren');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

}