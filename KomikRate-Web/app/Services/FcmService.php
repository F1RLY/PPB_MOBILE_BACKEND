<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\ServiceAccount;
use App\Models\ReplyNotification;
use Illuminate\Support\Facades\Log;

class FcmService
{
    protected $messaging;

    public function __construct()
    {
        try {
            $serviceAccount = ServiceAccount::fromJsonFile(
                config('firebase.credentials')
            );

            $firebase = (new Factory)
                ->withServiceAccount($serviceAccount)
                ->create();

            $this->messaging = $firebase->getMessaging();
        } catch (\Exception $e) {
            Log::error('Firebase init failed: ' . $e->getMessage());
            $this->messaging = null;
        }
    }

    /**
     * Send notification ke device user
     */
    public function sendReplyNotification(ReplyNotification $notification): bool
    {
        try {
            if (!$this->messaging) {
                throw new \Exception('Firebase messaging not initialized');
            }

            $token = $notification->recipient->fcm_token;
            if (!$token) {
                Log::warning("No FCM token for user {$notification->recipient_id}");
                return false;
            }

            $notification->load('reply.user', 'reply.review.comic');
            $sender = $notification->sender->name ?? 'Someone';
            $comic = $notification->reply->review->comic->title ?? 'a comic';

            $message = CloudMessage::fromArray([
                'token' => $token,
                'notification' => [
                    'title' => 'Balasan Baru',
                    'body'  => "{$sender} membalas review Anda",
                ],
                'data' => [
                    'reply_id'   => (string) $notification->reply->id,
                    'review_id'  => (string) $notification->reply->review_id,
                    'comic_id'   => (string) $notification->reply->review->comic_id,
                ],
                'android' => [
                    'priority' => 'high',
                ],
            ]);

            $this->messaging->send($message);
            
            $notification->update([
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('FCM send failed: ' . $e->getMessage());
            $notification->update([
                'delivery_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendTestNotification(string $token): bool
    {
        try {
            $message = CloudMessage::fromArray([
                'token' => $token,
                'notification' => [
                    'title' => 'Test',
                    'body' => 'FCM works! ✅',
                ],
            ]);

            $this->messaging->send($message);
            return true;
        } catch (\Exception $e) {
            Log::error('Test FCM failed: ' . $e->getMessage());
            return false;
        }
    }
}