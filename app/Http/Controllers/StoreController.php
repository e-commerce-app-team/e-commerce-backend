<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    
    //Get featured stores for the home scree with Paid Ads or Highest Rated Stores
public function getFeaturedStores(): JsonResponse
{
    $stores = User::whereIn('role', ['vendor', 'wholesale'])
        ->where('status', 'approved')
        ->withAvg('storeReviews', 'rating')
        ->withExists(['ads as has_paid_ad' => function ($query) {
            $query->where('status', 'active');
        }])
        ->get()
        // التصفية والفلترة بالذاكرة لضمان عدم حدوث تعارض SQL
        ->filter(function ($store) {
            $hasRating = $store->store_reviews_avg_rating > 0;
            $hasPaidAd = (bool) $store->has_paid_ad;

            // إظهار المتجر فقط إذا كان يملك إعلاناً مدفوعاً أو يملك تقييمات أكبر من 0
            return $hasPaidAd || $hasRating;
        })
        // الترتيب: الإعلانات المدفوعة أولاً، ثم الأعلى تقييماً
        ->sortByDesc(function ($store) {
            return [$store->has_paid_ad, $store->store_reviews_avg_rating];
        })
        ->take(10)
        ->values() // إعادة ترتيب الفهارس (Indexes)
        ->map(function ($store) {
            $rating = $store->store_reviews_avg_rating 
                ? round((float) $store->store_reviews_avg_rating, 1) 
                : 0.0;

            return [
                'id'       => $store->id,
                'logo'     => $store->store_logo ? asset('storage/' . $store->store_logo) : null,
                'name'     => $store->store_name ?? ($store->first_name . ' ' . $store->last_name),
                'category' => $store->category ?? 'General',
                'rating'   => $rating,
                'has_paid_ad' => (bool) $store->has_paid_ad,
                'is_open'  => true,
            ];
        });

    return response()->json([
        'status'  => true,
        'message' => 'Featured stores retrieved successfully',
        'data'    => $stores
    ], 200);
}

}
