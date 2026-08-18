<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use App\Jobs\SendAdNotification;
use Illuminate\Support\Facades\Storage; // 🔥 أضف هذا السطر
use Carbon\Carbon;  // 🔥🔥🔥 أضف هذا السطر هنا

class AdminController extends Controller
{
    // ============================================================
    // 📌 إدارة المستخدمين
    // ============================================================

    public function approve($id)
    {
        $user = User::findOrFail($id);
        $user->status = 'approved';
        $user->save();
        return response()->json(['message' => 'User approved']);
    }

    public function reject($id)
    {
        $user = User::findOrFail($id);
        $user->status = 'rejected';
        $user->save();
        return response()->json(['message' => 'User rejected']);
    }

    public function block($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'blocked']);

        return response()->json([
            'message' => 'User has been blocked successfully.'
        ]);
    }

    public function unblock($id)
    {
        $user = User::findOrFail($id);

        if ($user->status !== 'blocked') {
            return response()->json(['message' => 'User is not blocked.'], 400);
        }

        $user->update(['status' => 'approved']);

        return response()->json([
            'message' => 'User has been unblocked and set to approved.'
        ]);
    }

    public function allUsers()
    {
        return response()->json(User::all());
    }

    public function pendingUsers()
    {
        $users = User::where('status', 'pending')->get();
        return response()->json($users);
    }

    public function approvedUsers()
    {
        $users = User::where('status', 'approved')->get();
        return response()->json($users);
    }

    public function rejectedUsers()
    {
        $users = User::where('status', 'rejected')->get();
        return response()->json($users);
    }

    public function blockedUsers()
    {
        $users = User::where('status', 'blocked')->latest()->get();
        return response()->json($users);
    }

    // ============================================================
    // 📌 شحن الرصيد بواسطة الأدمن
    // ============================================================

    public function depositByAdmin(Request $request)
    {
        $admin = auth()->user();

        $request->validate([
            'user_id' => 'required|exists:users,id,role,buyer',
            'amount' => 'required|numeric|min:10'
        ]);

        $buyer = User::findOrFail($request->user_id);

        if ($buyer->role !== 'buyer') {
            return response()->json(['message' => 'Funds can only be added to buyer accounts.'], 400);
        }

        $buyer->balance += $request->amount;
        $buyer->save();

        Transaction::create([
            'user_id' => $buyer->id,
            'type' => 'deposit',
            'amount' => $request->amount,
            'description' => 'Wallet topped up by Admin: ' . $admin->first_name . ' ' . $admin->last_name
        ]);

        return response()->json([
            'message' => 'Balance topped up successfully for ' . $buyer->first_name,
            'new_balance' => $buyer->balance
        ]);
    }

    // ============================================================
    // 📌 إدارة الإعلانات (بدون admin_id)
    // ============================================================

    /**
     * الموافقة على إعلان
     */
    public function approveAd($id)
    {
        $ad = Ad::where('status', 'pending')->findOrFail($id);

        $ad->update([
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => $this->calculateExpiryDate($ad->duration),
            'admin_id' => auth()->id()
        ]);

        // 🔥 إطلاق جوب إرسال الإشعار إذا كان النوع إشعاراً مدفوعاً
        if ($ad->type === 'paid_notification') {
            SendAdNotification::dispatch($ad);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ad approved successfully.',
            'data' => $ad
        ]);
    }

    /**
     * رفض إعلان مع سبب
     */
    public function rejectAd(Request $request, $id)
    {
        $request->validate([
            'admin_notes' => 'required|string|max:500'
        ]);

        $ad = Ad::where('status', 'pending')->findOrFail($id);

        $ad->update([
            'status' => 'rejected',
            'admin_notes' => $request->admin_notes,
            'admin_id' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ad rejected.',
            'data' => $ad
        ]);
    }

    /**
     * إيقاف إعلان نشط
     */
    public function deactivateAd($id)
    {
        $ad = Ad::where('status', 'active')->findOrFail($id);

        $ad->update([
            'status' => 'expired',
            'admin_notes' => 'Deactivated by admin.'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ad deactivated successfully.'
        ]);
    }

    /**
     * عرض إحصائيات عامة للإعلانات
     */
    public function statsAd()
    {
        return response()->json([
            'success' => true,
            'stats' => [
                'total_ads' => Ad::count(),
                'pending_ads' => Ad::where('status', 'pending')->count(),
                'active_ads' => Ad::where('status', 'active')->count(),
                'expired_ads' => Ad::where('status', 'expired')->count(),
                'rejected_ads' => Ad::where('status', 'rejected')->count(),
                'total_revenue' => Ad::sum('price'),
                'total_views' => Ad::sum('views_count'),
                'total_clicks' => Ad::sum('clicks_count'),
            ],
            'by_type' => [
                'banner' => Ad::where('type', 'banner')->count(),
                'promoted_product' => Ad::where('type', 'promoted_product')->count(),
                'featured_store' => Ad::where('type', 'featured_store')->count(),
                'paid_notification' => Ad::where('type', 'paid_notification')->count(),
            ]
        ]);
    }

    // ============================================================
    // 📌 دوال مساعدة
    // ============================================================

    private function calculateExpiryDate($duration)
    {
        $map = [
            '1_day' => now()->addDay(),
            '3_days' => now()->addDays(3),
            '1_week' => now()->addWeek(),
            '1_month' => now()->addMonth(),
        ];

        return $map[$duration] ?? now()->addDay();
    }

    private function getViewsByDate($ad)
    {
        return $ad->views()
            ->where('type', 'view')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get();
    }

    private function getClicksByDate($ad)
    {
        return $ad->views()
            ->where('type', 'click')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get();
    }







    // ============================================================
// 📌 عرض جميع المنتجات مع بيانات البائع والتصنيف
// ============================================================

    public function allProducts(Request $request)
    {
        // جلب المنتجات مع علاقاتها
        $products = Product::with([
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone', 'role', 'status');
            },
            'category' => function ($query) {
                $query->select('id', 'name');
            },
            'department' => function ($query) {
                $query->select('id', 'name');
            }
        ])
            ->select(
                'id',
                'user_id',
                'category_id',
                'department_id',
                'name',
                'description',
                'original_price',
                'offer_price',
                'wholesale_price',
                'sku',
                'quantity',
                'status',
                'sales_count',
                'created_at',
                'images'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        // تنسيق البيانات للعرض
        $formattedProducts = $products->through(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => [
                    'original' => $product->original_price,
                    'offer' => $product->offer_price ?? null,
                    'wholesale' => $product->wholesale_price ?? null,
                ],
                'sku' => $product->sku,
                'quantity' => $product->quantity,
                'status' => $product->status,
                'sales_count' => $product->sales_count,
                'images' => $product->images ?? [],
                'seller' => $product->seller ? [
                    'id' => $product->seller->id,
                    'name' => $product->seller->first_name . ' ' . $product->seller->last_name,
                    'store_name' => $product->seller->store_name,
                    'email' => $product->seller->email,
                    'phone' => $product->seller->phone,
                    'role' => $product->seller->role,
                    'role_label' => $this->getRoleLabel($product->seller->role),
                    'status' => $product->seller->status,
                ] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'department' => $product->department ? [
                    'id' => $product->department->id,
                    'name' => $product->department->name,
                ] : null,
                'created_at' => $product->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedProducts,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    // ============================================================
// 📌 عرض منتجات البائعين العاديين (Vendor)
// ============================================================

    public function vendorProducts(Request $request)
    {
        // جلب جميع البائعين العاديين
        $vendorIds = User::where('role', 'vendor')->pluck('id');

        // جلب المنتجات مع علاقاتها
        $products = Product::with([
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone', 'role', 'status');
            },
            'category' => function ($query) {
                $query->select('id', 'name');
            },
            'department' => function ($query) {
                $query->select('id', 'name');
            }
        ])
            ->whereIn('user_id', $vendorIds)
            ->select(
                'id',
                'user_id',
                'category_id',
                'department_id',
                'name',
                'description',
                'original_price',
                'offer_price',
                'wholesale_price',
                'sku',
                'quantity',
                'status',
                'sales_count',
                'created_at',
                'images'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        // تنسيق البيانات للعرض
        $formattedProducts = $products->through(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => [
                    'original' => $product->original_price,
                    'offer' => $product->offer_price ?? null,
                    'wholesale' => $product->wholesale_price ?? null,
                ],
                'sku' => $product->sku,
                'quantity' => $product->quantity,
                'status' => $product->status,
                'sales_count' => $product->sales_count,
                'images' => $product->images ?? [],
                'seller' => $product->seller ? [
                    'id' => $product->seller->id,
                    'name' => $product->seller->first_name . ' ' . $product->seller->last_name,
                    'store_name' => $product->seller->store_name,
                    'email' => $product->seller->email,
                    'phone' => $product->seller->phone,
                    'role' => $product->seller->role,
                    'role_label' => $this->getRoleLabel($product->seller->role),
                    'status' => $product->seller->status,
                ] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'department' => $product->department ? [
                    'id' => $product->department->id,
                    'name' => $product->department->name,
                ] : null,
                'created_at' => $product->created_at?->toDateTimeString(),
            ];
        });

        // حساب إحصائيات
        $totalProducts = Product::whereIn('user_id', $vendorIds)->count();
        $activeProducts = Product::whereIn('user_id', $vendorIds)->where('status', 'active')->count();
        $draftProducts = Product::whereIn('user_id', $vendorIds)->where('status', 'draft')->count();
        $hiddenProducts = Product::whereIn('user_id', $vendorIds)->where('status', 'hidden')->count();
        $totalSellers = User::where('role', 'vendor')->count();
        $totalSales = Product::whereIn('user_id', $vendorIds)->sum('sales_count');
        $totalValue = Product::whereIn('user_id', $vendorIds)->sum(\DB::raw('original_price * quantity'));

        return response()->json([
            'success' => true,
            'type' => 'vendor',
            'type_label' => 'بائع عادي',
            'summary' => [
                'total_sellers' => $totalSellers,
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'draft_products' => $draftProducts,
                'hidden_products' => $hiddenProducts,
                'total_sales' => $totalSales,
                'total_stock_value' => number_format($totalValue, 2),
            ],
            'data' => $formattedProducts,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    // 📌 عرض منتجات تجار الجملة (Wholesale)
// ============================================================
    public function wholesaleProducts(Request $request)
    {
        // جلب جميع تجار الجملة
        $wholesaleIds = User::where('role', 'wholesale')->pluck('id');

        // جلب المنتجات مع علاقاتها
        $products = Product::with([
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone', 'role', 'status');
            },
            'category' => function ($query) {
                $query->select('id', 'name');
            },
            'department' => function ($query) {
                $query->select('id', 'name');
            }
        ])
            ->whereIn('user_id', $wholesaleIds)
            ->select(
                'id',
                'user_id',
                'category_id',
                'department_id',
                'name',
                'description',
                'original_price',
                'offer_price',
                'wholesale_price',
                'sku',
                'quantity',
                'status',
                'sales_count',
                'created_at',
                'images'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        // تنسيق البيانات للعرض
        $formattedProducts = $products->through(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => [
                    'original' => $product->original_price,
                    'offer' => $product->offer_price ?? null,
                    'wholesale' => $product->wholesale_price ?? null,
                ],
                'sku' => $product->sku,
                'quantity' => $product->quantity,
                'status' => $product->status,
                'sales_count' => $product->sales_count,
                'images' => $product->images ?? [],
                'seller' => $product->seller ? [
                    'id' => $product->seller->id,
                    'name' => $product->seller->first_name . ' ' . $product->seller->last_name,
                    'store_name' => $product->seller->store_name,
                    'email' => $product->seller->email,
                    'phone' => $product->seller->phone,
                    'role' => $product->seller->role,
                    'role_label' => $this->getRoleLabel($product->seller->role),
                    'status' => $product->seller->status,
                ] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'department' => $product->department ? [
                    'id' => $product->department->id,
                    'name' => $product->department->name,
                ] : null,
                'created_at' => $product->created_at?->toDateTimeString(),
            ];
        });

        // حساب إحصائيات
        $totalProducts = Product::whereIn('user_id', $wholesaleIds)->count();
        $activeProducts = Product::whereIn('user_id', $wholesaleIds)->where('status', 'active')->count();
        $draftProducts = Product::whereIn('user_id', $wholesaleIds)->where('status', 'draft')->count();
        $hiddenProducts = Product::whereIn('user_id', $wholesaleIds)->where('status', 'hidden')->count();
        $totalSellers = User::where('role', 'wholesale')->count();
        $totalSales = Product::whereIn('user_id', $wholesaleIds)->sum('sales_count');
        $totalValue = Product::whereIn('user_id', $wholesaleIds)->sum(\DB::raw('original_price * quantity'));

        return response()->json([
            'success' => true,
            'type' => 'wholesale',
            'type_label' => 'تاجر جملة',
            'summary' => [
                'total_sellers' => $totalSellers,
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'draft_products' => $draftProducts,
                'hidden_products' => $hiddenProducts,
                'total_sales' => $totalSales,
                'total_stock_value' => number_format($totalValue, 2),
            ],
            'data' => $formattedProducts,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    // 📌 عرض تفاصيل منتج محدد (للأدمن)
// ============================================================
    public function showProduct($id)
    {
        $product = Product::with([
            'seller' => function ($query) {
                $query->select(
                    'id',
                    'first_name',
                    'last_name',
                    'store_name',
                    'email',
                    'phone',
                    'role',
                    'status',
                    'profile_photo',
                    'store_logo',
                    'store_description'
                );
            },
            'category' => function ($query) {
                $query->select('id', 'name', 'image_url', 'icon_url');
            },
            'department' => function ($query) {
                $query->select('id', 'name', 'image_url');
            },
            'variants' => function ($query) {
                $query->select(
                    'id',
                    'product_id',
                    'attributes',
                    'price',
                    'quantity',
                    'sku',
                    'image_url',
                    'is_active'
                );
            }
        ])->findOrFail($id);

        // تنسيق بيانات المنتج
        $formattedProduct = [
            // 1. معلومات المنتج الأساسية
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'slug' => $product->slug ?? null,
                'sku' => $product->sku,
                'status' => $product->status,
                'sales_count' => $product->sales_count,
                'created_at' => $product->created_at?->toDateTimeString(),
                'updated_at' => $product->updated_at?->toDateTimeString(),
            ],

            // 2. الأسعار
            'pricing' => [
                'original_price' => $product->original_price,
                'offer_price' => $product->offer_price ?? null,
                'wholesale_price' => $product->wholesale_price ?? null,
                'min_wholesale_qty' => $product->min_wholesale_qty ?? null,
                'is_free_shipping' => $product->is_free_shipping ?? false,
                'offer_expires_at' => $product->offer_expires_at?->toDateTimeString(),
            ],

            // 3. المخزون
            'inventory' => [
                'quantity' => $product->quantity,
                'alert_threshold' => $product->alert_threshold ?? 5,
                'stock_status' => $this->getStockStatus($product),
                'warehouse_stock' => $product->warehouse_stock ?? [],
            ],

            // 4. الصور والفيديو
            'media' => [
                'images' => $product->images ?? [],
                'video_url' => $product->video_url ?? null,
            ],

            // 5. الأبعاد والوزن
            'dimensions' => [
                'weight' => $product->weight ?? null,
                'length' => $product->length ?? null,
                'width' => $product->width ?? null,
                'height' => $product->height ?? null,
            ],

            // 6. التصنيف والقسم
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'image_url' => $product->category->image_url,
                'icon_url' => $product->category->icon_url,
            ] : null,

            'department' => $product->department ? [
                'id' => $product->department->id,
                'name' => $product->department->name,
                'image_url' => $product->department->image_url,
            ] : null,

            // 7. معلومات البائع
            'seller' => $product->seller ? [
                'id' => $product->seller->id,
                'first_name' => $product->seller->first_name,
                'last_name' => $product->seller->last_name,
                'full_name' => $product->seller->first_name . ' ' . $product->seller->last_name,
                'store_name' => $product->seller->store_name,
                'store_description' => $product->seller->store_description,
                'email' => $product->seller->email,
                'phone' => $product->seller->phone,
                'role' => $product->seller->role,
                'role_label' => $this->getRoleLabel($product->seller->role),
                'status' => $product->seller->status,
                'profile_photo' => $product->seller->profile_photo ? asset('storage/' . $product->seller->profile_photo) : null,
                'store_logo' => $product->seller->store_logo ? asset('storage/' . $product->seller->store_logo) : null,
            ] : null,

            // 8. المتغيرات (Variants)
            'variants' => $product->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'attributes' => $variant->attributes,
                    'price' => $variant->price,
                    'quantity' => $variant->quantity,
                    'sku' => $variant->sku,
                    'image_url' => $variant->image_url ? asset('storage/' . $variant->image_url) : null,
                    'is_active' => (bool) $variant->is_active,
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $formattedProduct,
        ]);
    }


    public function allSellersInventory(Request $request)
    {
        // جلب جميع التجار (البائعين وتجار الجملة)
        $sellers = User::whereIn('role', ['vendor', 'wholesale'])
            ->select(
                'id',
                'first_name',
                'last_name',
                'store_name',
                'email',
                'phone',
                'role',
                'status',
                'profile_photo',
                'store_logo'
            )
            ->withCount('products')
            ->latest()
            ->paginate($request->input('per_page', 20));

        // تنسيق بيانات التجار مع مخزونهم
        $formattedSellers = $sellers->through(function ($seller) {

            // حساب إجماليات المخزون لهذا التاجر
            $totalProducts = Product::where('user_id', $seller->id)->count();
            $totalQuantity = Product::where('user_id', $seller->id)->sum('quantity');
            $totalValue = Product::where('user_id', $seller->id)
                ->sum(\DB::raw('original_price * quantity'));

            // جلب المنتجات الأكثر مبيعاً لهذا التاجر (أعلى 5)
            $topProducts = Product::where('user_id', $seller->id)
                ->select('id', 'name', 'original_price', 'quantity', 'sales_count', 'status')
                ->orderBy('sales_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->original_price,
                        'quantity' => $product->quantity,
                        'sales_count' => $product->sales_count,
                        'status' => $product->status,
                    ];
                });

            // إحصائيات حالة المنتجات
            $activeProducts = Product::where('user_id', $seller->id)->where('status', 'active')->count();
            $draftProducts = Product::where('user_id', $seller->id)->where('status', 'draft')->count();
            $hiddenProducts = Product::where('user_id', $seller->id)->where('status', 'hidden')->count();
            $outOfStock = Product::where('user_id', $seller->id)->where('quantity', 0)->count();
            $lowStock = Product::where('user_id', $seller->id)
                ->whereRaw('quantity <= alert_threshold')
                ->where('quantity', '>', 0)
                ->count();

            return [
                'seller' => [
                    'id' => $seller->id,
                    'name' => $seller->first_name . ' ' . $seller->last_name,
                    'store_name' => $seller->store_name,
                    'email' => $seller->email,
                    'phone' => $seller->phone,
                    'role' => $seller->role,
                    'role_label' => $this->getRoleLabel($seller->role),
                    'status' => $seller->status,
                    'profile_photo' => $seller->profile_photo ? asset('storage/' . $seller->profile_photo) : null,
                    'store_logo' => $seller->store_logo ? asset('storage/' . $seller->store_logo) : null,
                ],
                'inventory_summary' => [
                    'total_products' => $totalProducts,
                    'total_quantity' => $totalQuantity,
                    'total_stock_value' => number_format($totalValue, 2),
                    'active_products' => $activeProducts,
                    'draft_products' => $draftProducts,
                    'hidden_products' => $hiddenProducts,
                    'out_of_stock_products' => $outOfStock,
                    'low_stock_products' => $lowStock,
                ],
                'top_products' => $topProducts,
            ];
        });

        // حساب إجماليات عامة لكل التجار
        $allSellers = User::whereIn('role', ['vendor', 'wholesale'])->pluck('id');
        $totalSellers = $allSellers->count();

        $globalTotalProducts = Product::whereIn('user_id', $allSellers)->count();
        $globalTotalQuantity = Product::whereIn('user_id', $allSellers)->sum('quantity');
        $globalTotalValue = Product::whereIn('user_id', $allSellers)
            ->sum(\DB::raw('original_price * quantity'));

        $globalActive = Product::whereIn('user_id', $allSellers)->where('status', 'active')->count();
        $globalOutOfStock = Product::whereIn('user_id', $allSellers)->where('quantity', 0)->count();

        return response()->json([
            'success' => true,
            'global_summary' => [
                'total_sellers' => $totalSellers,
                'total_products' => $globalTotalProducts,
                'total_quantity' => $globalTotalQuantity,
                'total_stock_value' => number_format($globalTotalValue, 2),
                'active_products' => $globalActive,
                'out_of_stock_products' => $globalOutOfStock,
            ],
            'data' => $formattedSellers,
            'pagination' => [
                'current_page' => $sellers->currentPage(),
                'last_page' => $sellers->lastPage(),
                'per_page' => $sellers->perPage(),
                'total' => $sellers->total(),
            ]
        ]);
    }


    public function deleteProduct($id)
    {
        $product = Product::with('variants')->findOrFail($id);

        // 1. حذف صور المنتج الأساسية
        if (is_array($product->images)) {
            foreach ($product->images as $imagePath) {
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }
        }

        // 2. حذف فيديو المنتج
        if ($product->video_url && Storage::disk('public')->exists($product->video_url)) {
            Storage::disk('public')->delete($product->video_url);
        }

        // 3. حذف صور المتغيرات (Variants)
        foreach ($product->variants as $variant) {
            if ($variant->image_url && Storage::disk('public')->exists($variant->image_url)) {
                Storage::disk('public')->delete($variant->image_url);
            }
        }

        // 4. حذف المنتج (سيحذف المتغيرات تلقائياً بسبب cascade)
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product and all associated media deleted successfully.',
        ]);
    }






    public function allOrders(Request $request)
    {
        $orders = Order::with([
            'buyer' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'email', 'phone');
            },
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone');
            },
            'coupon' => function ($query) {
                $query->select('id', 'code', 'type', 'value');
            }
        ])
            ->select(
                'id',
                'user_id',
                'seller_id',
                'total_price',
                'status',
                'payment_status',
                'payment_method',
                'discount_amount',
                'coupon_id',
                'created_at',
                'shipped_at',
                'delivered_at'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        // تنسيق البيانات للعرض
        $formattedOrders = $orders->through(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'buyer' => $order->buyer ? [
                    'id' => $order->buyer->id,
                    'name' => $order->buyer->first_name . ' ' . $order->buyer->last_name,
                    'email' => $order->buyer->email,
                    'phone' => $order->buyer->phone,
                ] : null,
                'seller' => $order->seller ? [
                    'id' => $order->seller->id,
                    'name' => $order->seller->first_name . ' ' . $order->seller->last_name,
                    'store_name' => $order->seller->store_name,
                    'email' => $order->seller->email,
                    'phone' => $order->seller->phone,
                ] : null,
                'amount' => [
                    'total' => $order->total_price,
                    'discount' => $order->discount_amount ?? 0,
                    'final_total' => $order->total_price - ($order->discount_amount ?? 0),
                ],
                'coupon' => $order->coupon ? [
                    'code' => $order->coupon->code,
                    'type' => $order->coupon->type,
                    'value' => $order->coupon->value,
                ] : null,
                'status' => $order->status,
                'status_label' => $this->getOrderStatusLabel($order->status),
                'payment_status' => $order->payment_status,
                'payment_label' => $this->getPaymentStatusLabel($order->payment_status),
                'payment_method' => $order->payment_method,
                'dates' => [
                    'created_at' => $order->created_at?->toDateTimeString(),
                    'shipped_at' => $order->shipped_at?->toDateTimeString(),
                    'delivered_at' => $order->delivered_at?->toDateTimeString(),
                ],
            ];
        });

        // حساب إحصائيات الطلبات
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $processingOrders = Order::where('status', 'processing')->count();
        $shippedOrders = Order::where('status', 'shipped')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();
        $cancelledOrders = Order::where('status', 'cancelled_returned')->count();
        $totalRevenue = Order::sum('total_price');

        return response()->json([
            'success' => true,
            'summary' => [
                'total_orders' => $totalOrders,
                'pending' => $pendingOrders,
                'processing' => $processingOrders,
                'shipped' => $shippedOrders,
                'delivered' => $deliveredOrders,
                'cancelled' => $cancelledOrders,
                'total_revenue' => number_format($totalRevenue, 2),
            ],
            'data' => $formattedOrders,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    // 📌 عرض جميع طلبات البائعين العاديين (Vendor)
// ============================================================
    /**
     * عرض جميع طلبات البائعين العاديين (Vendors) فقط
     * GET /api/orders/vendors
     */
    public function vendorOrders(Request $request)
    {
        // ✅ جلب طلبات البائعين العاديين فقط (Vendors)
        $orders = Order::with([
            'buyer' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'email', 'phone');
            },
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone');
            },
            'coupon' => function ($query) {
                $query->select('id', 'code', 'type', 'value');
            }
        ])
            ->whereHas('seller', function ($query) {
                $query->where('role', 'vendor');  // ✅ شرط البائع العادي فقط
            })
            ->select(
                'id',
                'user_id',
                'seller_id',
                'total_price',
                'status',
                'payment_status',
                'payment_method',
                'discount_amount',
                'coupon_id',
                'created_at',
                'shipped_at',
                'delivered_at'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        // ✅ تنسيق البيانات للعرض
        $formattedOrders = $orders->through(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'buyer' => $order->buyer ? [
                    'id' => $order->buyer->id,
                    'name' => $order->buyer->first_name . ' ' . $order->buyer->last_name,
                    'email' => $order->buyer->email,
                    'phone' => $order->buyer->phone,
                ] : null,
                'seller' => $order->seller ? [
                    'id' => $order->seller->id,
                    'name' => $order->seller->first_name . ' ' . $order->seller->last_name,
                    'store_name' => $order->seller->store_name,
                    'email' => $order->seller->email,
                    'phone' => $order->seller->phone,
                ] : null,
                'amount' => [
                    'total' => $order->total_price,
                    'discount' => $order->discount_amount ?? 0,
                    'final_total' => $order->total_price - ($order->discount_amount ?? 0),
                ],
                'coupon' => $order->coupon ? [
                    'code' => $order->coupon->code,
                    'type' => $order->coupon->type,
                    'value' => $order->coupon->value,
                ] : null,
                'status' => $order->status,
                'status_label' => $this->getOrderStatusLabel($order->status),
                'payment_status' => $order->payment_status,
                'payment_label' => $this->getPaymentStatusLabel($order->payment_status),
                'payment_method' => $order->payment_method,
                'dates' => [
                    'created_at' => $order->created_at?->toDateTimeString(),
                    'shipped_at' => $order->shipped_at?->toDateTimeString(),
                    'delivered_at' => $order->delivered_at?->toDateTimeString(),
                ],
            ];
        });

        // ✅ حساب إحصائيات طلبات البائعين العاديين فقط
        $totalOrders = Order::whereHas('seller', function ($query) {
            $query->where('role', 'vendor');
        })->count();

        $pendingOrders = Order::whereHas('seller', function ($query) {
            $query->where('role', 'vendor');
        })->where('status', 'pending')->count();

        $processingOrders = Order::whereHas('seller', function ($query) {
            $query->where('role', 'vendor');
        })->where('status', 'processing')->count();

        $shippedOrders = Order::whereHas('seller', function ($query) {
            $query->where('role', 'vendor');
        })->where('status', 'shipped')->count();

        $deliveredOrders = Order::whereHas('seller', function ($query) {
            $query->where('role', 'vendor');
        })->where('status', 'delivered')->count();

        $cancelledOrders = Order::whereHas('seller', function ($query) {
            $query->where('role', 'vendor');
        })->where('status', 'cancelled_returned')->count();

        $totalRevenue = Order::whereHas('seller', function ($query) {
            $query->where('role', 'vendor');
        })->sum('total_price');

        return response()->json([
            'success' => true,
            'type' => 'vendor',
            'type_label' => 'بائع عادي',
            'summary' => [
                'total_orders' => $totalOrders,
                'pending' => $pendingOrders,
                'processing' => $processingOrders,
                'shipped' => $shippedOrders,
                'delivered' => $deliveredOrders,
                'cancelled' => $cancelledOrders,
                'total_revenue' => number_format($totalRevenue, 2),
            ],
            'data' => $formattedOrders,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }
    // 📌 عرض جميع طلبات تجار الجملة (Wholesale)
// ============================================================
    public function wholesaleOrders(Request $request)
    {
        // جلب جميع تجار الجملة
        $wholesaleIds = User::where('role', 'wholesale')->pluck('id');

        $orders = Order::with([
            'buyer' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'email', 'phone');
            },
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone');
            }
        ])
            ->whereIn('seller_id', $wholesaleIds)
            ->select(
                'id',
                'user_id',
                'seller_id',
                'total_price',
                'status',
                'payment_status',
                'payment_method',
                'discount_amount',
                'coupon_id',
                'created_at',
                'shipped_at',
                'delivered_at'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        // تنسيق البيانات للعرض
        $formattedOrders = $orders->through(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'buyer' => $order->buyer ? [
                    'id' => $order->buyer->id,
                    'name' => $order->buyer->first_name . ' ' . $order->buyer->last_name,
                    'email' => $order->buyer->email,
                    'phone' => $order->buyer->phone,
                ] : null,
                'seller' => $order->seller ? [
                    'id' => $order->seller->id,
                    'name' => $order->seller->first_name . ' ' . $order->seller->last_name,
                    'store_name' => $order->seller->store_name,
                    'email' => $order->seller->email,
                    'phone' => $order->seller->phone,
                ] : null,
                'amount' => [
                    'total' => $order->total_price,
                    'discount' => $order->discount_amount ?? 0,
                    'final_total' => $order->total_price - ($order->discount_amount ?? 0),
                ],
                'status' => $order->status,
                'status_label' => $this->getOrderStatusLabel($order->status),
                'payment_status' => $order->payment_status,
                'payment_label' => $this->getPaymentStatusLabel($order->payment_status),
                'payment_method' => $order->payment_method,
                'dates' => [
                    'created_at' => $order->created_at?->toDateTimeString(),
                    'shipped_at' => $order->shipped_at?->toDateTimeString(),
                    'delivered_at' => $order->delivered_at?->toDateTimeString(),
                ],
            ];
        });

        // حساب إحصائيات طلبات تجار الجملة
        $totalOrders = Order::whereIn('seller_id', $wholesaleIds)->count();
        $pendingOrders = Order::whereIn('seller_id', $wholesaleIds)->where('status', 'pending')->count();
        $processingOrders = Order::whereIn('seller_id', $wholesaleIds)->where('status', 'processing')->count();
        $shippedOrders = Order::whereIn('seller_id', $wholesaleIds)->where('status', 'shipped')->count();
        $deliveredOrders = Order::whereIn('seller_id', $wholesaleIds)->where('status', 'delivered')->count();
        $cancelledOrders = Order::whereIn('seller_id', $wholesaleIds)->where('status', 'cancelled_returned')->count();
        $totalRevenue = Order::whereIn('seller_id', $wholesaleIds)->sum('total_price');

        return response()->json([
            'success' => true,
            'type' => 'wholesale',
            'type_label' => 'تاجر جملة',
            'summary' => [
                'total_orders' => $totalOrders,
                'pending' => $pendingOrders,
                'processing' => $processingOrders,
                'shipped' => $shippedOrders,
                'delivered' => $deliveredOrders,
                'cancelled' => $cancelledOrders,
                'total_revenue' => number_format($totalRevenue, 2),
            ],
            'data' => $formattedOrders,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    // 📌 عرض تفاصيل طلب محدد (للأدمن)
// ============================================================

    public function showOrder($id)
    {
        $order = Order::with([
            'buyer' => function ($query) {
                $query->select(
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'profile_photo',
                    'detailed_address'
                );
            },
            'seller' => function ($query) {
                $query->select(
                    'id',
                    'first_name',
                    'last_name',
                    'store_name',
                    'store_description',
                    'email',
                    'phone',
                    'profile_photo',
                    'store_logo',
                    'role',
                    'status',
                    'detailed_address'
                );
            },
            'coupon' => function ($query) {
                $query->select('id', 'code', 'title', 'type', 'value', 'min_order_amount');
            },
            'products' => function ($query) {
                $query->select(
                    'products.id',
                    'products.name',
                    'products.sku',
                    'products.images',
                    'products.description'
                );
            }
        ])->findOrFail($id);

        // تنسيق بيانات المنتجات مع الأسعار
        $products = $order->products->map(function ($product) {
            $quantity = $product->pivot->quantity;
            $pricePerUnit = $product->pivot->price;
            $subtotal = $quantity * $pricePerUnit;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'quantity' => $quantity,
                'price_per_unit' => $pricePerUnit,
                'subtotal' => $subtotal,
                'image' => isset($product->images[0]) ? asset('storage/' . $product->images[0]) : null,
                'all_images' => array_map(function ($img) {
                    return asset('storage/' . $img);
                }, $product->images ?? []),
            ];
        });

        // حساب الإجماليات
        $subtotal = $products->sum('subtotal');
        $discount = $order->discount_amount ?? 0;
        $total = $order->total_price;

        // تنسيق بيانات الطلب
        $formattedOrder = [
            'order' => [
                'id' => $order->id,
                'order_number' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'status' => $order->status,
                'status_label' => $this->getOrderStatusLabel($order->status),
                'payment_status' => $order->payment_status,
                'payment_label' => $this->getPaymentStatusLabel($order->payment_status),
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at?->toDateTimeString(),
                'shipped_at' => $order->shipped_at?->toDateTimeString(),
                'delivered_at' => $order->delivered_at?->toDateTimeString(),
                'estimated_delivery' => $order->estimated_delivery_date?->toDateTimeString(),
                'customer_notes' => $order->customer_notes,
            ],

            'buyer' => $order->buyer ? [
                'id' => $order->buyer->id,
                'first_name' => $order->buyer->first_name,
                'last_name' => $order->buyer->last_name,
                'full_name' => $order->buyer->first_name . ' ' . $order->buyer->last_name,
                'email' => $order->buyer->email,
                'phone' => $order->buyer->phone,
                'address' => $order->shipping_address_details ?? $order->buyer->detailed_address,
                'address_title' => $order->shipping_address_title,
                'profile_photo' => $order->buyer->profile_photo ? asset('storage/' . $order->buyer->profile_photo) : null,
            ] : null,

            'seller' => $order->seller ? [
                'id' => $order->seller->id,
                'first_name' => $order->seller->first_name,
                'last_name' => $order->seller->last_name,
                'full_name' => $order->seller->first_name . ' ' . $order->seller->last_name,
                'store_name' => $order->seller->store_name,
                'store_description' => $order->seller->store_description,
                'email' => $order->seller->email,
                'phone' => $order->seller->phone,
                'role' => $order->seller->role,
                'role_label' => $this->getRoleLabel($order->seller->role),
                'status' => $order->seller->status,
                'address' => $order->seller->detailed_address,
                'profile_photo' => $order->seller->profile_photo ? asset('storage/' . $order->seller->profile_photo) : null,
                'store_logo' => $order->seller->store_logo ? asset('storage/' . $order->seller->store_logo) : null,
            ] : null,

            'products' => $products,

            'coupon' => $order->coupon ? [
                'id' => $order->coupon->id,
                'code' => $order->coupon->code,
                'title' => $order->coupon->title,
                'type' => $order->coupon->type,
                'value' => $order->coupon->value,
                'min_order_amount' => $order->coupon->min_order_amount,
            ] : null,

            'pricing' => [
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping_fee' => 0, // يمكن إضافة قيمة الشحن إذا وجدت
                'tax' => 0, // يمكن إضافة الضريبة إذا وجدت
                'total' => $total,
            ],

            'shipping' => [
                'address_title' => $order->shipping_address_title,
                'address_details' => $order->shipping_address_details,
            ],

            'timeline' => $order->status_timeline ?? [],

            'transactions' => $order->transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'type_label' => $this->getTransactionTypeLabel($transaction->type),
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at?->toDateTimeString(),
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $formattedOrder,
        ]);
    }


    // 📌 عرض جميع الإعلانات (للأدمن)
// ============================================================
    public function allAds(Request $request)
    {
        $ads = Ad::with([
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone', 'role', 'status');
            }
        ])
            ->select(
                'id',
                'seller_id',
                'title',
                'description',
                'type',
                'duration',
                'price',
                'status',
                'views_count',
                'clicks_count',
                'starts_at',
                'expires_at',
                'created_at',
                'image_url',
                'link'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        // تنسيق البيانات للعرض
        $formattedAds = $ads->through(function ($ad) {
            return [
                'id' => $ad->id,
                'title' => $ad->title,
                'description' => $ad->description,
                'type' => $ad->type,
                'type_label' => $this->getAdTypeLabel($ad->type),
                'type_icon' => $this->getAdTypeIcon($ad->type),
                'duration' => $ad->duration,
                'duration_label' => $this->getDurationLabel($ad->duration),
                'price' => $ad->price,
                'status' => $ad->status,
                'status_label' => $this->getAdStatusLabel($ad->status),
                'status_color' => $this->getAdStatusColor($ad->status),
                'views' => $ad->views_count,
                'clicks' => $ad->clicks_count,
                'starts_at' => $ad->starts_at?->toDateTimeString(),
                'expires_at' => $ad->expires_at?->toDateTimeString(),
                'created_at' => $ad->created_at?->toDateTimeString(),
                'image' => $ad->image_url ? asset('storage/' . $ad->image_url) : null,
                'link' => $ad->link,
                'seller' => $ad->seller ? [
                    'id' => $ad->seller->id,
                    'name' => $ad->seller->first_name . ' ' . $ad->seller->last_name,
                    'store_name' => $ad->seller->store_name,
                    'email' => $ad->seller->email,
                    'phone' => $ad->seller->phone,
                    'role' => $ad->seller->role,
                    'role_label' => $this->getRoleLabel($ad->seller->role),
                    'status' => $ad->seller->status,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedAds,
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ]
        ]);
    }

    // 📌 عرض الإعلانات قيد المراجعة (Pending)
// ============================================================

    public function pendingAds(Request $request)
    {
        $ads = Ad::with([
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone', 'role', 'status');
            }
        ])
            ->where('status', 'pending')
            ->select(
                'id',
                'seller_id',
                'title',
                'description',
                'type',
                'duration',
                'price',
                'status',
                'views_count',
                'clicks_count',
                'starts_at',
                'expires_at',
                'created_at',
                'image_url',
                'link'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        $formattedAds = $ads->through(function ($ad) {
            return [
                'id' => $ad->id,
                'title' => $ad->title,
                'description' => $ad->description,
                'type' => $ad->type,
                'type_label' => $this->getAdTypeLabel($ad->type),
                'type_icon' => $this->getAdTypeIcon($ad->type),
                'duration' => $ad->duration,
                'duration_label' => $this->getDurationLabel($ad->duration),
                'price' => $ad->price,
                'status' => $ad->status,
                'status_label' => $this->getAdStatusLabel($ad->status),
                'status_color' => $this->getAdStatusColor($ad->status),
                'views' => $ad->views_count,
                'clicks' => $ad->clicks_count,
                'starts_at' => $ad->starts_at?->toDateTimeString(),
                'expires_at' => $ad->expires_at?->toDateTimeString(),
                'created_at' => $ad->created_at?->toDateTimeString(),
                'image' => $ad->image_url ? asset('storage/' . $ad->image_url) : null,
                'link' => $ad->link,
                'seller' => $ad->seller ? [
                    'id' => $ad->seller->id,
                    'name' => $ad->seller->first_name . ' ' . $ad->seller->last_name,
                    'store_name' => $ad->seller->store_name,
                    'email' => $ad->seller->email,
                    'phone' => $ad->seller->phone,
                    'role' => $ad->seller->role,
                    'role_label' => $this->getRoleLabel($ad->seller->role),
                    'status' => $ad->seller->status,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedAds,
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ]
        ]);
    }

    // 📌 عرض الإعلانات النشطة (Active)
// ============================================================

    public function activeAds(Request $request)
    {
        $ads = Ad::with([
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone', 'role', 'status');
            }
        ])
            ->where('status', 'active')
            ->select(
                'id',
                'seller_id',
                'title',
                'description',
                'type',
                'duration',
                'price',
                'status',
                'views_count',
                'clicks_count',
                'starts_at',
                'expires_at',
                'created_at',
                'image_url',
                'link'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        $formattedAds = $ads->through(function ($ad) {
            return [
                'id' => $ad->id,
                'title' => $ad->title,
                'description' => $ad->description,
                'type' => $ad->type,
                'type_label' => $this->getAdTypeLabel($ad->type),
                'type_icon' => $this->getAdTypeIcon($ad->type),
                'duration' => $ad->duration,
                'duration_label' => $this->getDurationLabel($ad->duration),
                'price' => $ad->price,
                'status' => $ad->status,
                'status_label' => $this->getAdStatusLabel($ad->status),
                'status_color' => $this->getAdStatusColor($ad->status),
                'views' => $ad->views_count,
                'clicks' => $ad->clicks_count,
                'starts_at' => $ad->starts_at?->toDateTimeString(),
                'expires_at' => $ad->expires_at?->toDateTimeString(),
                'created_at' => $ad->created_at?->toDateTimeString(),
                'image' => $ad->image_url ? asset('storage/' . $ad->image_url) : null,
                'link' => $ad->link,
                'seller' => $ad->seller ? [
                    'id' => $ad->seller->id,
                    'name' => $ad->seller->first_name . ' ' . $ad->seller->last_name,
                    'store_name' => $ad->seller->store_name,
                    'email' => $ad->seller->email,
                    'phone' => $ad->seller->phone,
                    'role' => $ad->seller->role,
                    'role_label' => $this->getRoleLabel($ad->seller->role),
                    'status' => $ad->seller->status,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedAds,
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ]
        ]);
    }

    // 📌 عرض الإعلانات المرفوضة (Rejected)
// ============================================================
    public function rejectedAds(Request $request)
    {
        $ads = Ad::with([
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone', 'role', 'status');
            }
        ])
            ->where('status', 'rejected')
            ->select(
                'id',
                'seller_id',
                'title',
                'description',
                'type',
                'duration',
                'price',
                'status',
                'views_count',
                'clicks_count',
                'starts_at',
                'expires_at',
                'created_at',
                'image_url',
                'link'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        $formattedAds = $ads->through(function ($ad) {
            return [
                'id' => $ad->id,
                'title' => $ad->title,
                'description' => $ad->description,
                'type' => $ad->type,
                'type_label' => $this->getAdTypeLabel($ad->type),
                'type_icon' => $this->getAdTypeIcon($ad->type),
                'duration' => $ad->duration,
                'duration_label' => $this->getDurationLabel($ad->duration),
                'price' => $ad->price,
                'status' => $ad->status,
                'status_label' => $this->getAdStatusLabel($ad->status),
                'status_color' => $this->getAdStatusColor($ad->status),
                'views' => $ad->views_count,
                'clicks' => $ad->clicks_count,
                'starts_at' => $ad->starts_at?->toDateTimeString(),
                'expires_at' => $ad->expires_at?->toDateTimeString(),
                'created_at' => $ad->created_at?->toDateTimeString(),
                'image' => $ad->image_url ? asset('storage/' . $ad->image_url) : null,
                'link' => $ad->link,
                'seller' => $ad->seller ? [
                    'id' => $ad->seller->id,
                    'name' => $ad->seller->first_name . ' ' . $ad->seller->last_name,
                    'store_name' => $ad->seller->store_name,
                    'email' => $ad->seller->email,
                    'phone' => $ad->seller->phone,
                    'role' => $ad->seller->role,
                    'role_label' => $this->getRoleLabel($ad->seller->role),
                    'status' => $ad->seller->status,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedAds,
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ]
        ]);
    }

    // 📌 عرض الإعلانات المنتهية (Expired)
// ============================================================

    public function expiredAds(Request $request)
    {
        $ads = Ad::with([
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone', 'role', 'status');
            }
        ])
            ->where('status', 'expired')
            ->select(
                'id',
                'seller_id',
                'title',
                'description',
                'type',
                'duration',
                'price',
                'status',
                'views_count',
                'clicks_count',
                'starts_at',
                'expires_at',
                'created_at',
                'image_url',
                'link'
            )
            ->latest()
            ->paginate($request->input('per_page', 20));

        $formattedAds = $ads->through(function ($ad) {
            return [
                'id' => $ad->id,
                'title' => $ad->title,
                'description' => $ad->description,
                'type' => $ad->type,
                'type_label' => $this->getAdTypeLabel($ad->type),
                'type_icon' => $this->getAdTypeIcon($ad->type),
                'duration' => $ad->duration,
                'duration_label' => $this->getDurationLabel($ad->duration),
                'price' => $ad->price,
                'status' => $ad->status,
                'status_label' => $this->getAdStatusLabel($ad->status),
                'status_color' => $this->getAdStatusColor($ad->status),
                'views' => $ad->views_count,
                'clicks' => $ad->clicks_count,
                'starts_at' => $ad->starts_at?->toDateTimeString(),
                'expires_at' => $ad->expires_at?->toDateTimeString(),
                'created_at' => $ad->created_at?->toDateTimeString(),
                'image' => $ad->image_url ? asset('storage/' . $ad->image_url) : null,
                'link' => $ad->link,
                'seller' => $ad->seller ? [
                    'id' => $ad->seller->id,
                    'name' => $ad->seller->first_name . ' ' . $ad->seller->last_name,
                    'store_name' => $ad->seller->store_name,
                    'email' => $ad->seller->email,
                    'phone' => $ad->seller->phone,
                    'role' => $ad->seller->role,
                    'role_label' => $this->getRoleLabel($ad->seller->role),
                    'status' => $ad->seller->status,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedAds,
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ]
        ]);
    }

    // 📌 عرض تفاصيل إعلان محدد (للأدمن)
// ============================================================
    public function showAdDetails($id)
    {
        $ad = Ad::with([
            'seller' => function ($query) {
                $query->select(
                    'id',
                    'first_name',
                    'last_name',
                    'store_name',
                    'store_description',
                    'email',
                    'phone',
                    'role',
                    'status',
                    'profile_photo',
                    'store_logo',
                    'detailed_address'
                );
            },
            'views' => function ($query) {
                $query->select('id', 'ad_id', 'user_id', 'type', 'created_at');
            }
        ])->findOrFail($id);

        // حساب إحصائيات إضافية
        $totalViews = $ad->views_count;
        $totalClicks = $ad->clicks_count;
        $uniqueViewers = $ad->views()->where('type', 'view')->distinct('user_id')->count('user_id');
        $dailyViews = $ad->views()
            ->where('type', 'view')
            ->whereDate('created_at', today())
            ->count();

        // تنسيق بيانات الإعلان
        $formattedAd = [
            'id' => $ad->id,
            'title' => $ad->title,
            'description' => $ad->description,
            'type' => $ad->type,
            'type_label' => $this->getAdTypeLabel($ad->type),
            'type_icon' => $this->getAdTypeIcon($ad->type),
            'duration' => $ad->duration,
            'duration_label' => $this->getDurationLabel($ad->duration),
            'price' => $ad->price,
            'status' => $ad->status,
            'status_label' => $this->getAdStatusLabel($ad->status),
            'status_color' => $this->getAdStatusColor($ad->status),
            'link' => $ad->link,
            'image' => $ad->image_url ? asset('storage/' . $ad->image_url) : null,
            'dates' => [
                'starts_at' => $ad->starts_at?->toDateTimeString(),
                'expires_at' => $ad->expires_at?->toDateTimeString(),
                'created_at' => $ad->created_at?->toDateTimeString(),
                'updated_at' => $ad->updated_at?->toDateTimeString(),
            ],
            'stats' => [
                'total_views' => $totalViews,
                'total_clicks' => $totalClicks,
                'unique_viewers' => $uniqueViewers,
                'daily_views' => $dailyViews,
                'ctr' => $totalViews > 0
                    ? number_format(($totalClicks / $totalViews) * 100, 2) . '%'
                    : '0%',
            ],
            'seller' => $ad->seller ? [
                'id' => $ad->seller->id,
                'first_name' => $ad->seller->first_name,
                'last_name' => $ad->seller->last_name,
                'full_name' => $ad->seller->first_name . ' ' . $ad->seller->last_name,
                'store_name' => $ad->seller->store_name,
                'store_description' => $ad->seller->store_description,
                'email' => $ad->seller->email,
                'phone' => $ad->seller->phone,
                'role' => $ad->seller->role,
                'role_label' => $this->getRoleLabel($ad->seller->role),
                'status' => $ad->seller->status,
                'address' => $ad->seller->detailed_address,
                'profile_photo' => $ad->seller->profile_photo ? asset('storage/' . $ad->seller->profile_photo) : null,
                'store_logo' => $ad->seller->store_logo ? asset('storage/' . $ad->seller->store_logo) : null,
            ] : null,
            'admin_notes' => $ad->admin_notes,
        ];

        return response()->json([
            'success' => true,
            'data' => $formattedAd,
        ]);
    }


    /**
     * 1. إضافة تصنيف جديد (name + slug فقط)
     * POST /api/admin/categories
     */
    public function createCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'slug' => 'required|string|max:255|unique:categories,slug|regex:/^[a-z0-9-]+$/',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'parent_id' => null,
            'image_url' => null,
            'icon_url' => null,
            'order_position' => (Category::max('order_position') ?? 0) + 1,
            'is_visible' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'image_url' => null,
                'icon_url' => null,
                'order_position' => $category->order_position,
                'is_visible' => (bool) $category->is_visible,
                'created_at' => $category->created_at?->toDateTimeString(),
            ]
        ], 201);
    }

    /**
     * 2. عرض جميع التصنيفات
     * GET /api/admin/categories
     */
    public function allCategories(Request $request)
    {
        $categories = Category::withCount('products')
            ->when($request->has('search'), function ($query) use ($request) {
                return $query->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('slug', 'like', '%' . $request->search . '%');
            })
            ->orderBy('order_position', 'asc')
            ->paginate($request->input('per_page', 20));

        $formattedCategories = $categories->through(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'image_url' => $category->image_url ? asset('storage/' . $category->image_url) : null,
                'icon_url' => $category->icon_url,
                'order_position' => $category->order_position,
                'is_visible' => (bool) $category->is_visible,
                'products_count' => $category->products_count,
                'created_at' => $category->created_at?->toDateTimeString(),
                'updated_at' => $category->updated_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedCategories,
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ]
        ]);
    }
    /**
     * 4. تحديث تصنيف (name + slug فقط)
     * PUT /api/admin/categories/{id}
     */
    public function updateCategory(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $id,
            'slug' => 'sometimes|string|max:255|unique:categories,slug,' . $id . '|regex:/^[a-z0-9-]+$/',
        ]);

        // ✅ تحديث مباشر باستخدام only()
        $category->update($request->only(['name', 'slug']));

        // إعادة تحميل البيانات المحدثة
        $category->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'image_url' => $category->image_url ? asset('storage/' . $category->image_url) : null,
                'icon_url' => $category->icon_url,
                'order_position' => $category->order_position,
                'is_visible' => (bool) $category->is_visible,
                'updated_at' => $category->updated_at?->toDateTimeString(),
            ]
        ]);
    }

    /**
     * 5. حذف تصنيف
     * DELETE /api/admin/categories/{id}
     */
    public function deleteCategory($id)
    {
        $category = Category::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category because it contains ' . $category->products_count . ' products. Please reassign or delete products first.',
                'products_count' => $category->products_count,
            ], 400);
        }

        if ($category->image_url && Storage::disk('public')->exists($category->image_url)) {
            Storage::disk('public')->delete($category->image_url);
        }

        $categoryName = $category->name;
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => "Category '{$categoryName}' deleted successfully.",
        ]);
    }



    public function dashboardStats()
    {
        // ============================================================
        // 📌 1. إحصائيات المستخدمين
        // ============================================================
        $totalUsers = User::count();

        $newUsersThisMonth = User::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $lastMonthUsers = User::whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->count();

        $usersChange = $lastMonthUsers > 0
            ? round((($newUsersThisMonth - $lastMonthUsers) / $lastMonthUsers) * 100, 1)
            : 0;

        // ============================================================
        // 📌 2. إحصائيات الطلبات
        // ============================================================
        $totalOrders = Order::count();

        // 🔥 الطريقة الصحيحة - باستخدام pluck و toArray
        $ordersByStatus = Order::select('status', \DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $ordersByStatusArabic = [
            'pending' => $ordersByStatus['pending'] ?? 0,
            'processing' => $ordersByStatus['processing'] ?? 0,
            'shipped' => $ordersByStatus['shipped'] ?? 0,
            'delivered' => $ordersByStatus['delivered'] ?? 0,
            'cancelled_returned' => $ordersByStatus['cancelled_returned'] ?? 0,
        ];

        // إجمالي المبيعات
        $totalSales = Order::whereIn('status', ['delivered', 'shipped', 'processing'])
            ->sum('total_price');

        $currentMonthSales = Order::whereIn('status', ['delivered', 'shipped', 'processing'])
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total_price');

        $lastMonthSales = Order::whereIn('status', ['delivered', 'shipped', 'processing'])
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->sum('total_price');

        $salesChange = $lastMonthSales > 0
            ? round((($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 1)
            : 0;

        // تغير الطلبات
        $currentMonthOrders = Order::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $lastMonthOrders = Order::whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->count();

        $ordersChange = $lastMonthOrders > 0
            ? round((($currentMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100, 1)
            : 0;

        // ============================================================
        // 📌 3. إحصائيات الإعلانات
        // ============================================================
        $expiredAds = Ad::where('status', 'expired')->count();
        $activeAds = Ad::where('status', 'active')->count();

        $adRevenue = Ad::whereIn('status', ['active', 'expired'])->sum('price');

        $currentMonthRevenue = Ad::whereIn('status', ['active', 'expired'])
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('price');

        $lastMonthRevenue = Ad::whereIn('status', ['active', 'expired'])
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->sum('price');

        $revenueChange = $lastMonthRevenue > 0
            ? round((($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;

        // ============================================================
        // 📌 4. المبيعات الشهرية (آخر 6 أشهر)
        // ============================================================
        $monthlySales = Order::whereIn('status', ['delivered', 'shipped', 'processing'])
            ->where('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->select(
                \DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                \DB::raw('SUM(total_price) as total')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->translatedFormat('F'),
                    'total' => round($item->total, 2),
                ];
            });

        // ============================================================
        // 📌 5. التجميع والإرجاع
        // ============================================================

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_users' => $totalUsers,
                    'users_change' => $usersChange,
                    'total_orders' => $totalOrders,
                    'orders_change' => $ordersChange,
                    'total_sales' => round($totalSales, 2),
                    'sales_change' => $salesChange,
                    'ad_revenue' => round($adRevenue, 2),
                    'revenue_change' => $revenueChange,
                    'expired_ads' => $expiredAds,
                    'active_ads' => $activeAds,
                ],
                'orders_by_status' => $ordersByStatusArabic,
                'monthly_sales' => $monthlySales,
            ]
        ]);
    }



    // 📌 المنتجات الأكثر مبيعاً
// ============================================================
    public function topSellingProducts(Request $request)
    {
        $limit = $request->input('limit', 10);

        $products = Product::select(
            'id',
            'name',
            'original_price',
            'sales_count',
            'quantity',
            'status',
            'user_id'
        )
            ->with('seller:id,first_name,last_name,store_name')
            ->orderBy('sales_count', 'desc')
            ->limit($limit)
            ->get();

        $formattedProducts = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->original_price,
                'sales_count' => $product->sales_count,
                'quantity' => $product->quantity,
                'status' => $product->status,
                'seller' => $product->seller ? [
                    'id' => $product->seller->id,
                    'name' => $product->seller->first_name . ' ' . $product->seller->last_name,
                    'store_name' => $product->seller->store_name,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedProducts,
            'total' => $formattedProducts->count(),
        ]);
    }

    // 📌 المنتجات الأقل مبيعاً
// ============================================================
    public function leastSellingProducts(Request $request)
    {
        $limit = $request->input('limit', 10);

        $products = Product::select(
            'id',
            'name',
            'original_price',
            'sales_count',
            'quantity',
            'status',
            'user_id'
        )
            ->with('seller:id,first_name,last_name,store_name')
            ->where('sales_count', '>=', 0)
            ->orderBy('sales_count', 'asc')
            ->limit($limit)
            ->get();

        $formattedProducts = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->original_price,
                'sales_count' => $product->sales_count,
                'quantity' => $product->quantity,
                'status' => $product->status,
                'seller' => $product->seller ? [
                    'id' => $product->seller->id,
                    'name' => $product->seller->first_name . ' ' . $product->seller->last_name,
                    'store_name' => $product->seller->store_name,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedProducts,
            'total' => $formattedProducts->count(),
        ]);
    }

    // 📌 أرباح الإعلانات الشهرية (آخر 12 شهر)
// ============================================================
    public function adRevenueMonthly(Request $request)
    {
        $months = $request->input('months', 12);

        $revenue = Ad::whereIn('status', ['active', 'expired'])
            ->where('created_at', '>=', Carbon::now()->subMonths($months)->startOfMonth())
            ->select(
                \DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                \DB::raw('SUM(price) as total')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->translatedFormat('F'),
                    'total' => round($item->total, 2),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $revenue,
        ]);
    }

    // 📌 نمو المستخدمين الشهري (آخر 12 شهر)
// ============================================================
    public function usersGrowthMonthly(Request $request)
    {
        $months = $request->input('months', 12);

        $growth = User::where('created_at', '>=', Carbon::now()->subMonths($months)->startOfMonth())
            ->select(
                \DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                \DB::raw('COUNT(*) as total')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->translatedFormat('F'),
                    'total' => $item->total,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $growth,
        ]);
    }

    // 📌 الإعلانات الأكثر أداءً (حسب المشاهدات والنقرات)
// ============================================================
    public function topPerformingAds(Request $request)
    {
        $limit = $request->input('limit', 10);

        $ads = Ad::select(
            'id',
            'title',
            'type',
            'price',
            'views_count',
            'clicks_count',
            'status',
            'seller_id'
        )
            ->with('seller:id,first_name,last_name,store_name')
            ->where('status', 'active')
            ->orderBy('views_count', 'desc')
            ->limit($limit)
            ->get();

        $formattedAds = $ads->map(function ($ad) {
            $ctr = $ad->views_count > 0
                ? round(($ad->clicks_count / $ad->views_count) * 100, 2)
                : 0;

            return [
                'id' => $ad->id,
                'title' => $ad->title,
                'type' => $ad->type,
                'type_label' => $this->getAdTypeLabel($ad->type),
                'price' => $ad->price,
                'views' => $ad->views_count,
                'clicks' => $ad->clicks_count,
                'ctr' => $ctr . '%',
                'status' => $ad->status,
                'seller' => $ad->seller ? [
                    'id' => $ad->seller->id,
                    'name' => $ad->seller->first_name . ' ' . $ad->seller->last_name,
                    'store_name' => $ad->seller->store_name,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedAds,
        ]);
    }

    // 📌 أفضل المشتريين (حسب عدد الطلبات والإنفاق)
// ============================================================
    public function topBuyers(Request $request)
    {
        $limit = $request->input('limit', 10);

        $buyers = User::where('role', 'buyer')
            ->withCount('orders')
            ->withSum('orders', 'total_price')
            ->having('orders_count', '>', 0)
            ->orderBy('orders_count', 'desc')
            ->limit($limit)
            ->get();

        $formattedBuyers = $buyers->map(function ($buyer) {
            // حساب النفقات (مصاريف الشحن أو العمولات)
            $expenses = $buyer->orders_sum_total_price * 0.05;

            // أرباح الإعلانات (إذا كان المشتري يملك إعلانات)
            $adRevenue = Ad::where('seller_id', $buyer->id)->sum('price');

            return [
                'id' => $buyer->id,
                'name' => $buyer->first_name . ' ' . $buyer->last_name,
                'orders_count' => $buyer->orders_count,
                'total_spent' => round($buyer->orders_sum_total_price ?? 0, 2),
                'expenses' => round($expenses, 2),
                'ad_revenue' => round($adRevenue, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedBuyers,
        ]);
    }


    // 📌 أحدث الطلبات (آخر 10 طلبات)
// ============================================================
    public function latestOrders(Request $request)
    {
        $limit = $request->input('limit', 10);

        $orders = Order::with([
            'buyer' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'email', 'phone');
            },
            'seller' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'store_name', 'email', 'phone');
            }
        ])
            ->select(
                'id',
                'user_id',
                'seller_id',
                'total_price',
                'status',
                'payment_status',
                'payment_method',
                'created_at'
            )
            ->latest()
            ->limit($limit)
            ->get();

        $formattedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'buyer' => $order->buyer ? [
                    'id' => $order->buyer->id,
                    'name' => $order->buyer->first_name . ' ' . $order->buyer->last_name,
                ] : null,
                'seller' => $order->seller ? [
                    'id' => $order->seller->id,
                    'name' => $order->seller->first_name . ' ' . $order->seller->last_name,
                    'store_name' => $order->seller->store_name,
                ] : null,
                'total_price' => $order->total_price,
                'status' => $order->status,
                'status_label' => $this->getOrderStatusLabel($order->status),
                'payment_status' => $order->payment_status,
                'payment_label' => $this->getPaymentStatusLabel($order->payment_status),
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedOrders,
            'total' => $formattedOrders->count(),
        ]);
    }


    // ============================================================
// 📌 دوال مساعدة للإعلانات
// ============================================================

    /**
     * ترجمة نوع الإعلان
     */
    private function getAdTypeLabel(string $type): string
    {
        return match ($type) {
            'banner' => 'بانر رئيسي',
            'promoted_product' => 'منتج معزز',
            'featured_store' => 'متجر مميز',
            'paid_notification' => 'إشعار مدفوع',
            default => $type,
        };
    }

    /**
     * إيقونة نوع الإعلان
     */
    private function getAdTypeIcon(string $type): string
    {
        return match ($type) {
            'banner' => '📢',
            'promoted_product' => '⭐',
            'featured_store' => '🏪',
            'paid_notification' => '🔔',
            default => '📌',
        };
    }

    /**
     * ترجمة مدة الإعلان
     */
    private function getDurationLabel(string $duration): string
    {
        return match ($duration) {
            '1_day' => 'يوم واحد',
            '3_days' => '3 أيام',
            '1_week' => 'أسبوع',
            '1_month' => 'شهر',
            default => $duration,
        };
    }

    /**
     * ترجمة حالة الإعلان
     */
    private function getAdStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'قيد المراجعة',
            'active' => 'نشط',
            'rejected' => 'مرفوض',
            'expired' => 'منتهي',
            default => $status,
        };
    }

    /**
     * لون حالة الإعلان
     */
    private function getAdStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => '#FFA500',  // برتقالي
            'active' => '#00CC44',   // أخضر
            'rejected' => '#FF3333', // أحمر
            'expired' => '#666666',  // رمادي
            default => '#999999',
        };
    }

    // 📌 دوال مساعدة للمعاملات
// ============================================================

    /**
     * ترجمة نوع المعاملة
     */
    private function getTransactionTypeLabel(string $type): string
    {
        return match ($type) {
            'deposit' => 'إيداع',
            'payment' => 'دفع',
            'refund' => 'استرداد',
            'withdrawal' => 'سحب',
            default => $type,
        };
    }

    // ============================================================
// 📌 دوال مساعدة للطلبات
// ============================================================

    /**
     * ترجمة حالة الطلب
     */
    private function getOrderStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'قيد الانتظار',
            'processing' => 'جاري التجهيز',
            'shipped' => 'تم الشحن',
            'delivered' => 'تم التسليم',
            'cancelled_returned' => 'ملغي / مرتجع',
            default => $status,
        };
    }

    /**
     * ترجمة حالة الدفع
     */
    private function getPaymentStatusLabel(string $status): string
    {
        return match ($status) {
            'unpaid' => 'غير مدفوع',
            'paid_escrow' => 'مدفوع (محجوز)',
            'released' => 'تم التحرير',
            'refunded' => 'تم الاسترداد',
            default => $status,
        };
    }
    // ============================================================
// 📌   دوال مساعدة للمنتجات 
// ============================================================

    /**
     * تحديد حالة المخزون
     */
    private function getStockStatus($product): string
    {
        if ($product->quantity <= 0) {
            return 'out_of_stock';
        } elseif ($product->quantity <= ($product->alert_threshold ?? 5)) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    /**
     * تحديد حالة المخزون للمنتج
     */
    private function getStockStatusForSeller($product): string
    {
        if ($product->quantity <= 0) {
            return 'out_of_stock';
        } elseif ($product->quantity <= ($product->alert_threshold ?? 5)) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    /**
     * ترجمة نوع البائع
     */
    private function getRoleLabel(string $role): string
    {
        return match ($role) {
            'vendor' => 'بائع عادي',
            'wholesale' => 'تاجر جملة',
            'buyer' => 'مشتري',
            default => $role,
        };
    }
}