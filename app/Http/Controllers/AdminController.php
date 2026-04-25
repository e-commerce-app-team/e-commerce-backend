<?php

namespace App\Http\Controllers;

use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{

    public function approve($id)
    {
        $user = User::findOrFail($id);
        $user->status = 'approved';
        $user->save();
        return response()->json(['message' => 'User approved']);
    }

    public function reject($id)
    {
        $user = User::findOrFail($id);
        $user->status = 'rejected';
        $user->save();
        return response()->json(['message' => 'User rejected']);
    }

    public function block($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'blocked']);

        return response()->json([
            'message' => 'User has been blocked successfully.'
        ]);
    }

    public function unblock($id)
    {
        $user = User::findOrFail($id);

        // نتحقق أولاً إذا كان فعلاً محظوراً
        if ($user->status !== 'blocked') {
            return response()->json(['message' => 'User is not blocked.'], 400);
        }

        $user->update(['status' => 'approved']);

        return response()->json([
            'message' => 'User has been unblocked and set to approved.'
        ]);
    }

    public function allUsers()
    {
        return response()->json(User::all());
    }

    // 2. تابع يرجع فقط المستخدمين الذين في حالة انتظار (Pending)
    public function pendingUsers()
    {
        $users = User::where('status', 'pending')->get();
        return response()->json($users);
    }

    // 3. تابع يرجع فقط المستخدمين المقبولين (Approved)
    public function approvedUsers()
    {
        $users = User::where('status', 'approved')->get();
        return response()->json($users);
    }

    // 4. تابع يرجع فقط المستخدمين المرفوضين (Rejected)
    public function rejectedUsers()
    {
        $users = User::where('status', 'rejected')->get();
        return response()->json($users);
    }

    public function blockedUsers()
    {
        $users = User::where('status', 'blocked')->latest()->get();
        return response()->json($users);
    }

    public function processPayout(Request $request, $id, $action)
    {
        // البحث عن طلب السحب باستخدام المعرف (ID) وإذا لم يوجد يتم إرجاع خطأ 404 تلقائياً
        $payout = PayoutRequest::findOrFail($id);

        // التأكد أن حالة الطلب لا تزال "قيد الانتظار" (pending) لمنع معالجة نفس الطلب مرتين
        if ($payout->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 400);
        }

        // في حال قرر الأدمن الموافقة على الطلب (إتمام العملية)
        if ($action === 'complete') {
            // تحديث حالة الطلب إلى "مكتمل" وإضافة ملاحظات الأدمن أو نص افتراضي
            $payout->update([
                'status' => 'completed',
                'admin_notes' => $request->admin_notes ?? 'Transfer completed successfully.'
            ]);

            return response()->json(['message' => 'Payout confirmed and closed successfully.']);
        }

        // في حال قرر الأدمن رفض طلب السحب
        elseif ($action === 'reject') {
            // تحديث حالة الطلب إلى "مرفوض" مع ذكر السبب في الملاحظات
            $payout->update([
                'status' => 'rejected',
                'admin_notes' => $request->admin_notes ?? 'Request rejected by administration.'
            ]);

            // جلب بيانات المستخدم (البائع) صاحب هذا الطلب
            $user = $payout->user;

            // إعادة المبلغ المقتطع إلى رصيد البائع (لأننا خصمنا المبلغ منه عند إرسال الطلب)
            $user->balance += $payout->amount;
            $user->save();

            return response()->json(['message' => 'Request rejected and amount refunded to vendor balance.']);
        }

        // إرجاع خطأ في حال كان الإجراء (action) المرسل غير معروف (ليس complete أو reject)
        return response()->json(['message' => 'Invalid action. Must be "complete" or "reject".'], 400);
    }

}
