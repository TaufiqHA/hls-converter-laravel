<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\videoController;
use App\Http\Controllers\settingsController;
use App\Http\Controllers\StreamController;

// Authentication routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// User authentication profile routes (as per REQ.md)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [UserController::class, 'me']);
    Route::put('/auth/profile', [UserController::class, 'updateProfile']);
    Route::put('/auth/password', [UserController::class, 'changePassword']);
});

// Video routes (as per REQ.md)
Route::middleware('auth:sanctum')->group(function () {
    // Video upload routes
    Route::post('/videos/upload', [videoController::class, 'uploadVideo']);
    Route::post('/videos/remote-upload', [videoController::class, 'remoteUpload']);
    Route::post('/videos/chunk/start', [videoController::class, 'startChunkUpload']);
    Route::post('/videos/chunk/upload', [videoController::class, 'uploadChunk']);

    // Video listing and details
    Route::get('/videos', [videoController::class, 'getVideos']);
    Route::get('/videos/{id}', [videoController::class, 'getVideo']);

    // Video update and management
    Route::put('/videos/{id}', [videoController::class, 'updateVideo']);
    Route::put('/videos/{id}/toggle-download', [videoController::class, 'toggleDownload']);
    Route::put('/videos/{id}/toggle-embed', [videoController::class, 'toggleEmbed']);
    Route::put('/videos/{id}/allowed-domains', [videoController::class, 'updateAllowedDomains']);

    // Video deletion
    Route::delete('/videos/{id}', [videoController::class, 'deleteVideo']);

    // Video embed functionality
    Route::get('/videos/{id}/embed-url', [videoController::class, 'getEmbedUrl']);
    Route::get('/videos/{id}/embed-code', [videoController::class, 'getEmbedCode']);

    // Video status and queue
    Route::get('/videos/{id}/status', [videoController::class, 'getVideoStatus']);
    Route::get('/videos/queue/stats', [videoController::class, 'getQueueStats']); // Admin only

    // Video download and view tracking
    Route::get('/videos/{id}/download', [videoController::class, 'downloadVideo']);
    Route::post('/videos/{id}/view', [videoController::class, 'incrementViews']);
});

// Settings routes (as per REQ.md)
Route::middleware('auth:sanctum')->group(function () {
    // User settings routes
    Route::get('/settings', [settingsController::class, 'getSettings']);
    Route::put('/settings/player', [settingsController::class, 'updatePlayerSettings']);
    Route::put('/settings/ads', [settingsController::class, 'updateAdsSettings']);
    Route::put('/settings/watermark', [settingsController::class, 'updateWatermarkSettings']);
    Route::put('/settings/download', [settingsController::class, 'updateDownloadSettings']);
    Route::put('/settings/googledrive', [settingsController::class, 'updateGoogleDriveSettings']);
    Route::put('/settings/subtitles', [settingsController::class, 'updateSubtitleSettings']);

    // Admin settings routes
    Route::get('/settings/admin', [settingsController::class, 'getAdminSettings']);
    Route::put('/settings/admin/ffmpeg', [settingsController::class, 'updateFFmpegSettings']);
    Route::put('/settings/admin/s3', [settingsController::class, 'updateS3Settings']);
    Route::put('/settings/admin/website', [settingsController::class, 'updateWebsiteSettings']);
    Route::put('/settings/admin/security', [settingsController::class, 'updateSecuritySettings']);
    Route::put('/settings/admin/email', [settingsController::class, 'updateEmailSettings']);

    // Admin file upload routes
    Route::post('/settings/admin/favicon', [settingsController::class, 'uploadFavicon']);
    Route::post('/settings/admin/logo', [settingsController::class, 'uploadLogo']);
    Route::delete('/settings/admin/favicon', [settingsController::class, 'deleteFavicon']);
    Route::delete('/settings/admin/logo', [settingsController::class, 'deleteLogo']);

    // Admin email test
    Route::post('/settings/admin/email/test', [settingsController::class, 'testEmailSettings']);

    // Google Drive OAuth routes
    Route::get('/settings/googledrive/auth-url', [settingsController::class, 'getGoogleDriveAuthUrl']);
    Route::get('/settings/googledrive/callback', [settingsController::class, 'handleGoogleDriveCallback']);
    Route::delete('/settings/googledrive/revoke', [settingsController::class, 'revokeGoogleDriveToken']);
});

// Streaming route - handles HLS streaming for videos
// GET /api/stream/:userId/:videoId/* - handles all HLS-related file requests
Route::get('/stream/{userId}/{videoId}/{file}', [StreamController::class, 'streamFile'])->where('file', '.*');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
