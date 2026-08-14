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
    // 1. عرض الشجرة كاملة (تبدأ بالأقسام الرئيسية وتنزل للأعماق)
   /*  public function getAllCategories(): JsonResponse
    {
        $categories = Category::whereNull('parent_id')
            ->with(['recursiveChildren']) // جلب الشجرة عودياً
            ->orderBy('order_position', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ], 200);
    }

    // 2. إنشاء قسم جديد (رئيسي أو فرعي في أي مستوى)
    public function storeDepartment(CategorySaveRequest $request): JsonResponse
    {

        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['name']);

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
    public function updateDepartment(CategorySaveRequest $request, $id): JsonResponse
    {
        // استخدام find بدلاً من findOrFail لكي لا يرمي خطأ تلقائي
        $category = Category::find($id);

        // إذا لم يجد القسم في قاعدة البيانات
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, this category does not exist in the system to be updated'
            ], 404);
        }

        $validated = $request->validated();

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
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
        // 1. التحقق من المدخلات: يجب إرسال رقم القسم (id) والـ status (إما show أو hide)
        $request->validate([
            'id' => 'required|exists:categories,id',
            'status' => 'required|in:show,hide'
        ]);

        // 2. جلب القسم المطلوب تعديله
        $category = Category::find($request->id);

        // 3. تعديل قيمة الحقل بناءً على الكلمة القادمة
        if ($request->status === 'show') {
            $category->is_visible = true; // تحويله إلى ظاهر (1)
        } elseif ($request->status === 'hide') {
            $category->is_visible = false; // تحويله إلى مخفي (0)
        }

        // 4. حفظ التعديل في قاعدة البيانات
        $category->save();

        return response()->json([
            'success' => true,
            'message' => $request->status === 'show' ? 'Category is now visible.' : 'Category is now hidden.',
            'data' => $category
        ], 200);
    }
    // 5. تحديث ترتيب الأقسام بالسحب والإفلات (Drag & Drop)
    public function reorderDepartment(Request $request): JsonResponse
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
    public function destroyDepartment($id): JsonResponse
    {
        // البحث عن القسم
        $category = Category::find($id);

        // إذا لم يجد القسم في قاعدة البيانات عند محاولة الحذف
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, this category does not exist in the system to be deleted'
            ], 404);
        }

        // إذا وُجد القسم، يتم إكمال عملية الحذف وحذف ملفاته
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
 */
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
}
