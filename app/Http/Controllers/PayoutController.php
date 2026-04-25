<?php

namespace App\Http\Controllers;

use App\Models\PayoutRequest;
use Hash;
use Illuminate\Http\Request;

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

        // 1. التأكد أن المستخدم بائع أو تاجر جملة
        if ($user->role !== 'vendor' && $user->role !== 'wholesale') {
            return response()->json(['message' => 'Unauthorized. Only sellers can request payouts.'], 403);
        }

        // 2. التحقق من البيانات المدخلة (استخدام كلمة المرور بدل PIN)
        $request->validate([
            'amount' => 'required|numeric|min:50',
            'wallet_pin' => 'required'
        ]);

        // 3. التحقق من كلمة المرور للأمان
        if (!Hash::check($request->wallet_pin, $user->wallet_pin)) {
            return response()->json(['message' => 'Incorrect wallet_pin.'], 403);
        }

        // 4. التأكد من توفر الرصيد الكافي للسحب
        if ($user->balance < $request->amount) {
            return response()->json(['message' => 'Insufficient balance to complete this transaction.'], 400);
        }

        // 5. إنشاء سجل طلب السحب (هنا جوهر شرط موافقة الأدمن)
        // الحالة الافتراضية 'pending' تعني أن المال لن يخرج فعلياً إلا بقرار من الأدمن
        $payout = PayoutRequest::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'payout_method' => $user->payout_method,
            'payout_account' => $user->payout_account,
            'status' => 'pending' // الطلب يبقى معلقاً هنا
        ]);

        // 6. خصم المبلغ من رصيد المستخدم (عملية تجميد الرصيد)
        // هذا يمنع البائع من طلب سحب نفس المبلغ مرتين قبل موافقة الأدمن
        $user->balance -= $request->amount;
        $user->save();

        return response()->json([
            'message' => 'Your withdrawal request has been submitted and is awaiting Admin approval.',
            'payout_details' => $payout
        ]);
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
