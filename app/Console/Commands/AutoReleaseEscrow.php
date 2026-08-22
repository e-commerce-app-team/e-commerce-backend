<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\SubOrder;
use App\Services\EscrowReleaseService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoReleaseEscrow extends Command
{
    protected $signature = 'orders:auto-release-escrow';
    protected $description = 'Automatically release eligible seller escrow after the configured delivery period';

    public function handle(EscrowReleaseService $releaseService): int
    {
        $days = max(1, (int) PlatformSetting::getValue('auto_release_days', 3));
        $releasedCount = 0;

        SubOrder::query()
            ->where('status', 'shipped')
            ->whereNull('escrow_released_at')
            ->where('escrow_amount', '>', 0)
            ->where(function ($query) use ($days) {
                $query->whereNotNull('escrow_release_at')->where('escrow_release_at', '<=', now())
                    ->orWhere(function ($fallback) use ($days) {
                        $fallback->whereNull('escrow_release_at')->where('updated_at', '<=', now()->subDays($days));
                    });
            })
            ->orderBy('id')
            ->chunkById(50, function ($subOrders) use ($releaseService, &$releasedCount): void {
                foreach ($subOrders as $candidate) {
                    try {
                        DB::transaction(function () use ($candidate, $releaseService, &$releasedCount): void {
                            $result = $releaseService->release($candidate, 'delivery_auto_confirmed');
                            if (empty($result['released'])) return;
                            $releasedCount++;

                            $order = Order::whereKey($candidate->order_id)->lockForUpdate()->first();
                            if (! $order) return;
                            $subOrders = $order->subOrders()->get();
                            $allDelivered = $subOrders->isNotEmpty() && $subOrders->every(fn ($sub) => $sub->status === 'delivered');
                            $allReleased = $subOrders->isNotEmpty() && $subOrders->every(fn ($sub) => (bool) $sub->escrow_released_at);
                            $timeline = $order->status_timeline ?? [];
                            $timeline[] = ['status' => 'delivery_auto_confirmed', 'sub_order_id' => $candidate->id, 'title' => 'delivery_auto_confirmed', 'time' => now()->toDateTimeString()];
                            $order->update([
                                'status' => $allDelivered ? 'delivered' : 'shipped',
                                'payment_status' => $allReleased ? 'released' : 'paid_escrow',
                                'delivered_at' => $allDelivered ? now() : null,
                                'status_timeline' => $timeline,
                            ]);
                            $notifications = app(NotificationService::class);
                            $notifications->notify($order->load('buyer')->buyer, 'merchant_payment_released',
                                'notification_payment_released_title', 'notification_auto_release_message',
                                ['order_id' => (string) $order->id], ['order_id' => (string) $order->id, 'route' => 'order'], NotificationService::CATEGORY_ORDERS, true);
                            $candidate->load('seller');
                            if ($candidate->seller) {
                                $notifications->notify($candidate->seller, 'merchant_payment_released',
                                    'notification_payment_released_title', 'notification_payment_released_message',
                                    ['order_id' => (string) $order->id], ['order_id' => (string) $order->id, 'route' => 'wallet'], NotificationService::CATEGORY_ORDERS, true);
                            }
                        });
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            });

        $this->info("Released {$releasedCount} seller escrow record(s).");
        return self::SUCCESS;
    }
}
