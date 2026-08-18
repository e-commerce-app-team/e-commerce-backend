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
        return $user;
    }

    private function availableStock(Product $product, ?int $variantId): int
    {
        if ($variantId) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('id', $variantId)
                ->first();
            if ($variant) {
                return (int) $variant->quantity;
            }
        }
        return (int) $product->quantity;
    }

    private function effectiveUnitPrice(Product $product, int $qty): float
    {
        $priceOffer = ($product->offer_price && $product->offer_expires_at && now()->lessThan($product->offer_expires_at))
            ? (float) $product->offer_price
            : null;

        $priceWholesale = ($product->wholesale_price && $qty >= 10)
            ? (float) $product->wholesale_price
            : null;

        if ($priceOffer && $priceWholesale) {
            return min($priceOffer, $priceWholesale);
        }
        if ($priceOffer) {
            return $priceOffer;
        }
        if ($priceWholesale) {
            return $priceWholesale;
        }

        return (float) $product->original_price;
    }

    private function storageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        return url('storage/' . ltrim($path, '/'));
    }

    private function formatVariantLabel(?ProductVariant $variant): ?string
    {
        if (!$variant) {
            return null;
        }
        $attrs = $variant->attributes ?? [];
        if (!is_array($attrs) || empty($attrs)) {
            return null;
        }
        return collect($attrs)->map(fn ($v, $k) => "$k: $v")->implode(' — ');
    }

    private function mapCartItem(CartItem $item): array
    {
        $product  = $item->product;
        $variant  = $item->variant_id
            ? ProductVariant::find($item->variant_id)
            : null;
        $maxStock = $this->availableStock($product, $item->variant_id);
        $price    = $this->effectiveUnitPrice($product, $item->qty);
        $original = (float) $product->original_price;
        $images   = $product->images ?? [];
        $image    = is_array($images) && count($images) > 0
            ? $this->storageUrl($images[0])
            : null;

        return [
            'id'             => $item->id,
            'product_id'     => $item->product_id,
            'variant_id'     => $item->variant_id,
            'name'           => $product->name,
            'image'          => $image,
            'price'          => round($price, 2),
            'original_price' => $original > $price ? round($original, 2) : null,
            'quantity'       => (int) $item->qty,
            'max_stock'      => $maxStock,
            'is_out_of_stock'=> $maxStock <= 0,
            'variant'        => $this->formatVariantLabel($variant),
            'line_total'     => round($price * $item->qty, 2),
            'free_shipping'  => (bool) $product->is_free_shipping,
        ];
    }

    private function buildStoreGroups($items): array
    {
        $sellerIds = $items->pluck('seller_id')->unique()->filter();
        $sellers   = User::whereIn('id', $sellerIds)->get()->keyBy('id');

        $groups = [];
        foreach ($items->groupBy('seller_id') as $sellerId => $sellerItems) {
            $seller      = $sellers->get($sellerId);
            $mappedItems = $sellerItems->map(fn ($i) => $this->mapCartItem($i))->values();
            $subtotal    = $mappedItems->sum('line_total');
            $hasFreeShip = $mappedItems->contains(fn ($i) => $i['free_shipping']);

            $groups[] = [
                'seller_id'        => (int) $sellerId,
                'store_name'       => $seller?->store_name ?? trim(($seller?->first_name ?? '') . ' ' . ($seller?->last_name ?? '')),
                'store_logo'       => $this->storageUrl($seller?->store_logo),
                'items'            => $mappedItems,
                'items_count'      => $mappedItems->sum('quantity'),
                'subtotal'         => round($subtotal, 2),
                'has_free_shipping'=> $hasFreeShip,
                'shipping_options' => $seller
                    ? $this->shippingService->getOptionsForSeller($seller, $subtotal, $hasFreeShip)
                    : [],
            ];
        }

        return $groups;
    }

    public function addToCart(Request $request)
    {
        $user = $this->assertBuyer();

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty'        => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $product = Product::findOrFail($request->product_id);
        $max     = $this->availableStock($product, $request->variant_id);

        if ($request->qty > $max) {
            return response()->json(['message' => 'The requested quantity is not available in stock'], 400);
        }

        CartItem::updateOrCreate(
            [
                'user_id'    => $user->id,
                'product_id' => $product->id,
                'variant_id' => $request->variant_id,
            ],
            [
                'qty'       => $request->qty,
                'seller_id' => $product->user_id,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Added to cart successfully']);
    }

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

    public function checkout(Request $request)
    {
        $user = $this->assertBuyer();

        $validated = $request->validate([
            'address_id'   => 'required|exists:buyer_addresses,id',
            'driver_notes' => 'nullable|string|max:500',
            'stores'       => 'required|array|min:1',
            'stores.*.seller_id'           => 'required|integer|exists:users,id',
            'stores.*.shipping_option_id'  => 'required|string',
            'stores.*.coupon_code'         => 'nullable|string',
        ]);

        $address = BuyerAddress::where('user_id', $user->id)
            ->findOrFail($validated['address_id']);

        return DB::transaction(function () use ($user, $validated, $address) {
            $items = CartItem::where('user_id', $user->id)->with('product')->get();
            if ($items->isEmpty()) {
                return response()->json(['message' => 'Cart is empty'], 400);
            }

            foreach ($items as $item) {
                $max = $this->availableStock($item->product, $item->variant_id);
                if ($item->qty > $max) {
                    return response()->json([
                        'message' => "Product {$item->product->name} is out of stock",
                    ], 400);
                }
            }

            $storePayload = collect($validated['stores'])->keyBy('seller_id');
            $grouped      = $items->groupBy('seller_id');
            $grandTotal   = 0.0;
            $totalDiscount = 0.0;
            $totalShipping = 0.0;
            $subOrdersData = [];

            foreach ($grouped as $sellerId => $sellerItems) {
                $sellerConfig = $storePayload->get((string) $sellerId)
                    ?? $storePayload->get((int) $sellerId);

                if (!$sellerConfig) {
                    return response()->json(['message' => "Missing checkout config for seller $sellerId"], 422);
                }

                $seller   = User::findOrFail($sellerId);
                $subtotal = $sellerItems->sum(fn ($i) => $this->effectiveUnitPrice($i->product, $i->qty) * $i->qty);
                $hasFree  = $sellerItems->contains(fn ($i) => $i->product->is_free_shipping);
                $options  = $this->shippingService->getOptionsForSeller($seller, $subtotal, $hasFree);
                $shipping = $this->shippingService->resolveOption($options, $sellerConfig['shipping_option_id']);

                if (!$shipping) {
                    return response()->json(['message' => 'Invalid shipping option selected'], 422);
                }

                $discount = 0.0;
                $couponId = null;
                $productIds = $sellerItems->pluck('product_id')->all();

                if (!empty($sellerConfig['coupon_code'])) {
                    $coupon = Coupon::where('code', strtoupper(trim($sellerConfig['coupon_code'])))->first();
                    if (!$coupon || (int) $coupon->seller_id !== (int) $sellerId) {
                        return response()->json(['message' => 'Invalid coupon for this store'], 400);
                    }

                    $validation = $coupon->isValid($user->id, $subtotal, $productIds);
                    if (!$validation['valid']) {
                        return response()->json(['message' => $validation['message']], 400);
                    }

                    $discount = $coupon->calculateDiscount($subtotal);
                    if ($coupon->type === 'free_shipping') {
                        $shipping['cost'] = 0;
                    }
                    $couponId = $coupon->id;
                }

                $storeTotal = max(0, $subtotal - $discount + (float) $shipping['cost']);
                $grandTotal += $storeTotal;
                $totalDiscount += $discount;
                $totalShipping += (float) $shipping['cost'];

                $subOrdersData[] = [
                    'seller_id'           => $sellerId,
                    'items'               => $sellerItems,
                    'subtotal'            => $subtotal,
                    'discount'            => $discount,
                    'coupon_id'           => $couponId,
                    'shipping_method'     => $shipping['id'],
                    'shipping_label'      => $shipping['name'],
                    'shipping_cost'       => (float) $shipping['cost'],
                    'estimated_delivery'  => $shipping['estimated_delivery'],
                    'total'               => $storeTotal,
                ];
            }

            $mainOrder = Order::create([
                'user_id'                  => $user->id,
                'total_price'              => round($grandTotal, 2),
                'status'                   => 'pending',
                'payment_status'           => 'unpaid',
                'payment_method'           => 'wallet',
                'shipping_address_title'   => $address->title,
                'shipping_address_details' => $address->details,
                'customer_notes'           => $validated['driver_notes'] ?? $address->driver_notes,
                'address_id'               => $address->id,
                'shipping_lat'             => $address->latitude,
                'shipping_lng'             => $address->longitude,
                'discount_amount'          => round($totalDiscount, 2),
                'status_timeline'          => [[
                    'status' => 'pending',
                    'title'  => 'Order created — awaiting payment',
                    'time'   => now()->toDateTimeString(),
                ]],
            ]);

            foreach ($subOrdersData as $subData) {
                $subOrder = SubOrder::create([
                    'order_id'            => $mainOrder->id,
                    'seller_id'           => $subData['seller_id'],
                    'total'               => round($subData['total'], 2),
                    'shipping_method'     => $subData['shipping_method'],
                    'shipping_label'      => $subData['shipping_label'],
                    'shipping_cost'       => $subData['shipping_cost'],
                    'estimated_delivery'  => $subData['estimated_delivery'],
                    'coupon_id'           => $subData['coupon_id'],
                    'discount_amount'     => round($subData['discount'], 2),
                    'status'              => 'pending',
                ]);

                if ($subData['coupon_id']) {
                    Coupon::where('id', $subData['coupon_id'])->increment('used_count');
                    CouponUsage::create([
                        'coupon_id'                   => $subData['coupon_id'],
                        'user_id'                     => $user->id,
                        'order_id'                    => $mainOrder->id,
                        'discount_amount'             => $subData['discount'],
                        'order_total_before_discount' => $subData['subtotal'],
                        'order_total_after_discount'  => $subData['total'],
                    ]);
                }

                foreach ($subData['items'] as $item) {
                    OrderItem::create([
                        'sub_order_id' => $subOrder->id,
                        'product_id'   => $item->product_id,
                        'variant_id'   => $item->variant_id,
                        'quantity'     => $item->qty,
                        'price'        => $this->effectiveUnitPrice($item->product, $item->qty),
                    ]);

                    $item->product->decrement('quantity', $item->qty);
                }
            }

            CartItem::where('user_id', $user->id)->delete();

            return response()->json([
                'success'        => true,
                'message'        => 'Order created successfully. Proceed to payment.',
                'order_id'       => $mainOrder->id,
                'order_number'   => '#' . str_pad((string) $mainOrder->id, 6, '0', STR_PAD_LEFT),
                'total_price'    => round($grandTotal, 2),
                'shipping_total' => round($totalShipping, 2),
                'discount_total' => round($totalDiscount, 2),
                'payment_status' => 'unpaid',
            ], 201);
        });
    }
}
