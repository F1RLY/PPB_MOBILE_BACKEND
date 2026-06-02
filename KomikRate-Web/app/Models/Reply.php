<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reply extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'replies';

    protected $fillable = [
        'review_id',
        'user_id',
        'parent_reply_id',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ───────────────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Reply milik satu review
     */
    public function review()
    {
        return $this->belongsTo(RatingReview::class, 'review_id');
    }

    /**
     * Reply dibuat oleh satu user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Reply parent (jika ini reply dari reply lain)
     */
    public function parentReply()
    {
        return $this->belongsTo(Reply::class, 'parent_reply_id');
    }

    /**
     * Child replies (balasan dari reply ini)
     * Unlimited depth
     */
    public function childReplies()
    {
        return $this->hasMany(Reply::class, 'parent_reply_id');
    }

    /**
     * Recursive: dapatkan semua child replies (nested)
     */
    public function allChildReplies()
    {
        return $this->childReplies()->with('allChildReplies', 'user');
    }

    /**
     * Notifikasi yang dikirim untuk reply ini
     */
    public function notifications()
    {
        return $this->hasMany(ReplyNotification::class, 'reply_id');
    }

    // ───────────────────────────────────────────────────────────────────────
    // SCOPES
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Hanya top-level replies (bukan child)
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_reply_id');
    }

    /**
     * Hanya replies yang bukan dihapus (tanpa soft delete)
     */
    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Filter berdasarkan review ID
     */
    public function scopeByReview($query, int $reviewId)
    {
        return $query->where('review_id', $reviewId);
    }

    /**
     * Filter berdasarkan user ID (replies dari user tertentu)
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Replies terbaru
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at');
    }

    // ───────────────────────────────────────────────────────────────────────
    // ACCESSORS & MUTATORS
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Check apakah reply ini adalah child reply
     */
    public function getIsChildReply(): bool
    {
        return $this->parent_reply_id !== null;
    }

    /**
     * Count total child replies (recursive)
     */
    public function getChildReplyCount(): int
    {
        return $this->childReplies()->count() + 
               $this->childReplies()->sum(function ($reply) {
                   return $reply->getChildReplyCount();
               });
    }

    /**
     * Format waktu yang readable (e.g., "2 jam lalu")
     */
    public function getTimeAgoAttribute(): string
    {
        $diff = now()->diffForHumans($this->created_at, [
            'parts' => 1,
            'absolute' => true,
        ]);
        return str_replace(' ago', '', $diff) . ' lalu';
    }

    /**
     * Check apakah reply sudah diupdate
     */
    public function getIsUpdatedAttribute(): bool
    {
        return !$this->updated_at->isSameAs($this->created_at);
    }

    // ───────────────────────────────────────────────────────────────────────
    // HELPER METHODS
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Validasi apakah user bisa edit reply ini
     */
    public function canEdit(int $userId): bool
    {
        return $this->user_id === $userId && !$this->trashed();
    }

    /**
     * Validasi apakah user bisa delete reply ini
     */
    public function canDelete(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * Get review yang di-reply (eager load)
     */
    public function getReviewWithComic()
    {
        return $this->review()->with('comic')->first();
    }

    /**
     * Dapatkan path untuk deep linking
     * Format: /comic/1/reviews/5/replies/10
     */
    public function getDeepLinkPath(): string
    {
        $review = $this->review;
        if (!$review) return '';
        
        return "/comic/{$review->comic_id}/review/{$review->id}/reply/{$this->id}";
    }
}