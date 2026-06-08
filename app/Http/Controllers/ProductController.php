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
                    'price'      => $rawVariant['price'] ?? null,
                    'quantity'   => $rawVariant['quantity'] ?? 0,
                    'sku'        => $rawVariant['sku'] ?? $product->sku . '-' . ($index + 1),
                    'is_active'  => $rawVariant['is_active'] ?? true,
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
            'data'    => $product->load('variants')
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
                    'price'      => $rawVariant['price'] ?? null,
                    'quantity'   => $rawVariant['quantity'] ?? 0,
                    'sku'        => $rawVariant['sku'] ?? $product->sku . '-' . ($index + 1),
                    'is_active'  => $rawVariant['is_active'] ?? true,
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
            'data'    => $product->load('variants')
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
        'quantity'  => 'sometimes|integer|min:0',
        'price'     => 'sometimes|numeric|min:0',
    ]);

    // جلب المتغير والتأكد أن البائع الحالي يملك المنتج الأب لهذا المتغير
    $variant = \App\Models\ProductVariant::where('id', $id)
        ->whereHas('product', function ($query) {
            $query->where('user_id', auth()->id());
        })->firstOrFail();

    // تحديث البيانات الممررة فقط
    $variant->update($request->only(['is_active', 'quantity', 'price']));

    return response()->json([
        'success' => true,
        'message' => 'Variant updated successfully.',
        'data'    => $variant
    ], 200);
}
}
