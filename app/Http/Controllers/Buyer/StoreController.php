<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\StoreReview;
use App\Models\Product;  // استيراد الـ Model

use App\Models\User;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    private function storageUrl(?string $path, $id): ?string
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

        $isOpen = isset($store->is_open) ? (bool) $store->is_open : true;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $store->id,
                'store_name' => $store->store_name,
                'store_logo' => $store->store_logo ? asset('storage/' . $store->store_logo) : null,
                'store_cover' => $store->store_cover ? asset('storage/' . $store->store_cover) : null,

                // تقريب متوسط التقييم لمنزلتين عشرتَين (أو إرجاع 0.0 إذا لم يُقيّم بعد)
                'rating' => round($store->rating ?? 0, 2),

                'products_count' => $store->products_count,
                'is_open' => $isOpen
            ]
        ], 200);
    }

    public function show($id)
    {
        // 1. البحث عن المتجر والتأكد من أنه متجر فعلي
        $store = User::where('id', $id)
            ->where(function ($q) {
                $q->whereIn('role', ['vendor', 'wholesale', 'seller'])
                    ->orWhereNotNull('store_name');
            })
            ->select('id', 'store_name', 'store_logo', 'store_description', 'category')
            ->first();

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        // 2. جلب المنتجات التابعة لهذا المتجر فقط مع الترقيم
        $products = Product::where('user_id', $store->id)
            ->with(['department:id,name'])
            ->latest()
            ->paginate(12);

        // 3. معالجة وتنسيق المنتجات (فك الترجمة إذا كانت مخزنة كـ JSON)
        $products->getCollection()->transform(function ($product) {
            $productName = $product->name;
            if (is_string($productName)) {
                $decoded = json_decode($productName, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $locale = app()->getLocale();
                    $productName = $decoded[$locale] ?? $decoded['ar'] ?? $decoded['en'] ?? reset($decoded);
                }
            }

            $price = $product->offer_price ?? $product->original_price ?? $product->price;

            return [
                'id' => $product->id,
                'name' => $productName,
                'image' => $product->image,
                'price' => round((float) $price, 2),
                'original_price' => $product->original_price ? round((float) $product->original_price, 2) : null,
                'has_offer' => !empty($product->offer_price),
                'department' => $product->department ? $product->department->name : null,
            ];
        });

        // 4. جلب الأقسام التي يمتلك المتجر منتجات فيها (للفلترة في الفرونت)
        $departments = Product::where('user_id', $store->id)
            ->whereNotNull('department_id')
            ->with('department:id,name')
            ->get()
            ->pluck('department')
            ->unique('id')
            ->values();

        // 5. إرجاع الاستجابة النهائية
        return response()->json([
            'success' => true,
            'message' => 'Store details retrieved successfully',
            'data' => [
                'store' => $store,
                'departments' => $departments,
                'products' => $products,
            ]
        ], 200);
    }
    //جلب اقسام متجر محدد
    public function getStoreTree(Request $request, $store_id)
    {
        // جلب الأقسام الرئيسية الخاصة بالمتجر مع أقسامها الفرعية
        $categories = Department::where('seller_id', $store_id)
            ->whereNull('parent_id')
            ->with([
                'children' => function ($query) use ($store_id) {
                    $query->where('seller_id', $store_id)
                        ->select('id', 'name', 'slug', 'parent_id', 'seller_id');
                }
            ])
            ->withCount('products')
            ->orderBy('order_position')
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'This store does not have any departments currently',
                'data' => []
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Store departments tree retrieved successfully',
            'data' => $categories
        ], 200);
    }

    //تنسيق بيانات المنتج وتطبيق الترجمة وفحص المفضلة
    private function formatProduct($product, $buyerId = null)
    {
        // 1. معالجة اسم المنتج وتفكيك الـ JSON للغة الحالية
        $productName = $product->name;
        if (is_string($productName)) {
            $decoded = json_decode($productName, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $locale = app()->getLocale();
                $productName = $decoded[$locale] ?? $decoded['ar'] ?? $decoded['en'] ?? reset($decoded);
            }
        }

        // 2. حساب السعر المطلوب (عرض أو سعر أصلي)
        $price = $product->offer_price ?? $product->original_price ?? $product->price;

        return [
            'id' => $product->id,
            'name' => $productName,
            'image' => $product->image,
            'price' => round((float) $price, 2),
            'original_price' => $product->original_price ? round((float) $product->original_price, 2) : null,
            'has_offer' => !empty($product->offer_price),
            'category_id' => $product->department_id ?? null,
        ];
    }
    // جلب منتجات متجر محدد
    public function getStoreProducts($store_id)
    {
        // 1. استخدام اسم المتغير $paginated ليتطابق مع بقية الكود
        $paginated = Product::where('user_id', $store_id)
            ->latest()
            ->paginate(12);

        // 2. تعيين $buyerId في حال كانت الدالة formatProduct تتطلبه (مثلاً للتحقق من المفضلة)
        $buyerId = auth('sanctum')->id();

        return response()->json([
            'success' => true,
            'data' => [
                'data' => collect($paginated->items())
                    ->map(fn(Product $product) => $this->formatProduct($product, $buyerId))
                    ->values(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ], 200);
    }

    //جلب منتجات قسم محدد (مع منتجات الأقسام الفرعية التابعة له)
// جلب منتجات قسم محدد (مع منتجات الأقسام الفرعية التابعة له)
    public function getdepartmentProducts($category_id)
    {
        // 1. جلب IDs القسم المحدد وأي أقسام فرعية تابعة له مباشرة
        $categoryIds = Department::where('id', $category_id)
            ->orWhere('parent_id', $category_id)
            ->pluck('id');

        // 2. جلب المنتجات الموجودة في هذه الأقسام مع ترقيم الصفحات
        $paginated = Product::whereIn('department_id', $categoryIds)
            ->latest()
            ->paginate(12);

        // 3. تنسيق بيانات المنتجات وفك ترجمة الأسماء
        $formattedProducts = collect($paginated->items())->map(function ($product) {
            $productName = $product->name;
            if (is_string($productName)) {
                $decoded = json_decode($productName, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $locale = app()->getLocale();
                    $productName = $decoded[$locale] ?? $decoded['ar'] ?? $decoded['en'] ?? reset($decoded);
                }
            }

            $price = $product->offer_price ?? $product->original_price ?? $product->price;

            return [
                'id' => $product->id,
                'name' => $productName,
                'image' => $product->image,
                'price' => round((float) $price, 2),
                'original_price' => $product->original_price ? round((float) $product->original_price, 2) : null,
                'has_offer' => !empty($product->offer_price),
                'department_id' => $product->department_id,
            ];
        })->values();

        // 4. إرجاع الاستجابة المنسقة
        return response()->json([
            'success' => true,
            'message' => 'Department products retrieved successfully',
            'data' => [
                'data' => $formattedProducts,
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ], 200);
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
        }}

    // تابع لخريطة المتجر 
    public function getStoresMap(Request $request)
    {
        $lat = $request->lat;
        $lng = $request->lng;
        $radius = $request->radius ?? 10;

        $stores = User::query()
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
            ->withExists([
                'ads as has_paid_ad' => function ($query) {
                    $query->where('status', 'active');
                }
            ])
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
                    'id' => $store->id,
                    'logo' => $store->store_logo ? asset('storage/' . $store->store_logo) : null,
                    'name' => $store->store_name ?? ($store->first_name . ' ' . $store->last_name),
                    'category' => $store->category ?? 'General',
                    'rating' => $rating,
                    'has_paid_ad' => (bool) $store->has_paid_ad,
                    'is_open' => true,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Featured stores retrieved successfully',
            'data' => $stores
        ], 200);
    }
    //تابع المتاجر القريبة مني
    public function getNearbyStores(Request $request)
    {
        // 1. التحقق من المدخلات المطلوبة
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1', // النطاق بالكيلومتر (الافتراضي 10)
        ]);

        $userLat = $request->input('lat');
        $userLng = $request->input('lng');
        $radius = $request->input('radius', 10);
        $limit = $request->input('limit', 10);

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
            'data' => $stores->map(function ($store) {
                $logo = $store->store_logo
                    ? (str_starts_with($store->store_logo, 'http') ? $store->store_logo : asset('storage/' . $store->store_logo))
                    : null;

                return [
                    'id' => $store->id,
                    'store_name' => $store->store_name ?? ($store->first_name . ' ' . $store->last_name),
                    'store_description' => $store->store_description,
                    'store_logo' => $logo,
                    'category' => $store->category,
                    'latitude' => (float) $store->latitude,
                    'longitude' => (float) $store->longitude,
                    'detailed_address' => $store->detailed_address,
                    'distance_km' => round((float) $store->distance_km, 2),
                ];
            })
        ], 200);
    }

}
