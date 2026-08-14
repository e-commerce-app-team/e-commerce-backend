<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'label'];

    // ============================================================
    // 📌 Helper Methods
    // ============================================================

    /**
     * جلب قيمة إعداد معين بمفتاحه
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * تحديث أو إنشاء إعداد
     */
    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * جلب نسبة عمولة التاجر العادي (Vendor) كـ float
     */
    public static function vendorCommissionRate(): float
    {
        return (float) static::getValue('vendor_commission_rate', 10.0);
    }

    /**
     * جلب نسبة عمولة تاجر الجملة (Wholesale) كـ float
     */
    public static function wholesaleCommissionRate(): float
    {
        return (float) static::getValue('wholesale_commission_rate', 5.0);
    }

    /**
     * جلب جميع الإعدادات كـ key=>value array
     */
    public static function allSettings(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }
}
