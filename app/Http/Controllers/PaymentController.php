<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use DB;
use Hash;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // 1. عرض الرصيد الحالي للمشتري فقط
    public function getWalletBalance()
    {
        $user = auth()->user();

        // التأكد أن المستخدم مشتري
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Unauthorized. Only buyers can access wallet balance.'], 403);
        }

        return response()->json([
            'balance' => $user->balance
        ]);
    }

    public function payAndTransfer(Request $request, $orderId)
    {
        $user = auth()->user();

        // 1. التحقق من الصلاحية ووجود الطلب
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Unauthorized.Only buyers can perform this action.'], 403);
        }

        $order = Order::with('seller')->where('id', $orderId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // منع الدفع المتكرر
        if (in_array($order->status, ['paid', 'delivered'])) {
            return response()->json(['message' => 'Order already processed.'], 400);
        }

        // 2. التحقق من كلمة المرور وكفاية الرصيد
        $request->validate(['password' => 'required']);
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Incorrect password.'], 401);
        }

        if ($user->balance < $order->total_price) {
            return response()->json(['message' => 'Insufficient balance.'], 400);
        }

        // 3. العملية المالية المدمجة
        return DB::transaction(function () use ($user, $order) {

            // أ. خصم من المشتري
            $user->decrement('balance', $order->total_price);

            // ب. حساب العمولات فوراً
            $seller = $order->seller;
            $totalAmount = $order->total_price;
            $commissionRate = ($seller->role === 'wholesale') ? 0.05 : 0.10;
            $adminCommission = $totalAmount * $commissionRate;
            $sellerProfit = $totalAmount - $adminCommission;

            // ج. إضافة الرصيد للبائع وتحديث حالة الطلب
            $seller->increment('balance', $sellerProfit);
            $order->update(['status' => 'delivered']); // تحول لـ delivered مباشرة

            // د. تسجيل العمليات (سجل للمشتري وسجل للبائع)
            // سجل المشتري (Payment)
            Transaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'payment',
                'amount' => $totalAmount,
                'description' => "Paid for Order #{$order->id}"
            ]);

            // سجل البائع (Earnings)
            Transaction::create([
                'user_id' => $seller->id,
                'order_id' => $order->id,
                'type' => 'deposit',
                'amount' => $sellerProfit,
                'description' => "Earnings from Order #{$order->id} (Auto-transfer)"
            ]);

            return response()->json([
                'message' => 'Payment successful and funds transferred to seller.',
                'new_balance' => $user->balance,
                'order_status' => 'delivered'
            ], 200);
        });
    }
    // 4. عرض سجل العمليات (كشف الحساب) للمشتري
    public function getTransactionHistory()
    {
        $user = auth()->user();

        // 1. التأكد من الصلاحيات (مشتري أو بائع)
        if ($user->role !== 'buyer' && $user->role !== 'vendor' && $user->role !== 'wholesale') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // 2. جلب سجل الحركات المالية
        $history = $user->transactions()
            ->with('order:id,status,created_at') // جلب حقول محددة من الطلب
            ->latest()
            ->get();

        if ($history->isEmpty()) {
            return response()->json(['message' => 'No transactions found.'], 200);
        }

        return response()->json([
            'message' => 'Transaction history retrieved successfully.',
            'data' => $history
        ], 200);
    }

}

