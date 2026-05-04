<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use DB;
use Illuminate\Http\Request;

class OrderController extends Controller
{

    public function markAsDelivered($orderId)
    {
        return DB::transaction(function () use ($orderId) {

            // 1. جلب الطلب مع بيانات المشتري والبائع (باستخدام العلاقة الجديدة seller)
            // أضفنا lockForUpdate لضمان عدم تداخل العمليات المالية
            $order = Order::with(['buyer', 'seller'])->lockForUpdate()->findOrFail($orderId);

            // 2. منع تكرار العملية أو تنفيذها على طلب غير مدفوع
            if ($order->status === 'delivered') {
                return response()->json(['message' => 'Order already delivered and processed.'], 400);
            }

            // نصيحة أمان: تأكد أن الطلب مدفوع (paid) قبل تحويل الأرباح للبائع
            if ($order->status !== 'paid') {
                return response()->json(['message' => 'Cannot deliver an unpaid order.'], 400);
            }

            // 3. تحديث حالة الطلب
            $order->update(['status' => 'delivered']);

            // 4. تحديد العمولة بناءً على رتبة البائع (seller)
            $seller = $order->seller;
            $totalAmount = $order->total_price;

            // التحقق من وجود بائع مرتبط بالطلب
            if (!$seller || !in_array($seller->role, ['vendor', 'wholesale'])) {
                return response()->json(['message' => 'Invalid seller associated with this order.'], 400);
            }

            // حساب العمولة: 5% للجملة و 10% للبائع العادي
            $commissionRate = ($seller->role === 'wholesale') ? 0.05 : 0.10;
            $adminCommission = $totalAmount * $commissionRate;
            $sellerProfit = $totalAmount - $adminCommission;

            // 5. إضافة الأرباح لرصيد البائع بشكل آمن
            $seller->increment('balance', $sellerProfit);

            // 6. توثيق العملية في السجلات المالية للبائع
            Transaction::create([
                'user_id' => $seller->id,
                'order_id' => $order->id,
                'type' => 'deposit',
                'amount' => $sellerProfit,
                'description' => "Earnings from Order #{$order->id} (Seller Role: {$seller->role})"
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order marked as delivered. Funds transferred to seller balance.',
                'data' => [
                    'order_id' => $order->id,
                    'total_amount' => $totalAmount,
                    'seller_earned' => $sellerProfit,
                    'commission_deducted' => $adminCommission,
                    'seller_new_balance' => $seller->refresh()->balance
                ]
            ], 200);
        });
    }

    public function store(Request $request)
    {
        $buyer = auth()->user();

        $request->validate([
            // تعديل التحقق ليتأكد أن المعرف يخص بائعاً أو تاجر جملة فقط
            'seller_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereIn('role', ['vendor', 'wholesale']);
                }),
            ],
            'total_price' => 'required|numeric|min:1'
        ]);

        // 3. إنشاء الطلب
        $order = Order::create([
            'user_id' => $buyer->id,
            'seller_id' => $request->seller_id,
            'total_price' => $request->total_price,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'order_id' => $order->id
        ], 201);
    }
}
