<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class DatabaseConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load S3 configuration from database for admin user (typically user with id = 1 or first admin)
        $this->loadS3ConfigFromDatabase();
    }

    /**
     * Load S3 configuration from database and set it to Laravel config
     */
    private function loadS3ConfigFromDatabase(): void
    {
        try {
            // Try to get admin user settings - look for first user with admin role
            $adminUser = \App\Models\User::where('role', 'admin')->first();

            if (!$adminUser) {
                // If no admin user found, try first user
                $adminUser = \App\Models\User::first();
            }

            if ($adminUser) {
                $settings = Setting::where('userId', $adminUser->id)->first();

                if ($settings && isset($settings->s3Settings)) {
                    $s3Settings = $settings->s3Settings;

                    // Only update config if S3 settings exist in database
                    if (is_array($s3Settings)) {
                        // Update S3 filesystem configuration
                        Config::set('filesystems.disks.s3.key', $s3Settings['accessKey'] ?? Config::get('filesystems.disks.s3.key'));
                        Config::set('filesystems.disks.s3.secret', $s3Settings['secretKey'] ?? Config::get('filesystems.disks.s3.secret'));
                        Config::set('filesystems.disks.s3.region', $s3Settings['region'] ?? Config::get('filesystems.disks.s3.region'));
                        Config::set('filesystems.disks.s3.bucket', $s3Settings['bucket'] ?? Config::get('filesystems.disks.s3.bucket'));
                        Config::set('filesystems.disks.s3.endpoint', $s3Settings['endpoint'] ?? Config::get('filesystems.disks.s3.endpoint'));
                        Config::set('filesystems.disks.s3.use_path_style_endpoint', $s3Settings['forcePathStyle'] ?? Config::get('filesystems.disks.s3.use_path_style_endpoint'));

                        // Set enabled flag based on whether access key exists and is enabled
                        $isEnabled = (isset($s3Settings['enabled']) && $s3Settings['enabled']) &&
                                    !empty($s3Settings['accessKey']);

                        Config::set('filesystems.disks.s3.enabled', $isEnabled);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error loading S3 configuration from database: ' . $e->getMessage());
        }
    }
}
