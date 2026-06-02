<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComicApiController;
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
    
});