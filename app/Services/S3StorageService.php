<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;

/**
 * Handles S3-compatible storage operations for HLS files.
 *
 * Key Features:
 * - Supports multiple S3-compatible services (AWS S3, MinIO, Cloudflare R2, Garage)
 * - Uploads files and directories to S3
 * - Deletes files and directories from S3
 * - Generates signed URLs for private access
 * - Checks if files exist in S3
 * - Uploads HLS conversion results to S3
 * - Supports public URL generation
 */
class S3StorageService
{
    protected $client;
    protected $bucket;
    protected $region;
    protected $enabled;

    public function __construct()
    {
        $this->enabled = config('filesystems.disks.s3.enabled', false);
        
        if ($this->enabled) {
            $this->client = new S3Client([
                'version' => 'latest',
                'region' => config('filesystems.disks.s3.region', 'us-east-1'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint', false),
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);
            
            $this->bucket = config('filesystems.disks.s3.bucket');
            $this->region = config('filesystems.disks.s3.region', 'us-east-1');
        }
    }

    /**
     * Initializes S3 client from database settings
     * 
     * @return void
     */
    public function initFromDatabase(): void
    {
        // This would normally fetch S3 settings from the database and reinitialize the client
        // For now, we're using Laravel's config system which can be populated from database
    }

    /**
     * Uploads file to S3
     * 
     * @param string $localPath Local file path
     * @param string $s3Key S3 object key
     * @param string $contentType Content type of the file
     * @return bool Success status
     */
    public function uploadFile(string $localPath, string $s3Key, string $contentType = 'application/octet-stream'): bool
    {
        if (!$this->enabled) {
            Log::warning('S3 is not enabled');
            return false;
        }

        try {
            $fileContents = Storage::disk('local')->get($localPath);
            
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                'Body' => $fileContents,
                'ContentType' => $contentType,
                'ACL' => 'public-read' // Adjust based on privacy settings
            ]);

            return true;
        } catch (AwsException $e) {
            Log::error("Error uploading file to S3: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Uploads HLS conversion results to S3
     * 
     * @param string $localHlsDir Local HLS directory path
     * @param string $userId User ID
     * @param string $videoId Video ID
     * @return bool Success status
     */
    public function uploadHLSFiles(string $localHlsDir, string $userId, string $videoId): bool
    {
        if (!$this->enabled) {
            Log::warning('S3 is not enabled');
            return false;
        }

        try {
            // Get all files in the HLS directory
            $files = Storage::disk('local')->allFiles($localHlsDir);

            foreach ($files as $file) {
                $fileContent = Storage::disk('local')->get($file);
                $s3Key = "users/{$userId}/videos/{$videoId}/" . basename($file);

                $this->client->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $s3Key,
                    'Body' => $fileContent,
                    'ContentType' => $this->getMimeType($file),
                    'ACL' => 'public-read' // This could be configurable based on video privacy
                ]);
            }

            return true;
        } catch (AwsException $e) {
            Log::error("Error uploading HLS files to S3: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes file from S3
     * 
     * @param string $s3Key S3 object key
     * @return bool Success status
     */
    public function deleteFile(string $s3Key): bool
    {
        if (!$this->enabled) {
            Log::warning('S3 is not enabled');
            return false;
        }

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key
            ]);

            return true;
        } catch (AwsException $e) {
            Log::error("Error deleting file from S3: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets signed URL for private access
     * 
     * @param string $s3Key S3 object key
     * @param int $expiresIn Number of seconds until expiration (default 3600)
     * @return string|null Signed URL or null if error
     */
    public function getSignedUrl(string $s3Key, int $expiresIn = 3600): ?string
    {
        if (!$this->enabled) {
            Log::warning('S3 is not enabled');
            return null;
        }

        try {
            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $s3Key
            ]);

            $request = $this->client->createPresignedRequest($cmd, "+{$expiresIn} seconds");
            return (string) $request->getUri();
        } catch (AwsException $e) {
            Log::error("Error generating signed URL: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Checks if file exists in S3
     * 
     * @param string $s3Key S3 object key
     * @return bool Whether file exists
     */
    public function fileExists(string $s3Key): bool
    {
        if (!$this->enabled) {
            Log::warning('S3 is not enabled');
            return false;
        }

        try {
            return $this->client->doesObjectExist($this->bucket, $s3Key);
        } catch (AwsException $e) {
            Log::error("Error checking if file exists in S3: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets public URL for S3 object
     * 
     * @param string $s3Key S3 object key
     * @return string Public URL
     */
    public function getPublicUrl(string $s3Key): string
    {
        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$s3Key}";
    }

    /**
     * Delete HLS files from S3
     * 
     * @param string $videoId Video ID
     * @return bool Success status
     */
    public function deleteHLSFiles(string $videoId): bool
    {
        if (!$this->enabled) {
            Log::warning('S3 is not enabled');
            return false;
        }

        try {
            // For simplicity, we'll assume HLS files are stored under a specific path
            // In practice, you might want to list objects with a prefix and delete them
            $prefix = "videos/{$videoId}/";

            $objects = $this->client->listObjects([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix
            ]);

            if (isset($objects['Contents'])) {
                foreach ($objects['Contents'] as $object) {
                    $this->client->deleteObject([
                        'Bucket' => $this->bucket,
                        'Key' => $object['Key']
                    ]);
                }
            }

            return true;
        } catch (AwsException $e) {
            Log::error("Error deleting HLS files from S3: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper method to detect MIME type based on file extension
     * 
     * @param string $filename Filename
     * @return string MIME type
     */
    protected function getMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'm3u8' => 'application/vnd.apple.mpegurl',
            'ts' => 'video/mp2t',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            '3gp' => 'video/3gpp',
            'm4v' => 'video/x-m4v',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}