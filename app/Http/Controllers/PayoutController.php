<?php

namespace App\Http\Controllers;

use App\Models\PayoutRequest;
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

    // 2. تابع طلب سحب رصيد للبائع فقط
    public function requestWithdraw(Request $request)
    {
        $user = auth()->user();

        // 1. التأكد أن المستخدم بائع أو تاجر جملة (استخدام الـ Helpers التي أضفناها في الموديل)
        if (!$user->isVendor() && !$user->isWholesale()) {
            return response()->json(['message' => 'Unauthorized. Only sellers can request payouts.'], 403);
        }

        // 2. التحقق من البيانات المدخلة
        $request->validate([
            'amount' => 'required|numeric|min:50',
            'password' => 'required' // تغيير wallet_pin إلى password
        ]);

        // 3. التحقق من كلمة المرور للأمان
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Incorrect password.'], 403);
        }

        // 4. استخدام Transaction لضمان سلامة خصم الرصيد
        return DB::transaction(function () use ($user, $request) {

            // إعادة جلب بيانات المستخدم مع قفل السجل (Lock for update) لمنع السحب المزدوج
            $currentUser = User::where('id', $user->id)->lockForUpdate()->first();

            // 5. التأكد من توفر الرصيد الكافي
            if ($currentUser->balance < $request->amount) {
                return response()->json(['message' => 'Insufficient balance to complete this transaction.'], 400);
            }

            // 6. إنشاء سجل طلب السحب
            $payout = PayoutRequest::create([
                'user_id' => $currentUser->id,
                'amount' => $request->amount,
                // تأكد أن هذه الحقول موجودة في جدول المستخدمين أو يتم إرسالها في الطلب
                'payout_method' => $currentUser->payout_method ?? 'Not specified',
                'payout_account' => $currentUser->payout_account ?? 'Not specified',
                'status' => 'pending'
            ]);

            // 7. خصم المبلغ من رصيد المستخدم (تجميد الرصيد)
            $currentUser->decrement('balance', $request->amount);

            return response()->json([
                'success' => true,
                'message' => 'Your withdrawal request has been submitted and is awaiting Admin approval.',
                'new_balance' => $currentUser->balance,
                'payout_details' => $payout
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
