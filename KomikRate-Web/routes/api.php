<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComicApiController;
use App\Http\Controllers\Api\ReplyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (Tidak perlu login)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);

// Komik bisa dilihat tanpa login
Route::get('/comics',      [ComicApiController::class, 'index']);
Route::get('/comics/{id}', [ComicApiController::class, 'show']);

// GET semua replies untuk review tertentu
Route::get('/reviews/{reviewId}/replies', [ReplyController::class, 'getByReview']);
Route::get('/replies/{replyId}', [ReplyController::class, 'show']);
 


/*
|--------------------------------------------------------------------------
| Protected Routes (Wajib login - menggunakan Sanctum token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout',           [AuthController::class, 'logout']);
    Route::get('/me',                [AuthController::class, 'me']);
    Route::put('/profile/update',    [AuthController::class, 'updateProfile']);

    // Reviews (Create, Read, Update, Delete)
    Route::post('/comics/{id}/review',    [ComicApiController::class, 'storeReview']);
    Route::put('/reviews/{id}',           [ComicApiController::class, 'updateReview']); 
    Route::delete('/reviews/{id}',        [ComicApiController::class, 'destroyReview']);
    Route::get('/me/reviews',        [ComicApiController::class, 'getUserReviews']);
    Route::get('/me/reviews/stats',  [ComicApiController::class, 'getUserReviewStats']);
    
    // Replies (NEW)
    Route::post('/reviews/{reviewId}/reply', [ReplyController::class, 'store']);
    Route::put('/replies/{replyId}', [ReplyController::class, 'update']);
    Route::delete('/replies/{replyId}', [ReplyController::class, 'destroy']);
    Route::get('/me/replies', [ReplyController::class, 'getMyReplies']);
    Route::get('/me/replies/unread-count', [ReplyController::class, 'getUnreadCount']);
    Route::post('/replies/{replyId}/mark-as-read', [ReplyController::class, 'markNotificationAsRead']);
    
    // FCM Token
    Route::post('/fcm-token', [AuthController::class, 'updateFcmToken']);
});