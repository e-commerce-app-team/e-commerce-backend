<?php

namespace App\Jobs;

use App\Models\Ad;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

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
        try {
            $messaging = app('firebase.messaging');

            $notification = Notification::create($this->ad->title, $this->ad->description);
            if (!empty($this->ad->image_url)) {
                $notification = $notification->withImageUrl($this->ad->image_url);
            }

            $message = CloudMessage::withTarget('topic', 'all_users')
                ->withNotification($notification)
                ->withData([
                    'type' => 'ad_notification',
                    'ad_id' => (string) $this->ad->id,
                    'link' => $this->ad->link ?? '',
                ]);

            $messaging->send($message);

            Log::info("Ad Notification sent successfully for Ad ID: " . $this->ad->id);

        } catch (MessagingException $e) {
            Log::error('Firebase Messaging Error: ' . $e->getMessage());
        } catch (FirebaseException $e) {
            Log::error('Firebase Error: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('General Error sending notification: ' . $e->getMessage());
        }
    }
}
