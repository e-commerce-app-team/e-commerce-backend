<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;

use Illuminate\Http\Request;

class StoreController extends Controller
{
    //تابع ارجاع صفحة المتجر للمشتري 
    public function show($id)
    {
        $store = User::whereIn('role', ['vendor', 'wholesale'])
            ->withCount('products')
            ->find($id);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'This Store Is Not Found'
            ], 404);
        }
        $isOpen = isset($store->is_open) ? (bool)$store->is_open : true;
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $store->id,
                'store_name' => $store->store_name,          
                'store_logo' => $store->store_logo,         
                'store_cover' => $store->store_cover, 
                'rating' => $store->rating ?? 4.5,          
                'products_count' => $store->products_count,  
                'is_open' => $isOpen                    
            ]
        ], 200);
    }
//جلب اقسام متجر محدد
    public function getStoreTree($store_id)
    {
        // جلب الأقسام الرئيسية الخاصة بالمتجر (المربوطة بـ seller_id والتي parent_id لها null)
        $categories = Department::where('seller_id', $store_id) // 🌟 تم الاعتماد على seller_id
            ->whereNull('parent_id')                         
            ->with(['children' => function($query) use ($store_id) {
                // نضمن برمجياً أن الأقسام الفرعية تتبع لنفس المتجر أيضاً
                $query->where('seller_id', $store_id)
                      ->select('id', 'name', 'slug', 'parent_id', 'seller_id');
            }])
            ->select('id', 'name', 'slug', 'seller_id')
            ->get();

        // التحقق إذا كان المتجر لا يملك أقساماً بعد
        if ($categories->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'هذا المتجر لا يملك أي أقسام حالياً',
                'data' => []
            ], 200);
        }

        // إرجاع شجرة الأقسام منسقة وجاهزة للفرونت آيند
        return response()->json([
            'success' => true,
            'data' => $categories
        ], 200);
    }

   
     //جلب منتجات متجر محدد
    public function getStoreProducts($store_id)
    {
        $products = \App\Models\Product::where('user_id', $store_id)
          //  ->select('id', 'name', 'price', 'image', 'description', 'seller_id', 'department_id')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $products
        ], 200);
    }
    
     //جلب منتجات قسم محدد (مع منتجات الأقسام الفرعية التابعة له)
    public function getdepartmentProducts($category_id)
    {
        // 1. جلب IDs القسم المحدد وأي أقسام فرعية تابعة له
        $categoryIds = \App\Models\Department::where('id', $category_id)
            ->orWhere('parent_id', $category_id)
            ->pluck('id');

        // 2. جلب المنتجات الموجودة في هذه الأقسام
        $products = \App\Models\Product::whereIn('department_id', $categoryIds)
           // ->select('id', 'name', 'price', 'image', 'description', 'department_id')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $products
        ], 200);
    }

    // جلب التقييمات الخاصة بمتجر معين
 public function getStoreReviews($store_id)
{
    // لا تستدعي موديل Review أبداً لتجنب الخطأ
    return response()->json([
        'success' => true,
        'message' => 'نظام التقييمات قيد التطوير حالياً',
        'data' => [] // إرجاع مصفوفة فارغة لتجنب الانهيار
    ], 200);
}

// تابع لخريطة المتجر 
public function getStoresMap(Request $request)
{
    $lat = $request->lat;
    $lng = $request->lng;
    $radius = $request->radius ?? 10;

    $stores = \App\Models\User::query()
        ->whereIn('role', ['vendor', 'wholesale'])
        ->select(
            'id', 
            'store_name as name', 
            'latitude', // الاسم الصحيح من الداتابيز
            'longitude', // الاسم الصحيح من الداتابيز
            //'is_open', 
           // 'rating', 
            'store_logo as category_icon'
        )
        // نقوم بتبديل lat بـ latitude و lng بـ longitude في المعادلة الحسابية
       // ->selectRaw('( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance', [$lat, $lng, $lat])
       ->selectRaw('1 as is_open') 
        ->selectRaw('5.0 as rating')
        ->selectRaw('( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance', [$lat, $lng, $lat])
        ->having('distance', '<', $radius)
        ->get();
        

    return response()->json([
        'success' => true,
        'data' => $stores
    ], 200);
}
}