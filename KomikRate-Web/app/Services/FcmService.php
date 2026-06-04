<?php

namespace App\Services;

use App\Models\ReplyNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private function getAccessToken(): ?string
    {
        try {
            $keyFile = storage_path('app/firebase-key.json');
            $key     = json_decode(file_get_contents($keyFile), true);

            $now   = time();
            $claim = [
                'iss'   => $key['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ];

            // Buat JWT manual
            $header  = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = base64_encode(json_encode($claim));
            $header  = str_replace(['+', '/', '='], ['-', '_', ''], $header);
            $payload = str_replace(['+', '/', '='], ['-', '_', ''], $payload);

            $data = "$header.$payload";
            openssl_sign($data, $signature, $key['private_key'], 'SHA256');
            $signature = str_replace(
                ['+', '/', '='],
                ['-', '_', ''],
                base64_encode($signature)
            );

            $jwt = "$data.$signature";

            // Tukar JWT dengan access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            return $response->json('access_token');
        } catch (\Exception $e) {
            Log::error('FCM get token error: ' . $e->getMessage());
            return null;
        }
    }

    public function sendReplyNotification(ReplyNotification $notification): bool
    {
        try {
            $token = $notification->recipient->fcm_token ?? null;
            if (!$token) {
                Log::warning("No FCM token for user {$notification->recipient_id}");
                return false;
            }

            $notification->load('reply.user', 'reply.review.comic');

            $sender   = $notification->sender->name             ?? 'Someone';
            $comic    = $notification->reply->review->comic->title ?? 'a comic';
            $comicId  = $notification->reply->review->comic_id     ?? 0;
            $reviewId = $notification->reply->review_id            ?? 0;
            $replyId  = $notification->reply->id                   ?? 0;

            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                Log::error('FCM: gagal dapat access token');
                return false;
            }

            $keyFile   = json_decode(file_get_contents(storage_path('app/firebase-key.json')), true);
            $projectId = $keyFile['project_id'];

            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Content-Type'  => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => 'Balasan Baru 💬',
                        'body'  => "{$sender} membalas review kamu pada \"{$comic}\"",
                    ],
                    'data' => [
                        'comic_id'    => (string) $comicId,
                        'review_id'   => (string) $reviewId,
                        'reply_id'    => (string) $replyId,
                        'comic_title' => $comic,
                        'sender_name' => $sender,
                    ],
                    'android' => [
                        'priority' => 'high',
                    ],
                ],
            ]);

            if ($response->successful()) {
                $notification->update([
                    'delivery_status' => 'sent',
                    'sent_at'         => now(),
                ]);
                Log::info("FCM sent OK to user {$notification->recipient_id}");
                return true;
            }

            Log::error('FCM failed: ' . $response->body());
            $notification->update([
                'delivery_status' => 'failed',
                'error_message'   => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('FCM exception: ' . $e->getMessage());
            $notification->update([
                'delivery_status' => 'failed',
                'error_message'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendTestNotification(string $token): bool
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) return false;

            $keyFile   = json_decode(file_get_contents(storage_path('app/firebase-key.json')), true);
            $projectId = $keyFile['project_id'];

            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Content-Type'  => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token'        => $token,
                    'notification' => [
                        'title' => 'Test KomikRate',
                        'body'  => 'FCM works! ✅',
                    ],
                ],
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Test FCM failed: ' . $e->getMessage());
            return false;
        }
    }
}