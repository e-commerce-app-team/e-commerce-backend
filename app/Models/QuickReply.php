<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuickReply extends Model
{
    use HasFactory;

    protected $fillable = ['seller_id', 'title', 'message'];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
