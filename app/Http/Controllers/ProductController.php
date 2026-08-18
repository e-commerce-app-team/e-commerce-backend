<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductSaveRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Notifications\NewProductNotification;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class ProductController extends Controller
{
    public function store(ProductSaveRequest $request): JsonResponse
    {
        $user = auth()->user();

        // جلب البيانات التي تم التحقق منها بنجاح من الـ Request
        $validated = $request->validated();
        $validated['user_id'] = $user->id;

        // 1. معالجة الفيديو الاختياري
        if ($request->hasFile('video')) {
            $validated['video_url'] = $request->file('video')->store('products/videos', 'public');
        }

        // 2. معالجة الصور المتعددة الأساسية
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('products/images', 'public');
            }
        }
        $validated['images'] = $imagePaths;

        // 3. معالجة حقل مخزون المستودعات (Warehouse Stock) لتاجر الجملة
        // إذا قامت الواجهة الأمامية بإرساله كـ string (JSON)، نقوم بفك تشفيره ليتم حفظه كـ array
        if (isset($validated['warehouse_stock']) && is_string($validated['warehouse_stock'])) {
            $validated['warehouse_stock'] = json_decode($validated['warehouse_stock'], true);
        }

        // 4. إنشاء المنتج الأساسي (يحتوي تلقائياً على حقول الجملة والشحن المجاني)
        $product = Product::create($validated);

        // 5. معالجة المتغيرات (Variants) المرفقة مع المنتج
        if ($request->has('variants')) {
            // الدوران على مصفوفة المتغيرات القادمة من الطلب
            foreach ($request->input('variants', []) as $index => $rawVariant) {

                $variantFields = [
                    'product_id' => $product->id,
                    // التأكد إذا كانت الخصائص (attributes) تحتاج فك ترميز JSON أم لا
                    'attributes' => is_string($rawVariant['attributes']) ? json_decode($rawVariant['attributes'], true) : $rawVariant['attributes'],
                    'price' => $rawVariant['price'] ?? null,
                    'quantity' => $rawVariant['quantity'] ?? 0,
                    'sku' => $rawVariant['sku'] ?? $product->sku . '-' . ($index + 1),
                    'is_active' => $rawVariant['is_active'] ?? true,
                ];

                // رفع صورة المتغير الفرعية بناءً على الاندكس الحالي للمصفوفة
                if ($request->hasFile("variants.$index.image")) {
                    $variantFields['image_url'] = $request->file("variants.$index.image")->store('products/variants', 'public');
                }

                ProductVariant::create($variantFields);
            }
        }
        // 5.5 إرسال الإشعار لجميع متابعي البائع/المتجر الحالي
        $followers = $user->storeFollowers;

        if ($followers && $followers->count() > 0) {
            Notification::send($followers, new NewProductNotification($product));
        }
        // 6. إرجاع النتيجة النهائية مع تحميل المتغيرات
        return response()->json([
            'success' => true,
            'message' => 'Product and its variants created successfully.',
            'data' => $product->load('variants')
        ], 201);
    }

    public function update(ProductSaveRequest $request, $id): JsonResponse
    {
        $user = auth()->user();
        $product = Product::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $validated = $request->validated();

        // تحديث الفيديو والصور الأساسية للمنتج (كما هي في كودك القديم)
        if ($request->hasFile('video')) {
            if ($product->video_url) {
                Storage::disk('public')->delete($product->video_url);
            }
            $validated['video_url'] = $request->file('video')->store('products/videos', 'public');
        }

        if ($request->hasFile('images')) {
            if (is_array($product->images)) {
                foreach ($product->images as $oldImagePath) {
                    Storage::disk('public')->delete($oldImagePath);
                }
            }
            $newImagePaths = [];
            foreach ($request->file('images') as $image) {
                $newImagePaths[] = $image->store('products/images', 'public');
            }
            $validated['images'] = $newImagePaths;
        }

        $product->update($validated);

        // 4. تحديث المتغيرات (حذف القديم وإنشاء الجديد أو التحديث الذكي)
        if ($request->has('variants')) {
            // لحذف صور المتغيرات القديمة من السيرفر
            foreach ($product->variants as $oldVariant) {
                if ($oldVariant->image_url) {
                    Storage::disk('public')->delete($oldVariant->image_url);
                }
            }
            $product->variants()->delete(); // تصفير المتغيرات القديمة للمنتج

            // إعادة بناء المتغيرات المحدثة
            foreach ($request->file('variants', []) as $index => $variantData) {
                $rawVariant = $request->input("variants.$index");

                $variantFields = [
                    'product_id' => $product->id,
                    'attributes' => is_string($rawVariant['attributes']) ? json_decode($rawVariant['attributes'], true) : $rawVariant['attributes'],
                    'price' => $rawVariant['price'] ?? null,
                    'quantity' => $rawVariant['quantity'] ?? 0,
                    'sku' => $rawVariant['sku'] ?? $product->sku . '-' . ($index + 1),
                    'is_active' => $rawVariant['is_active'] ?? true,
                ];

                if ($request->hasFile("variants.$index.image")) {
                    $variantFields['image_url'] = $request->file("variants.$index.image")->store('products/variants', 'public');
                }

                ProductVariant::create($variantFields);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Product and its variants updated successfully.',
            'data' => $product->load('variants')
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $user = auth()->user();
        $product = Product::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        // حذف ملفات الميديا للمنتج الأساسي
        if ($product->video_url) {
            Storage::disk('public')->delete($product->video_url);
        }
        if (is_array($product->images)) {
            foreach ($product->images as $imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
        }

        // حذف صور المتغيرات التابعة له من السيرفر تلقائياً
        foreach ($product->variants as $variant) {
            if ($variant->image_url) {
                Storage::disk('public')->delete($variant->image_url);
            }
        }

        $product->delete(); // سيحذف المتغيرات من الداتابيز تلقائياً بسبب onDelete('cascade')

        return response()->json([
            'success' => true,
            'message' => 'Product, its variants, and all associated media files deleted successfully.'
        ], 200);
    }
    //هاد اذا بدي عدل بس عمتغير خاص بنتج معين مش على كلشي
    public function toggleVariant(Request $request, $id): JsonResponse
    {
        $request->validate([
            'is_active' => 'sometimes|boolean',
            'quantity' => 'sometimes|integer|min:0',
            'price' => 'sometimes|numeric|min:0',
        ]);

        // جلب المتغير والتأكد أن البائع الحالي يملك المنتج الأب لهذا المتغير
        $variant = ProductVariant::where('id', $id)
            ->whereHas('product', function ($query) {
                $query->where('user_id', auth()->id());
            })->firstOrFail();

        // تحديث البيانات الممررة فقط
        $variant->update($request->only(['is_active', 'quantity', 'price']));

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully.',
            'data' => $variant
        ], 200);
    }

    public function applySearch(Request $request): JsonResponse
    {
        // 1. التحقق من صحة البيانات القادمة من الـ JSON Body
        $request->validate([
            'name' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1'
        ]);

        $name = $request->input('name');
        $sku = $request->input('sku');

        // 2. الاستعلام الأساسي لجلب منتجات المستخدم الحالي
        $query = Product::where('user_id', auth()->id());

        // 3. تطبيق الفلترة إذا تم إرسال اسم أو SKU
        if ($name || $sku) {
            $query->where(function ($q) use ($name, $sku) {
                if ($name) {
                    $q->where('name', 'LIKE', "%{$name}%");
                }

                if ($sku) {
                    if ($name) {
                        $q->orWhere('sku', '=', $sku); // مطابقة تامة للـ SKU
                    } else {
                        $q->where('sku', '=', $sku);
                    }
                }
            });
        }

        // 4. جلب المتغيرات وتقسيم النتائج إلى صفحات
        $products = $query->with('variants')->paginate($request->input('per_page', 15));

        // 5. إرجاع النتيجة النهائية
        return response()->json([
            'success' => true,
            'message' => 'Products searched successfully.',
            'data' => $products
        ], 200);
    }


    public function filterByCategory(Request $request): JsonResponse
    {
        // 1. التحقق من صحة البيانات القادمة في الـ Body
        $request->validate([
            'category_id' => 'required|exists:categories,id', // تأكد من اسم جدول الفئات لديك
            'per_page' => 'nullable|integer|min:1'
        ]);

        // 2. الاستعلام الأساسي لجلب منتجات المستخدم الحالي مع متغيراتها
        $query = Product::where('user_id', auth()->id())->with('variants');

        // 3. جلب القيمة وتطبيق الفلترة
        $categoryId = $request->input('category_id');
        $query->where('category_id', $categoryId);

        // 4. تقسيم النتائج إلى صفحات
        $products = $query->paginate($request->input('per_page', 15));

        // 5. إرجاع النتيجة النهائية
        return response()->json([
            'success' => true,
            'message' => 'Products filtered by category successfully.',
            'data' => $products
        ], 200);
    }

    public function filterByStatus(Request $request): JsonResponse
    {
        // 1. التحقق من صحة البيانات القادمة في الـ Body
        $request->validate([
            'status' => 'required|string|in:active,draft,hidden', // أضف الحالات المعتمدة لديك هنا
            'per_page' => 'nullable|integer|min:1'
        ]);

        // 2. الاستعلام الأساسي لجلب منتجات المستخدم الحالي مع متغيراتها
        $query = Product::where('user_id', auth()->id())->with('variants');

        // 3. جلب القيمة من الـ Body وتطبيق الفلترة
        $status = $request->input('status');
        $query->where('status', $status);

        // 4. تقسيم النتائج إلى صفحات
        $products = $query->paginate($request->input('per_page', 15));

        // 5. إرجاع النتيجة النهائية
        return response()->json([
            'success' => true,
            'message' => 'Products filtered by status successfully.',
            'data' => $products
        ], 200);
    }


    public function filterByStock(Request $request): JsonResponse
    {
        // 1. التحقق من صحة البيانات القادمة في الـ Body
        $request->validate([
            'stock_level' => 'required|string|in:low,out,good',
            'per_page' => 'nullable|integer|min:1'
        ]);

        // 2. الاستعلام الأساسي لجلب منتجات المستخدم الحالي مع متغيراتها
        $query = Product::where('user_id', auth()->id())->with('variants');

        // 3. جلب القيمة من الـ Body وتطبيق الفلترة بناءً على الشرط
        $stock = $request->input('stock_level');

        if ($stock === 'low') {
            $query->whereRaw('quantity <= alert_threshold');
        } elseif ($stock === 'out') {
            $query->where('quantity', 0);
        } elseif ($stock === 'good') {
            $query->whereRaw('quantity > alert_threshold');
        }

        // 4. تقسيم النتائج إلى صفحات
        $products = $query->paginate($request->input('per_page', 15));

        // 5. إرجاع النتيجة النهائية
        return response()->json([
            'success' => true,
            'message' => 'Products filtered by stock level successfully.',
            'data' => $products
        ], 200);
    }
    public function applySorting(Request $request): JsonResponse
    {
        $request->validate([
            'sort_by' => 'nullable|string|in:latest,oldest,price_asc,price_desc,best_selling',
            'per_page' => 'nullable|integer|min:1'
        ]);

        $query = Product::where('user_id', auth()->id())->with('variants');
        $sortBy = $request->input('sort_by', 'latest');

        // دائماً نحسب المبيعات الحقيقية المدفوعة ديناميكياً لضمان دقة البيانات في كل الحالات
        $query->selectRaw('products.*, COALESCE((SELECT SUM(order_product.quantity) FROM order_product INNER JOIN orders ON orders.id = order_product.order_id WHERE order_product.product_id = products.id AND orders.status IN ("paid", "delivered")), 0) as paid_sales_sum');

        switch ($sortBy) {
            case 'best_selling':
                $query->orderBy('paid_sales_sum', 'desc')->orderBy('id', 'desc');
                break;

            case 'oldest':
                $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
                break;

            case 'price_asc':
                $query->orderBy('original_price', 'asc')->orderBy('id', 'asc');
                break;

            case 'price_desc':
                $query->orderBy('original_price', 'desc')->orderBy('id', 'desc');
                break;

            case 'latest':
            default:
                $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
                break;
        }

        $products = $query->paginate($request->input('per_page', 15));

        // 💡 تحويل القيمة ديناميكياً لكي يرى المستخدم الرقم الحقيقي المدفوع في حقل sales_count
        $products->getCollection()->transform(function ($product) {
            $product->sales_count = (int) $product->paid_sales_sum;
            unset($product->paid_sales_sum); // حذف الحقل الإضافي الزائد ليكون الـ JSON نظيفاً
            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'Sorted products retrieved successfully.',
            'data' => $products
        ], 200);
    }
    // دالة الإجراءات الجماعية (تفعيل، حذف، تخفيض)
    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|exists:products,id',
            'action' => 'required|in:activate,hide,delete,discount',
            'discount_percentage' => 'required_if:action,discount|numeric|min:1|max:99'
        ]);

        $userId = auth()->id();
        $ids = $request->input('ids');
        $action = $request->input('action');

        // التأكد أن جميع المنتجات المحددة تملكها نفس جهة الطلب لضمان الأمان
        $products = Product::whereIn('id', $ids)->where('user_id', $userId)->get();

        if ($products->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No valid products found.'], 404);
        }

        switch ($action) {
            case 'activate':
                Product::whereIn('id', $products->pluck('id'))->update(['status' => 'active']);
                $message = 'Selected products activated successfully.';
                break;

            case 'hide':
                Product::whereIn('id', $products->pluck('id'))->update(['status' => 'hidden']);
                $message = 'Selected products hidden successfully.';
                break;

            case 'discount':
                $percentage = $request->input('discount_percentage');
                foreach ($products as $product) {
                    $discountAmount = $product->original_price * ($percentage / 100);
                    $product->update([
                        'offer_price' => $product->original_price - $discountAmount,
                        'offer_expires_at' => now()->addDays(7) // افتراضي أسبوع
                    ]);
                }
                $message = 'Discount applied to selected products successfully.';
                break;

            case 'delete':
                foreach ($products as $product) {
                    // استدعاء دالة الحذف الفردية المجهزة عندك لمسح الميديا من السيرفر
                    $this->destroy($product->id);
                }
                $message = 'Selected products and their media deleted successfully.';
                break;
        }

        return response()->json([
            'success' => true,
            'message' => $message
        ], 200);
    }

    public function filterByDepartment(Request $request): JsonResponse
    {
        $request->validate([
            'department_id' => 'required|exists:departments,id',
            'per_page' => 'nullable|integer|min:1'
        ]);

        $products = Product::where('user_id', auth()->id())
            ->where('department_id', $request->input('department_id'))
            ->with('variants')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Products filtered by department successfully.',
            'data' => $products
        ], 200);
    }

    //فلاتر: الأقسام، السعر، التقييم، الشحن المجاني، العروض فقط
    public function FilttetByForBuyer(Request $request)
    {
        // 1. بدء استعلام الـ Query Builder بدون تنفيذ
        $query = Product::query();

        // 🔥 [الفلتر 1]: الأقسام (يجلب منتجات القسم نفسه وأقسامه الفرعية)
        if ($request->has('department_id') && !empty($request->department_id)) {
            $catId = $request->department_id;
            $query->where(function ($q) use ($catId) {
                $q->where('department_id', $catId)
                    ->orWhereHas('department', function ($subQ) use ($catId) {
                        $subQ->where('parent_id', $catId);
                    });
            });
        }

        // 🔥 [الفلتر 2]: السعر (أقل سعر وأعلى سعر)
        if ($request->has('min_price') && !empty($request->min_price)) {
            $query->where('original_price', '>=', $request->min_price);
        }
        if ($request->has('max_price') && !empty($request->max_price)) {
            $query->where('original_price', '<=', $request->max_price);
        }

        // 🔥 [الفلتر 3]: التقييم (مثلاً المنتجات اللي تقييمها 4 نجوم وأكير)
        if ($request->has('rating') && !empty($request->rating)) {
            $query->where('rating', '>=', $request->rating);
        }

        // 🔥 [الفلتر 4]: الشحن المجاني (يتوقع حقل boolean في جدول المنتجات اسمه is_free_shipping أو مشابه)
        if ($request->has('free_shipping') && $request->free_shipping == '1') {
            $query->where('is_free_shipping', 1);
        }

        // 🔥 [الفلتر 5]: العروض والخصومات فقط
        if ($request->has('has_discount') && $request->has_discount == '1') {
            // إذا كان عندك حقل السعر بعد الخصم اسمه discount_price، بنجيب المنتجات اللي سعر خصمها أكبر من 0
            $query->where('offer_price', '>', 0);
        }

        // 2. تنفيذ الاستعلام النهائي وجلب البيانات للفرونت آيند
        $products = $query
            //select('id', 'name', 'product_price', 'discount_price', 'is_free_shipping', 'rating', 'image')
            ->get(); // أو فيكي تستخدمي paginate(15) لتقسيم الصفحات

        return response()->json([
            'success' => true,
            'data' => $products
        ], 200);
    }

    // تابع جلب بيانات منتج معين حسب ال id  اي بشكل عام بيانات المنتج التفصيلية ولاي متجر تابع 
    public function showProductDetails($id)
    {
        // 1. جلب المنتج مع حساب متوسط التقييم وعدد المراجعات
        $product = \App\Models\Product::with([
            'variants',
            'seller:id,store_name,store_logo,store_description',
            'reviews.user:id,first_name,last_name,profile_photo'
        ])
            ->withAvg('reviews as rating', 'rating')
            ->withCount('reviews as reviews_count')
            ->find($id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'المنتج غير موجود'], 404);
        }

        // 2. المنتجات المشابهة
        $similarProducts = \App\Models\Product::where('department_id', $product->department_id)
            ->where('id', '!=', $id)
            ->limit(4)
            ->get();

        // 3. بناء الاستجابة بالتقييمات الحقيقية
        return response()->json([
            'success' => true,
            'data' => [
                'product' => array_merge($product->toArray(), [
                    'rating'        => round($product->rating ?? 0, 2),
                    'reviews_count' => $product->reviews_count,
                ]),
                'similar_products' => $similarProducts,
                'reviews' => $product->reviews->map(function ($review) {
                    $fullName = trim(($review->user->first_name ?? '') . ' ' . ($review->user->last_name ?? ''));
                    return [
                        'id'         => $review->id,
                        'rating'     => $review->rating,
                        'comment'    => $review->comment,
                        'created_at' => $review->created_at->format('Y-m-d H:i'),
                        'user'       => [
                            'id'            => $review->user->id ?? null,
                            'first_name'    => $review->user->first_name ?? null,
                            'last_name'     => $review->user->last_name ?? null,
                            'name'          => !empty($fullName) ? $fullName : 'مشتري',
                            'profile_photo' => isset($review->user->profile_photo)
                                ? asset('storage/' . $review->user->profile_photo)
                                : null,
                        ]
                    ];
                })
            ]
        ], 200);
    }


    // تسجيل مشاهدة لمنتج معين

    public function incrementViews($id)
    {
        $product = \App\Models\Product::find($id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        // زيادة قيمة المشاهدات بمقدار 1
        $product->increment('views');

        return response()->json([
            'success' => true,
            'message' => 'View recorded successfully',
            'views_count' => $product->views
        ], 200);
    }
    //تابع جلب المنتجات الرائجه بالهوم بيج 
    public function getTrendingProducts(Request $request)
    {
        $limit = $request->input('limit', 10);
        $locale = app()->getLocale();

        // 1. محاولة جلب المنتجات الأكثر طلباً في آخر 7 أيام
        $products = Product::where('status', 'active')
            ->has('orderItems')
            ->withCount(['orderItems as orders_count' => function ($query) {
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
            }])
            ->having('orders_count', '>', 0)
            ->orderByDesc('orders_count')
            ->limit($limit)
            ->get();

        // 2. Fallback: إذا كانت القائمة فارغة، نجلب الأكثر طلباً بشكل عام (All-time)
        if ($products->isEmpty()) {
            $products = Product::where('status', 'active')
                ->has('orderItems')
                ->withCount('orderItems as orders_count')
                ->having('orders_count', '>', 0)
                ->orderByDesc('orders_count')
                ->limit($limit)
                ->get();
        }

        // 3. Fallback إضافي: إذا لم تكن هناك أي طلبات في النظام مطلقاً، نجلب أحدث المنتجات
        if ($products->isEmpty()) {
            $products = Product::where('status', 'active')
                ->latest()
                ->limit($limit)
                ->get()
                ->map(function ($product) {
                    $product->orders_count = 0;
                    return $product;
                });
        }

        // إرجاع الاستجابة بنفس التنسيق
        return response()->json([
            'success' => true,
            'data' => $products->map(function ($product) use ($locale) {
                $name = $product->name;

                if (is_array($name)) {
                    $name = $name[$locale] ?? $name['ar'] ?? reset($name);
                } elseif (is_string($name) && str_starts_with($name, '{')) {
                    $decoded = json_decode($name, true);
                    if (is_array($decoded)) {
                        $name = $decoded[$locale] ?? $decoded['ar'] ?? reset($decoded);
                    }
                }

                $images = is_array($product->images)
                    ? array_map(fn($img) => str_starts_with($img, 'http') ? $img : asset('storage/' . $img), $product->images)
                    : [];

                return [
                    'id'             => $product->id,
                    'name'           => $name,
                    'original_price' => $product->original_price,
                    'offer_price'    => $product->offer_price,
                    'images'         => $images,
                    'orders_count'   => $product->orders_count,
                ];
            })
        ], 200);
    }
}
