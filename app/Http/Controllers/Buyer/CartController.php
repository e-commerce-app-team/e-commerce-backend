<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function __construct(private ShippingService $shippingService)
    {
    }

    // 1. تابع التحقق من المشتري فقط (Private Helper)
    private function assertBuyer()
    {
        $user = auth()->user();
        if (!$user) {
            abort(response()->json(['message' => 'Unauthenticated'], 401));
        }
        if ($user->role !== 'buyer') {
            abort(response()->json(['message' => 'Forbidden'], 403));
        }

        return $user;
    }

    // 2. تابع الإضافة للسلة المنفصل (Public Action)
    public function addToCart(Request $request)
    {
        // التحقق من الصلاحية وجلب المستخدم
        $user = $this->assertBuyer();

        // التحقق من المدخلات
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        // جلب المنتج
        $product = Product::findOrFail($request->product_id);

        // التحقق من المخزون (تمرير الـ variant_id أيضاً)
        if (!$this->validateStock($product, $request->qty, $request->variant_id)) {
            return response()->json(['message' => 'The requested quantity is not available in stock'], 400);
        }

        // الحفظ أو التحديث في السلة
        CartItem::updateOrCreate(
            [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'variant_id' => $request->variant_id,
            ],
            [
                'qty' => $request->qty,
                'seller_id' => $product->user_id,
            ]
        );

        // تسجيل سلوك إضافة المنتج للسلة
        try {
            \App\Models\UserBehavior::create([
                'user_id' => $user->id,
                'action' => 'cart',
                'product_id' => $product->id,
                'category_id' => $product->department_id,
            ]);
        } catch (\Exception $e) {
            // تجاهل الخطأ لعدم تعطيل الإضافة
        }

        return response()->json(['message' => 'Added to cart successfully']);
    }

    // تابع فحص المخزون المساعد (باستخدام حقل quantity)
    private function validateStock($product, $qty, $variantId = null)
    {
        // 1. إذا كان المنتج يتضمن متغيرات (Variants)
        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            if ($variant) {
                return $variant->quantity >= $qty;
            }
        }

        // 2. الفحص من مخزون المنتج الرئيسي عبر حقل quantity
        return $product->quantity >= $qty;
    }
    // 2. عرض السلة (مجمعة حسب التاجر)
    /// 1. عرض السلة (مجمعة حسب التاجر)
    public function getCart()
    {
        $user = $this->assertBuyer();

        // جلب عناصر السلة مع التاجر عبر علاقة seller والمنتج والـ Variant إن وجد
        $items = CartItem::where('user_id', $user->id)
            ->with(['product.seller', 'variant'])
            ->get();

        $groups = $this->buildStoreGroups($items);

        return response()->json([
            'success' => true,
            'message' => 'Cart retrieved successfully',
            'data' => [
                'stores' => array_values($groups),
                'items_count' => (int) $items->sum('qty'),
                'grand_subtotal' => round(collect($groups)->sum('subtotal'), 2),
            ],
        ]);
    }

    // 2. التابع المساعد لتجميع العناصر حسب المتجر/التاجر
    private function buildStoreGroups($items)
    {
        $groups = [];

        foreach ($items as $item) {
            $sellerId = $item->seller_id ?? $item->product->user_id;

            if (!isset($groups[$sellerId])) {
                // جلب اسم المتجر أو اسم التاجر الحقيقي
                $seller = $item->product->seller ?? User::find($sellerId);
                $storeName = $seller->store_name ?? $seller->name ?? ('Store #' . $sellerId);

                $groups[$sellerId] = [
                    'seller_id' => $sellerId,
                    'store_name' => $storeName,
                    'subtotal' => 0,
                    'items' => [],
                ];
            }

            // فك ترجمة اسم المنتج إذا كان مخزناً كـ JSON
            $productName = $item->product->name;
            if (is_string($productName)) {
                $decoded = json_decode($productName, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $locale = app()->getLocale(); // اللغّة الحالية للمشروع
                    $productName = $decoded[$locale] ?? $decoded['ar'] ?? $decoded['en'] ?? reset($decoded);
                }
            }

            $price = $item->product->offer_price ?? $item->product->original_price;
            $itemTotal = $price * $item->qty;

            $groups[$sellerId]['subtotal'] += $itemTotal;
            $groups[$sellerId]['items'][] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $productName,
                'image' => $item->product->image,
                'price' => round($price, 2),
                'qty' => $item->qty,
                'total_price' => round($itemTotal, 2),
                'variant' => $item->variant ? [
                    'id' => $item->variant->id,
                    'name' => $item->variant->name ?? null,
                ] : null,
            ];
        }

        return $groups;
    }

    public function getShippingOptions(Request $request, $sellerId)
    {
        $this->assertBuyer();

        $seller = User::findOrFail($sellerId);
        $subtotal = (float) $request->query('subtotal', 0);
        $hasFree = $request->boolean('free_shipping');

        return response()->json([
            'success' => true,
            'data' => $this->shippingService->getOptionsForSeller($seller, $subtotal, $hasFree),
        ]);
    }

    public function clearCart()
    {
        $user = $this->assertBuyer();
        CartItem::where('user_id', $user->id)->delete();

        return response()->json(['success' => true, 'message' => 'Cart cleared successfully']);
    }

    public function updateQty(Request $request, $id)
    {
        $user = $this->assertBuyer();

        $request->validate(['qty' => 'required|integer|min:1']);

        $item = CartItem::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['product', 'variant'])
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found or does not belong to this user'
            ], 404);
        }

        // جلب أقصى كمية متوفرة في المخزون
        $max = $this->availableStock($item);

        if ($request->qty > $max) {
            return response()->json([
                'success' => false,
                'message' => 'Requested quantity is not available in stock',
                'max_stock' => $max,
            ], 400);
        }

        // تحديث الكمية
        $item->update(['qty' => $request->qty]);

        return response()->json([
            'success' => true,
            'message' => 'Quantity updated successfully',
            'data' => $this->mapCartItem($item->fresh(['product', 'variant'])),
        ]);
    }

    // 2. تابع فحص المخزون المباشر والآمن
    private function availableStock($item)
    {
        // أ) إذا كان العنصر يحتوي على Variant
        if ($item->variant_id) {
            $variant = ProductVariant::find($item->variant_id);
            if ($variant) {
                return (int) $variant->quantity;
            }
        }

        // ب) الفحص المباشر من جدول المنتجات عبر product_id
        $product = Product::find($item->product_id);

        return $product ? (int) $product->quantity : 0;
    }

    // 2. تابع تنسيق مخرجات عنصر السلة (Map Item Response)
    private function mapCartItem($item)
    {
        $price = $item->product->offer_price ?? $item->product->original_price;

        // فك ترجمة اسم المنتج
        $productName = $item->product->name;
        if (is_string($productName)) {
            $decoded = json_decode($productName, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $locale = app()->getLocale();
                $productName = $decoded[$locale] ?? $decoded['ar'] ?? $decoded['en'] ?? reset($decoded);
            }
        }

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $productName,
            'image' => $item->product->image,
            'price' => round($price, 2),
            'qty' => (int) $item->qty,
            'total_price' => round($price * $item->qty, 2),
            'variant' => $item->variant ? [
                'id' => $item->variant->id,
                'name' => $item->variant->name ?? null,
            ] : null,
        ];
    }
    public function removeItem($id)
    {
        $user = $this->assertBuyer();

        $deleted = CartItem::where('user_id', $user->id)->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['message' => 'Item not found or already removed'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Product removed successfully']);
    }

        // 5. الـ Checkout (إنشاء الطلبات الفرعية)
    public function checkout()
    {
        return DB::transaction(function () {
            $user = auth()->user();
            $items = CartItem::where('user_id', $user->id)->with('product')->get();

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

            $mainOrder = Order::create([
                'user_id' => $user->id,
                'total_price' => $total
            ]);

            $grouped = $items->groupBy('seller_id');

            foreach ($grouped as $sellerId => $sellerItems) {
                $subOrder = SubOrder::create([
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
                    OrderItem::create([
                        'sub_order_id' => $subOrder->id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'quantity' => $item->qty,
                        'price' => $calculateEffectivePrice($item)
                    ]);

                    $item->product->decrement('quantity', $item->qty);

                    // 🎯 تسجيل سلوك الشراء (Buy) لربطه بخوارزمية التوصيات
                    try {
                        \App\Models\UserBehavior::create([
                            'user_id' => $user->id,
                            'action' => 'buy',
                            'product_id' => $item->product_id,
                            'category_id' => $item->product->department_id,
                        ]);
                    } catch (\Exception $e) {
                        // تجاهل الخطأ لعدم التأثير على عملية الشراء والدفع
                    }
                }
            }

            CartItem::where('user_id', $user->id)->delete();

            return response()->json(['message' => 'Order placed successfully']);
        });
    }
}
