<?php
namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;

class SearchController extends Controller
{

    // البحث الشامل والذكي (منتجات ومتاجر وأقسام)

    // البحث الشامل والذكي (منتجات ومتاجر وأقسام)

    public function search(Request $request)
    {
        $queryText = $request->input('q');
        if (empty($queryText)) {
            return response()->json([
                'success' => true,
                'data' => ['products' => [], 'stores' => []]
            ]);
        }

        // 1. تنظيف النص وتفكيك الكلمات المركبة (مثل: شورتات-رجالي أو شورتات رجالي)
        $cleanedQuery = str_replace('-', ' ', $queryText);
        $words = explode(' ', $cleanedQuery);
        $words = array_filter($words); // إزالة الفراغات الزائدة

        // ==========================================
        // 🔥 أولاً: بناء استعلام المنتجات الشامل
        // ==========================================
        $productsQuery = Product::query();

        foreach ($words as $word) {
            $productsQuery->where(function ($mainQuery) use ($word) {
                // أ) البحث في اسم المنتج ووصفه
                $mainQuery->where('name', 'LIKE', "%{$word}%")
                    ->orWhere('description', 'LIKE', "%{$word}%")

                    // ب) البحث في القسم الفرعي المرتبط بالمنتج (Department)
                    ->orWhereHas('department', function ($subDeptQuery) use ($word) {
                        $subDeptQuery->where('name', 'LIKE', "%{$word}%")

                            // ج) الصعود درجة للبحث في القسم الرئيسي الأب (Parent Department)
                            ->orWhereHas('parent', function ($parentDeptQuery) use ($word) {
                                $parentDeptQuery->where('name', 'LIKE', "%{$word}%");
                            });
                    });
            });
        }

        // جلب المنتجات النهائية (تأكدي من مطابقة اسم حقل السعر عندك كـ product_price أو price)
        $products = $productsQuery
            //select('id', 'name', 'product_price', 'image', 'user_id', 'department_id')
            ->limit(20)
            ->get();

        // ==========================================
        // 🔥 ثانياً: بناء استعلام المتاجر الشامل
        // ==========================================
        $storesQuery = User::whereIn('role', ['vendor', 'wholesale']);

        foreach ($words as $word) {
            $storesQuery->where(function ($q) use ($word) {
                // أ) البحث في اسم المتجر ووصفه وتخصصه الرئيسي
                $q->where('store_name', 'LIKE', "%{$word}%")
                    ->orWhere('store_description', 'LIKE', "%{$word}%")
                    ->orWhere('category', 'LIKE', "%{$word}%");
            });
        }

        $stores = $storesQuery->select('id', 'store_name', 'store_logo', 'store_description', 'category')
            ->limit(20)
            ->get();

        // 3. إرجاع النتيجة مجمعة ومنظمة للفرونت آيند
        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products,
                'stores' => $stores
            ]
        ], 200);
    }

    // الاقتراحات الفورية السريعة أثناء الكتابة
    public function suggestions(Request $request)
    {
        $queryText = $request->input('q');
        if (empty($queryText)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // جلب أول 5 أسماء منتجات
        $productSuggestions = Product::where('name', 'LIKE', "%{$queryText}%")
            ->pluck('name')
            ->take(5)
            ->toArray();

        // جلب أول 5 أسماء أقسام
        $departmentSuggestions = Department::where('name', 'LIKE', "%{$queryText}%")
            ->pluck('name')
            ->take(5)
            ->toArray();

        // دمج المصفوفات وعرض أول 5 اقتراحات فريدة
        $allSuggestions = array_unique(array_merge($productSuggestions, $departmentSuggestions));

        return response()->json([
            'success' => true,
            'data' => array_slice($allSuggestions, 0, 5)
        ], 200);
    }
}