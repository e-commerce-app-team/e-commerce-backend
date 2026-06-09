<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductSaveRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function store(ProductSaveRequest $request): JsonResponse
    {
        $user = auth()->user();
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

        // إنشاء المنتج الأساسي
        $product = Product::create($validated);

        // 3. السحر: توليد وحفظ مصفوفة المتغيرات تلقائياً إذا أرسلت من الواجهة
        if ($request->has('variants')) {
            foreach ($request->file('variants', []) as $index => $variantData) {
                // جلب البيانات النصية للمتغير المقابل للأندكس
                $rawVariant = $request->input("variants.$index");

                $variantFields = [
                    'product_id' => $product->id,
                    'attributes' => is_string($rawVariant['attributes']) ? json_decode($rawVariant['attributes'], true) : $rawVariant['attributes'],
                    'price' => $rawVariant['price'] ?? null,
                    'quantity' => $rawVariant['quantity'] ?? 0,
                    'sku' => $rawVariant['sku'] ?? $product->sku . '-' . ($index + 1),
                    'is_active' => $rawVariant['is_active'] ?? true,
                ];

                // إذا قام التاجر برفع صورة مستقلة لهذا المتغير المحدد
                if ($request->hasFile("variants.$index.image")) {
                    $variantFields['image_url'] = $request->file("variants.$index.image")->store('products/variants', 'public');
                }

                ProductVariant::create($variantFields);
            }
        }

        // إرجاع المنتج مع متغيراته كاملة
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
    public function toggleVariant(Request $request, $id): \Illuminate\Http\JsonResponse
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
        // يبدأ بالاستعلام الأساسي المؤمن للبائع الحالي
        $query = Product::where('user_id', auth()->id())->with('variants');

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($vQ) use ($search) {
                        $vQ->where('sku', 'like', "%{$search}%");
                    });
            });
        }

        // جلب المنتجات وإرجاعها فوراً
        $products = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Search results retrieved successfully.',
            'data' => $products
        ], 200);
    }

    /**
     * 2. تابع مستقل للتصفية فقط
     * الرابط: GET /api/products/filter?category_id=1&status=active&stock_level=low
     */
    public function applyFilters(Request $request): JsonResponse
    {
        // يبدأ بالاستعلام الأساسي المؤمن للبائع الحالي
        $query = Product::where('user_id', auth()->id())->with('variants');

        // التصفية حسب القسم
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // التصفية حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // التصفية حسب مستوى المخزون
        if ($request->filled('stock_level')) {
            $stock = $request->input('stock_level');
            if ($stock === 'low') {
                $query->whereRaw('quantity <= alert_threshold');
            } elseif ($stock === 'out') {
                $query->where('quantity', 0);
            }
        }

        // جلب المنتجات وإرجاعها فوراً
        $products = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Filtered products retrieved successfully.',
            'data' => $products
        ], 200);
    }

    /**
     * 3. تابع مستقل للترتيب فقط
     * الرابط: GET /api/products/sort?sort_by=price_asc
     */
    public function applySorting(Request $request): JsonResponse
    {
        // يبدأ بالاستعلام الأساسي المؤمن للبائع الحالي
        $query = Product::where('user_id', auth()->id())->with('variants');

        $sortBy = $request->input('sort_by', 'latest');

        switch ($sortBy) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'price_asc':
                $query->orderBy('original_price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('original_price', 'desc');
                break;
            case 'best_selling':
                $query->orderBy('id', 'desc');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // جلب المنتجات وإرجاعها فوراً
        $products = $query->paginate($request->input('per_page', 15));

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
}
