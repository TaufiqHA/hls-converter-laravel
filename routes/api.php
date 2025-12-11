<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\videoController;
use App\Http\Controllers\settingsController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\analyticsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\publicController;
use App\Http\Controllers\BackupController;

// Guest routes (no authentication required)
Route::post('/public/remote-upload', [videoController::class, 'guestRemoteUpload']);

// Public info route
Route::get('/public/info', [publicController::class, 'getPublicWebsiteInfo']);

// Authentication routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// User authentication profile routes (as per REQ.md)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);
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

// Analytics routes (as per analytics.md)
// Public routes
Route::post('/analytics/track', [analyticsController::class, 'trackEvent']);
Route::get('/analytics/session', [analyticsController::class, 'generateSession']);

// Private routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/analytics/video/{videoId}', [analyticsController::class, 'getVideoAnalytics']);
    Route::get('/analytics/summary', [analyticsController::class, 'getAnalyticsSummary']);
});

// Admin routes - requires admin authentication
Route::middleware(['auth:sanctum'])->group(function () {
    // Settings management
    Route::get('/admin/settings', [AdminController::class, 'getAllSettings']);
    Route::put('/admin/settings/website', [AdminController::class, 'updateWebsiteSettings']);
    Route::put('/admin/settings/ffmpeg', [AdminController::class, 'updateFFmpegSettings']);
    Route::put('/admin/settings/s3', [AdminController::class, 'updateS3Settings']);
    Route::put('/admin/settings/redis', [AdminController::class, 'updateRedisSettings']);
    Route::put('/admin/settings/ratelimit', [AdminController::class, 'updateRateLimitSettings']);
    Route::put('/admin/settings/cors', [AdminController::class, 'updateCorsSettings']);
    Route::put('/admin/settings/analytics', [AdminController::class, 'updateAnalyticsSettings']);
    Route::put('/admin/settings/security', [AdminController::class, 'updateSecuritySettings']);
    Route::put('/admin/settings/email', [AdminController::class, 'updateEmailSettings']);
    Route::get('/admin/settings/googledrive', [AdminController::class, 'getGoogleDriveSettings']);
    Route::put('/admin/settings/googledrive', [AdminController::class, 'updateGoogleDriveSettings']);

    // Favicon management
    Route::post('/admin/settings/favicon', [AdminController::class, 'uploadFavicon']);
    Route::delete('/admin/settings/favicon', [AdminController::class, 'deleteFavicon']);

    // User management
    Route::get('/admin/users', [AdminController::class, 'getUsers']);
    Route::get('/admin/users/{id}', [AdminController::class, 'getUser']);
    Route::put('/admin/users/{id}', [AdminController::class, 'updateUser']);
    Route::put('/admin/users/{id}/ban', [AdminController::class, 'toggleUserBan']);
    Route::put('/admin/users/{id}/storage', [AdminController::class, 'updateUserStorage']);
    Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);
    Route::put('/admin/users/{id}/reset-password', [AdminController::class, 'resetUserPassword']);
    Route::put('/admin/users/{id}/ads', [AdminController::class, 'toggleUserAds']);
    Route::get('/admin/videos', [AdminController::class, 'adminGetUserVideos']);

    // Storage management
    Route::get('/admin/storage', [AdminController::class, 'getStorageStats']);

    // Backup management
    Route::get('/admin/backup', [BackupController::class, 'listBackups']);
    Route::post('/admin/backup', [BackupController::class, 'createBackup']);
    Route::post('/admin/backup/restore', [BackupController::class, 'restoreBackup']);
    Route::post('/admin/backup/upload', [BackupController::class, 'uploadAndRestore']);
    Route::delete('/admin/backup/{filename}', [BackupController::class, 'deleteBackup']);
});

// Public routes for backup
Route::get('/admin/backup/download/{filename}', [BackupController::class, 'downloadBackup']);

// Public admin routes
Route::get('/admin/favicon', [AdminController::class, 'getFavicon']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
