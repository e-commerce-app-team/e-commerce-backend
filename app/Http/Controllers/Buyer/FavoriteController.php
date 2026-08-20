<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\CartItem;
use Illuminate\Http\Request;
use App\Models\Product;        // ✅ أضف هالسطر

class FavoriteController extends Controller
{
    public function add(Request $request)
    {
        // 1. جلب المنتج أولاً لمعرفة التصنيف (category_id)
        $product = Product::find($request->product_id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // 2. الحفظ في المفضلة
        Favorite::updateOrCreate(
            ['user_id' => auth()->id(), 'product_id' => $request->product_id]
        );

        // 🎯 تسجيل سلوك الإضافة للمفضلة لتغذية التوصيات
        try {
            if (auth()->check()) {
                \App\Models\UserBehavior::create([
                    'user_id' => auth()->id(),
                    'action' => 'cart', // نستخدم cart لكونها تعكس اهتماماً عالياً بالمنتج
                    'product_id' => $product->id,
                    'category_id' => $product->department_id,
                ]);
            }
        } catch (\Exception $e) {
            // تجاهل الخطأ لعدم التأثير على إضافة المفضلة
        }

        return response()->json(['message' => 'Product added to favorites successfully']);
    }
    public function remove($product_id)
    {
        // 1. نبحث عن السجل
        $favorite = Favorite::where('user_id', auth()->id())
            ->where('product_id', $product_id)
            ->first();

        // 2. إذا لم نجد السجل، نرسل خطأ 404
        if (!$favorite) {
            return response()->json([
                'message' => 'Product not found in your favorites'
            ], 404);
        }

        // 3. إذا وجدناه، نقوم بحذفه
        $favorite->delete();

        return response()->json([
            'message' => 'Product removed from favorites successfully'
        ], 200);
    }
    public function index()
    {
        $favorites = Favorite::where('user_id', auth()->id())->with('product')->get();

        $favoritesWithAlerts = $favorites->map(function ($favorite) {
            $product = $favorite->product;
            $alert = null;

            if ($product->quantity <= 0) {
                $alert = "Alert: This product is currently out of stock";
            } else {
                // هنا الفكرة: إذا كان المنتج كان مصفراً والآن أصبح متوفراً
                $alert = "Available: Ready to add to cart";
            }

            return [
                'id' => $favorite->id,
                'product_name' => $product->name,
                'quantity' => $product->quantity,
                'alert' => $alert
            ];
        });

        return response()->json($favoritesWithAlerts);
    }
    public function moveToCart(Request $request)
    {
        // 1. التحقق من أن المنتج موجود في قاعدة البيانات
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        // 2. جلب معلومات المنتج لجلب الـ seller_id لاحقاً
        $product = Product::find($request->product_id);

        // 3. التحقق من وجود المنتج في مفضلة المستخدم الحالي
        $favorite = Favorite::where('user_id', auth()->id())
            ->where('product_id', $request->product_id)
            ->first();

        if (!$favorite) {
            return response()->json([
                'message' => 'Product not found in your favorites list'
            ], 404);
        }

        // 4. إضافة المنتج للسلة مع الـ seller_id
        CartItem::create([
            'user_id' => auth()->id(),
            'product_id' => $request->product_id,
            'seller_id' => $product->user_id, // تم جلبها من بيانات المنتج الأساسية
            'qty' => 1
        ]);

        // 5. حذف المنتج من المفضلة بعد نقله للسلة
        $favorite->delete();

        // 6. الرد بالإنجليزية كما طلبتِ
        return response()->json([
            'message' => 'Product moved to cart successfully'
        ], 200);
    }
}
