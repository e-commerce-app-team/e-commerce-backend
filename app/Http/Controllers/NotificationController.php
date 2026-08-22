<?php

namespace App\Http\Controllers;

use App\Models\NotificationDevice;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate((int) $request->input('per_page', 30));

        return response()->json([
            'success' => true,
            'data' => collect($notifications->items())->map(fn ($notification) => $this->transform($notification)),
            'unread_count' => $user->unreadNotifications()->count(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();
        return response()->json(['success' => true, 'unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['success' => true, 'unread_count' => 0]);
    }

    public function preferences(Request $request)
    {
        $user = $request->user();
        $defaults = ['orders' => true, 'chat' => true, 'marketing' => true];
        $saved = $user->notificationPreferences()->pluck('enabled', 'type')->all();
        return response()->json(['success' => true, 'data' => collect($defaults)->map(fn ($value, $type) => [
            'type' => $type,
            'enabled' => array_key_exists($type, $saved) ? (bool) $saved[$type] : $value,
        ])->values()]);
    }

    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*.type' => 'required|in:orders,chat,marketing',
            'settings.*.enabled' => 'required|boolean',
        ]);
        foreach ($data['settings'] as $setting) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $request->user()->id, 'type' => $setting['type']],
                ['enabled' => $setting['enabled']],
            );
        }
        return $this->preferences($request);
    }

    public function registerDevice(Request $request)
    {
        $data = $request->validate([
            'fcm_token' => 'required|string',
            'platform' => 'nullable|string|max:30',
            'device_id' => 'nullable|string|max:191',
            'locale' => 'nullable|string|max:10',
        ]);
        $device = NotificationDevice::updateOrCreate(
            ['token' => $data['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'locale' => $data['locale'] ?? 'en',
                'last_seen_at' => now(),
            ],
        );
        return response()->json(['success' => true, 'data' => $device->only(['id', 'platform', 'locale'])]);
    }

    public function unregisterDevice(Request $request)
    {
        $data = $request->validate(['fcm_token' => 'required|string']);
        NotificationDevice::where('user_id', $request->user()->id)->where('token', $data['fcm_token'])->delete();
        return response()->json(['success' => true]);
    }

    private function transform($notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        return array_merge($data, [
            'id' => $notification->id,
            'type' => $data['type'] ?? $notification->type,
            'is_read' => $notification->read_at !== null,
            'created_at' => optional($notification->created_at)->toISOString(),
        ]);
    }
}
