<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategorySaveRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    // 2. إنشاء قسم جديد (رئيسي أو فرعي في أي مستوى)
    public function storeCategory(CategorySaveRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $slugSource = $validated['name'];
        if (is_array($validated['name'])) {
            $slugSource = $validated['name']['en'] ?? reset($validated['name']) ?? '';
        }
        $validated['slug'] = Str::slug($slugSource);

        if ($request->hasFile('image')) {
            $validated['image_url'] = $request->file('image')->store('categories/images', 'public');
        }

        if ($request->hasFile('icon')) {
            $validated['icon_url'] = $request->file('icon')->store('categories/icons', 'public');
        }

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => $category
        ], 201);
    }

    // 3. تعديل بيانات قسم معين
    public function updateCategory(CategorySaveRequest $request, $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, this category does not exist in the system to be updated'
            ], 404);
        }

        $validated = $request->validated();

        if (isset($validated['name'])) {
            $slugSource = $validated['name'];
            if (is_array($validated['name'])) {
                $slugSource = $validated['name']['en'] ?? reset($validated['name']) ?? '';
            }
            $validated['slug'] = Str::slug($slugSource);
        }

        if ($request->hasFile('image')) {
            if ($category->image_url) {
                Storage::disk('public')->delete($category->image_url);
            }
            $validated['image_url'] = $request->file('image')->store('categories/images', 'public');
        }

        if ($request->hasFile('icon')) {
            if ($category->icon_url) {
                Storage::disk('public')->delete($category->icon_url);
            }
            $validated['icon_url'] = $request->file('icon')->store('categories/icons', 'public');
        }

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => $category
        ], 200);
    }

    public function toggleVisibility(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:categories,id',
            'status' => 'required|in:show,hide'
        ]);

        $category = Category::find($request->id);

        if ($request->status === 'show') {
            $category->is_visible = true; 
        } elseif ($request->status === 'hide') {
            $category->is_visible = false; 
        }

        $category->save();

        return response()->json([
            'success' => true,
            'message' => $request->status === 'show' ? 'Category is now visible.' : 'Category is now hidden.',
            'data' => $category
        ], 200);
    }

    // 5. تحديث ترتيب الأقسام بالسحب والإفلات (Drag & Drop)
    public function reorderCategories(Request $request): JsonResponse
    {
        $request->validate([
            'positions' => 'required|array',
            'positions.*.id' => 'required|exists:categories,id',
            'positions.*.order_position' => 'required|integer',
        ]);
        
        foreach ($request->input('positions') as $item) {
            Category::where('id', $item['id'])->update(['order_position' => $item['order_position']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Categories reordered successfully.'
        ], 200);
    }

    // 6. حذف قسم (سيحذف الفروع التابعة له تلقائياً بسبب cascade)
    public function destroyCategory($id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, this category does not exist in the system to be deleted'
            ], 404);
        }

        if ($category->image_url) {
            Storage::disk('public')->delete($category->image_url);
        }
        if ($category->icon_url) {
            Storage::disk('public')->delete($category->icon_url);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category and its subcategories deleted successfully.'
        ], 200);
    }

    // عرض الشجرة كاملة للتصنيفات العامة في السيستم
    public function getAllCategories(): JsonResponse
    {
        $categories = Category::whereNull('parent_id')
            ->with(['recursiveChildren'])
            ->orderBy('order_position', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ], 200);
    }

     //جلب الأقسام الرئيسية 
    public function getMainCategories(): JsonResponse
    {
        $categories = Category::whereNull('parent_id')
            ->where('is_visible', true)
            ->orderBy('order_position', 'asc')
            ->get(['id', 'name', 'slug', 'image_url', 'icon_url', 'order_position']);

        return response()->json([
            'status' => true,
            'data'   => $categories
        ], 200);
    }

    
     //جلب الأقسام الفرعية لقسم معين 
    public function getChildrenCategories($id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status'  => false,
                'message' => 'Category not found'
            ], 404);
        }

        $children = $category->children()
            ->where('is_visible', true)
            ->get(['id', 'parent_id', 'name', 'slug', 'image_url', 'icon_url', 'order_position']);

        return response()->json([
            'status' => true,
            'data'   => $children
        ], 200);
    }
}

