<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ChatNotificationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
            'conversation_id' => 'required|string|max:191',
            'preview' => 'nullable|string|max:500',
        ]);
        abort_if((int) $data['recipient_id'] === (int) $request->user()->id, 422, 'Invalid chat recipient.');

        $recipient = User::findOrFail($data['recipient_id']);
        app(NotificationService::class)->notify(
            $recipient,
            'chat_message',
            'notification_chat_title',
            'notification_chat_message',
            [],
            [
                'conversation_id' => $data['conversation_id'],
                'sender_id' => (string) $request->user()->id,
                'preview' => $data['preview'] ?? '',
                'route' => 'chat',
            ],
            NotificationService::CATEGORY_CHAT,
            false,
        );

        return response()->json(['success' => true]);
    }
}
