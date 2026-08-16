<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ActiveAd extends Model
{
    use HasFactory;

    protected $table = 'active_ads';

    protected $fillable = [
        'seller_id',
        'image',
        'link_type',
        'link_id',
        'position',
        'start_date',
        'end_date',
    ];

    // Scope لجلب الإعلانات النشطة بحسب التاريخ المتاح ومرتبة بحسب الأولوية
    public function scopeActive($query)
    {
        $today = Carbon::now()->toDateString();

        return $query->where('start_date', '<=', $today)
                     ->where('end_date', '>=', $today)
                     ->orderBy('position', 'asc');
    }

    // علاقة الإعلان بتسجيلات الظهور والنقر
    public function impressions()
    {
        return $this->hasMany(AdImpression::class, 'ad_id');
    }
}