<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $fillable = ['user_id', 'product_id'];
    public $timestamps = false; // لأننا نستخدم added_at يدوياً

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
