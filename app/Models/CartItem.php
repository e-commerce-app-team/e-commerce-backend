<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $table = 'cart_items';
    protected $fillable = ['user_id', 'product_id', 'variant_id', 'seller_id', 'qty'];

    protected $casts = [
        'seller_id' => 'integer',
        'product_id' => 'integer',
        'user_id' => 'integer',
        'qty' => 'integer',
    ];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
