<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str; // تم إضافتها لاستخدام Str::slug
use Illuminate\Support\Facades\Storage; // تم إضافتها لحذف الملفات القديمة

class MerchantDepartment extends Controller
{
    // 1. عرض شجرة أقسام التاجر الحالي (رئيسي وفرعي)
    public function index()
    {
        $departments = Department::where('seller_id', auth()->id())
            ->whereNull('parent_id') // نبدأ بالأقسام الرئيسية للتاجر فقط
            ->with(['recursiveChildren']) // جلب الأبناء والأحفاد عودياً
            ->orderBy('order_position', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $departments
        ], 200);
    }

    // 2. إنشاء قسم جديد خاص بالتاجر (رئيسي أو فرعي)
    public function storeDepartment(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:departments,id', // التعديل هنا: التحقق من وجود القسم الأب
            'image' => 'nullable|image|max:2048',
            'icon' => 'nullable|image|max:1024',
        ]);

        // التعديل هنا: أخذ الـ parent_id من المدخلات
        $validated = $request->only(['name', 'parent_id']);
        $validated['seller_id'] = auth()->id();
        $validated['slug'] = Str::slug($request->name) . '-' . auth()->id();

        if ($request->hasFile('image')) {
            $validated['image_url'] = $request->file('image')->store('departments/images', 'public');
        }

        if ($request->hasFile('icon')) {
            $validated['icon_url'] = $request->file('icon')->store('departments/icons', 'public');
        }

        $department = Department::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully.',
            'data' => $department
        ], 201);
    }

    // 3. تعديل بيانات قسم معين للتاجر
    public function updateDepartment(Request $request, $id)
    {
        $department = Department::where('seller_id', auth()->id())->find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found or unauthorized.'
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:departments,id', // التعديل هنا: التحقق من وجود الأب عند التعديل
            'image' => 'nullable|image|max:2048',
            'icon' => 'nullable|image|max:1024',
        ]);

        if ($request->has('name')) {
            $department->name = $request->name;
            $department->slug = Str::slug($request->name) . '-' . auth()->id();
        }

        // التعديل هنا: تحديث الـ parent_id إذا تم إرساله
        if ($request->has('parent_id')) {
            $department->parent_id = $request->parent_id;
        }

        if ($request->hasFile('image')) {
            if ($department->image_url) {
                Storage::disk('public')->delete($department->image_url);
            }
            $department->image_url = $request->file('image')->store('departments/images', 'public');
        }

        if ($request->hasFile('icon')) {
            if ($department->icon_url) {
                Storage::disk('public')->delete($department->icon_url);
            }
            $department->icon_url = $request->file('icon')->store('departments/icons', 'public');
        }

        $department->save();

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully.',
            'data' => $department
        ], 200);
    }

    // 4. إخفاء وإظهار القسم للتاجر
    public function toggleVisibility(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:departments,id',
            'status' => 'required|in:show,hide'
        ]);

        $department = Department::where('seller_id', auth()->id())->find($request->id);

        if (!$department) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $department->is_visible = ($request->status === 'show');
        $department->save();

        return response()->json([
            'success' => true,
            'message' => $request->status === 'show' ? 'Department is now visible.' : 'Department is now hidden.'
        ], 200);
    }

    // 5. إعادة الترتيب بالسحب والإفلات لأقسام التاجر
    public function reorderDepartment(Request $request)
    {
        $request->validate([
            'positions' => 'required|array',
            'positions.*.id' => 'required|exists:departments,id',
            'positions.*.order_position' => 'required|integer',
        ]);

        foreach ($request->input('positions') as $item) {
            Department::where('id', $item['id'])
                ->where('seller_id', auth()->id())
                ->update(['order_position' => $item['order_position']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Departments reordered successfully.'
        ], 200);
    }

    // 6. حذف قسم التاجر الخاص
    public function destroyDepartment($id)
    {
        $department = Department::where('seller_id', auth()->id())->find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found or unauthorized.'
            ], 404);
        }

        if ($department->image_url) {
            Storage::disk('public')->delete($department->image_url);
        }
        if ($department->icon_url) {
            Storage::disk('public')->delete($department->icon_url);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully.'
        ], 200);
    }
//تابع جلب الاقسام الرئيسية للمنصة كاملة 
    public function getTree()
    {
        // جلب الأقسام التي ليس لها أب (أي الرئيسية فقط) مع أبنائها الفرعيين
        $categories = Department::whereNull('parent_id')
            ->with(['children' => function($query) {
                // جلب الحقول الأساسية للأقسام الفرعية
                $query->select('id', 'name', 'slug', 'parent_id', 'seller_id');
            }]) 
            ->select('id', 'name', 'slug', 'seller_id') // جلب الحقول للأقسام الرئيسية
            ->get();

        // إرجاع النتيجة للمشتري
        return response()->json([
            'success' => true,
            'data' => $categories
        ], 200);
    }
    
}