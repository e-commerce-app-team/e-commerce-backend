<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\StoreReview;

use Illuminate\Http\Request;

class StoreController extends Controller
{
    private function storageUrl(?string $path): ?string
    {
        // 1. جلب المتجر مع حساب عدد المنتجات ومتوسط التقييمات ديناميكياً
        $store = User::whereIn('role', ['vendor', 'wholesale'])
            ->withCount('products')
            ->withAvg('storeReviews as rating', 'rating') // حساب متوسط التقييم
            ->find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'This store was not found',
            ], 404);
        }

        $isOpen = isset($store->is_open) ? (bool)$store->is_open : true;

        return response()->json([
            'success' => true,
            'data' => [
                'id'             => $store->id,
                'store_name'     => $store->store_name,
                'store_logo'     => $store->store_logo ? asset('storage/' . $store->store_logo) : null,
                'store_cover'    => $store->store_cover ? asset('storage/' . $store->store_cover) : null,

                // تقريب متوسط التقييم لمنزلتين عشرتَين (أو إرجاع 0.0 إذا لم يُقيّم بعد)
                'rating'         => round($store->rating ?? 0, 2),

                'products_count' => $store->products_count,
                'is_open'        => $isOpen
            ]
        ], 200);
    }
    //جلب اقسام متجر محدد
    public function getStoreTree($store_id)
    {
        // جلب الأقسام الرئيسية الخاصة بالمتجر (المربوطة بـ seller_id والتي parent_id لها null)
        $categories = Department::where('seller_id', $store_id) // 🌟 تم الاعتماد على seller_id
            ->whereNull('parent_id')
            ->with(['children' => function ($query) use ($store_id) {
                // نضمن برمجياً أن الأقسام الفرعية تتبع لنفس المتجر أيضاً
                $query->where('seller_id', $store_id)
                    ->select('id', 'name', 'slug', 'parent_id', 'seller_id');
            }])
            ->withCount('products')
            ->orderBy('order_position')
            ->orderBy('name')
            ->get();

        // التحقق إذا كان المتجر لا يملك أقساماً بعد
        if ($categories->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'This store does not have any departments currently',
                'data' => []
            ], 200);
        }

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($request->filled('min_price')) {
            $query->where(function ($subQuery) use ($request) {
                $subQuery->where('offer_price', '>=', $request->min_price)
                    ->orWhere(function ($nested) use ($request) {
                        $nested->whereNull('offer_price')
                            ->where('original_price', '>=', $request->min_price);
                    });
            });
        }

        if ($request->filled('max_price')) {
            $query->where(function ($subQuery) use ($request) {
                $subQuery->where('offer_price', '<=', $request->max_price)
                    ->orWhere(function ($nested) use ($request) {
                        $nested->whereNull('offer_price')
                            ->where('original_price', '<=', $request->max_price);
                    });
            });
        }

        match ($request->get('sort_by', 'latest')) {
            'oldest' => $query->orderBy('created_at'),
            'price_asc' => $query->orderByRaw('COALESCE(offer_price, original_price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(offer_price, original_price) DESC'),
            'best_selling' => $query->orderByDesc('sales_count'),
            'rating' => $query->orderByDesc('rating'),
            default => $query->orderByDesc('created_at'),
        };

        $paginated = $query->paginate((int) $request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => collect($paginated->items())
                    ->map(fn (Product $product) => $this->formatProduct($product, $buyerId))
                    ->values(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }


    //جلب منتجات متجر محدد
    public function getStoreProducts($store_id)
    {
        $products = \App\Models\Product::where('user_id', $store_id)
            //  ->select('id', 'name', 'price', 'image', 'description', 'seller_id', 'department_id')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => collect($paginated->items())
                    ->map(fn (Product $product) => $this->formatProduct($product, $buyerId))
                    ->values(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    //جلب منتجات قسم محدد (مع منتجات الأقسام الفرعية التابعة له)
    public function getdepartmentProducts($category_id)
    {
        // 1. جلب IDs القسم المحدد وأي أقسام فرعية تابعة له
        $categoryIds = \App\Models\Department::where('id', $category_id)
            ->orWhere('parent_id', $category_id)
            ->pluck('id');

        // 2. جلب المنتجات الموجودة في هذه الأقسام
        $products = \App\Models\Product::whereIn('department_id', $categoryIds)
            // ->select('id', 'name', 'price', 'image', 'description', 'department_id')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => collect($reviews->items())->map(fn (StoreReview $review) => [
                    'id' => $review->id,
                    'buyer_name' => trim(($review->buyer?->first_name ?? '') . ' ' . ($review->buyer?->last_name ?? '')),
                    'rating' => (float) $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                ])->values(),
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    // جلب التقييمات الخاصة بمتجر معين
    public function getStoreReviews($store_id)
    {
        // 1. جلب التقييمات مع الحقول المحددة من جدول الـ Users
        $reviews = StoreReview::where('store_id', $store_id)
            ->with(['user:id,first_name,last_name,profile_photo']) // استخدام الحقول الخاصة بجدولكِ
            ->latest()
            ->paginate(10);

        // 2. حالة عدم وجود تقييمات للمتجر
        if ($reviews->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'There are no reviews for this store yet',
                'data'    => [],
                'stats'   => [
                    'average_rating' => 0,
                    'total_reviews'  => 0,
                ]
            ], 200);
        }

        // 3. حساب متوسط التقييمات
        $avgRating = StoreReview::where('store_id', $store_id)->avg('rating');

        // 4. إرجاع البيانات بفرز وتنسيق اسم المشتري وصورته
        return response()->json([
            'success' => true,
            'stats'   => [
                'average_rating' => round($avgRating, 2),
                'total_reviews'  => $reviews->total(),
            ],
            'data' => $reviews->through(function ($review) {
                // دمج الاسم الأول والأخير
                $fullName = trim(($review->user->first_name ?? '') . ' ' . ($review->user->last_name ?? ''));

                return [
                    'id'         => $review->id,
                    'rating'     => $review->rating,
                    'comment'    => $review->comment,
                    'created_at' => $review->created_at->format('Y-m-d H:i'),
                    'user'       => [
                        'id'    => $review->user->id ?? null,
                        'name'  => !empty($fullName) ? $fullName : 'مشتري',
                        'profile_photo' => isset($review->user->profile_photo)
                            ? asset('storage/' . $review->user->profile_photo)
                            : null,
                    ]
                ];
            })->items(),
            'pagination' => [
                'total'        => $reviews->total(),
                'per_page'     => $reviews->perPage(),
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
            ]
        ], 200);
    }

    // تابع لخريطة المتجر 
    public function getStoresMap(Request $request)
    {
        $lat = $request->lat;
        $lng = $request->lng;
        $radius = $request->radius ?? 10;

        $stores = \App\Models\User::query()
            ->whereIn('role', ['vendor', 'wholesale'])
            ->select(
                'id',
                'store_name as name',
                'latitude', // الاسم الصحيح من الداتابيز
                'longitude', // الاسم الصحيح من الداتابيز
                //'is_open', 
                // 'rating', 
                'store_logo as category_icon'
            )
            // نقوم بتبديل lat بـ latitude و lng بـ longitude في المعادلة الحسابية
            // ->selectRaw('( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance', [$lat, $lng, $lat])
            ->selectRaw('1 as is_open')
            ->selectRaw('5.0 as rating')
            ->selectRaw('( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance', [$lat, $lng, $lat])
            ->having('distance', '<', $radius)
            ->get();


        return response()->json([
            'success' => true,
            'data' => $stores
        ], 200);
    }
    //Get featured stores for the home scree with Paid Ads or Highest Rated Stores
    public function getFeaturedStores()
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
//تابع المتاجر القريبة مني
public function getNearbyStores(Request $request)
{
    // 1. التحقق من المدخلات المطلوبة
    $request->validate([
        'lat'    => 'required|numeric|between:-90,90',
        'lng'    => 'required|numeric|between:-180,180',
        'radius' => 'nullable|numeric|min:0.1', // النطاق بالكيلومتر (الافتراضي 10)
    ]);

    $userLat = $request->input('lat');
    $userLng = $request->input('lng');
    $radius  = $request->input('radius', 10);
    $limit   = $request->input('limit', 10);

    // 2. معادلة Haversine لحساب المسافة بالـ KM
    $haversine = "(6371 * acos(
        cos(radians(?)) 
        * cos(radians(latitude)) 
        * cos(radians(longitude) - radians(?)) 
        + sin(radians(?)) 
        * sin(radians(latitude))
    ))";

    // 3. الاستعلام عن التجار المعتمدين (approved)
    $stores = User::select('users.*')
        ->selectRaw("{$haversine} AS distance_km", [$userLat, $userLng, $userLat])
        ->whereIn('role', ['vendor', 'wholesale'])
        ->where('status', 'approved') // تم التحديث إلى approved
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->having('distance_km', '<=', $radius)
        ->orderBy('distance_km', 'asc')
        ->limit($limit)
        ->get();

    // 4. تنسيق الاستجابة
    return response()->json([
        'success' => true,
        'data'    => $stores->map(function ($store) {
            $logo = $store->store_logo 
                ? (str_starts_with($store->store_logo, 'http') ? $store->store_logo : asset('storage/' . $store->store_logo))
                : null;

            return [
                'id'                => $store->id,
                'store_name'        => $store->store_name ?? ($store->first_name . ' ' . $store->last_name),
                'store_description' => $store->store_description,
                'store_logo'        => $logo,
                'category'          => $store->category,
                'latitude'          => (float) $store->latitude,
                'longitude'         => (float) $store->longitude,
                'detailed_address'  => $store->detailed_address,
                'distance_km'       => round((float) $store->distance_km, 2),
            ];
        })
    ], 200);
}

}
