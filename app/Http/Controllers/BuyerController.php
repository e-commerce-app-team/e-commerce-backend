<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BuyerController extends Controller
{
    public function products(Request $request)
    {
        $section = $request->route('section') ?? $request->input('section');
        $query = Product::query()->where('status', 'active')->with(['variants', 'seller']);
        $query->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id));
        $query->when($request->store_id, fn ($q, $id) => $q->where('user_id', $id));
        $query->when($request->department_id, fn ($q, $id) => $q->where('department_id', $id));
        $query->when($request->q, fn ($q, $value) => $q->where('name', 'like', "%{$value}%"));
        $query->when($request->min_price, fn ($q, $value) => $q->where('original_price', '>=', $value));
        $query->when($request->max_price, fn ($q, $value) => $q->where('original_price', '<=', $value));
        $query->when($request->in_stock, fn ($q) => $q->where('quantity', '>', 0));
        $query->when($request->discounted, fn ($q) => $q->whereNotNull('offer_price'));
        $query->when($request->free_shipping, fn ($q) => $q->where('is_free_shipping', true));
        $query->when($request->min_rating, function ($q, $rating) {
            $q->whereIn('products.id', function ($subQuery) use ($rating) {
                $subQuery->from('product_reviews')
                    ->select('product_id')
                    ->groupBy('product_id')
                    ->havingRaw('AVG(rating) >= ?', [$rating]);
            });
        });
        $query->when($request->sort_by === 'price_asc', fn ($q) => $q->orderBy('original_price'));
        $query->when($request->sort_by === 'price_desc', fn ($q) => $q->orderByDesc('original_price'));
        $query->when($request->sort_by === 'name', fn ($q) => $q->orderBy('name'));
        if (!$section) {
            $query->when(!$request->sort_by || $request->sort_by === 'latest', fn ($q) => $q->latest());
        }

        $ratingSubquery = DB::table('product_reviews')
            ->selectRaw('COALESCE(AVG(product_reviews.rating), 0)')
            ->whereColumn('product_reviews.product_id', 'products.id');
        $reviewCountSubquery = DB::table('product_reviews')
            ->selectRaw('COUNT(*)')
            ->whereColumn('product_reviews.product_id', 'products.id');
        $query->addSelect([
            'average_rating' => $ratingSubquery,
            'reviews_count' => $reviewCountSubquery,
        ]);

        if ($section === 'offers') {
            $query->whereNotNull('offer_price')
                ->whereColumn('offer_price', '<', 'original_price')
                ->where(function ($q) {
                    $q->whereNull('offer_expires_at')->orWhere('offer_expires_at', '>', now()->addHours(48));
                })
                ->where('quantity', '>', 0)
                ->orderByDesc('offer_expires_at')->latest('updated_at');
        } elseif ($section === 'flash_sale') {
            $query->whereNotNull('offer_price')
                ->whereColumn('offer_price', '<', 'original_price')
                ->whereNotNull('offer_expires_at')
                ->whereBetween('offer_expires_at', [now(), now()->addHours(48)])
                ->where('quantity', '>', 0)
                ->orderBy('offer_expires_at');
        } elseif ($section === 'new_arrivals') {
            $query->where('quantity', '>', 0)->latest('created_at');
        } elseif ($section === 'trending') {
            $recentSales = DB::table('order_product as recent_order_items')
                ->join('orders as recent_orders', 'recent_orders.id', '=', 'recent_order_items.order_id')
                ->selectRaw('COALESCE(SUM(recent_order_items.quantity), 0)')
                ->whereColumn('recent_order_items.product_id', 'products.id')
                ->where('recent_orders.created_at', '>=', now()->subDays(30))
                ->whereNotIn('recent_orders.status', ['failed_payment', 'cancelled_returned']);
            $query->addSelect(['recent_sales_count' => $recentSales])
                ->where('quantity', '>', 0)
                ->orderByDesc('recent_sales_count')->orderByDesc('sales_count')->latest('updated_at');
        } elseif ($section === 'recommended') {
            $preferredCategoryIds = [];
            if (auth()->check()) {
                $userId = auth()->id();
                $preferredCategoryIds = DB::table('favorites')
                    ->join('products', 'products.id', '=', 'favorites.product_id')
                    ->where('favorites.user_id', $userId)->whereNotNull('products.category_id')
                    ->pluck('products.category_id')->all();
                $preferredCategoryIds = array_merge($preferredCategoryIds, DB::table('order_product')
                    ->join('orders', 'orders.id', '=', 'order_product.order_id')
                    ->join('products', 'products.id', '=', 'order_product.product_id')
                    ->where('orders.user_id', $userId)->whereNotNull('products.category_id')
                    ->pluck('products.category_id')->all());
                $preferredCategoryIds = array_values(array_unique(array_map('intval', $preferredCategoryIds)));
            }
            $query->where('quantity', '>', 0);
            if ($preferredCategoryIds !== []) {
                $query->whereIn('category_id', $preferredCategoryIds)->orderByDesc('average_rating')->orderByDesc('created_at');
            } else {
                $query->orderByDesc('average_rating')->latest('created_at');
            }
        } elseif ($section === 'featured') {
            $query->where('quantity', '>', 0)
                ->orderByDesc('average_rating')->orderByDesc('sales_count')->latest('created_at');
        }

        $page = $query->paginate($request->integer('per_page', 15));
        $favoriteIds = auth()->check()
            ? DB::table('favorites')->where('user_id', auth()->id())->pluck('product_id')->flip()->all()
            : null;
        $page->getCollection()->transform(fn (Product $product) => $this->productData($product, false, $favoriteIds));
        $payload = ['success' => true, 'data' => $page];
        if ($section === 'flash_sale') {
            $payload['meta'] = ['flash_sale_ends_at' => $page->getCollection()->min('offer_expires_at')];
        }
        return response()->json($payload);
    }

    public function product(string $id)
    {
        $product = Product::with(['variants', 'seller', 'category'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $this->productData($product, true)]);
    }

    public function stores(Request $request)
    {
        $section = $request->route('section') ?? $request->input('section');
        $query = User::query()->whereIn('role', ['vendor', 'wholesale']);
        $query->when($request->q, fn ($q, $value) => $q->where('store_name', 'like', "%{$value}%"));
        $query->when($request->has_products, fn ($q) => $q->has('products'));
        $query->when($request->min_rating, function ($q, $rating) {
            $q->whereIn('users.id', function ($subQuery) use ($rating) {
                $subQuery->from('store_reviews')
                    ->select('store_id')
                    ->groupBy('store_id')
                    ->havingRaw('AVG(rating) >= ?', [$rating]);
            });
        });
        $ratingSubquery = DB::table('store_reviews')
            ->selectRaw('COALESCE(AVG(store_reviews.rating), 0)')
            ->whereColumn('store_reviews.store_id', 'users.id');
        $query->addSelect(['average_rating' => $ratingSubquery])->withCount('products');
        if ($section === 'featured') {
            $featuredIds = Ad::active()->where('type', 'featured_store')->pluck('seller_id')->unique()->values()->all();
            if ($featuredIds !== []) {
                $query->whereIn('users.id', $featuredIds)
                    ->orderByRaw('FIELD(users.id, ' . implode(',', array_map('intval', $featuredIds)) . ')');
            } else {
                $query->orderByDesc('average_rating')->orderByDesc('products_count')->latest('created_at');
            }
        }
        $stores = $query->paginate($request->integer('per_page', 20));
        return response()->json(['success' => true, 'data' => $stores]);
    }

    public function store(string $id)
    {
        $store = User::whereIn('role', ['vendor', 'wholesale'])->withCount('products')->findOrFail($id);
        $reviews = DB::table('store_reviews')->where('store_id', $store->id);
        $store->rating = round((float) $reviews->avg('rating'), 1);
        $store->reviews_count = $reviews->count();
        $store->is_following = auth()->check() && DB::table('store_follows')
            ->where(['user_id' => auth()->id(), 'store_id' => $store->id])->exists();
        $store->products_count = $store->products_count;
        return response()->json(['success' => true, 'data' => $store]);
    }

    public function storeProducts(Request $request, string $id)
    {
        $request->merge(['store_id' => $id]);
        return $this->products($request);
    }

    public function storeDepartments(string $id)
    {
        return response()->json(['success' => true, 'data' => \App\Models\Department::where('seller_id', $id)
            ->where('is_visible', true)->with('recursiveChildren')->orderBy('order_position')->get()]);
    }

    public function cart()
    {
        $quantityColumn = Schema::hasColumn('cart_items', 'quantity') ? 'quantity' : 'qty';
        $items = DB::table('cart_items')->join('products', 'products.id', '=', 'cart_items.product_id')
            ->join('users', 'users.id', '=', 'products.user_id')
            ->where('cart_items.user_id', auth()->id())
            ->select('cart_items.*', 'products.name', 'products.images', 'products.original_price',
                'products.offer_price',
                'products.quantity as max_stock', 'products.user_id as seller_id',
                'users.store_name', 'users.store_logo')->get();
        $groups = $items->groupBy('seller_id')->map(function ($rows) use ($quantityColumn) {
            $first = $rows->first();
            $mapped = $rows->map(fn ($row) => [
                'id' => $row->id,
                'product_id' => $row->product_id,
                'variant_id' => $row->variant_id,
                'name' => $row->name,
                'image' => is_array($row->images) ? ($row->images[0] ?? '') : '',
                'price' => $row->offer_price ?? $row->original_price,
                'original_price' => $row->original_price,
                'quantity' => (int) ($row->{$quantityColumn} ?? 0),
                'max_stock' => $row->max_stock,
                'is_out_of_stock' => $row->max_stock < (int) ($row->{$quantityColumn} ?? 0),
            ])->values();
            return ['seller_id' => $first->seller_id, 'store_name' => $first->store_name,
                'store_logo' => $first->store_logo, 'items' => $mapped,
                'items_count' => $mapped->sum('quantity'), 'subtotal' => $mapped->sum(fn ($i) => $i['price'] * $i['quantity']),
                'shipping_options' => []];
        })->values();
        return response()->json(['success' => true, 'data' => ['stores' => $groups]]);
    }

    public function addCart(Request $request)
    {
        $quantityColumn = Schema::hasColumn('cart_items', 'quantity') ? 'quantity' : 'qty';
        $hasPriceColumn = Schema::hasColumn('cart_items', 'price');
        $hasSellerColumn = Schema::hasColumn('cart_items', 'seller_id');
        $data = $request->validate(['product_id' => 'required|exists:products,id', 'qty' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id']);
        $product = Product::findOrFail($data['product_id']);
        $price = $product->offer_price ?? $product->original_price;
        $item = DB::table('cart_items')->where(['user_id' => auth()->id(), 'product_id' => $product->id,
            'variant_id' => $data['variant_id'] ?? null])->first();
        if ($item) {
            $currentQuantity = (int) ($item->{$quantityColumn} ?? 0);
            DB::table('cart_items')->where('id', $item->id)->update([$quantityColumn => $currentQuantity + $data['qty'], 'updated_at' => now()]);
        } else {
            $insert = ['user_id' => auth()->id(), 'product_id' => $product->id,
                'variant_id' => $data['variant_id'] ?? null, $quantityColumn => $data['qty'],
                'created_at' => now(), 'updated_at' => now()];
            if ($hasPriceColumn) $insert['price'] = $price;
            if ($hasSellerColumn) $insert['seller_id'] = $product->user_id;
            DB::table('cart_items')->insert($insert);
        }
        return response()->json(['success' => true, 'message' => 'Product added to cart.'], 201);
    }

    public function updateCart(Request $request, string $id)
    {
        $quantityColumn = Schema::hasColumn('cart_items', 'quantity') ? 'quantity' : 'qty';
        $data = $request->validate(['qty' => 'required|integer|min:1']);
        DB::table('cart_items')->where(['id' => $id, 'user_id' => auth()->id()])->update([$quantityColumn => $data['qty'], 'updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function removeCart(string $id)
    {
        DB::table('cart_items')->where(['id' => $id, 'user_id' => auth()->id()])->delete();
        return response()->json(['success' => true]);
    }

    public function clearCart()
    {
        DB::table('cart_items')->where('user_id', auth()->id())->delete();
        return response()->json(['success' => true]);
    }

    public function favorites()
    {
        $items = DB::table('favorites')->join('products', 'products.id', '=', 'favorites.product_id')
            ->where('favorites.user_id', auth()->id())->select('products.*')->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function toggleFavorite(string $id)
    {
        $where = ['user_id' => auth()->id(), 'product_id' => $id];
        $existing = DB::table('favorites')->where($where)->first();
        $isFavorite = !$existing;
        $existing ? DB::table('favorites')->where($where)->delete() : DB::table('favorites')->insert($where + ['created_at' => now(), 'updated_at' => now()]);
        return response()->json(['success' => true, 'is_favorite' => $isFavorite]);
    }

    public function storeReviews(string $id)
    {
        $reviews = DB::table('store_reviews')->join('users', 'users.id', '=', 'store_reviews.user_id')
            ->where('store_reviews.store_id', $id)
            ->select('store_reviews.*', DB::raw("concat(users.first_name, ' ', users.last_name) as buyer_name"))
            ->latest('store_reviews.created_at')->get();
        return response()->json(['success' => true, 'data' => $reviews]);
    }

    public function addStoreReview(Request $request, string $id)
    {
        User::whereIn('role', ['vendor', 'wholesale'])->findOrFail($id);
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:2000']);
        DB::table('store_reviews')->updateOrInsert(
            ['user_id' => auth()->id(), 'store_id' => $id],
            $data + ['updated_at' => now(), 'created_at' => now()]
        );
        return response()->json(['success' => true]);
    }

    public function toggleFollow(string $id)
    {
        User::whereIn('role', ['vendor', 'wholesale'])->findOrFail($id);
        $where = ['user_id' => auth()->id(), 'store_id' => $id];
        $exists = DB::table('store_follows')->where($where)->exists();
        $exists ? DB::table('store_follows')->where($where)->delete() : DB::table('store_follows')->insert($where + ['created_at' => now(), 'updated_at' => now()]);
        return response()->json(['success' => true, 'is_following' => !$exists]);
    }

    public function profile()
    {
        $user = auth()->user();
        return response()->json(['success' => true, 'data' => [
            'id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'email' => $user->email, 'phone' => $user->phone, 'profile_photo' => $user->profile_photo,
            'reviews_count' => DB::table('product_reviews')->where('user_id', $user->id)->count(),
        ]]);
    }

    public function addresses()
    {
        return response()->json([
            'success' => true,
            'data' => DB::table('buyer_addresses')
                ->where('user_id', auth()->id())
                ->orderByDesc('is_default')->latest()->get(),
        ]);
    }

    public function addAddress(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'details' => 'required|string|max:2000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'driver_notes' => 'nullable|string|max:1000',
            'is_default' => 'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($data) {
            $isDefault = (bool) ($data['is_default'] ?? false);
            if ($isDefault || !DB::table('buyer_addresses')->where('user_id', auth()->id())->exists()) {
                DB::table('buyer_addresses')->where('user_id', auth()->id())->update(['is_default' => false]);
                $isDefault = true;
            }
            $data['user_id'] = auth()->id();
            $data['is_default'] = $isDefault;
            $id = DB::table('buyer_addresses')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
            return response()->json(['success' => true, 'data' => DB::table('buyer_addresses')->find($id)], 201);
        });
    }

    public function updateAddress(Request $request, string $id)
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:100', 'details' => 'sometimes|string|max:2000',
            'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180',
            'driver_notes' => 'nullable|string|max:1000', 'is_default' => 'sometimes|boolean',
        ]);
        $address = DB::table('buyer_addresses')->where(['id' => $id, 'user_id' => auth()->id()])->first();
        abort_if(!$address, 404);
        if (($data['is_default'] ?? false) === true) {
            DB::table('buyer_addresses')->where('user_id', auth()->id())->update(['is_default' => false]);
        }
        DB::table('buyer_addresses')->where('id', $id)->update($data + ['updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function deleteAddress(string $id)
    {
        $deleted = DB::table('buyer_addresses')->where(['id' => $id, 'user_id' => auth()->id()])->delete();
        abort_if(!$deleted, 404);
        return response()->json(['success' => true]);
    }

    public function setDefaultAddress(string $id)
    {
        $exists = DB::table('buyer_addresses')->where(['id' => $id, 'user_id' => auth()->id()])->exists();
        abort_if(!$exists, 404);
        DB::transaction(function () use ($id) {
            DB::table('buyer_addresses')->where('user_id', auth()->id())->update(['is_default' => false]);
            DB::table('buyer_addresses')->where(['id' => $id, 'user_id' => auth()->id()])->update(['is_default' => true, 'updated_at' => now()]);
        });
        return response()->json(['success' => true]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100', 'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|max:255', 'phone' => 'nullable|string|max:30',
        ]);
        auth()->user()->update($data);
        return $this->profile();
    }

    public function productReviews(string $id)
    {
        $reviews = DB::table('product_reviews')->join('users', 'users.id', '=', 'product_reviews.user_id')
            ->where('product_reviews.product_id', $id)
            ->select('product_reviews.*', DB::raw("concat(users.first_name, ' ', users.last_name) as user_name"))
            ->latest('product_reviews.created_at')->get();
        return response()->json(['success' => true, 'data' => $reviews]);
    }

    public function addProductReview(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'order_id' => 'nullable|exists:orders,id',
        ]);
        $purchased = false;
        if (DB::getSchemaBuilder()->hasTable('order_product')) {
            $purchased = DB::table('order_product')->join('orders', 'orders.id', '=', 'order_product.order_id')
                ->where('orders.user_id', auth()->id())->where('order_product.product_id', $data['product_id'])
                ->whereIn('orders.status', ['paid', 'processing', 'shipped', 'delivered'])->exists();
        }

        if (!$purchased && DB::getSchemaBuilder()->hasTable('order_items')) {
            $purchased = DB::table('order_items')
                ->join('sub_orders', 'sub_orders.id', '=', 'order_items.sub_order_id')
                ->join('orders', 'orders.id', '=', 'sub_orders.order_id')
                ->where('orders.user_id', auth()->id())
                ->where('order_items.product_id', $data['product_id'])
                ->whereIn('orders.status', ['paid', 'processing', 'shipped', 'delivered'])
                ->exists();
        }
        if (!$purchased) {
            return response()->json(['success' => false, 'message' => 'Product must be purchased before reviewing.'], 422);
        }
        DB::table('product_reviews')->updateOrInsert(
            ['user_id' => auth()->id(), 'product_id' => $data['product_id'], 'order_id' => $data['order_id'] ?? null],
            ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null, 'updated_at' => now(), 'created_at' => now()]
        );
        return response()->json(['success' => true]);
    }

    public function myReviews()
    {
        return response()->json(['success' => true, 'data' => DB::table('product_reviews')
            ->where('user_id', auth()->id())->latest()->get()]);
    }

    public function updateReview(Request $request, string $id)
    {
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:2000']);
        $updated = DB::table('product_reviews')->where(['id' => $id, 'user_id' => auth()->id()])
            ->update($data + ['updated_at' => now()]);
        abort_if(!$updated, 404);
        return response()->json(['success' => true]);
    }

    private function productData(Product $product, bool $includeReviews = false, ?array $favoriteIds = null): array
    {
        $data = $product->toArray();
        $originalPrice = (float) $product->original_price;
        $salePrice = $product->offer_price === null ? null : (float) $product->offer_price;
        $images = is_array($product->images) ? $product->images : [];
        $averageRating = array_key_exists('average_rating', $data)
            ? (float) $data['average_rating']
            : (float) DB::table('product_reviews')->where('product_id', $product->id)->avg('rating');
        $reviewsCount = array_key_exists('reviews_count', $data)
            ? (int) $data['reviews_count']
            : DB::table('product_reviews')->where('product_id', $product->id)->count();
        $reviews = $includeReviews
            ? DB::table('product_reviews')->join('users', 'users.id', '=', 'product_reviews.user_id')
                ->where('product_reviews.product_id', $product->id)
                ->select('product_reviews.*', DB::raw("concat(users.first_name, ' ', users.last_name) as user_name"))
                ->latest('product_reviews.created_at')->get()
            : [];
        return array_merge($data, [
            'price' => $salePrice ?? $originalPrice,
            'old_price' => $salePrice !== null && $salePrice < $originalPrice ? $originalPrice : null,
            'sale_price' => $salePrice,
            'image' => $images[0] ?? null,
            'image_url' => $images[0] ?? null,
            'category_name' => $product->category?->name ?? '',
            'free_shipping' => (bool) $product->is_free_shipping,
            'has_wholesale' => $product->wholesale_price !== null,
            'store_id' => $product->user_id, 'seller_id' => $product->user_id,
            'store_name' => $product->seller?->store_name ?? '',
            'store_logo' => $product->seller?->store_logo ?? '',
            'is_favorite' => $favoriteIds !== null
                ? isset($favoriteIds[$product->id])
                : (auth()->check() && DB::table('favorites')->where(['user_id' => auth()->id(), 'product_id' => $product->id])->exists()),
            'rating' => round($averageRating, 1),
            'reviews_count' => $reviewsCount,
            'reviews' => $reviews,
        ]);
    }
}
