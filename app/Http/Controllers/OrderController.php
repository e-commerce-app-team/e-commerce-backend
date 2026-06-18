<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use DB;
use Illuminate\Http\Request;

class OrderController extends Controller
{

    // public function store(Request $request)
    // {
    //   $buyer = auth()->user();

    // $request->validate([
    // تعديل التحقق ليتأكد أن المعرف يخص بائعاً أو تاجر جملة فقط
    //   'seller_id' => [
    //     'required',
    //   \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
    //     $query->whereIn('role', ['vendor', 'wholesale']);
    //            }),
    //      ],
    //   'total_price' => 'required|numeric|min:1'
    //  ]);

    // 3. إنشاء الطلب
    //  $order = Order::create([
    //    'user_id' => $buyer->id,
    //  'seller_id' => $request->seller_id,
    //  'total_price' => $request->total_price,
    //  'status' => 'pending',
    //  ]);

    //  return response()->json([
    //    'success' => true,
    //  'message' => 'Order created successfully',
    //'order_id' => $order->id
    // ], 201);
    // }



    // public function store(Request $request)
    //{
    //  $buyer = auth()->user();

    // 1. التحقق من البيانات القادمة (مصفوفة المنتجات وكمياتها فقط)
    //$request->validate([
    //  'seller_id' => [
    //    'required',
    //  \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
    //    $query->whereIn('role', ['vendor', 'wholesale']);
    //  }),
    // ],
    //    'items' => 'required|array|min:1',
    //    'items.*.product_id' => 'required|exists:products,id',
    //    'items.*.quantity' => 'required|integer|min:1',
    // ]);

    // 2. حساب الأسعار وتجهيز البيانات برمجياً من قاعدة البيانات
    //  $calculatedTotalPrice = 0;
    //  $validatedItems = [];

    //foreach ($request->input('items') as $item) {
    //  $product = Product::find($item['product_id']);

    //if ($product) {
    // التحقق إذا كان المنتج عليه عرض (offer_price) متاح، نأخذ سعر العرض، وإلا السعر الأصلي
    //  $currentPrice = ($product->offer_price && $product->offer_expires_at && $product->offer_expires_at->isFuture())
    //    ? $product->offer_price
    //  : $product->original_price;

    // حساب السعر الإجمالي لهذا العنصر (السعر * الكمية)
    //   $itemTotalPrice = $currentPrice * $item['quantity'];

    // إضافة السعر الإجمالي للعنصر إلى إجمالي الفاتورة العام
    // $calculatedTotalPrice += $itemTotalPrice;

    // تخزين البيانات الجاهزة لاستخدامها في الحفظ وزيادة العداد
    //   $validatedItems[] = [
    //     'product' => $product,
    //   'quantity' => $item['quantity'],
    // 'price' => $currentPrice
    //   ];
    //  }
    //  }

    // 3. إنشاء الطلب الأساسي بالسعر المحسوب تلقائياً من السيرفر
    // $order = Order::create([
    //   'user_id' => $buyer->id,
    // 'seller_id' => $request->seller_id,
    //   'total_price' => $calculatedTotalPrice, // السعر المحسوب من السيرفر حصراً
    // 'status' => 'pending',
    //  ]);

    // 4. حفظ تفاصيل الطلب وزيادة عداد الأكثر مبيعاً
    //foreach ($validatedItems as $validatedItem) {
    //  $product = $validatedItem['product'];
    //  $quantity = $validatedItem['quantity'];
    //  $price = $validatedItem['price'];




    // هدول معلقين من قبل //
    // أ) كود حفظ تفاصيل الطلب (قم بإلغاء التعليق إذا كان الجدول والموديل OrderItem موجود لديك)
    // OrderItem::create([
    //     'order_id' => $order->id,
    //     'product_id' => $product->id,
    //     'quantity' => $quantity,
    //     'price' => $price, // السعر الحقيقي للمنتج وقت الشراء
    // ]);

    // ب) زيادة عداد المبيعات (sales_count) بشكل تلقائي وحقيقي
    //  $product->increment('sales_count', $quantity);
    // }

    //return response()->json([
    //  'success' => true,
    //'message' => 'Order created successfully and total price calculated by server.',
    //  'order_id' => $order->id,
    //   'total_calculated_price' => $calculatedTotalPrice // نرجع السعر الإجمالي للتأكيد بالواجهة
    //  ], 201);
    //  }


    public function store(Request $request)
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
    }
}
