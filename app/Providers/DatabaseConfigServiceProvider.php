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

        // Load R2 configuration from database for admin user
        $this->loadR2ConfigFromDatabase();
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

    /**
     * Load R2 configuration from database and set it to Laravel config
     */
    private function loadR2ConfigFromDatabase(): void
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

                if ($settings && isset($settings->r2Settings)) {
                    $r2Settings = $settings->r2Settings;

                    // Only update config if R2 settings exist in database
                    if (is_array($r2Settings)) {
                        // Update R2 filesystem configuration using the correct field names from validation
                        Config::set('filesystems.disks.r2.key', $r2Settings['accessKeyId'] ?? Config::get('filesystems.disks.r2.key'));
                        Config::set('filesystems.disks.r2.secret', $r2Settings['secretAccessKey'] ?? Config::get('filesystems.disks.r2.secret'));
                        Config::set('filesystems.disks.r2.region', $r2Settings['region'] ?? Config::get('filesystems.disks.r2.region', 'auto'));
                        Config::set('filesystems.disks.r2.bucket', $r2Settings['bucket'] ?? Config::get('filesystems.disks.r2.bucket'));

                        // For R2, we might need to construct the URL differently based on the account ID
                        $r2AccountId = $r2Settings['r2AccountId'] ?? null;
                        $r2PublicDomain = $r2Settings['r2PublicDomain'] ?? Config::get('filesystems.disks.r2.url');
                        $endpoint = $r2Settings['endpoint'] ?? Config::get('filesystems.disks.r2.endpoint');

                        // If r2AccountId is provided, construct the R2-specific endpoint
                        if ($r2AccountId && empty($endpoint)) {
                            $endpoint = "https://{$r2AccountId}.r2.cloudflarestorage.com";
                        }

                        // Set the endpoint and potentially construct the URL
                        Config::set('filesystems.disks.r2.endpoint', $endpoint);

                        // Set URL - use publicUrlBase if provided, otherwise construct based on R2 pattern
                        $publicUrlBase = $r2Settings['publicUrlBase'] ?? $r2PublicDomain;
                        if ($publicUrlBase) {
                            Config::set('filesystems.disks.r2.url', $publicUrlBase);
                        } elseif ($r2AccountId) {
                            // Default to R2 public URL pattern if available
                            Config::set('filesystems.disks.r2.url', "https://{$r2AccountId}.r2.dev");
                        }

                        Config::set('filesystems.disks.r2.use_path_style_endpoint', $r2Settings['forcePathStyle'] ?? Config::get('filesystems.disks.r2.use_path_style_endpoint', true));

                        // Set enabled flag based on whether access key exists and is enabled
                        $isEnabled = (isset($r2Settings['enabled']) && $r2Settings['enabled']) &&
                                    !empty($r2Settings['accessKeyId']);

                        Config::set('filesystems.disks.r2.enabled', $isEnabled);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error loading R2 configuration from database: ' . $e->getMessage());
        }
    }
}
