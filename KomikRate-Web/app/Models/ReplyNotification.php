<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReplyNotification extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reply_notifications';

    protected $fillable = [
        'reply_id',
        'recipient_id',
        'sender_id',
        'fcm_token',
        'delivery_status',
        'is_read',
        'read_at',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'is_read'    => 'boolean',
            'read_at'    => 'datetime',
            'sent_at'    => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ───────────────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Notifikasi untuk reply tertentu
     */
    public function reply()
    {
        return $this->belongsTo(Reply::class, 'reply_id');
    }

    /**
     * User yang menerima notifikasi
     */
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * User yang mengirim reply (dan trigger notifikasi)
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ───────────────────────────────────────────────────────────────────────
    // SCOPES
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Notifikasi yang belum dibaca
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Notifikasi yang sudah dibaca
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Notifikasi dengan status delivery tertentu
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('delivery_status', $status);
    }

    /**
     * Notifikasi yang pending (belum dikirim)
     */
    public function scopePending($query)
    {
        return $query->where('delivery_status', 'pending');
    }

    /**
     * Notifikasi yang sudah berhasil dikirim
     */
    public function scopeSent($query)
    {
        return $query->where('delivery_status', 'sent');
    }

    /**
     * Notifikasi yang gagal dikirim
     */
    public function scopeFailed($query)
    {
        return $query->where('delivery_status', 'failed');
    }

    /**
     * Filter untuk user tertentu (recipient)
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('recipient_id', $userId);
    }

    /**
     * Notifikasi terbaru
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Notifikasi untuk periode tertentu (last X days)
     */
    public function scopeRecentDays($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ───────────────────────────────────────────────────────────────────────
    // ACCESSORS
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Pesan notifikasi yang akan ditampilkan
     * Format: "{Nama Sender} balasan review Anda pada {Judul Komik}"
     */
    public function getNotificationMessageAttribute(): string
    {
        $reply = $this->reply;
        if (!$reply || !$reply->review) {
            return 'Anda menerima balasan baru';
        }

        $comic = $reply->review->comic;
        $senderName = $this->sender->name ?? 'Someone';
        $comicTitle = $comic->title ?? 'a comic';

        return "{$senderName} membalas review Anda pada \"{$comicTitle}\"";
    }

    /**
     * Check apakah notifikasi bisa di-retry (jika failed)
     */
    public function canRetry(): bool
    {
        return $this->delivery_status === 'failed' && 
               $this->created_at->diffInHours(now()) < 24; // Max 24 jam
    }

    /**
     * Waktu yang readable untuk sent_at
     */
    public function getSentTimeAttribute(): ?string
    {
        if (!$this->sent_at) return null;
        return $this->sent_at->diffForHumans();
    }

    /**
     * Waktu yang readable untuk read_at
     */
    public function getReadTimeAttribute(): ?string
    {
        if (!$this->read_at) return null;
        return $this->read_at->diffForHumans();
    }

    // ───────────────────────────────────────────────────────────────────────
    // HELPER METHODS
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Mark notifikasi sebagai read
     */
    public function markAsRead(): bool
    {
        return $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Update delivery status setelah FCM dikirim
     */
    public function updateDeliveryStatus(string $status, ?string $errorMessage = null): bool
    {
        $updates = [
            'delivery_status' => $status,
        ];

        if ($status === 'sent') {
            $updates['sent_at'] = now();
        }

        if ($errorMessage) {
            $updates['error_message'] = $errorMessage;
        }

        return $this->update($updates);
    }

    /**
     * Get payload untuk FCM notification
     */
    public function getFcmPayload(): array
    {
        $reply = $this->reply;
        
        return [
            'title'       => 'Balasan Baru',
            'body'        => $this->notification_message,
            'replyId'     => (string) $reply->id,
            'reviewId'    => (string) $reply->review_id,
            'senderId'    => (string) $this->sender_id,
            'senderName'  => $this->sender->name,
            'deepLink'    => $reply->getDeepLinkPath(),
            'icon'        => 'ic_notification', // Android notification icon
            'priority'    => 'high',
        ];
    }

    /**
     * Check apakah recipient device token valid
     */
    public function hasFcmToken(): bool
    {
        return !empty($this->fcm_token) && strlen($this->fcm_token) > 10;
    }

    /**
     * Get count unread notifications untuk user
     */
    public static function getUnreadCountForUser(int $userId): int
    {
        return self::forUser($userId)->unread()->count();
    }

    /**
     * Soft delete semua old notifications (lebih dari 30 hari)
     */
    public static function deleteOldNotifications(int $days = 30): int
    {
        return self::where('created_at', '<', now()->subDays($days))
            ->where('is_read', true) // Hanya yang sudah dibaca
            ->delete();
    }
}