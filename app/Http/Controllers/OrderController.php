<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
class OrderController extends Controller
{

    /*     public function store(Request $request)
        {
            $buyer = auth()->user();

            // 1. التحقق من البيانات القادمة (مصفوفة المنتجات وكمياتها فقط)
            $request->validate([
                'seller_id' => [
                    'required',
                    \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
                        $query->whereIn('role', ['vendor', 'wholesale']);
                    }),
                ],
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            // 2. حساب الأسعار وتجهيز البيانات برمجياً من قاعدة البيانات
            $calculatedTotalPrice = 0;
            $validatedItems = [];

            foreach ($request->input('items') as $item) {
                $product = Product::find($item['product_id']);

                if ($product) {
                    // التحقق إذا كان المنتج عليه عرض (offer_price) متاح، نأخذ سعر العرض، وإلا السعر الأصلي
                    $currentPrice = ($product->offer_price && $product->offer_expires_at && $product->offer_expires_at->isFuture())
                        ? $product->offer_price
                        : $product->original_price;

                    // حساب السعر الإجمالي لهذا العنصر (السعر * الكمية)
                    $itemTotalPrice = $currentPrice * $item['quantity'];

                    // إضافة السعر الإجمالي للعنصر إلى إجمالي الفاتورة العام
                    $calculatedTotalPrice += $itemTotalPrice;

                    // تخزين البيانات الجاهزة لاستخدامها في الحفظ
                    $validatedItems[] = [
                        'product' => $product,
                        'quantity' => $item['quantity'],
                        'price' => $currentPrice
                    ];
                }
            }

            // 3. إنشاء الطلب الأساسي بحالة pending (السعر محسوب من السيرفر حصراً)
            $order = Order::create([
                'user_id' => $buyer->id,
                'seller_id' => $request->seller_id,
                'total_price' => $calculatedTotalPrice,
                'status' => 'pending',
            ]);

            // 4. تجهيز مصفوفة البيانات للحفظ في الجدول الوسيط (Many-to-Many)
            $syncData = [];
            foreach ($validatedItems as $validatedItem) {
                $syncData[$validatedItem['product']->id] = [
                    'quantity' => $validatedItem['quantity'],
                    'price' => $validatedItem['price'] // السعر الحقيقي للمنتج وقت الشراء
                ];
            }

            // 5. حفظ كافة المنتجات المرتبطة بهذا الطلب دفعة واحدة في الجدول الوسيط order_product
            $order->products()->attach($syncData);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully and items linked to pivot table.',
                'order_id' => $order->id,
                'total_calculated_price' => $calculatedTotalPrice
            ], 201);
        } */



    /**
     * 1. إنشاء طلب جديد وحجز المبلغ في الضمان (Escrow)
     */
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
            'payment_method' => 'nullable|string'
        ]);

        $calculatedTotalPrice = 0;
        $validatedItems = [];

        foreach ($request->input('items') as $item) {
            $product = Product::find($item['product_id']);

            if ($product) {
                // التحقق من العروض وتواريخ صلاحيتها
                $currentPrice = ($product->offer_price && $product->offer_expires_at && $product->offer_expires_at->isFuture())
                    ? $product->offer_price
                    : $product->original_price;

                $itemTotalPrice = $currentPrice * $item['quantity'];
                $calculatedTotalPrice += $itemTotalPrice;

                $validatedItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'price' => $currentPrice
                ];
            }
        }

        $order = Order::create([
            'user_id' => $buyer->id,
            'seller_id' => $request->seller_id,
            'total_price' => $calculatedTotalPrice,
            'status' => 'pending',
            'payment_status' => 'escrow_locked', // حجز المال تلقائياً في الضمان
            'payment_method' => $request->input('payment_method', 'wallet'),
            'shipping_address_title' => $request->shipping_address_title,
            'shipping_address_details' => $request->shipping_address_details,
            'customer_notes' => $request->customer_notes,
            'status_timeline' => [
                [
                    'status' => 'pending',
                    'title' => 'تم استلام الطلب بنجاح وهو قيد الانتظار',
                    'time' => now()->toDateTimeString()
                ]
            ]
        ]);

        $syncData = [];
        foreach ($validatedItems as $validatedItem) {
            $syncData[$validatedItem['product']->id] = [
                'quantity' => $validatedItem['quantity'],
                'price' => $validatedItem['price']
            ];
            $validatedItem['product']->increment('sales_count', $validatedItem['quantity']);
        }

        $order->products()->attach($syncData);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'order_id' => $order->id,
            'total_calculated_price' => $calculatedTotalPrice
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
    public function show($id)
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


    //
    //  * 1. قبول الطلب (التحقق من المخزون وتحويل الحالة إلى جاري التجهيز)
    /*
 /*   public function acceptOrder(Request $request)
   {
       $request->validate([
           'order_id' => 'required|exists:orders,id'
       ]);

       // جلب الطلب مع المنتجات المرتبطة به
       $order = Order::with('products')->where('id', $request->order_id)
           ->where('seller_id', auth()->id())
           ->where('status', 'pending')
           ->firstOrFail();

       // بيئة Transaction لضمان تنفيذ العمليات معاً أو إلغائها في حال حدوث خطأ
       DB::beginTransaction();
       try {
           // التحقق من أن المخزون كافٍ لجميع المنتجات في الطلب
           foreach ($order->products as $product) {
               $requestedQuantity = $product->pivot->quantity; // الكمية المطلوبة بالطلب

               if ($product->stock < $requestedQuantity) {
                   return response()->json([
                       'success' => false,
                       'message' => "لا يمكن قبول الطلب، المخزون غير كافٍ للمنتج: {$product->name}"
                   ], 400);
               }
           }

           // خصم الكمية من المخزون وتحديث حالة الطلب
           foreach ($order->products as $product) {
               $product->decrement('stock', $product->pivot->quantity);
           }

           $timeline = $order->status_timeline ?? [];
           $timeline[] = [
               'status' => 'processing',
               'title' => 'تم التأكد من المخزون وقبول الطلب، وهو الآن جاري التجهيز',
               'time' => now()->toDateTimeString()
           ];

           $order->update([
               'status' => 'processing',
               'status_timeline' => $timeline
           ]);

           DB::commit();

           return response()->json([
               'success' => true,
               'message' => 'تم التحقق من المخزون، وتثبيت خصم الكميات، وقبول الطلب بنجاح.',
               'order_status' => $order->status
           ]);

       } catch (\Exception $e) {
           DB::rollBack();
           return response()->json(['success' => false, 'message' => 'حدث خطأ غير متوقع أثناء معالجة الطلب'], 500);
       }
   }

   /**
    * 2. رفض الطلب (إرجاع المال للمشتري وتوثيق السبب)
    */
    /* public function rejectOrder(Request $request)
     {
         $request->validate([
             'order_id' => 'required|exists:orders,id',
             'rejection_reason' => 'required|string|max:500'
         ]);

         $order = Order::with('products')->where('id', $request->order_id)
             ->where('seller_id', auth()->id())
             ->whereIn('status', ['pending', 'processing']) 
             ->firstOrFail();

         DB::beginTransaction();
         try {
             if ($order->status === 'processing') {
                 foreach ($order->products as $product) {
                     $product->increment('stock', $product->pivot->quantity);
                 }
             }

             $timeline = $order->status_timeline ?? [];
             $timeline[] = [
                 'status' => 'cancelled',
                 'title' => 'تم رفض الطلب من قبل التاجر وإعادة الأموال للمشتري',
                 'reason' => $request->rejection_reason,
                 'time' => now()->toDateTimeString()
             ];

             // التعديل هنا: التحديث المباشر عبر الكلاس لمنع خطأ stdClass
             Order::where('id', $order->id)->update([
                 'status' => 'cancelled',
                 'payment_status' => 'escrow_refunded', 
                 'status_timeline' => $timeline
             ]);

             DB::commit();

             return response()->json([
                 'success' => true,
                 'message' => 'تم رفض الطلب بنجاح، إرجاع الكميات للمخزن، وتحويل الأموال للمشتري.',
                 'rejection_reason' => $request->rejection_reason
             ]);

         } catch (\Exception $e) {
             DB::rollBack();
             return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء إلغاء الطلب'], 500);
         }
     } */

    /**
     * 3. تعديل وقت التجهيز المتوقع وإرسال إشعار بالتأخير
     */
    /*    public function updatePreparationTime(Request $request)
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
           $notice = $request->delay_notice_message ?? 'تنبيه: تم تعديل الموعد المتوقع لتجهيز طلبكم شحنه.';

           $timeline[] = [
               'status' => 'delay_notice',
               'title' => 'إشعار بتعديل موعد الشحن والتسليم المتوقع',
               'note' => $notice,
               'new_date' => $request->estimated_delivery_date,
               'time' => now()->toDateTimeString()
           ];

           $order->update([
               'estimated_delivery_date' => $request->estimated_delivery_date,
               'status_timeline' => $timeline
           ]);

           // فكرة الإشعار: نرسل إشعار عبر نظام الإشعارات في لارافيل للمشتري
           // Notification::send($order->buyer, new OrderDelayNotification($order, $notice));

           return response()->json([
               'success' => true,
               'message' => 'تم تحديث الوقت المتوقع بنجاح وحفظ سجل الإشعار بالتأخير.',
               'new_estimated_date' => $request->estimated_delivery_date
           ]);
       } */

    /**
     * 4. تأكيد تجهيز الطلب بالكامل وطباعة بيان الشحن
     */
    /* public function readyForShipping(Request $request)
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
            'status' => 'ready',
            'title' => 'اكتمل التجهيز، الطلب بانتظار شركة الشحن لاستلامه بنجاح',
            'time' => now()->toDateTimeString()
        ];

        $order->update([
            'status' => 'shipped', 
            'status_timeline' => $timeline
        ]);

        // بيان الشحن الجاهز للطباعة
        $shippingManifest = [
            'invoice_number' => 'SHP-' . $order->id . '-' . now()->format('ymd'),
            'seller_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
            'buyer_name' => $order->buyer ? $order->buyer->first_name . ' ' . $order->buyer->last_name : 'N/A',
            'shipping_address' => $order->shipping_address_details ?? 'العنوان الافتراضي المسجل في الطلب',
            'total_amount' => $order->total_price . ' SAR',
            'items_count' => $order->products->sum('pivot.quantity'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد جاهزية الطلب، وإصدار بيان الشحن للطباعة.',
            'shipping_manifest' => $shippingManifest
        ]);
    } */


    /**

     * 1. قبول الطلب (التحقق من المخزون وتحويل الحالة إلى جاري التجهيز)
     */
    public function acceptOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        // جلب الطلب مع المنتجات المرتبطة به
        $order = Order::with('products')->where('id', $request->order_id)
            ->where('seller_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();

        // بيئة Transaction لضمان تنفيذ العمليات معاً أو إلغائها في حال حدوث خطأ
        DB::beginTransaction();
        try {
            // التحقق من أن المخزون (quantity) كافٍ لجميع المنتجات في الطلب
            foreach ($order->products as $product) {
                $requestedQuantity = $product->pivot->quantity;

                // جلب بيانات المنتج الطازجة للتأكد من حقل quantity الفعلي
                $freshProduct = $product->fresh();

                // التعديل: استخدام حقل quantity بدلاً من stock
                if ($freshProduct->quantity < $requestedQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "Order cannot be accepted. Insufficient stock for product: {$product->name}"
                    ], 400);
                }
            }

            // خصم الكمية المطلوبة من الحقل الصحيح (quantity) في المخزن
            foreach ($order->products as $product) {
                $product->decrement('quantity', $product->pivot->quantity);
            }

            // تحديث السجل الزمني لحالة الطلب
            $timeline = $order->status_timeline ?? [];
            $timeline[] = [
                'status' => 'processing',
                'title' => 'Stock verified and order accepted. Now in processing stage.',
                'time' => now()->toDateTimeString()
            ];

            // التحديث المباشر لحالة الطلب عبر الكلاس لمنع تداخل الأنواع والتحذيرات
            Order::where('id', $order->id)->update([
                'status' => 'processing',
                'status_timeline' => $timeline
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock verified, quantities deducted, and order accepted successfully.',
                'order_status' => 'processing'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred while processing the order.'], 500);
        }
    }

    /**
     * 2. رفض الطلب (إرجاع المال للمشتري وتوثيق السبب)
     */
    /**
     * 2. رفض الطلب (إرجاع المال للمشتري وتوثيق السبب)
     */
    /**
     * 2. رفض الطلب (إرجاع المال للمشتري وتوثيق السبب)
     */
    public function rejectOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'rejection_reason' => 'required|string|max:500'
        ]);

        // جلب الطلب مع المنتجات
        $order = Order::with('products')->where('id', $request->order_id)
            ->where('seller_id', auth()->id())
            ->whereIn('status', ['pending', 'processing'])
            ->firstOrFail();

        DB::beginTransaction();
        try {
            // إذا تم الرفض بعد القبول (الحالة قيد التجهيز)، نعيد الكميات للمخزن
            if ($order->status === 'processing') {
                foreach ($order->products as $product) {
                    Product::where('id', $product->id)->increment('quantity', $product->pivot->quantity);
                }
            }

            // تحديث السجل الزمني لحالة الرفض
            $timeline = $order->status_timeline ?? [];
            $timeline[] = [
                'status' => 'cancelled_returned',
                'title' => 'Order rejected by seller. Funds refunded to buyer.',
                'reason' => $request->rejection_reason,
                'time' => now()->toDateTimeString()
            ];

            // 💡 التعديل القاتل: استخدام 'cancelled_returned' و 'refunded' لتطابق الميجريشن تماماً
            Order::where('id', $order->id)->update([
                'status' => 'cancelled_returned',
                'payment_status' => 'refunded',
                'status_timeline' => $timeline
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order rejected successfully, stock restored, and funds refunded to buyer.',
                'rejection_reason' => $request->rejection_reason
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while cancelling the order.',
                'error' => $e->getMessage()
            ], 500);
        }
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

        // جلب الطلب المخصص للتاجر والتحقق من أنه قيد التجهيز
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

        // تحديث تاريخ التسليم المتوقع في قاعدة البيانات
        Order::where('id', $order->id)->update([
            'estimated_delivery_date' => $request->estimated_delivery_date,
            'status_timeline' => $timeline
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
    public function readyForShipping(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        // جلب الطلب مع بيانات المشتري والمنتجات لتجهيز الفاتورة أو بيان الشحن
        $order = Order::with(['buyer', 'products'])->where('id', $request->order_id)
            ->where('seller_id', auth()->id())
            ->where('status', 'processing')
            ->firstOrFail();

        $timeline = $order->status_timeline ?? [];
        $timeline[] = [
            'status' => 'ready',
            'title' => 'Preparation completed. Order is waiting for the shipping courier.',
            'time' => now()->toDateTimeString()
        ];

        // تحديث حالة الطلب إلى مشحون/جاهز للشحن
        Order::where('id', $order->id)->update([
            'status' => 'shipped',
            'status_timeline' => $timeline
        ]);

        // تجميع تفاصيل بيان الشحن الجاهز للطباعة على الطرود
        $shippingManifest = [
            'invoice_number' => 'SHP-' . $order->id . '-' . now()->format('ymd'),
            'seller_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
            'buyer_name' => $order->buyer ? $order->buyer->first_name . ' ' . $order->buyer->last_name : 'N/A',
            'shipping_address' => $order->shipping_address_details ?? 'Default registered address',
            'total_amount' => $order->total_price . ' SAR',
            'items_count' => $order->products->sum('pivot.quantity'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Order readiness confirmed. Shipping manifest generated for printing.',
            'shipping_manifest' => $shippingManifest
        ]);
    }


}

