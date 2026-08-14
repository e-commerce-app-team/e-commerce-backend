<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdView;
use Illuminate\Http\Request;

class AdController extends Controller
{
    // 📌 1. عرض الإعلانات النشطة للمستخدمين
    // ============================================================
    public function getActiveAds(Request $request)
    {
        $type = $request->query('type');

        $query = Ad::active()->with('seller:id,store_name');

        if ($type) {
            $query->where('type', $type);
        }

        $ads = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $ads
        ]);
    }

    // 📌 2. عرض بانرات الصفحة الرئيسية
    // ============================================================
    public function getBanners()
    {
        $banners = Ad::active()
            ->where('type', 'banner')
            ->with('seller:id,store_name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }

    // 📌 3. عرض المنتجات المعززة
    // ============================================================
    public function getPromotedProducts()
    {
        $products = Ad::active()
            ->where('type', 'promoted_product')
            ->with('seller:id,store_name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    // 📌 4. عرض المتاجر المميزة
    // ============================================================
    public function getFeaturedStores()
    {
        $stores = Ad::active()
            ->where('type', 'featured_store')
            ->with('seller:id,store_name,store_logo,store_description')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stores
        ]);
    }

    // 📌 5. تسجيل مشاهدة إعلان
    // ============================================================
    public function trackView(Request $request, $adId)
    {
        $ad = Ad::where('status', 'active')->findOrFail($adId);

        AdView::create([
            'ad_id' => $adId,
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'type' => 'view'
        ]);

        $ad->incrementViews();

        return response()->json([
            'success' => true,
            'message' => 'View tracked.'
        ]);
    }

    // 📌 6. تسجيل نقرة على إعلان
    // ============================================================
    public function trackClick(Request $request, $adId)
    {
        $ad = Ad::where('status', 'active')->findOrFail($adId);

        AdView::create([
            'ad_id' => $adId,
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'type' => 'click'
        ]);

        $ad->incrementClicks();

        // توجيه إلى الرابط
        if ($ad->link) {
            return redirect()->away($ad->link);
        }

        return response()->json([
            'success' => true,
            'message' => 'Click tracked.'
        ]);
    }
}