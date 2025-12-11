<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; // Assuming there's a User model
use Illuminate\Support\Facades\Storage;

class PublicController extends Controller
{
    /**
     * Get public website information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPublicWebsiteInfo()
    {
        // Get admin user for site settings
        $admin = User::where('role', 'admin')->first();

        // Prepare default values
        $defaultSettings = [
            'siteName' => 'HLS Video Converter',
            'siteDescription' => 'Convert videos to HLS format',
            'siteUrl' => config('app.url'),
            'contactEmail' => null,
            'logo' => null,
            'logoUrl' => null,
            'favicon' => null,
            'faviconUrl' => null,
            'enableRegistration' => true,
            'enableGuestUpload' => false,
            'maintenanceMode' => false,
            'maintenanceMessage' => 'Website sedang dalam perawatan',
            'allowedVideoFormats' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'],
        ];

        // If admin exists, try to get settings from admin or user attributes
        if ($admin) {
            // Override defaults with actual values if available
            $defaultSettings['siteName'] = $admin->site_name ?? $defaultSettings['siteName'];
            $defaultSettings['siteDescription'] = $admin->site_description ?? $defaultSettings['siteDescription'];
            $defaultSettings['siteUrl'] = $admin->site_url ?? $defaultSettings['siteUrl'];
            $defaultSettings['contactEmail'] = $admin->contact_email ?? $defaultSettings['contactEmail'];
            $defaultSettings['enableRegistration'] = $admin->enable_registration ?? $defaultSettings['enableRegistration'];
            $defaultSettings['enableGuestUpload'] = $admin->enable_guest_upload ?? $defaultSettings['enableGuestUpload'];
            $defaultSettings['maintenanceMode'] = $admin->maintenance_mode ?? $defaultSettings['maintenanceMode'];
            $defaultSettings['maintenanceMessage'] = $admin->maintenance_message ?? $defaultSettings['maintenanceMessage'];

            // Handle logo
            if ($admin->logo) {
                $defaultSettings['logo'] = $admin->logo;
                $defaultSettings['logoUrl'] = $this->generateAssetUrl($admin->logo, 'logo');
            } else {
                $defaultSettings['logoUrl'] = asset('images/default-logo.png'); // fallback
            }

            // Handle favicon
            if ($admin->favicon) {
                $defaultSettings['favicon'] = $admin->favicon;
                $defaultSettings['faviconUrl'] = $this->generateAssetUrl($admin->favicon, 'favicon');
            } else {
                $defaultSettings['faviconUrl'] = asset('images/default-favicon.ico'); // fallback
            }

            // Handle allowed video formats if stored
            if ($admin->allowed_video_formats) {
                $defaultSettings['allowedVideoFormats'] = json_decode($admin->allowed_video_formats, true) ?: $defaultSettings['allowedVideoFormats'];
            }
        } else {
            // No admin found, use default values with fallback assets
            $defaultSettings['logoUrl'] = asset('images/default-logo.png');
            $defaultSettings['faviconUrl'] = asset('images/default-favicon.ico');
        }

        return response()->json($defaultSettings);
    }

    /**
     * Generate proper asset URL depending on storage type (local/S3)
     *
     * @param string $filename
     * @param string $type
     * @return string
     */
    private function generateAssetUrl($filename, $type)
    {
        if (!$filename) {
            return null;
        }

        // Check if using cloud storage (S3)
        if (config('filesystems.default') === 's3' || in_array(config('filesystems.default'), ['cloud'])) {
            // For S3 storage
            return Storage::disk('s3')->url($filename);
        } else {
            // For local storage
            return Storage::url($type . '/' . $filename);
        }
    }
}
