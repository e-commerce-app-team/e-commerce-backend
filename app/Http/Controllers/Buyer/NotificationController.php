<?php

namespace App\Http\Controllers\Buyer;
use App\Models\NotificationPreference;
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

   public function updatePreferences(Request $request)
{

    if ($request->user()->role !== 'buyer') { 
        return response()->json([
            'status'  => false,
            'message' => 'Unauthorized. Only buyers can access this endpoint.'
        ], 403);
    }

    $request->validate([
        'settings'          => 'required|array',
        'settings.*.type'   => 'required|string',
        'settings.*.enabled' => 'required|boolean',
    ]);

    $user = $request->user();

    // 3. حفظ التفضيلات
    foreach ($request->settings as $setting) {
        NotificationPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'type'    => $setting['type'],
            ],
            [
                'enabled' => $setting['enabled'],
            ]
        );
    }

    return response()->json([
        'status'  => true,
        'message' => 'Notification preferences updated successfully.',
        'data'    => $user->notificationPreferences()->get(['type', 'enabled']),
    ], 200);
}

}