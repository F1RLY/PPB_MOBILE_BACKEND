<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateReplyRequest;
use App\Http\Requests\Api\UpdateReplyRequest;
use App\Models\Reply;
use App\Models\RatingReview;
use App\Models\ReplyNotification;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplyController extends Controller
{
    protected $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * POST /api/reviews/{reviewId}/reply
     * 
     * Create reply & trigger FCM notification
     */
    public function store(CreateReplyRequest $request, int $reviewId): JsonResponse
    {
        $review = RatingReview::findOrFail($reviewId);
        $user   = $request->user();

        if ($request->filled('parent_reply_id')) {
            $parentReply = Reply::find($request->parent_reply_id);
            if (!$parentReply || $parentReply->review_id !== $reviewId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent reply invalid untuk review ini.',
                ], 400);
            }
        }

        // ✅ Simpan reply dulu
        $reply = Reply::create([
            'review_id'       => $reviewId,
            'user_id'         => $user->id,
            'parent_reply_id' => $request->parent_reply_id,
            'content'         => $request->content,
        ]);

        $reply->load('user', 'childReplies.user');

        // ✅ Kirim FCM — dibungkus try-catch agar tidak ganggu response
        if ($review->user_id !== $user->id) {
            try {
                $notification = ReplyNotification::create([
                    'reply_id'        => $reply->id,
                    'recipient_id'    => $review->user_id,
                    'sender_id'       => $user->id,
                    'delivery_status' => 'pending',
                ]);

                // Buat FcmService manual, bukan inject
                $fcmService = new FcmService();
                $fcmService->sendReplyNotification($notification);

            } catch (\Exception $e) {
                // FCM gagal tidak boleh gagalkan reply
                \Log::error('FCM error (non-fatal): ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply berhasil dibuat.',
            'data'    => $this->formatReplyResponse($reply),
        ], 201);
    }

    /**
     * GET /api/reviews/{reviewId}/replies
     */
    public function getByReview(Request $request, int $reviewId): JsonResponse
    {
        $review = RatingReview::findOrFail($reviewId);

        $page = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 50);

        $replies = Reply::byReview($reviewId)
            ->topLevel()
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        $replies->load('user', 'childReplies.user', 'childReplies.childReplies.user');

        $formattedReplies = $replies->map(function ($reply) {
            return $this->formatReplyWithChildren($reply);
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'data'         => $formattedReplies,
                'current_page' => $replies->currentPage(),
                'last_page'    => $replies->lastPage(),
                'per_page'     => $replies->perPage(),
                'total'        => $replies->total(),
            ],
        ]);
    }

    /**
     * GET /api/replies/{replyId}
     */
    public function show(int $replyId): JsonResponse
    {
        $reply = Reply::with('user', 'review.comic')
            ->with('childReplies.user', 'childReplies.childReplies.user')
            ->findOrFail($replyId);

        return response()->json([
            'success' => true,
            'data'    => $this->formatReplyWithChildren($reply),
        ]);
    }

    /**
     * PUT /api/replies/{replyId}
     * 
     * Edit reply (author only)
     */
    public function update(UpdateReplyRequest $request, int $replyId): JsonResponse
    {
        $reply = Reply::findOrFail($replyId);
        $user = $request->user();

        // Check authorization
        if ($reply->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak bisa edit reply ini.',
            ], 403);
        }

        // Check if soft deleted
        if ($reply->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Reply ini sudah dihapus.',
            ], 410);
        }

        // Update
        $reply->update(['content' => $request->content]);
        $reply->load('user', 'childReplies.user');

        return response()->json([
            'success' => true,
            'message' => 'Reply berhasil diupdate.',
            'data'    => $this->formatReplyResponse($reply),
        ]);
    }

    /**
     * DELETE /api/replies/{replyId}
     * 
     * Delete reply (soft delete, author only)
     */
    public function destroy(Request $request, int $replyId): JsonResponse
    {
        $reply = Reply::findOrFail($replyId);
        $user = $request->user();

        // Check authorization
        if ($reply->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak bisa hapus reply ini.',
            ], 403);
        }

        // Soft delete
        $reply->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reply berhasil dihapus.',
        ]);
    }

    /**
     * GET /api/me/replies
     * 
     * Get user's own replies
     */
    public function getMyReplies(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Reply::byUser($user->id)
            ->with('user', 'review.comic');

        $page = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 50);

        $replies = $query->latest()->paginate($perPage, ['*'], 'page', $page);

        $formattedReplies = $replies->map(function ($reply) {
            return $this->formatReplyResponse($reply);
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'data'         => $formattedReplies,
                'current_page' => $replies->currentPage(),
                'last_page'    => $replies->lastPage(),
                'total'        => $replies->total(),
            ],
        ]);
    }

    /**
     * GET /api/me/replies/unread-count
     * 
     * Get unread notification count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadCount = ReplyNotification::where('recipient_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * POST /api/replies/{replyId}/mark-as-read
     * 
     * Mark notification as read
     */
    public function markNotificationAsRead(Request $request, int $replyId): JsonResponse
    {
        $user = $request->user();

        $updated = ReplyNotification::where('reply_id', $replyId)
            ->where('recipient_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai sebagai sudah dibaca.',
            'data'    => [
                'updated_count' => $updated,
            ],
        ]);
    }

    // ───────────────────────────────────────────────────────────────────────
    // HELPER METHODS
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Format simple reply
     */
    private function formatReplyResponse(Reply $reply): array
    {
        return [
            'id'              => $reply->id,
            'review_id'       => $reply->review_id,
            'parent_reply_id' => $reply->parent_reply_id,
            'content'         => $reply->content,
            'user'            => [
                'id'   => $reply->user->id,
                'name' => $reply->user->name,
            ],
            'created_at'      => $reply->created_at->toIso8601String(),
            'updated_at'      => $reply->updated_at->toIso8601String(),
            'is_edited'       => $reply->is_updated,
            'time_ago'        => $reply->time_ago,
        ];
    }

    /**
     * Format reply with nested children
     */
    private function formatReplyWithChildren(Reply $reply): array
    {
        $formatted = $this->formatReplyResponse($reply);

        $formatted['child_replies'] = $reply->childReplies
            ->map(fn($child) => $this->formatReplyWithChildren($child))
            ->values();

        $formatted['child_count'] = count($formatted['child_replies']);

        return $formatted;
    }
}