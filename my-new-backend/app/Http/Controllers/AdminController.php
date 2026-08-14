<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\PayoutRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // ============================================================
    // 📌 إدارة المستخدمين
    // ============================================================

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

    public function pendingUsers()
    {
        $users = User::where('status', 'pending')->get();
        return response()->json($users);
    }

    public function approvedUsers()
    {
        $users = User::where('status', 'approved')->get();
        return response()->json($users);
    }

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

    // ============================================================
    // 📌 شحن الرصيد بواسطة الأدمن
    // ============================================================

    public function depositByAdmin(Request $request)
    {
        $admin = auth()->user();

        $request->validate([
            'user_id' => 'required|exists:users,id,role,buyer',
            'amount' => 'required|numeric|min:10'
        ]);

        $buyer = User::findOrFail($request->user_id);

        if ($buyer->role !== 'buyer') {
            return response()->json(['message' => 'Funds can only be added to buyer accounts.'], 400);
        }

        $buyer->balance += $request->amount;
        $buyer->save();

        Transaction::create([
            'user_id' => $buyer->id,
            'type' => 'deposit',
            'amount' => $request->amount,
            'description' => 'Wallet topped up by Admin: ' . $admin->first_name . ' ' . $admin->last_name
        ]);

        return response()->json([
            'message' => 'Balance topped up successfully for ' . $buyer->first_name,
            'new_balance' => $buyer->balance
        ]);
    }

    // ============================================================
    // 📌 إدارة الإعلانات (بدون admin_id)
    // ============================================================

    /**
     * عرض جميع طلبات الإعلانات
     */
    public function index(Request $request)
    {
        $query = Ad::with('seller:id,first_name,last_name,store_name');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $ads = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $ads
        ]);
    }

    /**
     * عرض تفاصيل إعلان
     */
    public function showAd($id)
    {
        $ad = Ad::with(['seller', 'views'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ad,
            'stats' => [
                'views_count' => $ad->views_count,
                'clicks_count' => $ad->clicks_count,
                'views_by_date' => $this->getViewsByDate($ad),
                'clicks_by_date' => $this->getClicksByDate($ad),
            ]
        ]);
    }

    /**
     * الموافقة على إعلان
     */
    public function approveAd($id)
    {
        $ad = Ad::where('status', 'pending')->findOrFail($id);

        $ad->update([
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => $this->calculateExpiryDate($ad->duration),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ad approved successfully.',
            'data' => $ad
        ]);
    }

    /**
     * رفض إعلان مع سبب
     */
    public function rejectAd(Request $request, $id)
    {
        $request->validate([
            'admin_notes' => 'required|string|max:500'
        ]);

        $ad = Ad::where('status', 'pending')->findOrFail($id);

        $ad->update([
            'status' => 'rejected',
            'admin_notes' => $request->admin_notes
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ad rejected.',
            'data' => $ad
        ]);
    }

    /**
     * إيقاف إعلان نشط
     */
    public function deactivateAd($id)
    {
        $ad = Ad::where('status', 'active')->findOrFail($id);

        $ad->update([
            'status' => 'expired',
            'admin_notes' => 'Deactivated by admin.'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ad deactivated successfully.'
        ]);
    }

    /**
     * عرض إحصائيات عامة للإعلانات
     */
    public function statsAd()
    {
        return response()->json([
            'success' => true,
            'stats' => [
                'total_ads' => Ad::count(),
                'pending_ads' => Ad::where('status', 'pending')->count(),
                'active_ads' => Ad::where('status', 'active')->count(),
                'expired_ads' => Ad::where('status', 'expired')->count(),
                'rejected_ads' => Ad::where('status', 'rejected')->count(),
                'total_revenue' => Ad::sum('price'),
                'total_views' => Ad::sum('views_count'),
                'total_clicks' => Ad::sum('clicks_count'),
            ],
            'by_type' => [
                'banner' => Ad::where('type', 'banner')->count(),
                'promoted_product' => Ad::where('type', 'promoted_product')->count(),
                'featured_store' => Ad::where('type', 'featured_store')->count(),
                'paid_notification' => Ad::where('type', 'paid_notification')->count(),
            ]
        ]);
    }

    // ============================================================
    // 📌 دوال مساعدة
    // ============================================================

    private function calculateExpiryDate($duration)
    {
        $map = [
            '1_day' => now()->addDay(),
            '3_days' => now()->addDays(3),
            '1_week' => now()->addWeek(),
            '1_month' => now()->addMonth(),
        ];

        return $map[$duration] ?? now()->addDay();
    }

    private function getViewsByDate($ad)
    {
        return $ad->views()
            ->where('type', 'view')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get();
    }

    private function getClicksByDate($ad)
    {
        return $ad->views()
            ->where('type', 'click')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get();
    }
}