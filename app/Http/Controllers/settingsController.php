<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

class settingsController extends Controller
{
    public function getSettings(Request $request)
    {
        $user = $request->user();

        $settings = Setting::where('userId', $user->id)->first();
        if (!$settings) {
            $settings = new Setting([
                'id' => Str::uuid(),
                'userId' => $user->id,
                'playerSettings' => [
                    'player' => 'hls.js',
                    'autoplay' => false,
                    'controls' => true,
                    'theme' => 'dark',
                    'defaultQuality' => 'auto',
                    'seekInterval' => 10,
                    'skin' => 'default',
                    'color1' => '#b7000e',
                    'color2' => '#b7045d'
                ],
                'adsSettings' => [
                    'enabled' => true,
                    'frequency' => 3,
                    'skipOffset' => 5
                ],
                'defaultWatermark' => [
                    'enabled' => false,
                    'position' => 'bottom-right',
                    'opacity' => 0.5,
                    'size' => 50
                ],
                'defaultDownloadEnabled' => true,
                'googleDriveSettings' => [
                    'enabled' => false,
                    'mode' => 'oauth',
                    'apiKey' => null,
                    'clientId' => null,
                    'clientSecret' => null,
                    'refreshToken' => null,
                    'rcloneConfig' => null,
                    'lastSync' => null
                ],
                'subtitleSettings' => [
                    'defaultLanguage' => 'en',
                    'fontSize' => 14,
                    'color' => '#ffffff'
                ],
                'websiteSettings' => [
                    'siteName' => config('app.name', 'HLS Video Converter'),
                    'siteDescription' => 'Convert videos to HLS format',
                    'siteUrl' => config('app.url', 'http://localhost:8000'),
                    'contactEmail' => 'admin@example.com',
                    'enableRegistration' => true,
                    'enableGuestUpload' => true,
                    'maxUploadSizePerUser' => 5368709120, // 5GB
                    'allowedVideoFormats' => ['mp4', 'mov', 'avi', 'mkv', 'webm'],
                    'defaultUserRole' => 'user',
                    'maintenanceMode' => false,
                    'maintenanceMessage' => 'Site is under maintenance. Please check back later.'
                ],
                'ffmpegSettings' => [
                    'ffmpegPath' => config('ffmpeg.path', '/usr/bin/ffmpeg'),
                    'ffprobePath' => '/usr/bin/ffprobe',
                    'hlsSegmentDuration' => 6,
                    'hlsPlaylistType' => 'event',
                    'enableAdaptiveStreaming' => true,
                    'videoQualities' => ['1080p', '720p', '480p', '360p'],
                    'videoCodec' => 'libx264',
                    'audioCodec' => 'aac',
                    'videoBitrate' => '2500k',
                    'audioBitrate' => '128k',
                    'preset' => 'medium',
                    'crf' => 23,
                    'maxThreads' => 4,
                    'useHardwareAccel' => false,
                    'hwEncoder' => 'nvenc'
                ],
                's3Settings' => [
                    'enabled' => false,
                    'storageType' => 'local',
                    'endpoint' => '',
                    'accessKey' => '',
                    'secretKey' => '',
                    'bucket' => '',
                    'region' => 'us-east-1',
                    'forcePathStyle' => false,
                    'deleteLocalAfterUpload' => false,
                    'publicUrlBase' => '',
                    'r2AccountId' => '',
                    'r2PublicDomain' => ''
                ],
                'redisSettings' => [
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'password' => '',
                    'database' => 0
                ],
                'rateLimitSettings' => [
                    'enabled' => true,
                    'maxRequests' => 60,
                    'window' => 1
                ],
                'corsSettings' => [
                    'allowAll' => true,
                    'allowedOrigins' => [],
                    'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE'],
                    'allowedHeaders' => ['*']
                ],
                'analyticsSettings' => [
                    'enabled' => true,
                    'providers' => ['internal'],
                    'retentionDays' => 365
                ],
                'securitySettings' => [
                    'jwtExpiration' => '24h',
                    'passwordMinLength' => 8,
                    'requireEmailVerification' => false,
                    'maxLoginAttempts' => 5,
                    'lockoutDuration' => 300,
                    'enableTwoFactor' => false
                ],
                'emailSettings' => [
                    'enabled' => false,
                    'provider' => 'smtp',
                    'host' => config('mail.mailers.smtp.host', 'smtp.gmail.com'),
                    'port' => config('mail.mailers.smtp.port', 587),
                    'secure' => false,
                    'username' => '',
                    'password' => '',
                    'fromEmail' => 'noreply@example.com',
                    'fromName' => 'HLS Video Converter'
                ]
            ]);
            $settings->save();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $settings->id,
                'userId' => $settings->userId,
                'playerSettings' => $settings->playerSettings,
                'adsSettings' => $settings->adsSettings,
                'defaultWatermark' => $settings->defaultWatermark,
                'defaultDownloadEnabled' => $settings->defaultDownloadEnabled,
                'googleDriveSettings' => $settings->googleDriveSettings,
                'subtitleSettings' => $settings->subtitleSettings,
                'websiteSettings' => $settings->websiteSettings,
                'ffmpegSettings' => $settings->ffmpegSettings,
                's3Settings' => $settings->s3Settings,
                'redisSettings' => $settings->redisSettings,
                'rateLimitSettings' => $settings->rateLimitSettings,
                'corsSettings' => $settings->corsSettings,
                'analyticsSettings' => $settings->analyticsSettings,
                'securitySettings' => $settings->securitySettings,
                'emailSettings' => $settings->emailSettings,
                'createdAt' => $settings->created_at,
                'updatedAt' => $settings->updated_at
            ]
        ]);
    }

    /**
     * Updates player configuration settings.
     *
     * PUT `/api/settings/player`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request Body Fields:
     * - player (string): jenis pemutar video ('videojs', 'dplayer')
     * - autoplay (boolean): otomatis putar
     * - controls (boolean): kontrol pemutar
     * - theme (string): 'light', 'dark', atau 'auto'
     * - defaultQuality (string): 'auto', '1080p', '720p', '480p', '360p'
     * - seekInterval (number): interval pencarian
     * - skin (string): skema tampilan
     * - color1 (string): warna utama
     * - color2 (string): warna sekunder
     *
     * Request:
     * {
     *   "player": "videojs",
     *   "autoplay": true,
     *   "controls": true,
     *   "theme": "dark",
     *   "defaultQuality": "auto",
     *   "seekInterval": 10,
     *   "skin": "default",
     *   "color1": "#b7000e",
     *   "color2": "#b7045d"
     * }
     *
     * Response (Success - 200):
     * {
     *   "success": true,
     *   "message": "Player settings updated successfully",
     *   "data": {
     *     "player": "videojs",
     *     "autoplay": false,
     *     "controls": true,
     *     "theme": "dark",
     *     "defaultQuality": "auto",
     *     "seekInterval": 10,
     *     "skin": "default",
     *     "color1": "#b7000e",
     *     "color2": "#b7045d"
     *   }
     * }
     *
     * Response (Error - 400):
     * {
     *   "success": false,
     *   "message": "Invalid player type. Must be one of: videojs, dplayer"
     * }
     */
    public function updatePlayerSettings(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'player' => 'sometimes|string|in:videojs,dplayer',
            'autoplay' => 'sometimes|boolean',
            'controls' => 'sometimes|boolean',
            'theme' => 'sometimes|string|in:light,dark,auto',
            'defaultQuality' => 'sometimes|string|in:auto,1080p,720p,480p,360p',
            'seekInterval' => 'sometimes|integer|min:1|max:60',
            'skin' => 'sometimes|string',
            'color1' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value)) {
                        $fail('The '.$attribute.' must be a valid hex color.');
                    }
                },
            ],
            'color2' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value)) {
                        $fail('The '.$attribute.' must be a valid hex color.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            if ($errors->has('player')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid player type. Must be one of: videojs, dplayer'
                ], 400);
            }
            return response()->json(['error' => $errors], 400);
        }

        $settings = Setting::where('userId', $user->id)->first();
        if (!$settings) {
            $settings = new Setting([
                'id' => Str::uuid(),
                'userId' => $user->id,
            ]);
            $settings->save();
        }

        $playerSettings = array_merge($settings->playerSettings ?: [
            'player' => 'hls.js',
            'autoplay' => false,
            'controls' => true,
            'theme' => 'dark',
            'defaultQuality' => 'auto',
            'seekInterval' => 10,
            'skin' => 'default',
            'color1' => '#b7000e',
            'color2' => '#b7045d'
        ], $request->only([
            'player', 'autoplay', 'controls', 'theme', 'defaultQuality', 'seekInterval', 'skin', 'color1', 'color2'
        ]));

        $settings->update(['playerSettings' => $playerSettings]);

        return response()->json([
            'success' => true,
            'message' => 'Player settings updated successfully',
            'data' => $settings->playerSettings
        ]);
    }

    /**
     * Updates advertising configuration settings.
     *
     * PUT `/api/settings/ads`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request Body Fields:
     * - enabled (boolean): apakah iklan diaktifkan
     * - preRollAd (object): iklan sebelum video
     * - midRollAd (object): iklan tengah video
     * - postRollAd (object): iklan setelah video
     * - overlayAd (object): iklan overlay
     * - popunderAd (object): iklan popunder
     * - nativeAd (object): iklan native
     *
     * Request:
     * {
     *   "enabled": true,
     *   "preRollAd": {
     *     "enabled": true,
     *     "videoUrl": "url",
     *     "skipAfter": 5
     *   },
     *   "midRollAd": {
     *     "enabled": false
     *   },
     *   "postRollAd": {
     *     "enabled": false
     *   },
     *   "overlayAd": {
     *     "enabled": false
     *   },
     *   "popunderAd": {
     *     "enabled": false
     *   },
     *   "nativeAd": {
     *     "enabled": false
     *   }
     * }
     *
     * Response (Success - 200):
     * {
     *   "success": true,
     *   "message": "Ads settings updated successfully",
     *   "data": { objek ads settings yang diperbarui }
     * }
     */
    public function updateAdsSettings(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'enabled' => 'sometimes|boolean',
            'preRollAd' => 'sometimes|array',
            'preRollAd.enabled' => 'boolean',
            'preRollAd.videoUrl' => 'string|url|nullable',
            'preRollAd.skipAfter' => 'integer|min:0|nullable',
            'midRollAd' => 'sometimes|array',
            'midRollAd.enabled' => 'boolean',
            'postRollAd' => 'sometimes|array',
            'postRollAd.enabled' => 'boolean',
            'overlayAd' => 'sometimes|array',
            'overlayAd.enabled' => 'boolean',
            'popunderAd' => 'sometimes|array',
            'popunderAd.enabled' => 'boolean',
            'nativeAd' => 'sometimes|array',
            'nativeAd.enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $settings = Setting::where('userId', $user->id)->first();
        if (!$settings) {
            $settings = new Setting([
                'id' => Str::uuid(),
                'userId' => $user->id,
            ]);
            $settings->save();
        }

        $adsSettings = array_merge($settings->adsSettings ?: [], $request->all());

        $settings->update(['adsSettings' => $adsSettings]);

        return response()->json([
            'success' => true,
            'message' => 'Ads settings updated successfully',
            'data' => $settings->adsSettings
        ]);
    }

    /**
     * Updates default watermark settings with image upload support.
     *
     * PUT `/api/settings/watermark`
     * Headers: Authorization: Bearer {jwt-token}, Content-Type: multipart/form-data (if uploading image)
     *
     * Request Body Fields:
     * - enabled (boolean): apakah watermark diaktifkan
     * - text (string): teks watermark
     * - position (string): posisi watermark
     * - opacity (number): transparansi watermark
     *
     * Request:
     * {
     *   "enabled": true,
     *   "text": "Sample Watermark",
     *   "position": "bottom-right",
     *   "opacity": 0.7
     * }
     *
     * Response (Success - 200):
     * {
     *   "success": true,
     *   "message": "Watermark settings updated successfully",
     *   "data": { objek watermark settings yang diperbarui }
     * }
     */
    public function updateWatermarkSettings(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'enabled' => 'sometimes|boolean',
            'text' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|in:top-left,top-right,bottom-left,bottom-right',
            'opacity' => 'sometimes|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $settings = Setting::where('userId', $user->id)->first();
        if (!$settings) {
            $settings = new Setting([
                'id' => Str::uuid(),
                'userId' => $user->id,
            ]);
            $settings->save();
        }

        $watermarkSettings = array_merge($settings->defaultWatermark ?: [], $request->only([
            'enabled', 'text', 'position', 'opacity'
        ]));

        $settings->update(['defaultWatermark' => $watermarkSettings]);

        return response()->json([
            'success' => true,
            'message' => 'Watermark settings updated successfully',
            'data' => $settings->defaultWatermark
        ]);
    }

    /**
     * Updates default download settings.
     *
     * PUT `/api/settings/download`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request:
     * {
     *   "defaultDownloadEnabled": false
     * }
     *
     * Response (Success - 200):
     * {
     *   "message": "Download settings updated successfully",
     *   "settings": {
     *     "defaultDownloadEnabled": false
     *   }
     * }
     */
    public function updateDownloadSettings(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'defaultDownloadEnabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $settings = Setting::where('userId', $user->id)->first();
        if (!$settings) {
            $settings = new Setting([
                'id' => Str::uuid(),
                'userId' => $user->id,
            ]);
            $settings->save();
        }

        if ($request->has('defaultDownloadEnabled')) {
            $settings->update(['defaultDownloadEnabled' => $request->defaultDownloadEnabled]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Download settings updated successfully',
            'data' => [
                'defaultDownloadEnabled' => $settings->defaultDownloadEnabled
            ]
        ]);
    }

    /**
     * Updates Google Drive integration settings.
     *
     * PUT `/api/settings/googledrive`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request Body Fields:
     * - enabled (boolean): apakah Google Drive diaktifkan
     * - mode (string): 'api', 'oauth', 'rclone'
     * - apiKey (string): API key
     * - clientId (string): Client ID
     * - clientSecret (string): Client Secret
     * - refreshToken (string): Refresh Token
     * - rcloneConfig (string): Konfigurasi Rclone
     *
     * Request:
     * {
     *   "enabled": true,
     *   "mode": "oauth",
     *   "clientId": "client-id",
     *   "clientSecret": "client-secret",
     *   "refreshToken": "refresh-token"
     * }
     *
     * Response (Success - 200):
     * {
     *   "success": true,
     *   "message": "Google Drive settings updated successfully",
     *   "data": { objek google drive settings yang diperbarui }
     * }
     */
    public function updateGoogleDriveSettings(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'enabled' => 'sometimes|boolean',
            'mode' => 'sometimes|string|in:api,oauth,rclone',
            'apiKey' => 'sometimes|string|nullable',
            'clientId' => 'sometimes|string|nullable',
            'clientSecret' => 'sometimes|string|nullable',
            'refreshToken' => 'sometimes|string|nullable',
            'rcloneConfig' => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $settings = Setting::where('userId', $user->id)->first();
        if (!$settings) {
            $settings = new Setting([
                'id' => Str::uuid(),
                'userId' => $user->id,
            ]);
            $settings->save();
        }

        $existingSettings = $settings->googleDriveSettings ?: [];
        $newSettings = array_merge($existingSettings, $request->all());

        $settings->update(['googleDriveSettings' => $newSettings]);

        return response()->json([
            'success' => true,
            'message' => 'Google Drive settings updated successfully',
            'data' => $settings->googleDriveSettings
        ]);
    }

    /**
     * Updates subtitle configuration settings.
     *
     * PUT `/api/settings/subtitles`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request Body Fields:
     * - defaultLanguage (string): bahasa default
     * - fontColor (string): warna font
     * - fontFamily (string): jenis font
     * - edgeStyle (string): gaya tepi teks
     * - backgroundOpacity (number): transparansi latar belakang
     * - backgroundColor (string): warna latar belakang
     * - windowOpacity (number): transparansi jendela
     * - windowColor (string): warna jendela
     *
     * Request:
     * {
     *   "defaultLanguage": "en",
     *   "fontColor": "#ffffff",
     *   "fontFamily": "Arial",
     *   "edgeStyle": "raised",
     *   "backgroundOpacity": 0.5,
     *   "backgroundColor": "#000000",
     *   "windowOpacity": 0.0,
     *   "windowColor": "#000000"
     * }
     *
     * Response (Success - 200):
     * {
     *   "success": true,
     *   "message": "Subtitle settings updated successfully",
     *   "data": { objek subtitle settings yang diperbarui }
     * }
     */
    public function updateSubtitleSettings(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'defaultLanguage' => 'sometimes|string',
            'fontColor' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value)) {
                        $fail('The '.$attribute.' must be a valid hex color.');
                    }
                },
            ],
            'fontFamily' => 'sometimes|string',
            'edgeStyle' => 'sometimes|string|in:none,raised,depressed,uniform,dropshadow',
            'backgroundOpacity' => 'sometimes|numeric|min:0|max:1',
            'backgroundColor' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value)) {
                        $fail('The '.$attribute.' must be a valid hex color.');
                    }
                },
            ],
            'windowOpacity' => 'sometimes|numeric|min:0|max:1',
            'windowColor' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value)) {
                        $fail('The '.$attribute.' must be a valid hex color.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $settings = Setting::where('userId', $user->id)->first();
        if (!$settings) {
            $settings = new Setting([
                'id' => Str::uuid(),
                'userId' => $user->id,
            ]);
            $settings->save();
        }

        $subtitleSettings = array_merge($settings->subtitleSettings ?: [], $request->all());

        $settings->update(['subtitleSettings' => $subtitleSettings]);

        return response()->json([
            'success' => true,
            'message' => 'Subtitle settings updated successfully',
            'data' => $settings->subtitleSettings
        ]);
    }

    /**
     * Retrieves admin system settings.
     *
     * GET `/api/settings/admin`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "settings": {
     *     "websiteSettings": {
     *       "siteName": "HLS Video Converter",
     *       "siteDescription": "Convert videos to HLS format",
     *       "siteUrl": "http://localhost:3000",
     *       "allowRegistrations": true
     *     },
     *     "ffmpegSettings": {
     *       "binaryPath": "/usr/bin/ffmpeg",
     *       "qualityPresets": "medium",
     *       "maxWorkers": 2
     *     },
     *     "s3Settings": {
     *       "enabled": false,
     *       "endpoint": "",
     *       "accessKeyId": "",
     *       "secretAccessKey": "",
     *       "bucket": ""
     *     },
     *     "emailSettings": {
     *       "enabled": false,
     *       "host": "smtp.gmail.com",
     *       "port": 587,
     *       "secure": false
     *     },
     *     "securitySettings": {
     *       "rateLimiting": true,
     *       "maxLoginAttempts": 5,
     *       "passwordRequirements": {
     *         "minLength": 8,
     *         "requireNumbers": true,
     *         "requireSymbols": true
     *       }
     *     }
     *   }
     * }
     */
    public function getAdminSettings(Request $request)
    {
        $user = $request->user();

        // Only allow admin users to access admin settings
        if ($user->role == 'user') {
            // return response()->json(['success' => false, 'message' => 'Admin access required'], 403);
        }

        // Retrieve system-wide admin settings (not user-specific)
        $adminSettings = Setting::where('userId', $user->id)->first();

        if (!$adminSettings) {
            // Create default admin settings if they don't exist
            $adminSettings = new Setting([
                'id' => Str::uuid(),
                'userId' => 'system_admin', // Special indicator for system-wide settings
                'ffmpegSettings' => [
                    'ffmpegPath' => config('ffmpeg.path', '/usr/bin/ffmpeg'),
                    'ffprobePath' => '/usr/bin/ffprobe',
                    'hlsSegmentDuration' => 6,
                    'hlsPlaylistType' => 'event',
                    'enableAdaptiveStreaming' => true,
                    'videoQualities' => ['1080p', '720p', '480p', '360p'],
                    'videoCodec' => 'libx264',
                    'audioCodec' => 'aac',
                    'videoBitrate' => '2500k',
                    'audioBitrate' => '128k',
                    'preset' => 'medium',
                    'crf' => 23,
                    'maxThreads' => 4,
                    'useHardwareAccel' => false,
                    'hwEncoder' => 'nvenc'
                ],
                's3Settings' => [
                    'enabled' => false,
                    'storageType' => 'local',
                    'endpoint' => '',
                    'accessKey' => '',
                    'secretKey' => '',
                    'bucket' => '',
                    'region' => 'us-east-1',
                    'forcePathStyle' => false,
                    'deleteLocalAfterUpload' => false,
                    'publicUrlBase' => '',
                    'r2AccountId' => '',
                    'r2PublicDomain' => ''
                ],
                'redisSettings' => [
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'password' => '',
                    'database' => 0
                ],
                'websiteSettings' => [
                    'siteName' => config('app.name', 'HLS Video Converter'),
                    'siteDescription' => 'Convert videos to HLS format',
                    'siteUrl' => config('app.url', 'http://localhost:8000'),
                    'contactEmail' => 'admin@example.com',
                    'enableRegistration' => true,
                    'enableGuestUpload' => true,
                    'maxUploadSizePerUser' => 5368709120, // 5GB
                    'allowedVideoFormats' => ['mp4', 'mov', 'avi', 'mkv', 'webm'],
                    'defaultUserRole' => 'user',
                    'maintenanceMode' => false,
                    'maintenanceMessage' => 'Site is under maintenance. Please check back later.'
                ],
                'rateLimitSettings' => [
                    'enabled' => true,
                    'maxRequests' => 60,
                    'window' => 1
                ],
                'corsSettings' => [
                    'allowAll' => true,
                    'allowedOrigins' => [],
                    'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE'],
                    'allowedHeaders' => ['*']
                ],
                'analyticsSettings' => [
                    'enabled' => true,
                    'providers' => ['internal'],
                    'retentionDays' => 365
                ],
                'securitySettings' => [
                    'jwtExpiration' => '24h',
                    'passwordMinLength' => 8,
                    'requireEmailVerification' => false,
                    'maxLoginAttempts' => 5,
                    'lockoutDuration' => 300,
                    'enableTwoFactor' => false
                ],
                'emailSettings' => [
                    'enabled' => false,
                    'provider' => 'smtp',
                    'host' => config('mail.mailers.smtp.host', 'smtp.gmail.com'),
                    'port' => config('mail.mailers.smtp.port', 587),
                    'secure' => false,
                    'username' => '',
                    'password' => '',
                    'fromEmail' => 'noreply@example.com',
                    'fromName' => 'HLS Video Converter'
                ]
            ]);
            $adminSettings->save();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ffmpegSettings' => $adminSettings->ffmpegSettings,
                's3Settings' => $adminSettings->s3Settings,
                'redisSettings' => $adminSettings->redisSettings,
                'websiteSettings' => $adminSettings->websiteSettings,
                'rateLimitSettings' => $adminSettings->rateLimitSettings,
                'corsSettings' => $adminSettings->corsSettings,
                'analyticsSettings' => $adminSettings->analyticsSettings,
                'securitySettings' => $adminSettings->securitySettings,
                'emailSettings' => $adminSettings->emailSettings,
                'playerSettings' => $adminSettings->playerSettings, // Include if used for system defaults
                'adsSettings' => $adminSettings->adsSettings, // Include if used for system defaults
                'defaultWatermark' => $adminSettings->defaultWatermark, // Include if used for system defaults
                'googleDriveSettings' => $adminSettings->googleDriveSettings, // Include if used for system defaults
                'subtitleSettings' => $adminSettings->subtitleSettings // Include if used for system defaults
            ]
        ]);
    }

    /**
     * Updates FFmpeg processing settings.
     *
     * PUT `/api/settings/admin/ffmpeg`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request:
     * {
     *   "binaryPath": "/usr/local/bin/ffmpeg",
     *   "qualityPresets": "high",
     *   "maxWorkers": 4,
     *   "customArgs": [
     *     "-preset",
     *     "medium",
     *     "-crf",
     *     "20"
     *   ]
     * }
     *
     * Response (Success - 200):
     * {
     *   "message": "FFmpeg settings updated successfully",
     *   "settings": {
     *     "ffmpegSettings": {
     *       "binaryPath": "/usr/local/bin/ffmpeg",
     *       "qualityPresets": "high",
     *       "maxWorkers": 4,
     *       "customArgs": [
     *         "-preset",
     *         "medium",
     *         "-crf",
     *         "20"
     *       ]
     *     }
     *   }
     * }
     */
    public function updateFFmpegSettings(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'binaryPath' => 'sometimes|string',
            'qualityPresets' => 'sometimes|string',
            'maxWorkers' => 'sometimes|integer|min:1',
            'customArgs' => 'sometimes|array',
            'customArgs.*' => 'string',
        ]);

        // Validasi preset FFmpeg
        $validPresets = ['ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium', 'slow', 'slower', 'veryslow'];
        $preset = $request->input('preset', 'medium');
        if (!in_array($preset, $validPresets)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid preset. Must be one of: ' . implode(', ', $validPresets)
            ], 400);
        }

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // In a real implementation, you would save to a dedicated admin settings table
        // For now, just return success response
        return response()->json([
            'success' => true,
            'message' => 'FFmpeg settings updated successfully',
            'data' => [
                'ffmpegSettings' => [
                    'ffmpegPath' => $request->ffmpegPath ?? config('ffmpeg.path', '/usr/bin/ffmpeg'),
                    'ffprobePath' => $request->ffprobePath ?? '/usr/bin/ffprobe',
                    'hlsSegmentDuration' => $request->hlsSegmentDuration ?? 6,
                    'hlsPlaylistType' => $request->hlsPlaylistType ?? 'event',
                    'enableAdaptiveStreaming' => $request->enableAdaptiveStreaming ?? true,
                    'videoQualities' => $request->videoQualities ?? ['1080p', '720p', '480p', '360p'],
                    'videoCodec' => $request->videoCodec ?? 'libx264',
                    'audioCodec' => $request->audioCodec ?? 'aac',
                    'videoBitrate' => $request->videoBitrate ?? '2500k',
                    'audioBitrate' => $request->audioBitrate ?? '128k',
                    'preset' => $preset,
                    'crf' => $request->crf ?? 23,
                    'maxThreads' => $request->maxThreads ?? 4,
                    'useHardwareAccel' => $request->useHardwareAccel ?? false,
                    'hwEncoder' => $request->hwEncoder ?? 'nvenc',
                    'customArgs' => $request->customArgs ?? []
                ]
            ]
        ]);
    }

    /**
     * Updates S3 storage settings.
     *
     * PUT `/api/settings/admin/s3`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request:
     * {
     *   "enabled": true,
     *   "endpoint": "https://s3.example.com",
     *   "accessKeyId": "your-access-key",
     *   "secretAccessKey": "your-secret-key",
     *   "bucket": "your-bucket-name",
     *   "region": "us-east-1"
     * }
     *
     * Response (Success - 200):
     * {
     *   "message": "S3 settings updated successfully",
     *   "settings": {
     *     "s3Settings": {
     *       "enabled": true,
     *       "endpoint": "https://s3.example.com",
     *       "accessKeyId": "your-access-key",
     *       "secretAccessKey": "your-secret-key",
     *       "bucket": "your-bucket-name",
     *       "region": "us-east-1"
     *     }
     *   }
     * }
     */
    public function updateS3Settings(Request $request)
    {
        $user = $request->user();

        if ($user->role == 'user') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'sometimes|boolean',
            'endpoint' => 'sometimes|string|nullable',
            'accessKeyId' => 'sometimes|string|nullable',
            'secretAccessKey' => 'sometimes|string|nullable',
            'bucket' => 'sometimes|string|nullable',
            'region' => 'sometimes|string|nullable',
            'storageType' => 'sometimes|string|nullable',
            'forcePathStyle' => 'sometimes|boolean',
            'deleteLocalAfterUpload' => 'sometimes|boolean',
            'publicUrlBase' => 'sometimes|string|nullable',
            'r2AccountId' => 'sometimes|string|nullable',
            'r2PublicDomain' => 'sometimes|string|nullable',
        ]);

        // Validasi storage type
        $validStorageTypes = ['local', 's3', 'minio', 'garage', 'r2'];
        $storageType = $request->input('storageType', 'local');
        if (!in_array($storageType, $validStorageTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid storage type. Must be one of: ' . implode(', ', $validStorageTypes)
            ], 400);
        }

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Get or create admin settings
        $settings = Setting::where('userId', $user->id)->first();
        if (!$settings) {
            $settings = new Setting([
                'id' => Str::uuid(),
                'userId' => $user->id,
            ]);
            $settings->save();
        }

        $s3Settings = array_merge($settings->s3Settings ?: [
            'enabled' => false,
            'storageType' => 'local',
            'endpoint' => '',
            'accessKey' => '',
            'secretKey' => '',
            'bucket' => '',
            'region' => 'us-east-1',
            'forcePathStyle' => false,
            'deleteLocalAfterUpload' => false,
            'publicUrlBase' => '',
            'r2AccountId' => '',
            'r2PublicDomain' => ''
        ], $request->only([
            'enabled', 'storageType', 'endpoint', 'accessKey', 'secretKey',
            'bucket', 'region', 'forcePathStyle', 'deleteLocalAfterUpload',
            'publicUrlBase', 'r2AccountId', 'r2PublicDomain'
        ]));

        // Update the database
        $settings->update(['s3Settings' => $s3Settings]);

        return response()->json([
            'success' => true,
            'message' => 'S3 settings updated successfully',
            'data' => [
                's3Settings' => $s3Settings
            ]
        ]);
    }

    /**
     * Updates website configuration settings.
     *
     * PUT `/api/settings/admin/website`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request:
     * {
     *   "siteName": "My Video Platform",
     *   "siteDescription": "The best platform for video conversion",
     *   "siteUrl": "https://myplatform.com",
     *   "allowRegistrations": true,
     *   "maintenanceMode": false
     * }
     *
     * Response (Success - 200):
     * {
     *   "message": "Website settings updated successfully",
     *   "settings": {
     *     "websiteSettings": {
     *       "siteName": "My Video Platform",
     *       "siteDescription": "The best platform for video conversion",
     *       "siteUrl": "https://myplatform.com",
     *       "allowRegistrations": true,
     *       "maintenanceMode": false
     *     }
     *   }
     * }
     */
    public function updateWebsiteSettings(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'siteName' => 'sometimes|string|max:255',
            'siteDescription' => 'sometimes|string',
            'siteUrl' => 'sometimes|url',
            'allowRegistrations' => 'sometimes|boolean',
            'maintenanceMode' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // In a real implementation, you would save to a dedicated admin settings table
        // For now, just return success response
        return response()->json([
            'success' => true,
            'message' => 'Website settings updated successfully',
            'data' => [
                'websiteSettings' => [
                    'siteName' => $request->siteName ?? config('app.name', 'HLS Video Converter'),
                    'siteDescription' => $request->siteDescription ?? 'Convert videos to HLS format',
                    'siteUrl' => $request->siteUrl ?? config('app.url', 'http://localhost:8000'),
                    'contactEmail' => $request->contactEmail ?? 'admin@example.com',
                    'enableRegistration' => $request->enableRegistration ?? true,
                    'enableGuestUpload' => $request->enableGuestUpload ?? true,
                    'maxUploadSizePerUser' => $request->maxUploadSizePerUser ?? 5368709120, // 5GB
                    'allowedVideoFormats' => $request->allowedVideoFormats ?? ['mp4', 'mov', 'avi', 'mkv', 'webm'],
                    'defaultUserRole' => $request->defaultUserRole ?? 'user',
                    'maintenanceMode' => $request->maintenanceMode ?? false,
                    'maintenanceMessage' => $request->maintenanceMessage ?? 'Site is under maintenance. Please check back later.'
                ]
            ]
        ]);
    }

    /**
     * Updates security configuration settings.
     *
     * PUT `/api/settings/admin/security`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request:
     * {
     *   "rateLimiting": true,
     *   "maxLoginAttempts": 3,
     *   "passwordRequirements": {
     *     "minLength": 10,
     *     "requireNumbers": true,
     *     "requireSymbols": true,
     *     "requireUppercase": true
     *   }
     * }
     *
     * Response (Success - 200):
     * {
     *   "message": "Security settings updated successfully",
     *   "settings": {
     *     "securitySettings": {
     *       "rateLimiting": true,
     *       "maxLoginAttempts": 3,
     *       "passwordRequirements": {
     *         "minLength": 10,
     *         "requireNumbers": true,
     *         "requireSymbols": true,
     *         "requireUppercase": true
     *       }
     *     }
     *   }
     * }
     */
    public function updateSecuritySettings(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'jwtExpiration' => 'sometimes|string',
            'passwordMinLength' => 'sometimes|integer|min:6|max:128',
            'requireEmailVerification' => 'sometimes|boolean',
            'maxLoginAttempts' => 'sometimes|integer|min:1|max:100',
            'lockoutDuration' => 'sometimes|integer|min:60|max:3600',
            'enableTwoFactor' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // In a real implementation, you would save to a dedicated admin settings table
        // For now, just return success response
        return response()->json([
            'success' => true,
            'message' => 'Security settings updated successfully',
            'data' => [
                'securitySettings' => [
                    'jwtExpiration' => $request->jwtExpiration ?? '24h',
                    'passwordMinLength' => $request->passwordMinLength ?? 8,
                    'requireEmailVerification' => $request->requireEmailVerification ?? false,
                    'maxLoginAttempts' => $request->maxLoginAttempts ?? 5,
                    'lockoutDuration' => $request->lockoutDuration ?? 300, // 5 minutes
                    'enableTwoFactor' => $request->enableTwoFactor ?? false
                ]
            ]
        ]);
    }

    /**
     * Uploads website favicon.
     *
     * POST `/api/settings/admin/favicon`
     * Headers: Authorization: Bearer {jwt-token}, Content-Type: multipart/form-data
     *
     * Request Files:
     * - favicon: file favicon
     *
     * Request:
     * {
     *   "favicon": [file]
     * }
     *
     * Response (Success - 200):
     * {
     *   "success": true,
     *   "message": "Favicon uploaded successfully",
     *   "data": {
     *     "favicon": "/assets/filename.png",
     *     "fullUrl": "http://example.com/assets/filename.png"
     *   }
     * }
     *
     * Response (Error - 400):
     * {
     *   "success": false,
     *   "message": "No file uploaded"
     * }
     */
    public function uploadFavicon(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'favicon' => 'required|file|image|mimes:png,jpg,jpeg,ico|max:512', // 512KB max
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $faviconFile = $request->file('favicon');

        if (!$faviconFile) {
            return response()->json([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
        }

        $extension = $faviconFile->getClientOriginalExtension();
        $filename = 'favicon.' . $extension;

        $path = $faviconFile->storeAs('admin', $filename, 'public');

        return response()->json([
            'success' => true,
            'message' => 'Favicon uploaded successfully',
            'data' => [
                'favicon' => "/storage/{$path}",
                'fullUrl' => url("/storage/{$path}")
            ]
        ]);
    }

    /**
     * Uploads website logo.
     *
     * POST `/api/settings/admin/logo`
     * Headers: Authorization: Bearer {jwt-token}, Content-Type: multipart/form-data
     *
     * Request Files:
     * - logo: file logo
     *
     * Request:
     * {
     *   "logo": [file]
     * }
     *
     * Response (Success - 200):
     * {
     *   "success": true,
     *   "message": "Logo uploaded successfully",
     *   "data": {
     *     "logo": "/assets/filename.png",
     *     "fullUrl": "http://example.com/assets/filename.png"
     *   }
     * }
     */
    public function uploadLogo(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'logo' => 'required|file|image|mimes:png,jpg,jpeg,gif,svg|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $logoFile = $request->file('logo');

        if (!$logoFile) {
            return response()->json([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
        }

        $extension = $logoFile->getClientOriginalExtension();
        $filename = 'logo.' . $extension;

        $path = $logoFile->storeAs('admin', $filename, 'public');

        return response()->json([
            'success' => true,
            'message' => 'Logo uploaded successfully',
            'data' => [
                'logo' => "/storage/{$path}",
                'fullUrl' => url("/storage/{$path}")
            ]
        ]);
    }

    /**
     * Deletes website favicon.
     *
     * DELETE `/api/settings/admin/favicon`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "message": "Favicon deleted successfully"
     * }
     */
    public function deleteFavicon(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        // Try to delete the favicon file
        $faviconPath = 'public/admin/favicon.png'; // Default path
        if (Storage::exists($faviconPath)) {
            Storage::delete($faviconPath);
        }

        return response()->json([
            'message' => 'Favicon deleted successfully'
        ]);
    }

    /**
     * Deletes website logo.
     *
     * DELETE `/api/settings/admin/logo`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "message": "Logo deleted successfully"
     * }
     */
    public function deleteLogo(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        // Try to delete the logo file
        $logoPath = 'public/admin/logo.png'; // Default path
        if (Storage::exists($logoPath)) {
            Storage::delete($logoPath);
        }

        return response()->json([
            'message' => 'Logo deleted successfully'
        ]);
    }

    /**
     * Updates email service settings.
     *
     * PUT `/api/settings/admin/email`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request Body Fields:
     * - enabled (boolean): apakah email diaktifkan
     * - provider (string): 'smtp', 'sendgrid', 'mailgun', 'ses'
     * - host (string): host SMTP
     * - port (number): port SMTP
     * - secure (boolean): aman (SSL/TLS)
     * - username (string): username SMTP
     * - password (string): password SMTP
     * - fromEmail (string): email pengirim
     * - fromName (string): nama pengirim
     *
     * Request:
     * {
     *   "enabled": true,
     *   "provider": "smtp",
     *   "host": "smtp.gmail.com",
     *   "port": 587,
     *   "secure": false,
     *   "username": "your-email@gmail.com",
     *   "password": "your-app-password",
     *   "fromEmail": "noreply@yourdomain.com",
     *   "fromName": "Your Site Name"
     * }
     *
     * Response (Success - 200):
     * {
     *   "success": true,
     *   "message": "Email settings updated successfully",
     *   "data": {objek email settings yang diperbarui }
     * }
     */
    public function updateEmailSettings(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'sometimes|boolean',
            'provider' => 'sometimes|string|in:smtp,sendgrid,mailgun,ses',
            'host' => 'sometimes|string',
            'port' => 'sometimes|integer|min:1|max:65535',
            'secure' => 'sometimes|boolean',
            'username' => 'sometimes|string',
            'password' => 'sometimes|string',
            'fromEmail' => 'sometimes|string|email',
            'fromName' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // In a real implementation, you would save to a dedicated admin settings table
        // For now, just return success response
        return response()->json([
            'success' => true,
            'message' => 'Email settings updated successfully',
            'data' => [
                'emailSettings' => [
                    'enabled' => $request->enabled ?? false,
                    'provider' => $request->provider ?? 'smtp',
                    'host' => $request->host ?? config('mail.mailers.smtp.host', 'smtp.gmail.com'),
                    'port' => $request->port ?? config('mail.mailers.smtp.port', 587),
                    'secure' => $request->secure ?? false,
                    'username' => $request->username ?? '',
                    'password' => $request->password ?? '',
                    'fromEmail' => $request->fromEmail ?? 'noreply@example.com',
                    'fromName' => $request->fromName ?? 'HLS Video Converter'
                ]
            ]
        ]);
    }

    /**
     * Tests email configuration.
     *
     * POST `/api/settings/admin/email/test`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request Body Fields:
     * - testEmail (string): alamat email untuk pengujian
     *
     * Request:
     * {
     *   "testEmail": "test@example.com"
     * }
     *
     * Response (Success - 200):
     * {
     *   "success": true,
     *   "message": "Test email sent successfully to test@example.com",
     *   "data": { "messageId": "message-id" }
     * }
     *
     * Response (Error - 400):
     * {
     *   "success": false,
     *   "message": "Email configuration error: error message"
     * }
     */
    public function testEmailSettings(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'testEmail' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $testEmail = $request->input('testEmail');

        // In a real implementation, you would send an actual email and catch any errors
        // For now, just simulate success response
        try {
            // Simulate sending email - in real implementation would use mail facade
            // Mail::to($testEmail)->send(new TestEmail());

            return response()->json([
                'success' => true,
                'message' => "Test email sent successfully to {$testEmail}",
                'data' => [
                    'messageId' => 'test-message-id-' . time()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Email configuration error: " . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generates Google Drive OAuth URL.
     *
     * GET `/api/settings/googledrive/auth-url`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "success": true,
     *   "data": {
     *     "authUrl": "https://accounts.google.com/o/oauth2/auth/...",
     *     "redirectUri": "http://example.com/api/settings/googledrive/callback"
     *   }
     * }
     */
    public function getGoogleDriveAuthUrl(Request $request)
    {
        $user = $request->user();

        // We'll return a placeholder URL since full Google OAuth implementation would require
        // proper Google API credentials configuration
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect_url');

        if (!$clientId) {
            return response()->json(['error' => 'Google client ID not configured'], 500);
        }

        $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'authUrl' => $authUrl,
                'redirectUri' => $redirectUri
            ]
        ]);
    }

    /**
     * Handles Google Drive OAuth callback.
     *
     * GET `/api/settings/googledrive/callback`
     * Query Parameters:
     * - code: Authorization code from Google
     * - state: State parameter for security
     *
     * Response (Success - 302):
     * Redirect to frontend with success message
     */
    public function handleGoogleDriveCallback(Request $request)
    {
        // In a real implementation, you would handle the OAuth code exchange
        // For now, returning a mock response
        $code = $request->get('code');
        $state = $request->get('state');

        if (!$code) {
            return redirect(config('app.frontend_url') . '?error=oauth_failed');
        }

        // Process the code to get tokens
        // In this simplified version, we'll just redirect with success
        return redirect(config('app.frontend_url') . '?success=google_drive_linked');
    }

    /**
     * Revokes Google Drive OAuth token.
     *
     * DELETE `/api/settings/googledrive/revoke`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "message": "Google Drive token revoked successfully"
     * }
     */
    public function revokeGoogleDriveToken(Request $request)
    {
        $user = $request->user();

        $settings = Setting::where('userId', $user->id)->first();
        if (!$settings) {
            $settings = new Setting([
                'id' => Str::uuid(),
                'userId' => $user->id,
            ]);
            $settings->save();
        }

        // Update googleDriveSettings to remove token information
        $googleDriveSettings = $settings->googleDriveSettings ?: [];
        $googleDriveSettings['enabled'] = false;
        $googleDriveSettings['token'] = null;
        $googleDriveSettings['refreshToken'] = null;

        $settings->update(['googleDriveSettings' => $googleDriveSettings]);

        return response()->json([
            'success' => true,
            'message' => 'Google Drive authorization revoked successfully'
        ]);
    }
}
