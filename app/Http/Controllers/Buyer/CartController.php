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

    private function assertBuyer()
    {
        $user = auth()->user();
        if (!$user) {
            abort(response()->json(['message' => 'Unauthenticated'], 401));
        }
        if ($user->role !== 'buyer') {
            abort(response()->json(['message' => 'Forbidden'], 403));
        }

        // 3. جلب المنتج
        $product = Product::findOrFail($request->product_id);

        // 4. التحقق من المخزون
        if (!$this->validateStock($product, $request->qty)) {
            return response()->json(['message' => 'The requested quantity is not available in stock'], 400);
        }

        // 5. الحفظ (updateOrCreate)
        CartItem::updateOrCreate(
            [
                'user_id'    => $user->id,
                'product_id' => $product->id,
                'variant_id' => $request->variant_id,
            ],
            [
                'qty'       => $request->qty,
                'seller_id' => $product->user_id
            ]
        );

        //  تسجيل سلوك إضافة المنتج للسلة (Cart)
        try {
            \App\Models\UserBehavior::create([
                'user_id'     => $user->id,
                'action'      => 'cart',
                'product_id'  => $product->id,
                'category_id' => $product->department_id,
            ]);
        } catch (\Exception $e) {
            // تجاهل أي خطأ لضمان إتمام الإضافة للسلة بنجاح ودون انقطاع
        }

        return response()->json(['message' => 'Added to cart successfully']);
    }
    // 2. عرض السلة (مجمعة حسب التاجر)
    public function getCart()
    {
        $user = $this->assertBuyer();

        $items = CartItem::where('user_id', $user->id)
            ->with(['product.seller'])
            ->get();

        $groups = $this->buildStoreGroups($items);

        return response()->json([
            'success' => true,
            'message' => 'Cart retrieved successfully',
            'data'    => [
                'stores'           => $groups,
                'items_count'      => $items->sum('qty'),
                'grand_subtotal'   => round(collect($groups)->sum('subtotal'), 2),
            ],
        ]);
    }

    public function getShippingOptions(Request $request, $sellerId)
    {
        $this->assertBuyer();

        $seller = User::findOrFail($sellerId);
        $subtotal = (float) $request->query('subtotal', 0);
        $hasFree  = $request->boolean('free_shipping');

        return response()->json([
            'success' => true,
            'data'    => $this->shippingService->getOptionsForSeller($seller, $subtotal, $hasFree),
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

        $item = CartItem::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $max  = $this->availableStock($item->product, $item->variant_id);

        if ($request->qty > $max) {
            return response()->json([
                'success'   => false,
                'message'   => 'Requested quantity is not available in stock',
                'max_stock' => $max,
            ], 400);
        }

        $item->update(['qty' => $request->qty]);

        return response()->json([
            'success' => true,
            'message' => 'Quantity updated successfully',
            'data'    => $this->mapCartItem($item->fresh(['product'])),
        ]);
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
                        'sub_order_id' => $subOrder->id,
                        'product_id'   => $item->product_id,
                        'variant_id'   => $item->variant_id,
                        'quantity'     => $item->qty,
                        'price'        => $calculateEffectivePrice($item)
                    ]);

                    $item->product->decrement('quantity', $item->qty);

                    // 🎯 تسجيل سلوك الشراء (Buy) لربطه بخوارزمية التوصيات
                    try {
                        \App\Models\UserBehavior::create([
                            'user_id'     => $user->id,
                            'action'      => 'buy',
                            'product_id'  => $item->product_id,
                            'category_id' => $item->product->department_id,
                        ]);
                    } catch (\Exception $e) {
                        // تجاهل الخطأ لعدم التأثير على عملية الشراء والدفع
                    }
                }
            }

            \App\Models\CartItem::where('user_id', $user->id)->delete();

            return response()->json(['message' => 'Order placed successfully']);
        });
    }
}
