<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComicApiController;
use App\Http\Controllers\Api\ReplyController;
use Illuminate\Support\Facades\Route;

// ─── PUBLIC ROUTES (tidak perlu auth) ───────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);

Route::get('/comics', [ComicApiController::class, 'index']);
Route::get('/comics/{id}', [ComicApiController::class, 'show']);

Route::get('/reviews/{reviewId}/replies', [ReplyController::class, 'getByReview']);
Route::get('/replies/{replyId}', [ReplyController::class, 'show']);

// ─── PROTECTED ROUTES (wajib auth:sanctum) ──────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/fcm-token', [AuthController::class, 'updateFcmToken']);
    
    // Reviews
    Route::post('/comics/{id}/review', [ComicApiController::class, 'storeReview']);
    Route::put('/reviews/{id}', [ComicApiController::class, 'updateReview']);
    Route::delete('/reviews/{id}', [ComicApiController::class, 'destroyReview']);
    Route::get('/me/reviews', [ComicApiController::class, 'getUserReviews']);
    Route::get('/me/reviews/stats', [ComicApiController::class, 'getUserReviewStats']);

    // Replies
    Route::post('/reviews/{reviewId}/reply', [ReplyController::class, 'store']);
    Route::put('/replies/{replyId}', [ReplyController::class, 'update']);
    Route::delete('/replies/{replyId}', [ReplyController::class, 'destroy']);
    Route::get('/me/replies', [ReplyController::class, 'getMyReplies']);
    Route::get('/me/replies/unread-count', [ReplyController::class, 'getUnreadCount']);
    Route::post('/replies/{replyId}/mark-as-read', [ReplyController::class, 'markNotificationAsRead']);
});