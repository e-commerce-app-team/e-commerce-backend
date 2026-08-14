<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /**
     * جلب جميع إعدادات المنصة
     */
    public function index()
    {
        $settings = PlatformSetting::all()->map(function ($s) {
            return [
                'key'   => $s->key,
                'value' => $s->value,
                'type'  => $s->type,
                'label' => $s->label,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $settings
        ]);
    }

    /**
     * تحديث إعداد محدد بمفتاحه
     */
    public function update(Request $request, string $key)
    {
        $request->validate([
            'value' => 'required'
        ]);

        $setting = PlatformSetting::where('key', $key)->firstOrFail();
        $setting->update(['value' => $request->value]);

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully.',
            'data'    => $setting->fresh()
        ]);
    }
}
