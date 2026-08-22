<?php

namespace App\Jobs;

use App\Models\Ad;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAdNotification implements ShouldQueue
{
    use Queueable;

    public $ad;

    /**
     * Create a new job instance.
     */
    public function __construct(Ad $ad)
    {
        $this->ad = $ad;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        User::whereIn('role', ['buyer', 'vendor', 'wholesale', 'staff'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    app(NotificationService::class)->notifyAnnouncement($user, [
                        'ad_id' => (string) $this->ad->id,
                        'route' => 'announcement',
                        'link' => $this->ad->link ?? '',
                        'image_url' => $this->ad->image_url ?? '',
                        'title_ar' => $this->ad->title_ar ?: $this->ad->title,
                        'title_en' => $this->ad->title_en ?: $this->ad->title,
                        'message_ar' => $this->ad->description_ar ?: $this->ad->description,
                        'message_en' => $this->ad->description_en ?: $this->ad->description,
                    ]);
                }
            });
    }
}
