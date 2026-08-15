<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/buyer/notifications
     * عرض جميع الإشعارات الخاصة بالمشتري الحالي
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // جلب الإشعارات وقراءة الحقول من عمود الـ data التابع لـ DatabaseNotification
        $notifications = $user->notifications()
            ->latest()
            ->get()
            ->map(function ($notification) {
                return [
                    'id'           => $notification->id,
                    'title'        => $notification->data['title'] ?? $notification->data['product_name'] ?? 'إشعار جديد',
                    'message'      => $notification->data['message'] ?? $notification->data['body'] ?? '',
                    'product_id'   => $notification->data['product_id'] ?? null,
                    'is_read'      => $notification->read_at !== null,
                    'created_at'   => $notification->created_at->toDateTimeString(),
                ];
            });

        return response()->json([
            'status' => true,
            'data'   => $notifications
        ], 200);
    }
}