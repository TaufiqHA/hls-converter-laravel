<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleDriveService
{
    /**
     * Download a file from Google Drive
     *
     * @param Video $video The video model
     * @param string $googleDriveUrl The Google Drive URL
     * @return array Result with success status and file information
     * @throws \Exception
     */
    public function downloadFromGoogleDrive(Video $video, string $googleDriveUrl): array
    {
        // Extract Google Drive file ID from URL
        $fileId = $this->extractGoogleDriveFileId($googleDriveUrl);
        if (!$fileId) {
            throw new \Exception("Could not extract Google Drive file ID from URL: {$googleDriveUrl}");
        }

        // Create direct download URL using Google Drive API
        $downloadUrl = "https://drive.google.com/uc?export=download&id={$fileId}";

        // Create context for file download
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 300, // 5 minutes timeout
                'user_agent' => 'Mozilla/5.0 (compatible; VideoConverterBot/1.0)'
            ]
        ]);

        // Get the temporary file path from the video model
        $tempFilePath = $video->originalFilePath;

        // Use the public disk which is typically where temporary files are stored
        // This matches the error logs showing files in storage/app/public/temp/remote_uploads/...
        $storageDisk = Storage::disk('public');

        // Ensure directory exists using the public disk
        $dir = dirname($tempFilePath);
        if (!$storageDisk->exists($dir)) {
            $storageDisk->makeDirectory($dir, 0755, true, true); // create directory recursively
        }

        // Download the file
        $downloadedContent = file_get_contents($downloadUrl, false, $context);

        if ($downloadedContent === false) {
            // If the direct download failed, it might be because of large files
            // Google Drive sometimes redirects to a virus scan page for large files
            // We should follow that redirect or use the confirm parameter
            $downloadUrl = "https://drive.google.com/uc?export=download&confirm=1&id={$fileId}";
            $downloadedContent = file_get_contents($downloadUrl, false, $context);

            if ($downloadedContent === false) {
                throw new \Exception("Failed to download file from Google Drive: {$downloadUrl}");
            }
        }

        // Save the downloaded content to the temp file using the public disk for consistency
        $storageDisk->put($tempFilePath, $downloadedContent);

        // Get the actual file size after download using the public disk
        try {
            $actualFileSize = $storageDisk->size($tempFilePath);
        } catch (\Exception $e) {
            Log::error("Could not retrieve file size for downloaded file: {$tempFilePath}. Error: " . $e->getMessage());
            throw new \Exception("Could not retrieve file size for downloaded file: {$tempFilePath}. Error: " . $e->getMessage());
        }

        // Verify that the file exists and has content before proceeding
        if (!$actualFileSize || $actualFileSize === 0) {
            throw new \Exception("Downloaded file is empty or does not exist: {$tempFilePath}");
        }

        // Additional verification: ensure the file is accessible via Laravel Storage facade
        if (!$storageDisk->exists($tempFilePath)) {
            Log::warning("File downloaded but not accessible via Storage facade: {$tempFilePath}");
            // Wait a bit and try again - this might be a timing issue
            sleep(1);
            if (!$storageDisk->exists($tempFilePath)) {
                throw new \Exception("Downloaded file is not accessible via Storage facade: {$tempFilePath}");
            }
        }

        Log::info("Successfully downloaded Google Drive file (ID: {$fileId}) to {$tempFilePath}, Size: {$actualFileSize} bytes");

        // After successful download, move the file to the proper uploads/originals directory
        $originalFileName = $video->originalFileName;
        $extension = pathinfo($originalFileName, PATHINFO_EXTENSION) ?: 'mp4'; // Default to mp4 if no extension
        $finalFilePath = "uploads/originals/{$video->id}.{$extension}";
        
        // Ensure the uploads/originals directory exists using the same disk
        $uploadDir = dirname($finalFilePath);
        if (!$storageDisk->exists($uploadDir)) {
            $storageDisk->makeDirectory($uploadDir, 0755, true, true); // create directory recursively
        }
        
        // Move the temporary file to the final location using the same disk
        if (!$storageDisk->move($tempFilePath, $finalFilePath)) {
            throw new \Exception("Failed to move temporary file from {$tempFilePath} to {$finalFilePath}");
        }

        // Update the video record with the proper file path
        $video->update([
            'originalFilePath' => $finalFilePath
        ]);

        Log::info("Moved downloaded Google Drive file from {$tempFilePath} to {$finalFilePath}");

        // Update the video record with the actual file size
        $video->update([
            'originalFileSize' => $actualFileSize
        ]);

        // Update user's storage used with the actual file size
        $user = $video->user;
        $user->increment('storageUsed', $actualFileSize);

        return [
            'success' => true,
            'filePath' => $finalFilePath,
            'fileSize' => $actualFileSize,
            'fileId' => $fileId
        ];
    }

    /**
     * Extract Google Drive file ID from URL
     *
     * @param string $url The Google Drive URL
     * @return string|null The file ID or null if not found
     */
    public function extractGoogleDriveFileId(string $url): ?string
    {
        // Handle various Google Drive URL formats
        // Examples:
        // https://drive.google.com/file/d/FILEID/view?usp=sharing
        // https://drive.google.com/open?id=FILEID
        // https://drive.google.com/uc?export=download&id=FILEID

        // Pattern for /file/d/FILEID/ format
        if (preg_match('/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for ?id=FILEID format
        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for export=download&id=FILEID format
        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
    
    /**
     * Validate if the Google Drive URL is accessible
     *
     * @param string $url The Google Drive URL
     * @return bool Whether the URL is valid and accessible
     */
    public function validateGoogleDriveUrl(string $url): bool
    {
        $fileId = $this->extractGoogleDriveFileId($url);
        if (!$fileId) {
            Log::error("Could not extract file ID from Google Drive URL: {$url}");
            return false;
        }

        // Try multiple approaches to validate the URL
        // First, try the direct metadata URL
        $metadataUrl = "https://drive.google.com/uc?export=metadata&id={$fileId}";

        // Create context with proper user agent
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; VideoConverterBot/1.0)',
                'follow_location' => 0,  // Don't follow redirects for faster response
            ]
        ]);

        $headers = @get_headers($metadataUrl, 1, $context);

        if ($headers === false) {
            // If first approach failed, try the download URL
            $downloadUrl = "https://drive.google.com/uc?export=download&id={$fileId}";
            $headers = @get_headers($downloadUrl, 1, $context);

            if ($headers === false) {
                Log::warning("Could not get headers for Google Drive file ID: {$fileId}");
                return false;
            }
        }

        // Check if we got a valid response
        if (isset($headers[0])) {
            $statusCode = substr($headers[0], 9, 3);
            $statusCodeInt = intval($statusCode);

            // Common success status codes (2xx) or redirect codes (3xx) that indicate accessible content
            if (($statusCodeInt >= 200 && $statusCodeInt < 300) ||
                ($statusCodeInt >= 300 && $statusCodeInt < 400)) {
                return true;
            }
        }

        // If headers don't contain response status, check for specific Google Drive headers
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (is_string($key) && stripos($key, 'location') !== false) {
                    // If there's a Location header, it means the file exists and is accessible
                    return true;
                }
            }
        }

        return false;
    }
}