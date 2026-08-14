<?php

namespace App\Http\Controllers\Buyer;

use App\Models\Product;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SubOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class CartController extends Controller
{
    // دالة مساعدة للتحقق من المخزون
    private function validateStock($product, $qty)
    {
        return $qty <= $product->quantity;
    }

    // 1. إضافة للمنتج
    public function addToCart(Request $request)
    {
        // 1. التأكد من هوية المستخدم (لا بد من وجود Bearer Token في الـ Header)
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized, please log in'], 401);
        }
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Forbidden: Only buyers can add to cart'], 403);
        }
        // 3. جلب المنتج
        $product = Product::findOrFail($request->product_id);

        // 4. التحقق من المخزون (تأكدي أن $this->validateStock تستخدم $product->quantity)
        if (!$this->validateStock($product, $request->qty)) {
            return response()->json(['message' => 'The requested quantity is not available in stock'], 400);
        }

        // 5. الحفظ (updateOrCreate)
        // نستخدم $user->id لضمان ربط السلة بالمستخدم الحالي
        CartItem::updateOrCreate(
            [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'variant_id' => $request->variant_id
            ],
            [
                'qty' => $request->qty,
                'seller_id' => $product->user_id // تأكدي أن عمود seller_id في جدول cart_items موجود
            ]
        );

        return response()->json(['message' => 'Added to cart successfully']);
    }

    // 2. عرض السلة (مجمعة حسب التاجر)
    public function getCart()
    {
        $user = auth()->user();

        // 1. حماية التابع (التأكد من تسجيل الدخول)
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // 2. التحقق من الصلاحية (أن المشتري فقط من يمكنه عرض سلة مشترياته)
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 3. جلب البيانات مع العلاقات اللازمة
        // 1. جلب البيانات
        $items = CartItem::where('user_id', auth()->id())
            ->with('product')
            ->get();

        // 2. التجميع اليدوي (هذه الطريقة لا تفشل أبداً)
        $grouped = [];
        foreach ($items as $item) {
            $sellerId = $item->seller_id;
            // نضع السجل في المصفوفة تحت مفتاح الـ seller_id
            $grouped[$sellerId][] = $item;
        }

        return response()->json([
            'message' => 'Cart retrieved successfully',
            'data' => $grouped
        ]);
    }
    //تابع لحذف جميع المنتجات التي وضعت في السلة 
    public function clearCart()
    {
        // 1. حماية التابع (التأكد من أن المشتري هو من يقوم بالعملية)
        $user = auth()->user();
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Forbidden: Only buyers can clear the cart'], 403);
        }

        // 2. حذف كافة عناصر السلة التي تخص المستخدم الحالي
        $deletedCount = CartItem::where('user_id', $user->id)->delete();

        // 3. التحقق من النتيجة
        if ($deletedCount === 0) {
            return response()->json(['message' => 'Cart is already empty'], 200);
        }

        return response()->json(['message' => 'Cart cleared successfully']);
    }
    //تعديل الكمية في السلة
    public function updateQty(Request $request, $id)
    {
        $user = auth()->user();

        // 1. التأكد من الصلاحية (أن المستخدم هو المشتري المخول)
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Forbidden: Only buyers can update cart'], 403);
        }

        // 2. البحث عن العنصر بشرطين: 
        // - الـ id الخاص بالعنصر
        // - الـ user_id الخاص بالمستخدم الحالي (لضمان أنه لا يعدل إلا سلة نفسه)
        $item = CartItem::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // 3. التحقق من المخزون
        if (!$this->validateStock($item->product, $request->qty)) {
            return response()->json(['message' => 'Requested quantity is not available in stock'], 400);
        }

        // 4. التحديث
        $item->update(['qty' => $request->qty]);

        return response()->json(['message' => 'Quantity updated successfully']);
    }

    // 4. حذف منتج
    public function removeItem($id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized, please log in'], 401);
        }

        // 2. الحذف المشروط (حذف عنصر يخص هذا المستخدم فقط)
        $deleted = CartItem::where('user_id', $user->id)
            ->where('id', $id)
            ->delete();
        if (!$deleted) {
            return response()->json(['message' => 'Item not found or already removed'], 404);
        }

        return response()->json(['message' => 'Product removed successfully']);
    }

    // 5. الـ Checkout (إنشاء الطلبات الفرعية)
  public function checkout()
{
    return \Illuminate\Support\Facades\DB::transaction(function () {
        $user = auth()->user();
        $items = \App\Models\CartItem::where('user_id', $user->id)->with('product')->get();
        
        if ($items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $calculateEffectivePrice = function ($item) {
            $product = $item->product;
            
            $priceOffer = ($product->offer_price && $product->offer_expires_at && now()->lessThan($product->offer_expires_at)) 
                          ? $product->offer_price : null;
            
            $priceWholesale = ($product->wholesale_price && $item->qty >= 10) 
                              ? $product->wholesale_price : null;

            if ($priceOffer && $priceWholesale) {
                return min($priceOffer, $priceWholesale);
            } elseif ($priceOffer) {
                return $priceOffer;
            } elseif ($priceWholesale) {
                return $priceWholesale;
            } else {
                return $product->original_price;
            }
        };

        $total = $items->sum(fn($i) => $calculateEffectivePrice($i) * $i->qty);

        $mainOrder = \App\Models\Order::create([
            'user_id' => $user->id, 
            'total_price' => $total
        ]);

        $grouped = $items->groupBy('seller_id');

        foreach ($grouped as $sellerId => $sellerItems) {
            // التعديل هنا: حفظ النتيجة في متغير $subOrder
            $subOrder = \App\Models\SubOrder::create([
                'order_id' => $mainOrder->id,
                'seller_id' => $sellerId,
                'total' => $sellerItems->sum(fn($i) => $calculateEffectivePrice($i) * $i->qty)
            ]);

            foreach ($sellerItems as $item) {
                // التحقق من المخزون قبل الإضافة
                if ($item->product->quantity < $item->qty) {
                    throw new \Exception("Product {$item->product->name} is out of stock");
                }

                // إضافة المنتج للجدول الجديد
                \App\Models\OrderItem::create([
                    'sub_order_id' => $subOrder->id, // أصبح المتغير معرفاً الآن
                    'product_id'   => $item->product_id,
                    'variant_id'   => $item->variant_id,
                    'quantity'     => $item->qty,
                    'price'        => $calculateEffectivePrice($item)
                ]);

                $item->product->decrement('quantity', $item->qty);
            }
        }

        \App\Models\CartItem::where('user_id', $user->id)->delete();
        
        return response()->json(['message' => 'Order placed successfully']);
    });
}
}
