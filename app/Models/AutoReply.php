<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoReply extends Model
{
    use HasFactory;

    protected $fillable = ['seller_id', 'keyword', 'message', 'is_active'];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
