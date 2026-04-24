<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class Admin extends Model
{

    use HasApiTokens, Notifiable;
    protected $fillable = [
        'first_name',
        'last_name',
        'email',           // أضفنا الإيميل بدل الهاتف حسب طلبك
        'password',
        'profile_photo',   // تأكدي من تسميته profile_photo وليس profile_picture
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];
}
