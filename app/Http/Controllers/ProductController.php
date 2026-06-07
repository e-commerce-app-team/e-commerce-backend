<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductSaveRequest;
use App\Models\Product;
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

        // 2. معالجة الصور المتعددة المدمجة
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('products/images', 'public');
            }
        }
        $validated['images'] = $imagePaths; // تُحفظ كمصفوفة داخل الحقل المدمج

        // إنشاء المنتج في قاعدة البيانات
        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product
        ], 201);
    }


    public function update(ProductSaveRequest $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        // جلب المنتج والتأكد أن البائع هو المالك له
        $product = Product::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $validated = $request->validated();

        // 1. تحديث الفيديو إذا تم رفع ملف جديد
        if ($request->hasFile('video')) {
            if ($product->video_url) {
                Storage::disk('public')->delete($product->video_url);
            }
            $validated['video_url'] = $request->file('video')->store('products/videos', 'public');
        }

        // 2. تحديث الصور إذا أرسل البائع صوراً جديدة
        if ($request->hasFile('images')) {
            // حذف الصور القديمة من السيرفر لتوفير المساحة
            if (is_array($product->images)) {
                foreach ($product->images as $oldImagePath) {
                    Storage::disk('public')->delete($oldImagePath);
                }
            }

            // رفع الصور الجديدة
            $newImagePaths = [];
            foreach ($request->file('images') as $image) {
                $newImagePaths[] = $image->store('products/images', 'public');
            }
            $validated['images'] = $newImagePaths;
        }

        // تحديث المنتج بجميع البيانات الجديدة
        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $user = auth()->user();

        // جلب المنتج والتأكد أن البائع الحالي هو المالك له
        $product = Product::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        // 1. حذف الفيديو من السيرفر إذا كان موجوداً
        if ($product->video_url) {
            Storage::disk('public')->delete($product->video_url);
        }

        // 2. حذف جميع الصور المرتبطة بالمنتج من السيرفر
        if (is_array($product->images)) {
            foreach ($product->images as $imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
        }

        // 3. حذف المنتج من قاعدة البيانات
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product and its associated media files deleted successfully.'
        ], 200);
    }
}
