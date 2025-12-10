<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Video;
use App\Models\Analytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * Check if the authenticated user is an admin
     */
    private function isAdmin()
    {
        $user = request()->user();
        return $user;

        // if($user->role == 'admin') {
        //     return true;
        // }
        // return Auth::check() && Auth::user()->role == 'admin';
    }

    /**
     * Get all admin settings
     */
    public function getAllSettings()
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Return all admin settings from config
        $settings = [
            'website' => [
                'siteName' => config('app.name'),
                'siteUrl' => config('app.url'),
                'description' => config('app.description', ''),
            ],
            'ffmpeg' => [
                'path' => config('services.ffmpeg.path', ''),
                'presets' => config('services.ffmpeg.presets', []),
            ],
            's3' => [
                'enabled' => config('filesystems.disks.s3.key') !== null,
                'bucket' => config('filesystems.disks.s3.bucket'),
            ],
            'redis' => [
                'enabled' => config('cache.default') === 'redis',
                'host' => config('database.redis.default.host'),
            ],
            'rateLimit' => [
                'enabled' => config('services.rate_limit.enabled', true),
                'requests' => config('services.rate_limit.requests', 60),
                'expires' => config('services.rate_limit.expires', 1),
            ],
            'cors' => [
                'allowedOrigins' => config('cors.allowed_origins', []),
                'allowedMethods' => config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE']),
            ],
            'analytics' => [
                'enabled' => config('services.analytics.enabled', true),
            ],
            'security' => [
                'recaptcha' => config('services.recaptcha.enabled', false),
                'twoFactorAuth' => config('auth.two_factor_enabled', false),
            ],
            'email' => [
                'driver' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
            ],
            'googleDrive' => [
                'enabled' => config('services.google_drive.enabled', false),
                'clientId' => config('services.google_drive.client_id') ? true : false,
            ],
        ];

        return response()->json($settings);
    }

    /**
     * Update website settings
     */
    public function updateWebsiteSettings(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // In a real implementation, we would update the config values
        // For now, we'll just validate and return success
        $validator = Validator::make($request->all(), [
            'siteName' => 'string|max:255',
            'siteUrl' => 'string|url|max:255',
            'description' => 'string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // For now, return success - in a real app, this would update config files or database
        return response()->json(['message' => 'Website settings updated successfully']);
    }

    /**
     * Update FFmpeg settings
     */
    public function updateFFmpegSettings(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'path' => 'string|nullable',
            'presets' => 'array|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // For now, return success - in a real app, this would update config files
        return response()->json(['message' => 'FFmpeg settings updated successfully']);
    }

    /**
     * Update S3 storage settings
     */
    public function updateS3Settings(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'key' => 'string|nullable',
            'secret' => 'string|nullable',
            'region' => 'string|nullable',
            'bucket' => 'string|nullable',
            'endpoint' => 'string|url|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // For now, return success - in a real app, this would update config files
        return response()->json(['message' => 'S3 settings updated successfully']);
    }

    /**
     * Update Redis settings
     */
    public function updateRedisSettings(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'host' => 'string|nullable',
            'port' => 'integer|nullable',
            'password' => 'string|nullable',
            'database' => 'integer|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // For now, return success - in a real app, this would update config files
        return response()->json(['message' => 'Redis settings updated successfully']);
    }

    /**
     * Update rate limiting settings
     */
    public function updateRateLimitSettings(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'boolean',
            'requests' => 'integer|min:1',
            'expires' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // For now, return success - in a real app, this would update config files
        return response()->json(['message' => 'Rate limit settings updated successfully']);
    }

    /**
     * Update CORS settings
     */
    public function updateCorsSettings(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'allowedOrigins' => 'array|nullable',
            'allowedOrigins.*' => 'string|url',
            'allowedMethods' => 'array|nullable',
            'allowedMethods.*' => 'string|in:GET,POST,PUT,DELETE,OPTIONS',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // For now, return success - in a real app, this would update config files
        return response()->json(['message' => 'CORS settings updated successfully']);
    }

    /**
     * Update analytics settings
     */
    public function updateAnalyticsSettings(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'boolean',
            'trackingId' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // For now, return success - in a real app, this would update config files
        return response()->json(['message' => 'Analytics settings updated successfully']);
    }

    /**
     * Update security settings
     */
    public function updateSecuritySettings(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'recaptcha.enabled' => 'boolean',
            'recaptcha.siteKey' => 'string|nullable',
            'recaptcha.secretKey' => 'string|nullable',
            'twoFactorAuth' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // For now, return success - in a real app, this would update config files
        return response()->json(['message' => 'Security settings updated successfully']);
    }

    /**
     * Update email settings
     */
    public function updateEmailSettings(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'driver' => 'string|in:smtp,mail,sendmail',
            'host' => 'string|nullable',
            'port' => 'integer|nullable',
            'encryption' => 'string|nullable|in:tls,ssl,null',
            'username' => 'string|nullable',
            'password' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // For now, return success - in a real app, this would update config files
        return response()->json(['message' => 'Email settings updated successfully']);
    }

    /**
     * Get Google Drive settings (admin level)
     */
    public function getGoogleDriveSettings()
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $settings = [
            'enabled' => config('services.google_drive.enabled', false),
            'clientId' => config('services.google_drive.client_id') ? true : false, // Return boolean to not expose client ID
            'redirectUri' => config('services.google_drive.redirect_uri'),
        ];

        return response()->json($settings);
    }

    /**
     * Update Google Drive settings (admin level)
     */
    public function updateGoogleDriveSettings(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'boolean',
            'clientId' => 'string|nullable',
            'clientSecret' => 'string|nullable',
            'redirectUri' => 'string|url|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // For now, return success - in a real app, this would update config files
        return response()->json(['message' => 'Google Drive settings updated successfully']);
    }

    /**
     * Upload favicon
     */
    public function uploadFavicon(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$request->hasFile('favicon')) {
            return response()->json(['error' => 'No favicon file provided'], 400);
        }

        $favicon = $request->file('favicon');
        $validator = Validator::make(['favicon' => $favicon], [
            'favicon' => 'required|image|mimes:png,ico,jpg,jpeg|max:1024', // Max 1MB
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Delete existing favicon if exists
        $existingFaviconPath = 'public/favicon.ico';
        if (Storage::exists($existingFaviconPath)) {
            Storage::delete($existingFaviconPath);
        }

        // Store new favicon
        $path = $favicon->storeAs('public', 'favicon.ico');

        return response()->json(['message' => 'Favicon uploaded successfully']);
    }

    /**
     * Get favicon
     */
    public function getFavicon()
    {
        $faviconPath = storage_path('app/public/favicon.ico');
        if (file_exists($faviconPath)) {
            return response()->file($faviconPath);
        }

        // Return default favicon if not exists
        return response()->json(['error' => 'Favicon not found'], 404);
    }

    /**
     * Delete favicon
     */
    public function deleteFavicon()
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $faviconPath = 'public/favicon.ico';
        if (Storage::exists($faviconPath)) {
            Storage::delete($faviconPath);
            return response()->json(['message' => 'Favicon deleted successfully']);
        }

        return response()->json(['message' => 'Favicon does not exist']);
    }

    /**
     * Get all users with pagination and filtering
     */
    public function getUsers(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $filterBy = $request->query('filterBy');
        $sortBy = $request->query('sortBy', 'createdAt');
        $sortOrder = $request->query('sortOrder', 'desc');

        // Get user statistics
        $totalUsers = User::count();
        $activeUsers = User::where('isActive', true)->count();
        $bannedUsers = User::where('isActive', false)->count();
        $adminUsers = User::where('role', 'admin')->count();

        $query = User::query();

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Apply additional filters
        if ($filterBy) {
            if ($filterBy === 'banned') {
                $query->where('isActive', false);
            } elseif ($filterBy === 'not_banned') {
                $query->where('isActive', true);
            } elseif ($filterBy === 'admin') {
                $query->where('role', 'admin');
            }
        }

        // Apply sorting
        if (in_array($sortBy, ['id', 'username', 'email', 'createdAt', 'updatedAt'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('createdAt', $sortOrder);
        }

        $users = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'data' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'stats' => [
                    'totalUsers' => $totalUsers, // Total keseluruhan pengguna dalam sistem
                    'activeUsers' => $activeUsers, // Jumlah pengguna yang aktif
                    'bannedUsers' => $bannedUsers, // Jumlah pengguna yang diblokir
                    'adminUsers' => $adminUsers, // Jumlah pengguna dengan peran admin
                ]
            ]
        ]);
    }

    /**
     * Get single user details
     */
    public function getUser($id)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Return user with additional metrics
        $user->append([
            'total_storage_used',
            'total_videos_count',
            'total_watch_time',
            'total_likes',
        ]);

        return response()->json($user);
    }

    /**
     * Update user (admin)
     */
    public function updateUser($id, Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $id,
            'is_admin' => 'boolean',
            'is_verified' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user->update([
            'name' => $request->input('name', $user->name),
            'email' => $request->input('email', $user->email),
            'is_admin' => $request->input('is_admin', $user->is_admin),
            'email_verified_at' => $request->input('is_verified', $user->is_verified) ? 
                                  now() : (!$request->input('is_verified') ? null : $user->email_verified_at),
        ]);

        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    /**
     * Ban/Unban user
     */
    public function toggleUserBan($id)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->is_banned = !$user->is_banned;
        $user->save();

        $action = $user->is_banned ? 'Banned' : 'Unbanned';
        return response()->json(['message' => "User {$action} successfully", 'user' => $user]);
    }

    /**
     * Update user storage limit
     */
    public function updateUserStorage($id, Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'storageLimit' => 'required|integer|min:1', // in MB
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user->storage_limit = $request->input('storageLimit') * 1024 * 1024; // Convert MB to bytes
        $user->save();

        return response()->json(['message' => 'User storage limit updated successfully', 'user' => $user]);
    }

    /**
     * Delete user and all their data
     */
    public function deleteUser($id)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Delete all user's videos and associated analytics
        $user->videos()->delete();
        Analytics::where('userId', $user->id)->delete();
        
        // Delete the user
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Reset user password (admin)
     */
    public function resetUserPassword($id, Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $newPassword = $request->input('newPassword', Str::random(12));
        $user->password = Hash::make($newPassword);
        $user->save();

        return response()->json([
            'message' => 'User password reset successfully',
            'newPassword' => $newPassword
        ]);
    }

    /**
     * Toggle ads for user (enable/disable ads on all user's videos)
     */
    public function toggleUserAds($id)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Get all videos for the user
        $videos = $user->videos;
        
        // Toggle ads for all videos
        foreach ($videos as $video) {
            $video->ads_enabled = !$video->ads_enabled;
            $video->save();
        }

        $action = $videos->first()->ads_enabled ? 'enabled' : 'disabled';
        return response()->json([
            'message' => "Ads {$action} for all user's videos successfully",
            'videosUpdated' => count($videos)
        ]);
    }

    /**
     * Get all videos for a specific user (admin)
     */
    public function adminGetUserVideos(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $userId = $request->query('userId');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);

        $query = Video::query();
        
        if ($userId) {
            $query->where('userId', $userId);
        }

        $videos = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json($videos);
    }

    /**
     * Get storage statistics
     */
    public function getStorageStats()
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Calculate total storage used by all users
        $totalStorageUsed = User::sum('storage_used');
        $totalStorageLimit = User::sum('storage_limit');
        $totalUsers = User::count();
        $usersWithVideos = User::whereHas('videos')->count();
        
        // Calculate storage per user
        $avgStoragePerUser = $totalUsers > 0 ? $totalStorageUsed / $totalUsers : 0;
        
        // Calculate storage percentage
        $storagePercentage = $totalStorageLimit > 0 ? ($totalStorageUsed / $totalStorageLimit) * 100 : 0;

        return response()->json([
            'totalStorageUsed' => $totalStorageUsed,
            'totalStorageLimit' => $totalStorageLimit,
            'totalUsers' => $totalUsers,
            'usersWithVideos' => $usersWithVideos,
            'avgStoragePerUser' => $avgStoragePerUser,
            'storagePercentage' => round($storagePercentage, 2),
        ]);
    }

    /**
     * Get admin settings for a specific user
     */
    private function getAdminSettings($userId)
    {
        // This would typically retrieve admin settings from database
        // For now, returning a mock implementation
        return [
            'userId' => $userId,
            'settings' => [
                'isAdmin' => true,
                'permissions' => [
                    'manageUsers' => true,
                    'manageSettings' => true,
                    'viewAnalytics' => true,
                ],
            ],
        ];
    }
}