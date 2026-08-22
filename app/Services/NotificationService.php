<?php

namespace App\Services;

use App\Models\NotificationDevice;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class NotificationService
{
    public const CATEGORY_ORDERS = 'orders';
    public const CATEGORY_CHAT = 'chat';
    public const CATEGORY_MARKETING = 'marketing';

    public function notify(
        User $user,
        string $type,
        string $titleKey,
        string $messageKey,
        array $params = [],
        array $payload = [],
        string $category = self::CATEGORY_ORDERS,
        bool $required = false,
        ?string $titleAr = null,
        ?string $titleEn = null,
        ?string $messageAr = null,
        ?string $messageEn = null,
    ): ?DatabaseNotification {
        [$resolvedTitleAr, $resolvedTitleEn, $resolvedMessageAr, $resolvedMessageEn] = $this->resolveCopy($titleKey, $messageKey, $params);
        $titleAr ??= $resolvedTitleAr;
        $titleEn ??= $resolvedTitleEn;
        $messageAr ??= $resolvedMessageAr;
        $messageEn ??= $resolvedMessageEn;
        $data = array_merge([
            'type' => $type,
            'title_key' => $titleKey,
            'message_key' => $messageKey,
            'params' => $params,
            'category' => $category,
            'required' => $required,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'message_ar' => $messageAr,
            'message_en' => $messageEn,
        ], $payload);

        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'system',
            'data' => array_merge($data, ['notification_id' => null]),
        ]);
        $data['notification_id'] = $notification->id;
        $notification->update(['data' => $data]);

        if ($required || $this->isEnabled($user, $category)) {
            $this->sendToDevices($user, $data);
        }

        return $notification;
    }

    private function resolveCopy(string $titleKey, string $messageKey, array $params): array
    {
        $titles = [
            'notification_order_created_title' => ['تم إنشاء الطلب', 'Order created'],
            'notification_new_order_title' => ['طلب جديد', 'New order'],
            'notification_shipping_quote_title' => ['تكلفة شحن جديدة', 'Shipping cost set'],
            'notification_shipping_approved_title' => ['تمت الموافقة على الشحن', 'Shipping approved'],
            'notification_payment_success_title' => ['نجاح الدفع', 'Payment successful'],
            'notification_order_preparing_title' => ['بدأ تجهيز الطلب', 'Order preparation started'],
            'notification_order_shipped_title' => ['تم شحن الطلب', 'Order shipped'],
            'notification_delivery_confirmation_title' => ['تأكيد الاستلام مطلوب', 'Delivery confirmation required'],
            'notification_delivery_confirmed_title' => ['تم تأكيد الاستلام', 'Delivery confirmed'],
            'notification_payment_released_title' => ['تم تحرير المستحقات', 'Payment released'],
            'notification_order_cancelled_title' => ['تم إلغاء الطلب', 'Order cancelled'],
            'notification_refund_title' => ['تم رد المبلغ', 'Refund completed'],
            'notification_chat_title' => ['رسالة جديدة', 'New message'],
            'notification_announcement_title' => ['إعلان جديد', 'New announcement'],
            'notification_generic_title' => ['إشعار جديد', 'New notification'],
        ];
        $messages = [
            'notification_order_created_message' => ['تم استلام طلبك رقم @order_id بنجاح', 'Your order @order_id was received successfully'],
            'notification_new_order_message' => ['وصل طلب جديد رقم @order_id', 'A new order @order_id has arrived'],
            'notification_shipping_quote_message' => ['حدد التاجر تكلفة شحن الطلب @order_id', 'The seller set a shipping cost for order @order_id'],
            'notification_shipping_approved_message' => ['وافق المشتري على شحن الطلب @order_id', 'The buyer approved shipping for order @order_id'],
            'notification_payment_success_message' => ['تم الدفع للطلب @order_id وحجز المبلغ بأمان', 'Payment for order @order_id is safely held'],
            'notification_seller_payment_success_message' => ['تم دفع الطلب @order_id وحجز المبلغ', 'Order @order_id was paid and held in escrow'],
            'notification_order_preparing_message' => ['بدأ تجهيز الطلب @order_id', 'The seller started preparing order @order_id'],
            'notification_order_shipped_message' => ['تم شحن الطلب @order_id', 'Your order @order_id has been shipped'],
            'notification_delivery_confirmation_message' => ['يرجى تأكيد استلام الطلب @order_id', 'Please confirm delivery for order @order_id'],
            'notification_delivery_confirmed_message' => ['أكد المشتري استلام الطلب @order_id', 'The buyer confirmed delivery for order @order_id'],
            'notification_payment_released_message' => ['تم تحرير مستحقات الطلب @order_id', 'Payment for order @order_id was released'],
            'notification_auto_release_message' => ['تم تحرير مستحقات الطلب @order_id تلقائياً', 'Payment for order @order_id was released automatically'],
            'notification_order_cancelled_message' => ['تم إلغاء الطلب @order_id', 'Order @order_id was cancelled'],
            'notification_refund_message' => ['تم رد مبلغ الطلب @order_id', 'The amount for order @order_id was refunded'],
            'notification_chat_message' => ['لديك رسالة جديدة', 'You have a new message'],
            'notification_announcement_message' => ['لديك إعلان جديد', 'You have a new announcement'],
            'notification_generic_message' => ['لديك إشعار جديد', 'You have a new notification'],
        ];
        $replace = static function (string $text) use ($params): string {
            foreach ($params as $key => $value) $text = str_replace('@' . $key, (string) $value, $text);
            return $text;
        };
        $title = $titles[$titleKey] ?? [$titleKey, $titleKey];
        $message = $messages[$messageKey] ?? [$messageKey, $messageKey];
        return [$replace($title[0]), $replace($title[1]), $replace($message[0]), $replace($message[1])];
    }

    public function notifyAnnouncement(User $user, array $data): ?DatabaseNotification
    {
        return $this->notify(
            $user,
            'announcement',
            'notification_announcement_title',
            'notification_announcement_message',
            [],
            $data,
            self::CATEGORY_MARKETING,
            false,
            $data['title_ar'] ?? null,
            $data['title_en'] ?? null,
            $data['message_ar'] ?? null,
            $data['message_en'] ?? null,
        );
    }

    public function isEnabled(User $user, string $category): bool
    {
        $type = match ($category) {
            self::CATEGORY_CHAT => 'chat',
            self::CATEGORY_MARKETING => 'marketing',
            default => 'orders',
        };

        return (bool) ($user->notificationPreferences()->where('type', $type)->value('enabled') ?? true);
    }

    private function sendToDevices(User $user, array $data): void
    {
        $devices = $user->notificationDevices()->get();
        if ($devices->isEmpty() && $user->fcm_token) {
            $devices = collect([(object) [
                'token' => $user->fcm_token,
                'locale' => 'en',
            ]]);
        }

        foreach ($devices as $device) {
            try {
                $locale = str_starts_with((string) ($device->locale ?? 'en'), 'ar') ? 'ar' : 'en';
                $title = $data[$locale === 'ar' ? 'title_ar' : 'title_en'] ?: $data['title_key'];
                $body = $data[$locale === 'ar' ? 'message_ar' : 'message_en'] ?: $data['message_key'];
                $message = CloudMessage::new()
                    ->toToken((string) $device->token)
                    ->withNotification(FcmNotification::create((string) $title, (string) $body))
                    ->withData($this->stringifyData(array_merge($data, [
                        'notification_id' => (string) ($data['notification_id'] ?? ''),
                    ])));
                app('firebase.messaging')->send($message);
            } catch (\Throwable $e) {
                Log::warning('FCM notification failed', [
                    'user_id' => $user->id,
                    'device_id' => $device->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function stringifyData(array $data): array
    {
        return collect($data)->mapWithKeys(function ($value, $key) {
            return [$key => is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)];
        })->all();
    }
}
