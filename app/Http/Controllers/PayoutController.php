<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Transaction;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;   // هذا السطر يحل مشكلة الـ DB
use App\Models\User;                 // تأكد من وجوده عشان الـ User::where
use Carbon\Carbon;
class PayoutController extends Controller
{
    // 🔥 عرض الرصيد المتاح للبائع باستثناء الطلبات المحجوزة (تحت الـ 48 ساعة ولم تُستلم)
    // ==============================================================
    public function getBalance()
    {
        $user = auth()->user();

        if ($user->getAttribute('role') !== 'vendor' && $user->getAttribute('role') !== 'wholesale') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // 1. جلب رصيد الحساب الكلي شامل المبالغ الحالية
        $totalBalance = $user->getAttribute('balance');

        // 2. حساب المبالغ المحجوزة حالياً بالنظام:
        // وهي مبالغ الطلبات التي لم يتم تأكيد استلامها الفوري، ولم يمر على شحنها 48 ساعة بعد.
        $lockedFunds = Order::where('seller_id', $user->id)
            ->where('payment_status', 'paid_escrow') // الحالة محجوزة
            ->where(function ($query) {
                $query->whereNull('shipped_at') // لم تشحن بعد (قيد التجهيز أو الانتظار)
                    ->orWhere('shipped_at', '>=', Carbon::now()->subHours(48)); // أو شُحنت ولكن لم يمر 48 ساعة عليها
            })
            ->join('transactions', 'orders.id', '=', 'transactions.order_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.type', 'deposit')
            ->sum('transactions.amount');

        // 3. الرصيد المتاح للسحب الفعلي
        $availableBalance = max(0, $totalBalance - $lockedFunds);

        return response()->json([
            'total_balance' => round($totalBalance, 2),
            'locked_balance' => round($lockedFunds, 2),       // الرصيد المحجوز المحمي بالضمان
            'available_balance' => round($availableBalance, 2), // الرصيد الصافي القابل للسحب فوراً
            'payout_method' => $user->getAttribute('payout_method'),
            'payout_account' => $user->getAttribute('payout_account'),
        ]);
    }
    // 2. إجراء عملية سحب فورية بناءً على الرصيد المتاح فقط
    /*  public function instantWithdraw(Request $request)
     {
         $user = auth()->user();

         if ($user->role !== 'vendor' && $user->role !== 'wholesale') {
             return response()->json(['message' => 'Unauthorized.'], 403);
         }

         $request->validate([
             'amount' => 'required|numeric|min:50',
             'password' => 'required'
         ]);

         if (!Hash::check($request->password, $user->password)) {
             return response()->json(['message' => 'Incorrect password.'], 403);
         }

         return DB::transaction(function () use ($user, $request) {
             $currentUser = User::where('id', $user->id)->lockForUpdate()->first();

             // إعادة الحساب داخل الـ transaction لمنع ثغرات التزامن الرقمي
             $lockedFunds = Order::where('seller_id', $currentUser->id)
                 ->where('payment_status', 'paid_escrow')
                 ->where(function ($query) {
                     $query->whereNull('shipped_at')
                         ->orWhere('shipped_at', '>=', Carbon::now()->subHours(48));
                 })
                 ->join('transactions', 'orders.id', '=', 'transactions.order_id')
                 ->where('transactions.user_id', $currentUser->id)
                 ->where('transactions.type', 'deposit')
                 ->sum('transactions.amount');

             $availableBalance = max(0, $currentUser->balance - $lockedFunds);

             if ($availableBalance < $request->amount) {
                 return response()->json([
                     'message' => 'Insufficient available balance. Remaining funds are locked under 48h escrow protection.',
                     'available_balance' => round($availableBalance, 2)
                 ], 400);
             }

             $currentUser->decrement('balance', $request->amount);

             $payout = PayoutRequest::create([
                 'user_id' => $currentUser->id,
                 'amount' => $request->amount,
                 'payout_method' => $currentUser->payout_method ?? 'Default Method',
                 'payout_account' => $currentUser->payout_account ?? 'Default Account',
                 'status' => 'completed',
                 'admin_notes' => 'Instant withdrawal processed successfully.'
             ]);

             Transaction::create([
                 'user_id' => $currentUser->id,
                 'type' => 'withdrawal',
                 'amount' => $request->amount,
                 'description' => "Instant withdrawal of {$request->amount}"
             ]);

             return response()->json([
                 'success' => true,
                 'message' => 'Withdrawal successful.',
                 'new_balance' => round($currentUser->balance, 2),
                 'details' => $payout
             ]);
         });
     }
  */

    public function instantWithdraw(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'vendor' && $user->role !== 'wholesale') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // 📌 قواعد التحقق حسب طريقة السحب
        $rules = [
            'amount' => 'required|numeric|min:50',
            'payout_method' => 'required|string|in:sham cash,bank account', // إجباري
        ];

        // إذا اختار شام كاش
        if ($request->payout_method === 'sham cash') {
            $rules['qr_image'] = 'required|image|max:2048'; // صورة QR إجبارية
            $rules['sham_code'] = 'required|string|max:50'; // رمز شام كاش إجباري
        }
        // إذا اختار بنك
        elseif ($request->payout_method === 'bank account') {
            $rules['payout_account'] = 'required|string|max:100'; // رقم حساب إجباري
        }

        $request->validate($rules);

        // ❌ تم حذف التحقق من كلمة المرور بالكامل

        return DB::transaction(function () use ($user, $request) {
            $currentUser = User::where('id', $user->id)->lockForUpdate()->first();

            // حساب المبلغ المحجوز
            $lockedFunds = Order::where('seller_id', $currentUser->id)
                ->where('payment_status', 'paid_escrow')
                ->where(function ($query) {
                    $query->whereNull('shipped_at')
                        ->orWhere('shipped_at', '>=', Carbon::now()->subHours(48));
                })
                ->join('transactions', 'orders.id', '=', 'transactions.order_id')
                ->where('transactions.user_id', $currentUser->id)
                ->where('transactions.type', 'deposit')
                ->sum('transactions.amount');

            $availableBalance = max(0, $currentUser->balance - $lockedFunds);

            if ($availableBalance < $request->amount) {
                return response()->json([
                    'message' => 'Insufficient available balance. Remaining funds are locked under 48h escrow protection.',
                    'available_balance' => round($availableBalance, 2)
                ], 400);
            }

            // خصم الرصيد
            $currentUser->decrement('balance', $request->amount);

            // 📌 معالجة صورة QR إذا وجدت
            $qrPath = null;
            if ($request->hasFile('qr_image')) {
                $qrPath = $request->file('qr_image')->store('qr_codes', 'public');
            }

            // 📌 تحضير بيانات السحب حسب الطريقة
            $payoutData = [
                'user_id' => $currentUser->id,
                'amount' => $request->amount,
                'payout_method' => $request->payout_method,
                'status' => 'completed',
                'admin_notes' => 'Instant withdrawal processed successfully.'
            ];

            // إذا كانت شام كاش
            if ($request->payout_method === 'sham cash') {
                $payoutData['payout_account'] = 'Sham Cash Wallet';
                $payoutData['sham_code'] = $request->sham_code;
                $payoutData['qr_image'] = $qrPath;
            }
            // إذا كانت بنك
            elseif ($request->payout_method === 'bank account') {
                $payoutData['payout_account'] = $request->payout_account;
                $payoutData['sham_code'] = null;
                $payoutData['qr_image'] = null;
            }

            $payout = PayoutRequest::create($payoutData);

            // تسجيل المعاملة
            Transaction::create([
                'user_id' => $currentUser->id,
                'type' => 'withdrawal',
                'amount' => $request->amount,
                'description' => "Instant withdrawal of {$request->amount} via ({$request->payout_method})"
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal successful.',
                'new_balance' => round($currentUser->balance, 2),
                'details' => $payout
            ]);
        });
    }
    // 3. مراجعة تاريخ السحوبات للبائع فقط
    public function payoutHistory()
    {
        $user = auth()->user();
        if ($user->role !== 'vendor' && $user->role !== 'wholesale') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        return response()->json($user->payoutRequests()->latest()->get());
    }

}
