<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (!$user->fcm_token) {
            return;
        }

        $serverKey = config('services.firebase.server_key');
        if (!$serverKey) {
            Log::info('Push skipped (no FCM server key)', [
                'user_id' => $user->id,
                'title'   => $title,
            ]);
            return;
        }

        try {
            Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to'           => $user->fcm_token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::warning('FCM push failed: ' . $e->getMessage());
        }
    }
}
