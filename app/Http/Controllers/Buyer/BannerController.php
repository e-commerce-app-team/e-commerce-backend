<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActiveAd;
use App\Models\AdImpression;
use Illuminate\Support\Facades\Auth;
class BannerController extends Controller
{
    // 1. GET /api/home/banners - يُرجع الإعلانات النشطة ويسجل ظهورها
    public function index(Request $request)
    {
        $ads = ActiveAd::active()->get();

        $userId = Auth::guard('sanctum')->check() ? Auth::guard('sanctum')->id() : null;
        $ipAddress = $request->ip();

        // تسجيل عملية ظهور (Impression) لكل إعلان تم استرجاعه
        $impressionsData = [];
        foreach ($ads as $ad) {
            $impressionsData[] = [
                'ad_id'      => $ad->id,
                'user_id'    => $userId,
                'type'       => 'impression',
                'ip_address' => $ipAddress,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($impressionsData)) {
            AdImpression::insert($impressionsData);
        }

        // تحسين الروابط وحزمة الاستجابة
        $formattedAds = $ads->map(function ($ad) {
            return [
                'id'         => $ad->id,
                'seller_id'  => $ad->seller_id,
                'image_url'  => asset('storage/' . $ad->image),
                'link_type'  => $ad->link_type, // e.g., 'store', 'product', 'offer'
                'link_id'    => $ad->link_id,
                'position'   => $ad->position,
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $formattedAds
        ], 200);
    }

    // 2. POST /api/home/banners/{id}/click - تسجيل النقر على الإعلان
    public function recordClick(Request $request, $id)
    {
        $ad = ActiveAd::findOrFail($id);

        $userId = Auth::guard('sanctum')->check() ? Auth::guard('sanctum')->id() : null;

        AdImpression::create([
            'ad_id'      => $ad->id,
            'user_id'    => $userId,
            'type'       => 'click',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Ad click recorded successfully',
            'data'    => [
                'link_type' => $ad->link_type,
                'link_id'   => $ad->link_id,
            ]
        ], 200);
    }
}

