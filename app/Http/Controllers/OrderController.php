<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;  // 🔥🔥🔥 أضف هذا السطر

class OrderController extends Controller
{



    // 1. إنشاء الطلب (يبقى pending وبدون تغيير جوهري)
// ==========================================
    /*   public function store(Request $request)
      {
          $buyer = auth()->user();

          $request->validate([
              'seller_id' => [
                  'required',
                  Rule::exists('users', 'id')->where(function ($query) {
                      $query->whereIn('role', ['vendor', 'wholesale']);
                  }),
              ],
              'items' => 'required|array|min:1',
              'items.*.product_id' => 'required|exists:products,id',
              'items.*.quantity' => 'required|integer|min:1',
              'shipping_address_title' => 'nullable|string|max:255',
              'shipping_address_details' => 'required|string',
              'customer_notes' => 'nullable|string',
              'payment_method' => 'nullable|string'
          ]);

          $calculatedSubtotal = 0;
          $calculatedTotalPrice = 0;
          $validatedItems = [];
          $vatRate = 0.15;

          foreach ($request->input('items') as $item) {
              $product = Product::find($item['product_id']);
              if ($product) {
                  $basePrice = ($product->offer_price && $product->offer_expires_at && $product->offer_expires_at->isFuture())
                      ? $product->offer_price
                      : $product->original_price;

                  $itemSubtotal = $basePrice * $item['quantity'];
                  $calculatedSubtotal += $itemSubtotal;

                  $priceWithVat = $basePrice * (1 + $vatRate);
                  $itemTotalPrice = $priceWithVat * $item['quantity'];
                  $calculatedTotalPrice += $itemTotalPrice;

                  $validatedItems[] = [
                      'product' => $product,
                      'quantity' => $item['quantity'],
                      'price' => $priceWithVat
                  ];
              }
          }

          $totalVatAmount = $calculatedTotalPrice - $calculatedSubtotal;

          $order = Order::create([
              'user_id' => $buyer->id,
              'seller_id' => $request->seller_id,
              'total_price' => round($calculatedTotalPrice, 2),
              'status' => 'pending',
              'payment_status' => 'unpaid', // تبدأ غير مدفوعة
              'payment_method' => $request->input('payment_method', 'wallet'),
              'shipping_address_title' => $request->shipping_address_title,
              'shipping_address_details' => $request->shipping_address_details,
              'customer_notes' => $request->customer_notes,
              'status_timeline' => [
                  [
                      'status' => 'pending',
                      'title' => 'تم استلام الطلب بنجاح وهو قيد الانتظار وبانتظار الدفع',
                      'time' => now()->toDateTimeString()
                  ]
              ]
          ]);

          $syncData = [];
          foreach ($validatedItems as $validatedItem) {
              $syncData[$validatedItem['product']->id] = [
                  'quantity' => $validatedItem['quantity'],
                  'price' => round($validatedItem['price'], 2)
              ];
              $validatedItem['product']->increment('sales_count', $validatedItem['quantity']);
          }
          $order->products()->attach($syncData);

          return response()->json([
              'success' => true,
              'message' => 'Order created successfully. Please proceed to payment.',
              'order_id' => $order->id,
              'order_status' => 'pending',
              'pricing_details' => [
                  'subtotal_before_vat' => round($calculatedSubtotal, 2),
                  'vat_amount' => round($totalVatAmount, 2),
                  'total_after_vat' => round($calculatedTotalPrice, 2)
              ]
          ], 201);
      } */

    public function store(Request $request)
    {
        $buyer = auth()->user();

        $request->validate([
            'seller_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereIn('role', ['vendor', 'wholesale']);
                }),
            ],
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address_title' => 'nullable|string|max:255',
            'shipping_address_details' => 'required|string',
            'customer_notes' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'coupon_code' => 'nullable|string|exists:coupons,code'
        ]);

        // 🔥🔥🔥 التحقق من أن جميع المنتجات تخص البائع المحدد
        $productIds = collect($request->input('items'))->pluck('product_id')->toArray();
        $products = Product::whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            if ($product->user_id != $request->seller_id) {
                return response()->json([
                    'success' => false,
                    'message' => "Product '{$product->name}' (ID: {$product->id}) does not belong to the selected seller.",
                ], 400);
            }
        }

        $calculatedSubtotal = 0;
        $calculatedTotalPrice = 0;
        $validatedItems = [];
        $productIds = [];

        foreach ($request->input('items') as $item) {
            // تحميل علاقة القسم لاستخدامها في حساب الضريبة الديناميكية
            $product = Product::with('category')->find($item['product_id']);
            if ($product) {
                $basePrice = ($product->offer_price && $product->offer_expires_at && $product->offer_expires_at->isFuture())
                    ? $product->offer_price
                    : $product->original_price;

                // تجميع بيانات المنتجات لحساب الضريبة لاحقاً، بدون تضمين الضريبة في base_price
                $validatedItems[] = [
                    'product'    => $product,
                    'quantity'   => $item['quantity'],
                    'base_price' => (float) $basePrice,
                ];
            }
        }

        // حساب الضريبة باستخدام TaxService (كل منتج بمعدله الخاص)
        $taxService = app(\App\Services\TaxService::class);
        $taxResult  = $taxService->calculateOrderTax($validatedItems);

        // تجميع معرفات المنتجات للكاش Cache
        foreach ($validatedItems as $vi) {
            $productIds[] = $vi['product']->id;
        }
        $productIds = array_unique($productIds);

        // تخزين البيانات في Cache لاستخدامها في عملية الدفع لاحقاً
        Cache::put('order_total_' . $buyer->id, $taxResult['total'], 3600);
        Cache::put('order_product_ids_' . $buyer->id, $productIds, 3600);
        Cache::put('order_items_' . $buyer->id, $request->input('items'), 3600);

        // 🔥 معالجة الكوبون مع تشخيص
        $couponId = null;
        $discountAmount = 0;
        $calculatedTotalPrice = $taxResult['total'];
        $finalPrice = $calculatedTotalPrice;

        if ($request->filled('coupon_code')) {
            $coupon = Coupon::where('code', strtoupper($request->coupon_code))->first();

            if ($coupon) {
                $validation = $coupon->isValid(
                    $buyer->id,
                    $calculatedTotalPrice,
                    $productIds
                );

                if (!$validation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Coupon validation failed: ' . $validation['message'],
                        'debug' => [
                            'coupon' => [
                                'id' => $coupon->id,
                                'code' => $coupon->code,
                                'is_active' => $coupon->is_active,
                                'starts_at' => $coupon->starts_at,
                                'expires_at' => $coupon->expires_at,
                                'min_order_amount' => $coupon->min_order_amount,
                                'max_uses' => $coupon->max_uses,
                                'used_count' => $coupon->used_count,
                                'usage_limit_per_user' => $coupon->usage_limit_per_user,
                                'apply_to_all_products' => $coupon->apply_to_all_products,
                                'product_ids' => $coupon->product_ids,
                            ],
                            'order_total' => $calculatedTotalPrice,
                            'product_ids_in_cart' => $productIds,
                            'validation_message' => $validation['message']
                        ]
                    ], 400);
                }

                if ($validation['valid']) {
                    $discountAmount = $coupon->calculateDiscount($taxResult['total']);
                    $finalPrice = $taxResult['total'] - $discountAmount;
                    $couponId = $coupon->id;

                    $coupon->increment('used_count');

                    CouponUsage::create([
                        'coupon_id' => $coupon->id,
                        'user_id'   => $buyer->id,
                        'order_id'  => null,
                        'discount_amount'              => $discountAmount,
                        'order_total_before_discount'  => $taxResult['total'],
                        'order_total_after_discount'   => $taxResult['total'] - $discountAmount
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Coupon not found: ' . $request->coupon_code
                ], 404);
            }
        }

        $order = Order::create([
            'user_id'                  => $buyer->id,
            'seller_id'                => $request->seller_id,
            'total_price'              => round($taxResult['total'] - $discountAmount, 2),
            'subtotal_before_tax'      => $taxResult['subtotal'],
            'tax_amount'               => $taxResult['tax_amount'],
            'tax_breakdown'            => $taxResult['breakdown'],
            'status'                   => 'pending',
            'payment_status'           => 'unpaid',
            'payment_method'           => $request->input('payment_method', 'wallet'),
            'shipping_address_title'   => $request->shipping_address_title,
            'shipping_address_details' => $request->shipping_address_details,
            'customer_notes'           => $request->customer_notes,
            'coupon_id'                => $couponId,
            'discount_amount'          => round($discountAmount, 2),
            'commission_rate_snapshot' => 0, // تُحدد وقت الدفع
            'status_timeline'          => [
                [
                    'status' => 'pending',
                    'title'  => 'تم استلام الطلب بنجاح وهو قيد الانتظار وبانتظار الدفع',
                    'time'   => now()->toDateTimeString()
                ]
            ]
        ]);

        if ($couponId) {
            CouponUsage::where('coupon_id', $couponId)
                ->where('user_id', $buyer->id)
                ->whereNull('order_id')
                ->latest()
                ->first()
                    ?->update(['order_id' => $order->id]);
        }

        $syncData = [];
        foreach ($validatedItems as $validatedItem) {
            $syncData[$validatedItem['product']->id] = [
                'quantity' => $validatedItem['quantity'],
                // حفظ سعر الوحدة شاملاً الضريبة في الجدول الوسيط
                'price' => round(
                    $validatedItem['base_price'] * (1 + $validatedItem['product']->effectiveTaxRate() / 100),
                    2
                ),
            ];
            $validatedItem['product']->increment('sales_count', $validatedItem['quantity']);
        }
        $order->products()->attach($syncData);

        // 🔥 تنظيف Cache بعد إنشاء الطلب (اختياري)
        // Cache::forget('order_total_' . $buyer->id);
        // Cache::forget('order_product_ids_' . $buyer->id);
        // Cache::forget('order_items_' . $buyer->id);

        return response()->json([
            'success'         => true,
            'message'         => 'Order created successfully. Please proceed to payment.',
            'order_id'        => $order->id,
            'order_status'    => 'pending',
            'pricing_details' => [
                'subtotal_before_tax' => $taxResult['subtotal'],
                'tax_amount'          => $taxResult['tax_amount'],
                'total_after_tax'     => $taxResult['total'],
                'discount_amount'     => round($discountAmount, 2),
                'final_total'         => round($taxResult['total'] - $discountAmount, 2),
                'tax_breakdown'       => $taxResult['breakdown'],
            ]
        ], 201);
    }
    /**
     * 2. تحديث حالة الطلب والـ Timeline (مسموح فقط للبائع صاحب الطلب)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled_returned'
        ]);

        // تأمين: التاجر صاحب الطلب هو الوحيد المخول بالتعديل
        $order = Order::where('seller_id', auth()->id())->findOrFail($id);
        $newStatus = $request->status;

        $timeline = $order->status_timeline ?? [];
        $timeline[] = [
            'status' => $newStatus,
            'title' => $this->getStatusArabicTitle($newStatus),
            'time' => now()->toDateTimeString()
        ];

        // التحكم الآلي بحالة الضمان المالي Escrow بناءً على التحديث
        $paymentStatus = $order->payment_status;
        if ($newStatus === 'delivered') {
            $paymentStatus = 'released'; // فك الحجز وإرسال الأموال للبائع
        } elseif ($newStatus === 'cancelled_returned') {
            $paymentStatus = 'refunded'; // إرجاع المال للمشتري
        }

        $order->update([
            'status' => $newStatus,
            'payment_status' => $paymentStatus,
            'status_timeline' => $timeline
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated and timeline recorded successfully.',
            'order' => $order
        ]);
    }

    /**
     * 3. جلب تفاصيل الطلب الكاملة (محمي: للمشتري أو البائع الفعلي للطلب فقط)
     */
    /*  public function show($id)
     {
         $order = Order::with(['buyer:id,first_name,last_name,email,phone', 'products'])
             ->where(function ($query) {
                 $query->where('user_id', auth()->id())
                     ->orWhere('seller_id', auth()->id());
             })
             ->findOrFail($id);

         return response()->json([
             'success' => true,
             'data' => [
                 'order_id' => $order->id,
                 'date_time' => $order->created_at->toDateTimeString(),
                 'total_price' => $order->total_price,
                 'status' => $order->status,
                 'payment_status' => $order->payment_status,
                 'payment_method' => $order->payment_method,
                 'shipping' => [
                     'title' => $order->shipping_address_title,
                     'details' => $order->shipping_address_details,
                 ],
                 'buyer' => $order->buyer,
                 'products' => $order->products->map(function ($product) {
                     return [
                         'id' => $product->id,
                         'name' => $product->name,
                         'quantity' => $product->pivot->quantity,
                         'price' => $product->pivot->price,
                     ];
                 }),
                 'timeline' => $order->status_timeline,
                 'customer_notes' => $order->customer_notes
             ]
         ]);
     } */
    public function show($id)
    {
        $order = Order::with([
            'buyer:id,first_name,last_name,email,phone',
            'products',
            'coupon' // 🔥 إضافة علاقة الكوبون
        ])
            ->where(function ($query) {
                $query->where('user_id', auth()->id())
                    ->orWhere('seller_id', auth()->id());
            })
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'date_time' => $order->created_at->toDateTimeString(),
                'total_price' => $order->total_price,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'shipping' => [
                    'title' => $order->shipping_address_title,
                    'details' => $order->shipping_address_details,
                ],
                'buyer' => $order->buyer,
                'products' => $order->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $product->pivot->quantity,
                        'price' => $product->pivot->price,
                    ];
                }),
                // 🔥 إضافة معلومات الكوبون
                'coupon' => $order->coupon ? [
                    'code' => $order->coupon->code,
                    'title' => $order->coupon->title,
                    'type' => $order->coupon->type,
                    'value' => $order->coupon->value,
                ] : null,
                'discount_amount' => $order->discount_amount ?? 0,
                'timeline' => $order->status_timeline,
                'customer_notes' => $order->customer_notes
            ]
        ]);
    }

    /**
     * 4. جلب أعداد التبويبات (Badges Count) للبائع
     */
    public function getVendorBadges()
    {
        $badges = Order::where('seller_id', auth()->id())
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'success' => true,
            'badges' => [
                'pending' => $badges->get('pending', 0),
                'processing' => $badges->get('processing', 0),
                'shipped' => $badges->get('shipped', 0),
                'delivered' => $badges->get('delivered', 0),
                'cancelled_returned' => $badges->get('cancelled_returned', 0)
            ]
        ]);
    }

    /*  *
     * ترجمة مخصصة للحالات في الـ Timeline
     */
    private function getStatusArabicTitle($status)
    {
        return match ($status) {
            'pending' => 'تم استلام الطلب بنجاح وهو قيد الانتظار',
            'processing' => 'الطلب قيد التجهيز الآن في مستودعات التاجر',
            'shipped' => 'تم شحن الطلب وهو في طريقه إليك',
            'delivered' => 'تم تسليم الطلب بنجاح وإغلاق العملية وفك حجز الضمان',
            'cancelled_returned' => 'تم إلغاء الطلب أو إرجاع الشحنة وإعادة الأموال للمشتري',
            default => 'تم تحديث حالة الطلب'
        };
    }

    /**
     * 1. البحث برقم الطلب أو اسم المشتري
     */
    public function search(Request $request)
    {
        $query = Order::with(['buyer'])->where('seller_id', auth()->id());

        // قراءة المتغيرات مباشرة من الـ Body
        if ($request->filled('order_id')) {
            $query->where('id', $request->input('order_id'));
        }

        if ($request->filled('buyer_name')) {
            $query->whereHas('buyer', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->input('buyer_name') . '%')
                    ->orWhere('last_name', 'like', '%' . $request->input('buyer_name') . '%');
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate(15)
        ]);
    }

    /**
     * 2. فلترة الطلبات بنطاق تاريخ (من .. إلى)
     */
    public function filterByDate(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        $orders = Order::with(['buyer'])
            ->where('seller_id', auth()->id())
            ->whereBetween('created_at', [
                Carbon::parse($request->input('date_from'))->startOfDay(),
                Carbon::parse($request->input('date_to'))->endOfDay()
            ])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * 3. فلترة الطلبات بنطاق المبلغ (الحد الأدنى والأقصى)
     */
    public function filterByAmount(Request $request)
    {
        $query = Order::with(['buyer'])->where('seller_id', auth()->id());

        if ($request->filled('price_min')) {
            $query->where('total_price', '>=', $request->input('price_min'));
        }

        if ($request->filled('price_max')) {
            $query->where('total_price', '<=', $request->input('price_max'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate(15)
        ]);
    }

    /**
     * 4. تقرير المبيعات (يومي / أسبوعي / شهري)
     */
    public function salesReport(Request $request)
    {
        $request->validate([
            'period' => 'required|in:daily,weekly,monthly'
        ]);

        $query = Order::where('seller_id', auth()->id())
            ->whereIn('status', ['delivered', 'processing', 'shipped']);

        $period = $request->input('period');

        if ($period === 'daily') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($period === 'weekly') {
            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($period === 'monthly') {
            $query->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year);
        }

        return response()->json([
            'success' => true,
            'period' => $period,
            'report' => [
                'total_orders' => $query->count(),
                'total_revenue' => number_format($query->sum('total_price'), 2),
            ]
        ]);
    }

    /**
     * 5. تصدير قائمة الطلبات CSV
     */
    public function exportCSV()
    {
        $orders = Order::with('buyer')->where('seller_id', auth()->id())->latest()->get();
        $filename = "orders_" . now()->format('Y-m-d') . ".csv";

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['رقم الطلب', 'اسم المشتري', 'الإجمالي', 'الحالة', 'تاريخ الطلب'];

        $callback = function () use ($orders, $columns) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, $columns);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->id,
                    $order->buyer ? $order->buyer->first_name . ' ' . $order->buyer->last_name : 'N/A',
                    $order->total_price,
                    $order->status,
                    $order->created_at->toDateTimeString(),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }




    /**

     * 1. قبول الطلب (التحقق من المخزون وتحويل الحالة إلى جاري التجهيز)
     */
    // ==========================================
// 2. قبول التاجر للطلب (يتحول إلى processing)
// ==========================================
    public function acceptOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        $order = Order::with('products')->where('id', $request->order_id)
            ->where('seller_id', auth()->id())
            ->where('status', 'pending')
            ->where('payment_status', 'paid_escrow')
            ->firstOrFail();

        DB::beginTransaction();
        try {
            foreach ($order->products as $product) {
                $requestedQuantity = $product->pivot->quantity;
                $freshProduct = $product->fresh();

                if ($freshProduct->quantity < $requestedQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "Order cannot be accepted. Insufficient stock for product: {$product->name}"
                    ], 400);
                }
            }

            foreach ($order->products as $product) {
                $product->decrement('quantity', $product->pivot->quantity);
            }

            $timeline = $order->status_timeline ?? [];
            $timeline[] = [
                'status' => 'processing',
                'title' => 'Stock verified and order accepted by seller. Now in processing stage.',
                'time' => now()->toDateTimeString()
            ];

            Order::where('id', $order->id)->update([
                'status' => 'processing',
                'status_timeline' => json_encode($timeline) // ✅ استخدام json_encode
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order accepted successfully. Preparing items.',
                'order_status' => 'processing'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'An error occurred.'], 500);
        }
    }
    /**


     * 2. رفض الطلب (إرجاع المال للمشتري، استرداد المخزون، وخصم عداد المبيعات)
     */
    public function rejectOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'rejection_reason' => 'required|string|max:500'
        ]);

        $order = Order::with(['products', 'buyer', 'seller'])->where('id', $request->order_id)
            ->where('seller_id', auth()->id())
            ->whereIn('status', ['pending', 'processing'])
            ->firstOrFail();

        return DB::transaction(function () use ($order, $request) {

            if ($order->payment_status === 'paid_escrow') {
                $buyer = $order->buyer;
                $seller = $order->seller;

                $totalAmount = $order->total_price;
                $commissionRate = ($seller->role === 'wholesale') ? 0.05 : 0.10;
                $adminCommission = $totalAmount * $commissionRate;
                $sellerProfit = $totalAmount - $adminCommission;

                $buyer->increment('balance', $totalAmount);
                $seller->decrement('balance', $sellerProfit);

                Transaction::create([
                    'user_id' => $buyer->id,
                    'order_id' => $order->id,
                    'type' => 'refund',
                    'amount' => $totalAmount,
                    'description' => "Refunded amount for rejected Order #{$order->id}"
                ]);

                Transaction::create([
                    'user_id' => $seller->id,
                    'order_id' => $order->id,
                    'type' => 'withdrawal',
                    'amount' => $sellerProfit,
                    'description' => "Deducted escrow funds due to rejection of Order #{$order->id}"
                ]);
            }

            foreach ($order->products as $product) {
                $orderQuantity = $product->pivot->quantity;

                if ($order->status === 'processing') {
                    $product->increment('quantity', $orderQuantity);
                }

                if ($product->sales_count >= $orderQuantity) {
                    $product->decrement('sales_count', $orderQuantity);
                } else {
                    $product->update(['sales_count' => 0]);
                }
            }

            $timeline = $order->status_timeline ?? [];
            $timeline[] = [
                'status' => 'cancelled_returned',
                'title' => 'Order rejected by seller. Total funds successfully refunded to buyer.',
                'reason' => $request->rejection_reason,
                'time' => now()->toDateTimeString()
            ];

            Order::where('id', $order->id)->update([
                'status' => 'cancelled_returned',
                'payment_status' => 'refunded',
                'status_timeline' => json_encode($timeline) // ✅ استخدام json_encode
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order rejected successfully. Buyer balance restored, stock/sales count reverted.',
                'rejection_reason' => $request->rejection_reason
            ], 200);
        });
    }
    /**
     * 3. تعديل وقت التجهيز المتوقع وإرسال إشعار بالتأخير
     */
    public function updatePreparationTime(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'estimated_delivery_date' => 'required|date|after:now',
            'delay_notice_message' => 'nullable|string|max:500'
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('seller_id', auth()->id())
            ->where('status', 'processing')
            ->firstOrFail();

        $timeline = $order->status_timeline ?? [];
        $notice = $request->delay_notice_message ?? 'Notice: The expected preparation and shipping time for your order has been updated.';

        $timeline[] = [
            'status' => 'delay_notice',
            'title' => 'Shipping and delivery schedule update',
            'note' => $notice,
            'new_date' => $request->estimated_delivery_date,
            'time' => now()->toDateTimeString()
        ];

        Order::where('id', $order->id)->update([
            'estimated_delivery_date' => $request->estimated_delivery_date,
            'status_timeline' => json_encode($timeline) // ✅ استخدام json_encode
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Estimated preparation time updated successfully and delay log saved.',
            'new_estimated_date' => $request->estimated_delivery_date
        ]);
    }

    /**
     * 4. تأكيد تجهيز الطلب بالكامل وطباعة بيان الشحن
     */
    // 3. شحن الطلب من قبل التاجر (يتحول إلى shipped)
// ==========================================
    public function readyForShipping(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        $order = Order::with(['buyer', 'products'])->where('id', $request->order_id)
            ->where('seller_id', auth()->id())
            ->where('status', 'processing')
            ->firstOrFail();

        $timeline = $order->status_timeline ?? [];
        $timeline[] = [
            'status' => 'shipped',
            'title' => 'Order has been dispatched and handed over to courier.',
            'time' => now()->toDateTimeString()
        ];

        Order::where('id', $order->id)->update([
            'status' => 'shipped',
            'shipped_at' => now(),
            'status_timeline' => json_encode($timeline) // ✅ استخدام json_encode
        ]);

        $shippingManifest = [
            'invoice_number' => 'SHP-' . $order->id . '-' . now()->format('ymd'),
            'seller_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
            'buyer_name' => $order->buyer ? $order->buyer->first_name . ' ' . $order->buyer->last_name : 'N/A',
            'shipping_address' => $order->shipping_address_details ?? 'Default address',
            'total_amount' => $order->total_price . ' SAR',
            'items_count' => $order->products->sum('pivot.quantity'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Order marked as shipped.',
            'order_status' => 'shipped',
            'shipping_manifest' => $shippingManifest
        ]);
    }

    /*    public function myOrders(Request $request)
       {
           $user = auth()->user();

           $query = Order::with([
               'buyer:id,first_name,last_name,email,phone',
               'products'
           ]);

           if (in_array($user->role, ['vendor', 'wholesale'])) {
               // ✅ البائع: يشوف كل الطلبات اللي وردته (كل المشترين)
               $query->where('seller_id', $user->id);
           } else {
               // ✅ المشتري: يشوف كل طلباته (من كل البائعين)
               $query->where('user_id', $user->id);
           }

           // 📌 فلترة حسب الحالة (اختياري) - للكل
           if ($request->has('status')) {
               $query->where('status', $request->status);
           }

           // 📌 فلترة حسب التاريخ (اختياري)
           if ($request->has('from_date')) {
               $query->whereDate('created_at', '>=', $request->from_date);
           }
           if ($request->has('to_date')) {
               $query->whereDate('created_at', '<=', $request->to_date);
           }

           $orders = $query->latest()->paginate($request->input('per_page', 15));

           // 📌 إضافة بيانات إضافية لكل طلبية
           $orders->getCollection()->transform(function ($order) {
               $order->total_items = $order->products->sum('pivot.quantity');
               return $order;
           });

           return response()->json([
               'success' => true,
               'data' => $orders
           ], 200);
       } */

    public function myOrders(Request $request)
    {
        $user = auth()->user();

        $query = Order::with([
            'buyer:id,first_name,last_name,email,phone',
            'products',
            'coupon' // 🔥 إضافة علاقة الكوبون
        ]);

        if (in_array($user->role, ['vendor', 'wholesale'])) {
            $query->where('seller_id', $user->id);
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $orders = $query->latest()->paginate($request->input('per_page', 15));

        $orders->getCollection()->transform(function ($order) {
            $order->total_items = $order->products->sum('pivot.quantity');
            // 🔥 إضافة الخصم للعرض
            $order->discount_amount = $order->discount_amount ?? 0;
            $order->final_price = $order->total_price;
            return $order;
        });

        return response()->json([
            'success' => true,
            'data' => $orders
        ], 200);
    }
}

