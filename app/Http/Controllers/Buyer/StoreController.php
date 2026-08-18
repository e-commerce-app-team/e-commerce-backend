<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\StoreFollow;
use App\Models\StoreReview;
use App\Models\User;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    private function storageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return url('storage/' . ltrim($path, '/'));
    }

    private function activeStoreQuery()
    {
        return User::whereIn('role', ['vendor', 'wholesale']);
    }

    private function departmentIdsWithChildren(int $departmentId): array
    {
        $ids = [$departmentId];
        $children = Department::where('parent_id', $departmentId)->pluck('id')->all();

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->departmentIdsWithChildren((int) $childId));
        }

        return array_values(array_unique($ids));
    }

    private function formatProduct(Product $product, ?int $buyerId = null): array
    {
        $images = $product->images ?? [];
        $image = is_array($images) && count($images) > 0
            ? $this->storageUrl($images[0])
            : null;

        $hasOffer = $product->offer_price
            && $product->offer_price < $product->original_price
            && (!$product->offer_expires_at || $product->offer_expires_at->isFuture());

        $price = $hasOffer ? (float) $product->offer_price : (float) $product->original_price;
        $oldPrice = $hasOffer ? (float) $product->original_price : null;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'image' => $image,
            'images' => collect($images)->map(fn ($path) => $this->storageUrl($path))->values(),
            'price' => $price,
            'old_price' => $oldPrice,
            'original_price' => (float) $product->original_price,
            'offer_price' => $product->offer_price ? (float) $product->offer_price : null,
            'quantity' => (int) $product->quantity,
            'department_id' => $product->department_id,
            'category_id' => $product->category_id,
            'is_free_shipping' => (bool) $product->is_free_shipping,
            'rating' => (float) ($product->rating ?? 0),
            'rating_count' => (int) ($product->rating_count ?? 0),
            'is_favorite' => $buyerId
                ? Favorite::where('user_id', $buyerId)->where('product_id', $product->id)->exists()
                : false,
        ];
    }

    public function show(Request $request, $id)
    {
        $store = $this->activeStoreQuery()
            ->withCount([
                'products',
                'storeReviews as reviews_count',
                'storeFollowers as followers_count',
            ])
            ->withAvg('storeReviews as average_rating', 'rating')
            ->find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'This store was not found',
            ], 404);
        }

        $buyerId = optional($request->user())->id;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $store->id,
                'seller_id' => $store->id,
                'store_name' => $store->store_name,
                'name' => $store->store_name,
                'description' => $store->store_description,
                'category' => $store->category,
                'store_logo' => $this->storageUrl($store->store_logo),
                'logo_url' => $this->storageUrl($store->store_logo),
                'store_cover' => $this->storageUrl($store->store_cover_photo),
                'cover_url' => $this->storageUrl($store->store_cover_photo),
                'phone' => $store->phone,
                'email' => $store->store_email ?: $store->email,
                'address' => $store->detailed_address,
                'social_links' => $store->social_links ?? [],
                'working_hours' => $store->working_hours,
                'return_policy' => $store->return_policy,
                'rating' => round((float) ($store->average_rating ?? 0), 1),
                'reviews_count' => (int) $store->reviews_count,
                'followers_count' => (int) $store->followers_count,
                'products_count' => (int) $store->products_count,
                'is_following' => $buyerId
                    ? StoreFollow::where('store_id', $store->id)->where('user_id', $buyerId)->exists()
                    : false,
                'is_open' => true,
            ],
        ]);
    }

    public function getStoreTree($storeId)
    {
        $departments = Department::where('seller_id', $storeId)
            ->whereNull('parent_id')
            ->where('is_visible', true)
            ->with(['recursiveChildren' => function ($query) use ($storeId) {
                $query->where('seller_id', $storeId)->where('is_visible', true);
            }])
            ->withCount('products')
            ->orderBy('order_position')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }

    public function getStoreProducts(Request $request, $storeId)
    {
        $buyerId = optional($request->user())->id;
        $query = Product::where('user_id', $storeId)
            ->where('status', 'active')
            ->with('department');

        if ($request->filled('department_id')) {
            $query->whereIn(
                'department_id',
                $this->departmentIdsWithChildren((int) $request->department_id)
            );
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

    public function getdepartmentProducts(Request $request, $departmentId)
    {
        $buyerId = optional($request->user())->id;
        $paginated = Product::whereIn('department_id', $this->departmentIdsWithChildren((int) $departmentId))
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 12));

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

    public function getStoreReviews($storeId)
    {
        $reviews = StoreReview::where('store_id', $storeId)
            ->with('buyer:id,first_name,last_name,profile_photo')
            ->latest()
            ->paginate(10);

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

    public function addStoreReview(Request $request, $storeId)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $store = $this->activeStoreQuery()->findOrFail($storeId);

        $review = StoreReview::updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $request->user()->id],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Review saved successfully',
            'data' => $review,
        ]);
    }

    public function toggleFollow(Request $request, $storeId)
    {
        $store = $this->activeStoreQuery()->findOrFail($storeId);
        $existing = StoreFollow::where('store_id', $store->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $isFollowing = false;
        } else {
            StoreFollow::create([
                'store_id' => $store->id,
                'user_id' => $request->user()->id,
            ]);
            $isFollowing = true;
        }

        return response()->json([
            'success' => true,
            'is_following' => $isFollowing,
            'followers_count' => StoreFollow::where('store_id', $store->id)->count(),
        ]);
    }

    public function getStoresMap(Request $request)
    {
        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $radius = (float) ($request->radius ?? 10);

        $stores = User::query()
            ->whereIn('role', ['vendor', 'wholesale'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'store_name as name', 'latitude', 'longitude', 'store_logo as category_icon')
            ->selectRaw('1 as is_open')
            ->selectRaw('5.0 as rating')
            ->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$lat, $lng, $lat])
            ->having('distance', '<', $radius)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stores,
        ]);
    }
}
