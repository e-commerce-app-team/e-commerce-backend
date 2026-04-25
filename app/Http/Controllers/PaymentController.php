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

    // 2. شحن الرصيد (Deposit) للمشتري فقط
    public function deposit(Request $request)
    {
        $user = auth()->user();

        // التأكد أن المستخدم مشتري
        if ($user->role !== 'buyer') {
            return response()->json(['message' => 'Unauthorized. Only buyers can deposit funds.'], 403);
        }

        // التحقق من المبلغ المراد شحنه
        $request->validate(['amount' => 'required|numeric|min:1000']);

        // تحديث رصيد المستخدم
        $user->balance += $request->amount;
        $user->save();

        // تسجيل العملية في جدول الـ Transactions للتوثيق
        Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $request->amount,
            'description' => 'Wallet top-up'
        ]);

        return response()->json([
            'message' => 'Balance topped up successfully.',
            'new_balance' => $user->balance
        ]);
    }

    // 3. الدفع باستخدام المحفظة للمشتري فقط
    public function payWithWallet(Request $request, $orderId)
    {
        // الحصول على المستخدم الحالي (المشتري)
        $user = auth()->user();

        // 1. التأكد أن المستخدم يمتلك صلاحية مشتري (Buyer)
        if ($user->role !== 'buyer') {
            return response()->json([
                'message' => 'Unauthorized. Only buyers can perform this action.'
            ], 403);
        }

        // 2. التحقق من وجود الطلب ومن ملكيته لهذا المستخدم
        // إذا لم يجد الطلب سيرسل استجابة 404 تلقائياً بسبب findOrFail أو firstOrFail
        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // منع الدفع إذا كان الطلب مدفوعاً بالفعل أو تم تسليمه
        if (in_array($order->status, ['paid', 'delivered'])) {
            return response()->json([
                'message' => 'This order has already been paid or processed.'
            ], 400);
        }

        // 3. التحقق من صحة كلمة المرور كإجراء أمان إضافي قبل الخصم
        $request->validate([
            'password' => 'required'
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Incorrect password. Payment failed.'
            ], 401);
        }

        // 4. التحقق من كفاية الرصيد
        if ($user->balance < $order->total_price) {
            // تحديث حالة الطلب إلى "فشل الدفع" في قاعدة البيانات
            $order->update(['status' => 'failed_payment']);

            return response()->json([
                'message' => 'Insufficient balance in your wallet.',
                'order_status' => 'failed_payment',
                'current_balance' => $user->balance,
                'required_amount' => $order->total_price
            ], 400);
        }

        // 5. بدء عملية الدفع (Transaction) لضمان سلامة البيانات
        return DB::transaction(function () use ($user, $order) {

            // أ. خصم المبلغ من محفظة المشتري
            $user->balance -= $order->total_price;
            $user->save();

            // ب. تحديث حالة الطلب إلى "مدفوع"
            $order->update(['status' => 'paid']);

            // ج. تسجيل العملية في جدول السجلات (Transactions)
            Transaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'payment',
                'amount' => $order->total_price,
                'description' => "Wallet payment successful for Order #{$order->id}"
            ]);

            // د. إرسال استجابة النجاح
            return response()->json([
                'status' => 'success',
                'message' => 'Payment completed successfully.',
                'order_id' => $order->id,
                'order_status' => 'paid',
                'new_balance' => $user->balance
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
