<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class BuyerHomeController extends Controller
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Build a fully-qualified storage URL for a given path.
     * Returns null if path is blank so the Flutter side can show a placeholder.
     */
    private function storageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return url('storage/' . ltrim($path, '/'));
    }

    private function minutesFromTime(string $time): ?int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
            return null;
        }

        return ((int) $matches[1]) * 60 + (int) $matches[2];
    }

    private function storeIsOpenNow(User $store): bool
    {
        $hours = $store->working_hours;
        if (!is_array($hours) || empty($hours)) {
            return true;
        }

        $today = strtolower(now()->format('l'));
        $entry = collect($hours)->first(function ($item) use ($today) {
            if (!is_array($item)) {
                return false;
            }

            $dayKey = strtolower((string) ($item['day_key'] ?? $item['dayKey'] ?? ''));
            return $dayKey === $today;
        });

        if (!$entry) {
            return true;
        }

        $isOpen = (bool) ($entry['is_open'] ?? $entry['isOpen'] ?? true);
        if (!$isOpen) {
            return false;
        }

        $openTime = (string) ($entry['open_time'] ?? $entry['openTime'] ?? '');
        $closeTime = (string) ($entry['close_time'] ?? $entry['closeTime'] ?? '');
        if ($openTime === '' || $closeTime === '') {
            return true;
        }

        $currentMinutes = ((int) now()->format('H')) * 60 + (int) now()->format('i');
        $openMinutes = $this->minutesFromTime($openTime);
        $closeMinutes = $this->minutesFromTime($closeTime);

        if ($openMinutes === null || $closeMinutes === null) {
            return true;
        }

        if ($openMinutes <= $closeMinutes) {
            return $currentMinutes >= $openMinutes && $currentMinutes <= $closeMinutes;
        }

        return $currentMinutes >= $openMinutes || $currentMinutes <= $closeMinutes;
    }

    /**
     * Map a Product model → the standard buyer product JSON shape.
     */
    private function formatProduct(Product $product, ?int $authUserId = null, ?string $badgeLabel = null, ?int $adId = null): array
    {
        // Determine effective price (offer_price if valid and not expired)
        $now           = now();
        $hasOffer      = $product->offer_price &&
                         $product->offer_price < $product->original_price &&
                         (!$product->offer_expires_at || $product->offer_expires_at > $now);

        $price         = $hasOffer ? (float) $product->offer_price : (float) $product->original_price;
        $oldPrice      = $hasOffer ? (float) $product->original_price : null;
        $discountPct   = $oldPrice ? round(($oldPrice - $price) / $oldPrice * 100) : null;

        // First image
        $images  = $product->images ?? [];
        $image   = is_array($images) && count($images) > 0
            ? $this->storageUrl($images[0])
            : null;

        // Favourite flag
        $isFav = false;
        if ($authUserId) {
            $isFav = Favorite::where('user_id', $authUserId)
                              ->where('product_id', $product->id)
                              ->exists();
        }

        return [
            'id'               => $product->id,
            'name'             => $product->name,
            'price'            => $price,
            'old_price'        => $oldPrice,
            'discount_percent' => $discountPct,
            'image'            => $image,
            'rating'           => $product->rating ?? 0,
            'rating_count'     => $product->rating_count ?? 0,
            'badge_label'      => $badgeLabel,
            'ad_id'            => $adId,
            'store_id'         => $product->user_id,
            'store_name'       => $product->seller?->store_name ?? null,
            'category_id'      => $product->category_id ?? $product->department_id,
            'category_name'    => $product->category?->name ?? $product->department?->name ?? null,
            'department_id'    => $product->department_id,
            'department_name'  => $product->department?->name,
            'quantity'         => (int) ($product->quantity ?? 0),
            'is_favorite'      => $isFav,
            'free_shipping'    => (bool) $product->is_free_shipping,
            'has_wholesale'    => $product->wholesale_price !== null,
        ];
    }

    /**
     * Map a User (vendor/wholesale) model → the standard buyer store JSON shape.
     */
    private function formatStore(User $store, bool $isFeatured = false, ?float $distance = null, ?int $adId = null): array
    {
        return [
            'id'             => $store->id,
            'store_name'     => $store->store_name,
            'store_logo'     => $this->storageUrl($store->store_logo),
            'store_cover'    => $this->storageUrl($store->store_cover_photo),
            'rating'         => (float) ($store->store_reviews_avg_rating ?? 0),
            'is_open'        => $this->storeIsOpenNow($store),
            'category_id'    => $store->category,
            'category'       => $store->globalCategory?->name ?? $store->category ?? 'متجر',
            'review_count'   => (int) ($store->store_reviews_count ?? 0),
            'products_count' => (int) ($store->products_count ?? 0),
            'is_featured'    => $isFeatured,
            'ad_id'          => $adId,
            'distance'       => $distance,
        ];
    }

    // ─── GET /buyer/banners ───────────────────────────────────────────────────

    public function getBanners(Request $request)
    {
        try {
            $ads = Ad::active()
                ->byType('banner')
                ->with('seller:id,store_name')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();

            $locale = str_starts_with((string) $request->query('locale', 'en'), 'ar') ? 'ar' : 'en';
            $data = $ads->map(fn(Ad $ad) => [
                'id'          => $ad->id,
                'title'       => $locale === 'ar' ? ($ad->title_ar ?: $ad->title) : ($ad->title_en ?: $ad->title),
                'subtitle'    => $locale === 'ar' ? ($ad->description_ar ?: $ad->description) : ($ad->description_en ?: $ad->description),
                'image'       => $this->storageUrl($ad->image_url),
                'badge_label' => null,
                'seller_id'   => $ad->seller_id,
                'link'        => $ad->link,
            ])->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/categories ────────────────────────────────────────────────

    public function getCategories()
    {
        try {
            $categories = Category::orderBy('order_position')
                ->orderBy('name')
                ->get(['id', 'name', 'image_url', 'icon_url', 'is_visible']);

            $data = $categories->map(fn(Category $c) => [
                'id'             => $c->id,
                'name'           => $c->name, // auto-localized via accessor
                'icon'           => $this->storageUrl($c->icon_url ?? $c->image_url),
                'color'          => null, // color not in DB yet
                'products_count' => $c->products_count,
            ])->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/stores/featured ───────────────────────────────────────────

    public function getFeaturedStores()
    {
        try {
            // Get seller IDs that have an active featured_store ad
            $featuredAds = Ad::active()
                ->byType('featured_store')
                ->get(['id', 'seller_id']);
            $boostedIds = $featuredAds->pluck('seller_id')->unique()->values()->all();
            $featuredAdBySeller = $featuredAds->groupBy('seller_id')
                ->map(fn ($ads) => (int) $ads->first()->id)
                ->all();

            // Boosted stores plus all stores with their real review average.
            $stores = User::whereIn('role', ['vendor', 'wholesale'])
                ->with('globalCategory:id,name')
                ->withAvg('storeReviews', 'rating')
                ->withCount('products')
                ->orderByRaw('FIELD(id, ' . (count($boostedIds) ? implode(',', $boostedIds) : '0') . ') DESC')
                ->orderByDesc('store_reviews_avg_rating')
                ->limit(10)
                ->get();

            $data = $stores->map(function (User $store) use ($boostedIds, $featuredAdBySeller) {
                return $this->formatStore(
                    $store,
                    in_array($store->id, $boostedIds),
                    null,
                    $featuredAdBySeller[$store->id] ?? null,
                );
            })->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/stores/nearby?lat=&lng=&radius= ───────────────────────────

    public function getNearbyStores(Request $request)
    {
        $lat    = (float) $request->lat;
        $lng    = (float) $request->lng;
        $radius = (float) ($request->radius ?? 10);

        if (!$lat || !$lng) {
            return response()->json(['success' => false, 'message' => 'lat and lng required', 'data' => []]);
        }

        try {
            $stores = User::whereIn('role', ['vendor', 'wholesale'])
                ->with('globalCategory:id,name')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->selectRaw("id, store_name, store_logo, store_cover_photo,
                    ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) )
                    * cos( radians( longitude ) - radians(?) )
                    + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance", [$lat, $lng, $lat])
                ->withCount('products')
                ->having('distance', '<', $radius)
                ->orderBy('distance')
                ->limit(10)
                ->get();

            $data = $stores->map(function ($store) {
                return $this->formatStore($store, false, round($store->distance, 1));
            })->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/products/featured ─────────────────────────────────────────

    public function getStores(Request $request)
    {
        try {
            $query = User::whereIn('role', ['vendor', 'wholesale'])
                ->with('globalCategory:id,name')
                ->withAvg('storeReviews', 'rating')
                ->withCount(['products', 'storeReviews']);

            if ($request->filled('q')) {
                $term = trim($request->q);
                $query->where(function ($q) use ($term) {
                    $q->where('store_name', 'like', "%{$term}%")
                      ->orWhere('store_description', 'like', "%{$term}%")
                      ->orWhere('detailed_address', 'like', "%{$term}%");
                });
            }

            if ($request->filled('category_id') && $request->category_id !== 'all') {
                $query->where('category', $request->category_id);
            }

            if ($request->boolean('has_products')) {
                $query->has('products');
            }

            if ($request->filled('min_rating')) {
                $query->having('store_reviews_avg_rating', '>=', (float) $request->min_rating);
            }

            if ($request->filled('lat') && $request->filled('lng')) {
                $lat = (float) $request->lat;
                $lng = (float) $request->lng;
                $radius = (float) ($request->radius ?? 10);
                $query->selectRaw("users.*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) )
                    * cos( radians( longitude ) - radians(?) )
                    + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance", [$lat, $lng, $lat])
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->having('distance', '<=', $radius);
            }

            match ($request->sort_by ?? 'latest') {
                'rating'  => $query->orderByDesc('store_reviews_avg_rating'),
                'popular' => $query->orderByDesc('products_count'),
                'name'    => $query->orderBy('store_name'),
                default   => $query->orderByDesc('created_at'),
            };

            $perPage = min((int) ($request->per_page ?? 80), 100);
            $paginated = $query->paginate($perPage);

            $data = collect($paginated->items())
                ->when($request->boolean('open_now'), function ($items) {
                    return $items->filter(fn(User $store) => $this->storeIsOpenNow($store));
                })
                ->map(fn($store) => $this->formatStore(
                    $store,
                    false,
                    isset($store->distance) ? round((float) $store->distance, 1) : null
                ))
                ->values();

            return response()->json([
                'success' => true,
                'data'    => [
                    'data'         => $data,
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'total'        => $paginated->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    public function getFeaturedProducts(Request $request)
    {
        try {
            $authUser = $request->user();

            $promotedAds = Ad::active()
                ->where('type', 'promoted_product')
                ->whereNotNull('product_id')
                ->orderByDesc('created_at')
                ->get(['id', 'product_id']);
            $promotedIds = $promotedAds->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            $promotedAdByProduct = $promotedAds->keyBy(fn ($ad) => (int) $ad->product_id);

            $promoted = collect();
            if ($promotedIds !== []) {
                $promoted = Product::where('status', 'active')
                    ->whereIn('id', $promotedIds)
                    ->with('seller:id,store_name')
                    ->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', $promotedIds)) . ')')
                    ->limit(10)
                    ->get();
            }

            $organic = Product::where('status', 'active')
                ->when($promotedIds !== [], fn ($query) => $query->whereNotIn('id', $promotedIds))
                ->with('seller:id,store_name')
                ->orderByDesc('sales_count')
                ->limit(max(0, 10 - $promoted->count()))
                ->get();
            $products = $promoted->concat($organic);
            $locale = str_starts_with((string) $request->query('locale', 'en'), 'ar') ? 'ar' : 'en';

            $data = $products->map(function ($product) use ($authUser, $promotedIds, $promotedAdByProduct, $locale) {
                $isPromoted = in_array($product->id, $promotedIds, true);
                $ad = $isPromoted ? $promotedAdByProduct->get((int) $product->id) : null;
                return $this->formatProduct(
                    $product,
                    $authUser?->id,
                    $isPromoted ? ($locale === 'ar' ? 'منتج معزز' : 'Promoted') : null,
                    $ad?->id,
                );
            })->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/products/flash-sale ───────────────────────────────────────

    public function getFlashSaleProducts(Request $request)
    {
        try {
            $authUser = $request->user();
            $now      = now();

            $products = Product::where('status', 'active')
                ->whereNotNull('offer_price')
                ->whereColumn('offer_price', '<', 'original_price')
                ->where(function ($q) use ($now) {
                    $q->whereNull('offer_expires_at')
                      ->orWhere('offer_expires_at', '>', $now);
                })
                ->with('seller:id,store_name')
                ->orderByRaw('(original_price - offer_price) / original_price DESC')
                ->limit(10)
                ->get();

            $data = $products->map(fn($p) => $this->formatProduct($p, $authUser?->id))->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/products/trending ─────────────────────────────────────────

    public function getTrendingProducts(Request $request)
    {
        try {
            $authUser = $request->user();

            $products = Product::where('status', 'active')
                ->with('seller:id,store_name')
                ->orderByDesc('sales_count')
                ->limit(10)
                ->get();

            $data = $products->map(fn($p) => $this->formatProduct($p, $authUser?->id))->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/products/new-arrivals ─────────────────────────────────────

    public function getNewArrivals(Request $request)
    {
        try {
            $authUser = $request->user();

            $products = Product::where('status', 'active')
                ->with('seller:id,store_name')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            $data = $products->map(fn($p) => $this->formatProduct($p, $authUser?->id))->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/products/offers ───────────────────────────────────────────

    public function getOffers(Request $request)
    {
        // Offers must be real active discounts and must not be expired.
        try {
            $authUser = $request->user();

            $products = Product::where('status', 'active')
                ->whereNotNull('offer_price')
                ->whereColumn('offer_price', '<', 'original_price')
                ->where(function ($q) {
                    $q->whereNull('offer_expires_at')
                      ->orWhere('offer_expires_at', '>', now());
                })
                ->with('seller:id,store_name')
                ->orderByRaw('(original_price - offer_price) / original_price DESC')
                ->limit(10)
                ->get();

            $data = $products->map(fn($p) => $this->formatProduct($p, $authUser?->id))->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/products/recommended ─────────────────────────────────────

    public function getRecommended(Request $request)
    {
        try {
            $authUser = $request->user();

            // Fallback: top-rated + most-sold products as a proxy for "recommended"
            $products = Product::where('status', 'active')
                ->with('seller:id,store_name')
                ->orderByDesc('sales_count')
                ->limit(10)
                ->get();

            $data = $products->map(fn($p) => $this->formatProduct($p, $authUser?->id))->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/products ──────────────────────────────────────────────────

    public function getAllProducts(Request $request)
    {
        try {
            $authUser   = $request->user();
            $categoryId = $request->category_id;
            $sortBy     = $request->sort_by ?? 'newest';

            $query = Product::where('status', 'active')
                ->with(['seller:id,store_name', 'category:id,name', 'department:id,name']);

            if ($categoryId) {
                $query->where(function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId)
                      ->orWhere('department_id', $categoryId);
                });
            }

            if ($request->filled('q')) {
                $term = trim($request->q);
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('description', 'like', "%{$term}%")
                      ->orWhereHas('seller', fn($seller) => $seller->where('store_name', 'like', "%{$term}%"));
                });
            }

            if ($request->filled('store_id')) {
                $query->where('user_id', $request->store_id);
            }

            if ($request->filled('min_price')) {
                $query->where('original_price', '>=', (float) $request->min_price);
            }

            if ($request->filled('max_price')) {
                $query->where('original_price', '<=', (float) $request->max_price);
            }

            if ($request->filled('min_rating')) {
                $query->where('rating', '>=', (float) $request->min_rating);
            }

            if ($request->boolean('free_shipping')) {
                $query->where('is_free_shipping', true);
            }

            if ($request->boolean('discounted')) {
                $query->whereNotNull('offer_price')
                    ->whereColumn('offer_price', '<', 'original_price')
                    ->where(function ($q) {
                        $q->whereNull('offer_expires_at')
                          ->orWhere('offer_expires_at', '>', now());
                    });
            }

            if ($request->boolean('in_stock')) {
                $query->where('quantity', '>', 0);
            }

            match ($sortBy) {
                'price_asc'  => $query->orderBy('original_price'),
                'price_desc' => $query->orderByDesc('original_price'),
                'rating'     => $query->orderByDesc('rating'),
                'popular'    => $query->orderByDesc('sales_count'),
                'name'       => $query->orderBy('name'),
                default      => $query->orderByDesc('created_at'),
            };

            $perPage = min((int) ($request->per_page ?? 12), 100);
            $paginated = $query->paginate($perPage);

            $data = collect($paginated->items())
                ->map(fn($p) => $this->formatProduct($p, $authUser?->id))
                ->values();

            return response()->json([
                'success' => true,
                'data'    => [
                    'data'         => $data,
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'total'        => $paginated->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── GET /buyer/favorites ─────────────────────────────────────────────────

    public function getFavorites(Request $request)
    {
        try {
            $user = $request->user();

            $favorites = Favorite::where('user_id', $user->id)
                ->with('product.seller:id,store_name')
                ->get();

            $data = $favorites
                ->filter(fn($f) => $f->product && $f->product->status === 'active')
                ->map(function ($f) use ($user) {
                    $p = $f->product;
                    $formatted = $this->formatProduct($p, $user->id);
                    $formatted['is_favorite'] = true;
                    return $formatted;
                })
                ->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    // ─── POST /buyer/favorites/{productId}/toggle ─────────────────────────────

    public function toggleFavorite(Request $request, $productId)
    {
        try {
            $user     = $request->user();
            $existing = Favorite::where('user_id', $user->id)
                                 ->where('product_id', $productId)
                                 ->first();

            if ($existing) {
                $existing->delete();
                $isFav = false;
            } else {
                Favorite::create(['user_id' => $user->id, 'product_id' => $productId]);
                $isFav = true;
            }

            return response()->json([
                'success'     => true,
                'is_favorite' => $isFav,
                'message'     => $isFav ? 'تمت الإضافة إلى المفضلة' : 'تمت الإزالة من المفضلة',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
