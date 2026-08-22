<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDevice extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'device_id', 'locale', 'last_seen_at'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
