<?php

namespace App\Services;

use App\Models\User;
class PushNotificationService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        app(NotificationService::class)->notify(
            $user,
            (string) ($data['type'] ?? 'system_notification'),
            (string) ($data['title_key'] ?? 'notification_generic_title'),
            (string) ($data['message_key'] ?? 'notification_generic_message'),
            [],
            array_merge($data, ['title_en' => $title, 'message_en' => $body]),
            (string) ($data['category'] ?? NotificationService::CATEGORY_ORDERS),
            (bool) ($data['required'] ?? true),
            (string) ($data['title_ar'] ?? $title),
            $title,
            (string) ($data['message_ar'] ?? $body),
            $body,
        );
    }
}
