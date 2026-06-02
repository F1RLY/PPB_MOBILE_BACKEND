<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateReplyRequest;
use App\Http\Requests\Api\UpdateReplyRequest;
use App\Models\Reply;
use App\Models\RatingReview;
use App\Models\ReplyNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplyController extends Controller
{
    /**
     * POST /api/reviews/{reviewId}/reply
     * 
     * Buat reply baru untuk review tertentu
     * Support nested replies (jika ada parent_reply_id)
     */
    public function store(CreateReplyRequest $request, int $reviewId): JsonResponse
    {
        // Verify review exists
        $review = RatingReview::findOrFail($reviewId);
        $user = $request->user();

        // Validate parent_reply jika ada
        if ($request->filled('parent_reply_id')) {
            $parentReply = Reply::find($request->parent_reply_id);
            if (!$parentReply || $parentReply->review_id !== $reviewId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reply parent tidak valid untuk review ini.',
                ], 400);
            }
        }

        // Create reply
        $reply = Reply::create([
            'review_id'       => $reviewId,
            'user_id'         => $user->id,
            'parent_reply_id' => $request->parent_reply_id,
            'content'         => $request->content,
        ]);

        // Load relationships
        $reply->load('user', 'childReplies.user');

        return response()->json([
            'success' => true,
            'message' => 'Reply berhasil dibuat.',
            'data'    => $this->formatReplyResponse($reply),
        ], 201);
    }

    /**
     * GET /api/reviews/{reviewId}/replies
     * 
     * Dapatkan semua replies untuk review tertentu
     * Support pagination dan nested replies
     */
    public function getByReview(Request $request, int $reviewId): JsonResponse
    {
        // Verify review exists
        $review = RatingReview::findOrFail($reviewId);

        // Get top-level replies with nested structure
        $page = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 50);

        $replies = Reply::byReview($reviewId)
            ->topLevel()
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        // Eager load user dan child replies
        $replies->load('user', 'childReplies.user', 'childReplies.childReplies.user');

        // Transform response
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
     * 
     * Dapatkan detail single reply dengan nested children
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
     * Update/edit reply yang sudah ada
     * Hanya bisa diedit oleh author
     */
    public function update(UpdateReplyRequest $request, int $replyId): JsonResponse
    {
        $reply = Reply::findOrFail($replyId);
        $user = $request->user();

        // Check authorization - hanya author yang bisa edit
        if ($reply->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk edit reply ini.',
            ], 403);
        }

        // Check if reply is soft deleted
        if ($reply->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Reply ini sudah dihapus.',
            ], 410);
        }

        // Update
        $reply->update([
            'content' => $request->content,
        ]);

        $reply->load('user', 'childReplies.user');

        return response()->json([
            'success' => true,
            'message' => 'Reply berhasil diperbarui.',
            'data'    => $this->formatReplyResponse($reply),
        ]);
    }

    /**
     * DELETE /api/replies/{replyId}
     * 
     * Hapus reply (soft delete)
     * Hanya bisa dihapus oleh author
     */
    public function destroy(Request $request, int $replyId): JsonResponse
    {
        $reply = Reply::findOrFail($replyId);
        $user = $request->user();

        // Check authorization
        if ($reply->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk hapus reply ini.',
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
     * Dapatkan replies yang dibuat oleh user yang login
     * Support sorting dan pagination
     */
    public function getMyReplies(Request $request): JsonResponse
    {
        $user = $request->user();

        // Query
        $query = Reply::byUser($user->id)
            ->with('user', 'review.comic');

        // Sorting
        match ($request->get('sort', 'newest')) {
            'oldest' => $query->oldest(),
            default  => $query->latest(),
        };

        // Pagination
        $page = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 50);

        $replies = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform
        $formattedReplies = $replies->map(function ($reply) {
            return $this->formatReplyResponse($reply);
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
     * POST /api/replies/{replyId}/mark-as-read
     * 
     * Mark notification sebagai read
     * (User telah membaca reply yang mereka terima)
     */
    public function markNotificationAsRead(Request $request, int $replyId): JsonResponse
    {
        $user = $request->user();

        // Update all unread notifications untuk reply ini yang ke-user
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

    /**
     * GET /api/me/replies/unread-count
     * 
     * Dapatkan jumlah notifikasi belum dibaca
     * Untuk badge di Flutter app
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadCount = ReplyNotification::forUser($user->id)
            ->unread()
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    // ───────────────────────────────────────────────────────────────────────
    // HELPER METHODS
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Format reply response (simple format)
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
     * Format reply dengan nested children
     * Recursively format child replies
     */
    private function formatReplyWithChildren(Reply $reply): array
    {
        $formatted = $this->formatReplyResponse($reply);

        // Add child replies
        $formatted['child_replies'] = $reply->childReplies
            ->map(fn($child) => $this->formatReplyWithChildren($child))
            ->values();

        $formatted['child_count'] = count($formatted['child_replies']);

        return $formatted;
    }
}