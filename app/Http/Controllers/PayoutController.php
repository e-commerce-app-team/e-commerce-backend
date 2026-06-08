<?php

namespace App\Http\Controllers;

use App\Models\PayoutRequest;
use App\Models\Transaction;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;   // هذا السطر يحل مشكلة الـ DB
use App\Models\User;                 // تأكد من وجوده عشان الـ User::where

class PayoutController extends Controller
{
    // 1. عرض الرصيد وبيانات السحب للبائع فقط
    public function getBalance()
    {
        $user = auth()->user();

        // التأكد أن المستخدم بائع أو تاجر جملة
        if ($user->role !== 'vendor' && $user->role !== 'wholesale') {
            return response()->json(['message' => 'Unauthorized. Only sellers can access this information.'], 403);
        }

        return response()->json([
            'current_balance' => $user->balance,
            'payout_method' => $user->payout_method,
            'payout_account' => $user->payout_account,
        ]);
    }
//تابع لاجراء عملية سحب فورية للتاجر والبائع العادي 
    public function instantWithdraw(Request $request)
    {
        $user = auth()->user();

        // 1. التأكد أن المستخدم بائع أو تاجر جملة
        if (!$user->isVendor() && !$user->isWholesale()) {
            return response()->json(['message' => 'Unauthorized. Only sellers can withdraw funds.'], 403);
        }

        // 2. التحقق من البيانات (المبلغ وكلمة المرور)
        $request->validate([
            'amount' => 'required|numeric|min:50',
            'password' => 'required'
        ]);

        // 3. التحقق من كلمة المرور للأمان
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Incorrect password.'], 403);
        }

        return DB::transaction(function () use ($user, $request) {
            // قفل السجل لمنع العمليات المتزامنة (Race Condition)
            $currentUser = User::where('id', $user->id)->lockForUpdate()->first();

            // 4. التأكد من توفر الرصيد الكافي
            if ($currentUser->balance < $request->amount) {
                return response()->json(['message' => 'Insufficient balance.'], 400);
            }

            // 5. خصم المبلغ فوراً من الرصيد
            $currentUser->decrement('balance', $request->amount);

            // 6. إنشاء سجل السحب بحالة "مكتمل" (Completed) مباشرة
            $payout = PayoutRequest::create([
                'user_id' => $currentUser->id,
                'amount' => $request->amount,
                'payout_method' => $currentUser->payout_method ?? 'Default Method',
                'payout_account' => $currentUser->payout_account ?? 'Default Account',
                'status' => 'completed', // الحالة مكتملة فوراً
                'admin_notes' => 'Instant withdrawal processed by user.'
            ]);

            // 7. (اختياري) تسجيل العملية في جدول الـ Transactions العام لتوثيق حركة الأموال
            Transaction::create([
                'user_id' => $currentUser->id,
                'type' => 'withdrawal',
                'amount' => $request->amount,
                'description' => "Instant withdrawal of {$request->amount}"
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal successful. Funds have been deducted.',
                'new_balance' => $currentUser->balance,
                'details' => $payout
            ]);
        });
    }
    // 3. مراجعة تاريخ السحوبات للبائع فقط
    public function payoutHistory()
    {
        $user = auth()->user();

        // التأكد أن المستخدم بائع أو تاجر جملة
        if ($user->role !== 'vendor' && $user->role !== 'wholesale') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $history = $user->payoutRequests()->latest()->get();
        return response()->json($history);
    }
}
