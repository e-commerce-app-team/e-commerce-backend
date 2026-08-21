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
use App\Models\Transaction;
use App\Models\User;
use App\Services\PriceCalculationService;
use App\Services\ShippingService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CartController extends Controller
{
    public function __construct(
        private ShippingService $shippingService,
        private PriceCalculationService $prices,
        private WalletService $wallet,
    ) {
    }

    private function assertBuyer(): User
    {
        $user = auth()->user();
        if (! $user) {
            abort(response()->json(['message' => 'Unauthenticated'], 401));
        }
        if ($user->role !== 'buyer') {
            abort(response()->json(['message' => 'Only buyers can use the cart'], 403));
        }

        return $user;
    }

    public function addToCart(Request $request)
    {
        $user = $this->assertBuyer();
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $product = Product::with('variants')->findOrFail($data['product_id']);
        $variant = $this->variantFor($product, $data['variant_id'] ?? null);
        $item = CartItem::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('variant_id', $data['variant_id'] ?? null)
            ->first();
        $newQty = (int) ($item?->qty ?? 0) + (int) $data['qty'];

        if ($newQty > $this->stockFor($product, $variant)) {
            return response()->json([
                'success' => false,
                'message' => 'The requested quantity is not available in stock.',
                'max_stock' => $this->stockFor($product, $variant),
            ], 422);
        }

        if ($item) {
            $item->update(['qty' => $newQty, 'seller_id' => $product->user_id]);
        } else {
            CartItem::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'seller_id' => $product->user_id,
                'qty' => $newQty,
            ]);
        }

        $this->recordCartBehavior($user, $product);
        return response()->json(['success' => true, 'message' => 'Product added to cart.'], 201);
    }

    public function getCart()
    {
        $user = $this->assertBuyer();
        $items = CartItem::where('user_id', $user->id)
            ->with(['product.category', 'product.seller', 'variant'])
            ->orderBy('id')
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

    private function buildStoreGroups($items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }
            $sellerId = (int) $product->user_id;
            $seller = $product->seller;
            if (! isset($groups[$sellerId])) {
                $groups[$sellerId] = [
                    'seller_id' => $sellerId,
                    'store_name' => $seller?->store_name ?: $this->userName($seller),
                    'store_logo' => $seller?->store_logo,
                    'subtotal' => 0.0,
                    'items_count' => 0,
                    'has_free_shipping' => false,
                    'items' => [],
                    'shipping_options' => [],
                ];
            }

            $quote = $this->prices->quote($product, (int) $item->qty, $item->variant);
            $groups[$sellerId]['subtotal'] += $quote['line_total'];
            $groups[$sellerId]['items_count'] += (int) $item->qty;
            $groups[$sellerId]['has_free_shipping'] = $groups[$sellerId]['has_free_shipping'] || (bool) $product->is_free_shipping;
            $groups[$sellerId]['items'][] = $this->mapItem($item, $quote);
        }

        foreach ($groups as &$group) {
            $seller = User::find($group['seller_id']);
            $group['shipping_options'] = $this->shippingService->getOptionsForSeller(
                $seller,
                (float) $group['subtotal'],
                (bool) $group['has_free_shipping'],
            );
            $group['subtotal'] = round($group['subtotal'], 2);
        }
        unset($group);

        return $groups;
    }

    public function getShippingOptions(Request $request, $sellerId)
    {
        $user = $this->assertBuyer();
        $seller = User::whereIn('role', ['vendor', 'wholesale'])->findOrFail($sellerId);
        $items = CartItem::where('user_id', $user->id)->where('seller_id', $seller->id)
            ->with(['product.category', 'variant'])->get();
        $subtotal = $items->sum(fn ($item) => $this->prices->quote($item->product, (int) $item->qty, $item->variant)['line_total']);
        $hasFree = $items->contains(fn ($item) => (bool) $item->product->is_free_shipping);

        return response()->json([
            'success' => true,
            'data' => $this->shippingService->getOptionsForSeller($seller, (float) $subtotal, $hasFree),
        ]);
    }

    public function updateQty(Request $request, $id)
    {
        $user = $this->assertBuyer();
        $data = $request->validate(['qty' => 'required|integer|min:1']);
        $item = CartItem::where('id', $id)->where('user_id', $user->id)
            ->with(['product.category', 'variant'])->first();
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Cart item not found.'], 404);
        }

        $max = $this->stockFor($item->product, $item->variant);
        if ((int) $data['qty'] > $max) {
            return response()->json([
                'success' => false,
                'message' => 'Requested quantity is not available in stock.',
                'max_stock' => $max,
            ], 422);
        }

        $item->update(['qty' => (int) $data['qty']]);
        $fresh = $item->fresh(['product.category', 'variant']);
        return response()->json(['success' => true, 'data' => $this->mapItem($fresh, $this->prices->quote($fresh->product, $fresh->qty, $fresh->variant))]);
    }

    public function removeItem($id)
    {
        $this->assertBuyer();
        $deleted = CartItem::where('id', $id)->where('user_id', auth()->id())->delete();
        if (! $deleted) {
            return response()->json(['success' => false, 'message' => 'Cart item not found.'], 404);
        }
        return response()->json(['success' => true, 'message' => 'Product removed successfully.']);
    }

    public function clearCart()
    {
        $this->assertBuyer();
        CartItem::where('user_id', auth()->id())->delete();
        return response()->json(['success' => true, 'message' => 'Cart cleared successfully.']);
    }

    /** Create an order request. Wallet payment starts only after seller shipping quote. */
    public function checkout(Request $request)
    {
        $user = $this->assertBuyer();
        $request->validate([
            'address_id' => 'required|integer',
            'driver_notes' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:100',
            'stores' => 'nullable|array',
            'stores.*.seller_id' => 'required|integer',
            'stores.*.shipping_option_id' => 'nullable|string|max:40',
            'stores.*.coupon_code' => 'nullable|string|max:80',
        ]);

        $key = trim((string) ($request->input('idempotency_key') ?: Str::uuid()));

        try {
            return DB::transaction(function () use ($request, $user, $key) {
                $buyer = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = Order::where('user_id', $buyer->id)->where('checkout_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    return $this->checkoutResponse($existing, true, $buyer);
                }

                $address = BuyerAddress::where('id', $request->integer('address_id'))
                    ->where('user_id', $buyer->id)->first();
                if (! $address) {
                    throw ValidationException::withMessages(['address_id' => 'The selected delivery address is invalid.']);
                }

                $cartItems = CartItem::where('user_id', $buyer->id)->lockForUpdate()->get();
                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'Cart is empty.']);
                }

                $productIds = $cartItems->pluck('product_id')->unique()->values();
                $products = Product::with('category')->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
                $variantIds = $cartItems->pluck('variant_id')->filter()->unique()->values();
                $variants = $variantIds->isEmpty()
                    ? collect()
                    : ProductVariant::whereIn('id', $variantIds)->lockForUpdate()->get()->keyBy('id');

                $prepared = [];
                $remainingStock = [];
                foreach ($cartItems as $cartItem) {
                    $product = $products->get($cartItem->product_id);
                    if (! $product || $product->status !== 'active') {
                        throw ValidationException::withMessages(['cart' => 'A product in the cart is no longer available.']);
                    }
                    $variant = $cartItem->variant_id ? $variants->get($cartItem->variant_id) : null;
                    if ($cartItem->variant_id && (! $variant || (int) $variant->product_id !== (int) $product->id || ! $variant->is_active)) {
                        throw ValidationException::withMessages(['cart' => 'A selected product variant is no longer available.']);
                    }

                    $stockKey = $variant ? 'variant:' . $variant->id : 'product:' . $product->id;
                    $remainingStock[$stockKey] ??= $this->stockFor($product, $variant);
                    if ($remainingStock[$stockKey] < (int) $cartItem->qty) {
                        throw ValidationException::withMessages(['cart' => "Insufficient stock for product #{$product->id}."]);
                    }
                    $remainingStock[$stockKey] -= (int) $cartItem->qty;
                    $quote = $this->prices->quote($product, (int) $cartItem->qty, $variant);
                    $prepared[] = compact('cartItem', 'product', 'variant', 'quote');
                }

                $storeRequests = collect($request->input('stores', []))->keyBy(fn ($store) => (string) ($store['seller_id'] ?? ''));
                $sellers = collect($prepared)->map(fn ($row) => (int) $row['product']->user_id)->unique()->values();
                $sellerModels = User::whereIn('id', $sellers)->whereIn('role', ['vendor', 'wholesale'])->get()->keyBy('id');
                $groups = [];
                $couponIdsUsed = [];

                foreach ($sellers as $sellerId) {
                    $seller = $sellerModels->get($sellerId);
                    if (! $seller) {
                        throw ValidationException::withMessages(['cart' => 'A seller for this cart is unavailable.']);
                    }
                    $rows = collect($prepared)->where(fn ($row) => (int) $row['product']->user_id === (int) $sellerId)->values();
                    $subtotal = round($rows->sum(fn ($row) => $row['quote']['line_total']), 2);
                    $baseSubtotal = round($rows->sum(fn ($row) => $row['quote']['base_subtotal']), 2);
                    $taxAmount = round($rows->sum(fn ($row) => $row['quote']['tax_amount']), 2);
                    $config = $storeRequests->get((string) $sellerId, []);
                    $productIdsForCoupon = $rows->map(fn ($row) => (int) $row['product']->id)->unique()->values()->all();
                    $coupon = null;
                    $discount = 0.0;
                    if (! empty($config['coupon_code'])) {
                        $coupon = Coupon::where('code', strtoupper(trim($config['coupon_code'])))
                            ->where('seller_id', $sellerId)->lockForUpdate()->first();
                        if (! $coupon) {
                            throw ValidationException::withMessages(['coupon' => 'The coupon does not belong to this store.']);
                        }
                        $validation = $coupon->isValid($buyer->id, $subtotal, $productIdsForCoupon);
                        if (! $validation['valid']) {
                            throw ValidationException::withMessages(['coupon' => $validation['message']]);
                        }
                        $discount = round($coupon->calculateDiscount($subtotal), 2);
                        $couponIdsUsed[] = $coupon->id;
                    }

                    $hasFreeProduct = $rows->contains(fn ($row) => (bool) $row['product']->is_free_shipping);
                    $options = $this->shippingService->getOptionsForSeller(
                        $seller,
                        $subtotal,
                        $hasFreeProduct || $coupon?->type === 'free_shipping',
                    );
                    $shippingId = (string) ($config['shipping_option_id'] ?? 'standard');
                    $shipping = $this->shippingService->resolveOption($options, $shippingId);
                    if (! $shipping) {
                        throw ValidationException::withMessages(['shipping' => 'The selected shipping option is not available.']);
                    }

                    $shippingCost = $shipping['cost'] === null ? null : round((float) $shipping['cost'], 2);
                    $total = round(max(0, $subtotal - $discount + ($shippingCost ?? 0)), 2);
                    $groups[$sellerId] = compact('seller', 'rows', 'subtotal', 'baseSubtotal', 'taxAmount', 'coupon', 'discount', 'shipping', 'shippingCost', 'total');
                }

                $total = round(collect($groups)->sum('total'), 2);
                if ($total <= 0) {
                    throw ValidationException::withMessages(['payment' => 'The order total must be greater than zero.']);
                }
                $firstSellerId = (int) array_key_first($groups);
                $shippingPending = collect($groups)->contains(fn ($group) => $group['shippingCost'] === null);
                $mainOrder = Order::create([
                    'user_id' => $buyer->id,
                    'seller_id' => $firstSellerId,
                    'total_price' => $total,
                    'subtotal_before_tax' => round(collect($groups)->sum('baseSubtotal'), 2),
                    'tax_amount' => round(collect($groups)->sum('taxAmount'), 2),
                    'tax_breakdown' => collect($prepared)->map(fn ($row) => [
                        'product_id' => $row['product']->id,
                        'quantity' => $row['cartItem']->qty,
                        'unit_price' => $row['quote']['base_unit_price'],
                        'tax_rate' => $row['quote']['tax_rate'],
                        'tax_amount' => $row['quote']['tax_amount'],
                    ])->values()->all(),
                    'status' => 'pending',
                    'payment_method' => 'wallet',
                    'payment_status' => 'unpaid',
                    'stock_reserved' => false,
                    'checkout_key' => $key,
                    'shipping_pending' => $shippingPending,
                    'shipping_address_title' => $address->title,
                    'shipping_address_details' => $address->details,
                    'address_id' => $address->id,
                    'shipping_lat' => $address->latitude,
                    'shipping_lng' => $address->longitude,
                    'customer_notes' => $request->input('driver_notes') ?: $address->driver_notes,
                    'discount_amount' => round(collect($groups)->sum('discount'), 2),
                    'commission_rate_snapshot' => 0,
                    'platform_commission' => 0,
                    'status_timeline' => [[
                        // Shipping approval is a separate concern from the
                        // order lifecycle. Even when every seller quote is
                        // free and auto-approved, the order must remain
                        // pending until the buyer pays into escrow.
                        'status' => 'pending',
                        'title' => $shippingPending ? 'order_submitted' : 'shipping_approved',
                        'time' => now()->toDateTimeString(),
                    ]],
                ]);

                $legacyPivot = [];
                foreach ($groups as $sellerId => $group) {
                    $subOrder = SubOrder::create([
                        'order_id' => $mainOrder->id,
                        'seller_id' => $sellerId,
                        'total' => $group['total'],
                        'escrow_amount' => 0,
                        'shipping_method' => $group['shipping']['id'],
                        'shipping_label' => $group['shipping']['name'],
                        'shipping_cost' => $group['shippingCost'],
                        'shipping_approved' => $group['shippingCost'] !== null && (float) $group['shippingCost'] === 0.0,
                        'shipping_approved_at' => $group['shippingCost'] !== null && (float) $group['shippingCost'] === 0.0 ? now() : null,
                        'estimated_delivery' => $group['shipping']['estimated_delivery'],
                        'coupon_id' => $group['coupon']?->id,
                        'discount_amount' => $group['discount'],
                        'status' => 'pending',
                    ]);

                    foreach ($group['rows'] as $row) {
                        $product = $row['product'];
                        $variant = $row['variant'];
                        $qty = (int) $row['cartItem']->qty;
                        $quote = $row['quote'];
                        OrderItem::create([
                            'sub_order_id' => $subOrder->id,
                            'product_id' => $product->id,
                            'variant_id' => $variant?->id,
                            'quantity' => $qty,
                            // Keep the legacy required column in sync with the
                            // newer unit_price snapshot used by checkout.
                            'price' => $quote['unit_price'],
                            'unit_price' => $quote['unit_price'],
                            'total_price' => $quote['line_total'],
                        ]);

                        $legacyPivot[$product->id] = [
                            'quantity' => ($legacyPivot[$product->id]['quantity'] ?? 0) + $qty,
                            'price' => $quote['unit_price'],
                        ];
                    }
                }
                $mainOrder->products()->sync($legacyPivot);

                foreach (array_unique($couponIdsUsed) as $couponId) {
                    $group = collect($groups)->first(fn ($group) => (int) $group['coupon']?->id === (int) $couponId);
                    $coupon = $group['coupon'];
                    $discount = $group['discount'];
                    // Coupon usage is committed only when the buyer pays the final total.
                }

                CartItem::where('user_id', $buyer->id)->whereIn('id', $cartItems->pluck('id'))->delete();
                return $this->checkoutResponse($mainOrder, false, $buyer);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            // Never expose an SQL exception to a buyer. The technical detail is
            // logged for us; the application receives a safe, actionable state.
            Log::error('Buyer order request could not be created.', [
                'buyer_id' => $buyer->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to submit the order request right now. Please try again.',
                'message_ar' => 'تعذر إرسال طلبك حاليًا. حاول مرة أخرى.',
            ], 422);
        }
    }

    private function checkoutResponse(Order $order, bool $duplicate = false, ?User $buyer = null)
    {
        $order = $order->fresh();
        return response()->json([
            'success' => true,
            'duplicate' => $duplicate,
            'message' => $duplicate ? 'Order request already submitted.' : 'Order request submitted. Waiting for seller shipping cost.',
            'order_id' => $order->id,
            'order_number' => '#' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            'order_status' => $order->status,
            'payment_status' => $order->payment_status,
            'shipping_pending' => (bool) $order->shipping_pending,
            'total_price' => (float) $order->total_price,
            'wallet' => $buyer ? $this->wallet->summary($buyer->fresh()) : null,
        ], $duplicate ? 200 : 201);
    }

    private function mapItem(CartItem $item, array $quote): array
    {
        $product = $item->product;
        $name = $this->localized($product->name);
        $images = is_array($product->images) ? $product->images : [];
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'name' => $name,
            'product_name' => $name,
            'image' => $images[0] ?? null,
            'price' => $quote['unit_price'],
            'unit_price' => $quote['unit_price'],
            'original_price' => (float) ($item->variant?->price ?: $product->original_price),
            'quantity' => (int) $item->qty,
            'qty' => (int) $item->qty,
            'max_stock' => $this->stockFor($product, $item->variant),
            'is_out_of_stock' => $this->stockFor($product, $item->variant) < (int) $item->qty,
            'total_price' => $quote['line_total'],
            'line_total' => $quote['line_total'],
            'variant' => $item->variant ? ($item->variant->name ?? $item->variant->attributes) : null,
        ];
    }

    private function variantFor(Product $product, $variantId): ?ProductVariant
    {
        if (! $variantId) {
            return null;
        }
        $variant = ProductVariant::whereKey($variantId)->where('product_id', $product->id)->first();
        if (! $variant || ! $variant->is_active) {
            throw ValidationException::withMessages(['variant_id' => 'The selected variant is invalid.']);
        }
        return $variant;
    }

    private function stockFor(Product $product, ?ProductVariant $variant): int
    {
        return (int) ($variant?->quantity ?? $product->quantity ?? 0);
    }

    private function localized($value): string
    {
        if (is_array($value)) {
            return (string) ($value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? reset($value));
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;
        if (is_array($decoded)) {
            return (string) ($decoded[app()->getLocale()] ?? $decoded['ar'] ?? $decoded['en'] ?? reset($decoded));
        }
        return (string) $value;
    }

    private function userName(?User $user): string
    {
        return trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')) ?: 'Store';
    }

    private function recordCartBehavior(User $user, Product $product): void
    {
        try {
            \App\Models\UserBehavior::create([
                'user_id' => $user->id,
                'action' => 'cart',
                'product_id' => $product->id,
                'category_id' => $product->department_id,
            ]);
        } catch (\Throwable) {
            // Analytics must not break a cart mutation.
        }
    }

    private function recordBuyBehavior(User $user, Product $product): void
    {
        try {
            \App\Models\UserBehavior::create([
                'user_id' => $user->id,
                'action' => 'buy',
                'product_id' => $product->id,
                'category_id' => $product->department_id,
            ]);
        } catch (\Throwable) {
            // Analytics must not break an atomic checkout.
        }
    }
}
