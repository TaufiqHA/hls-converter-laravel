<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\GoogleDriveService;

class RemoteUploaderService
{
    /**
     * Get filename from URL by checking Content-Disposition header or extracting from URL path
     *
     * @param string $url The URL of the file
     * @param array|null $googleDriveSettings Google Drive settings if it's a Google Drive URL
     * @return string|null The filename or null if not found
     */
    public function getFilenameFromUrl(string $url, ?array $googleDriveSettings = null): ?string
    {
        if ($this->isGoogleDriveUrl($url)) {
            return $this->getGoogleDriveFilename($url, $googleDriveSettings);
        }

        // First try to get filename from URL path
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $filename = basename($path);
            if ($filename && $filename !== '/') {
                return $filename;
            }
        }

        // If we can't get from URL path, try to get from Content-Disposition header
        $headers = $this->getUrlHeaders($url);
        if ($headers) {
            foreach ($headers as $header) {
                if (preg_match('/filename[^;=\n]*=((?:".*?")|(?:.*))/i', $header, $matches)) {
                    $filename = trim($matches[1], "\"'");
                    if ($filename) {
                        return $filename;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get filename from Google Drive using Google Drive API
     *
     * @param string $url The Google Drive URL
     * @param array|null $googleDriveSettings Google Drive settings for authentication
     * @return string|null The filename or null if not found
     */
    public function getGoogleDriveFilename(string $url, ?array $googleDriveSettings = null): ?string
    {
        try {
            $googleDriveService = new GoogleDriveService();
            
            // Extract file ID from URL first
            $fileId = $googleDriveService->extractGoogleDriveFileId($url);
            if (!$fileId) {
                Log::error("Could not extract file ID from Google Drive URL: {$url}");
                return null;
            }

            // For now, we'll use a simpler approach to get the filename
            // In a full implementation, we would use the Google Drive API to get file information
            $downloadUrl = "https://drive.google.com/uc?export=metadata&id={$fileId}";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; VideoConverterBot/1.0)'
                ]
            ]);

            $metadata = @get_headers($downloadUrl, 1);
            if ($metadata && isset($metadata['Content-Disposition'])) {
                $contentDisposition = is_array($metadata['Content-Disposition']) 
                    ? $metadata['Content-Disposition'][0] 
                    : $metadata['Content-Disposition'];
                
                if (preg_match('/filename[^;=\n]*=((?:".*?")|(?:.*))/i', $contentDisposition, $matches)) {
                    return trim($matches[1], "\"'");
                }
            }

            // As fallback, return a generic name with proper extension
            return "google_drive_file_" . $fileId . ".mp4";
        } catch (\Exception $e) {
            Log::error("Error getting Google Drive filename: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Download file from a URL to local storage
     *
     * @param string $url The URL to download from
     * @param string $userId The user ID
     * @param callable|null $onProgress Callback for progress tracking
     * @return array Result with success status and file information
     */
    public function downloadFromUrl(string $url, string $userId, ?callable $onProgress = null): array
    {
        // Validate protocol (must be HTTP/HTTPS)
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower($scheme), ['http', 'https'])) {
            throw new \Exception("Invalid protocol. Only HTTP/HTTPS URLs are allowed.");
        }

        // Get file info from HEAD request
        $fileInfo = $this->getFileInfoFromUrl($url);
        if (!$fileInfo) {
            throw new \Exception("Could not get file information from URL: {$url}");
        }

        // Check file size limit
        $maxFileSize = config('app.max_remote_file_size', 1024 * 1024 * 1024); // 1GB default
        if (isset($fileInfo['size']) && $fileInfo['size'] > $maxFileSize) {
            throw new \Exception("File size exceeds limit: {$fileInfo['size']} bytes > {$maxFileSize} bytes");
        }

        // Create user's upload directory
        $uploadDir = "uploads/{$userId}/temp";
        if (!Storage::exists($uploadDir)) {
            Storage::makeDirectory($uploadDir, 0755, true);
        }

        // Determine filename
        $filename = $fileInfo['filename'] ?? basename(parse_url($url, PHP_URL_PATH)) ?? 'remote_file_' . time() . '.mp4';
        
        // Ensure the file has a proper extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if (empty($extension)) {
            $filename .= '.mp4'; // Default extension
        }

        $filePath = $uploadDir . '/' . $filename;

        // Create context for file download with progress tracking
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 3600, // 1 hour timeout for large files
                'user_agent' => 'Mozilla/5.0 (compatible; VideoConverterBot/1.0)',
            ]
        ]);

        // Download the file
        $tempFile = tmpfile();
        $meta = stream_get_meta_data($tempFile);
        $tempPath = $meta['uri'];

        $source = fopen($url, 'r', false, $context);
        if (!$source) {
            throw new \Exception("Failed to open URL for download: {$url}");
        }

        $dest = fopen($tempPath, 'w');
        if (!$dest) {
            fclose($source);
            throw new \Exception("Failed to create temporary file for download");
        }

        $totalSize = $fileInfo['size'] ?? 0;
        $downloaded = 0;
        $bufferSize = 8192; // 8KB buffer

        while (!feof($source)) {
            $buffer = fread($source, $bufferSize);
            if ($buffer === false) {
                break;
            }
            
            $bytesWritten = fwrite($dest, $buffer);
            if ($bytesWritten === false) {
                break;
            }
            
            $downloaded += $bytesWritten;
            
            // Call progress callback if provided
            if ($onProgress && $totalSize > 0) {
                $progress = min(95, intval(($downloaded / $totalSize) * 100)); // Never reach 100% until complete
                $onProgress($progress, $downloaded, $totalSize);
            }
        }

        fclose($source);
        fclose($dest);

        if ($totalSize > 0 && $downloaded !== $totalSize) {
            Log::warning("Downloaded size ({$downloaded}) differs from expected size ({$totalSize}) for URL: {$url}");
        }

        // Move from temp file to storage
        $storagePath = Storage::path($filePath);
        $moveResult = rename($tempPath, $storagePath);

        if (!$moveResult) {
            // Fallback: try to copy instead
            $moveResult = copy($tempPath, $storagePath);
            unlink($tempPath);
        }

        if (!$moveResult) {
            throw new \Exception("Failed to move downloaded file to storage: {$filePath}");
        }

        // Update progress to 100% if progress callback was provided
        if ($onProgress) {
            $onProgress(100, $downloaded, $totalSize);
        }

        return [
            'success' => true,
            'filePath' => $filePath,
            'fileSize' => $downloaded,
            'filename' => basename($filePath)
        ];
    }

    /**
     * Download file from Google Drive
     *
     * @param string $url The Google Drive URL
     * @param string $userId The user ID
     * @param array|null $googleDriveSettings Google Drive settings for authentication
     * @param callable|null $onProgress Callback for progress tracking
     * @return array Result with success status and file information
     */
    public function downloadFromGoogleDrive(string $url, string $userId, ?array $googleDriveSettings = null, ?callable $onProgress = null): array
    {
        // Create a temporary video record for the Google Drive download
        $fileId = $this->extractFileIdFromGoogleDriveUrl($url);
        if (!$fileId) {
            throw new \Exception("Could not extract Google Drive file ID from URL: {$url}");
        }

        $filename = $this->getGoogleDriveFilename($url, $googleDriveSettings);
        if (!$filename) {
            $filename = "google_drive_file_{$fileId}.mp4";
        }

        // Create temporary file path
        $tempDir = "temp/remote_uploads/{$userId}";
        if (!Storage::exists($tempDir)) {
            Storage::makeDirectory($tempDir, 0755, true);
        }

        $tempFileName = pathinfo($filename, PATHINFO_FILENAME) . '_' . Str::random(10) . '.tmp';
        $tempFilePath = $tempDir . '/' . $tempFileName;

        // Create a temporary video model to pass to GoogleDriveService
        $tempVideo = new \App\Models\Video();
        $tempVideo->id = Str::uuid();
        $tempVideo->originalFileName = $filename;
        $tempVideo->originalFilePath = $tempFilePath;
        $tempVideo->user = new \App\Models\User();
        $tempVideo->user->id = $userId;

        // Use the existing GoogleDriveService for actual download with progress tracking
        $googleDriveService = new GoogleDriveService();
        $result = $googleDriveService->downloadFromGoogleDrive($tempVideo, $url);

        // Update progress to 100% if progress callback was provided
        if ($onProgress) {
            $onProgress(100, $result['fileSize'], $result['fileSize']);
        }

        return $result;
    }

    /**
     * Validate URL before download
     *
     * @param string $url The URL to validate
     * @param array|null $googleDriveSettings Google Drive settings if it's a Google Drive URL
     * @return array Result with validation status and URL type
     */
    public function validateUrl(string $url, ?array $googleDriveSettings = null): array
    {
        if ($this->isGoogleDriveUrl($url)) {
            // Validate Google Drive URL
            $isValid = $this->validateGoogleDriveUrl($url, $googleDriveSettings);
            return [
                'isValid' => $isValid,
                'urlType' => 'googledrive',
                'message' => $isValid ? 'Valid Google Drive URL' : 'Invalid or inaccessible Google Drive URL'
            ];
        }

        // Validate regular URL
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower($scheme), ['http', 'https'])) {
            return [
                'isValid' => false,
                'urlType' => 'regular',
                'message' => 'Invalid URL scheme. Only HTTP/HTTPS URLs are allowed.'
            ];
        }

        // Check if URL is accessible
        $headers = $this->getUrlHeaders($url);
        if (!$headers) {
            return [
                'isValid' => false,
                'urlType' => 'regular',
                'message' => 'Could not access URL or URL is not responding.'
            ];
        }

        // Check if content type is acceptable (video format)
        $contentType = $this->getContentTypeFromHeaders($headers);
        $acceptableTypes = [
            'video/',
            'application/octet-stream',
            'application/x-shockwave-flash',
            'application/vnd.apple.mpegurl', 
            'application/x-mpegurl'
        ];

        $isVideo = false;
        foreach ($acceptableTypes as $type) {
            if (strpos($contentType, $type) !== false) {
                $isVideo = true;
                break;
            }
        }

        if (!$isVideo) {
            Log::warning("URL content type may not be a video: {$contentType}. URL: {$url}");
        }

        return [
            'isValid' => true,
            'urlType' => 'regular',
            'message' => 'Valid URL',
            'contentType' => $contentType
        ];
    }

    /**
     * Check if URL is a Google Drive URL
     *
     * @param string $url The URL to check
     * @return bool Whether the URL is a Google Drive URL
     */
    public function isGoogleDriveUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        return ($host && (strpos($host, 'drive.google.com') !== false || strpos($host, 'docs.google.com') !== false));
    }

    /**
     * Extract file ID from Google Drive URL
     *
     * @param string $url The Google Drive URL
     * @return string|null The file ID or null if not found
     */
    public function extractFileIdFromGoogleDriveUrl(string $url): ?string
    {
        $googleDriveService = new GoogleDriveService();
        return $googleDriveService->extractGoogleDriveFileId($url);
    }

    /**
     * Validate Google Drive URL
     *
     * @param string $url The Google Drive URL
     * @param array|null $googleDriveSettings Google Drive settings for authentication
     * @return bool Whether the URL is valid and accessible
     */
    private function validateGoogleDriveUrl(string $url, ?array $googleDriveSettings = null): bool
    {
        try {
            $googleDriveService = new GoogleDriveService();
            return $googleDriveService->validateGoogleDriveUrl($url);
        } catch (\Exception $e) {
            Log::error("Error validating Google Drive URL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file information from URL using HEAD request
     *
     * @param string $url The URL to check
     * @return array|null File information or null if failed
     */
    private function getFileInfoFromUrl(string $url): ?array
    {
        $headers = $this->getUrlHeaders($url);
        if (!$headers) {
            return null;
        }

        $contentLength = 0;
        $contentType = '';
        $contentDisposition = '';

        foreach ($headers as $header) {
            $header = trim($header);
            if (stripos($header, 'content-length:') === 0) {
                $contentLength = (int)trim(substr($header, 15));
            } elseif (stripos($header, 'content-type:') === 0) {
                $contentType = trim(substr($header, 13));
            } elseif (stripos($header, 'content-disposition:') === 0) {
                $contentDisposition = trim(substr($header, 20));
            }
        }

        // Extract filename from Content-Disposition if available
        $filename = null;
        if ($contentDisposition && preg_match('/filename[^;=\n]*=((?:".*?")|(?:.*))/i', $contentDisposition, $matches)) {
            $filename = trim($matches[1], "\"'");
        }

        return [
            'size' => $contentLength,
            'type' => $contentType,
            'filename' => $filename
        ];
    }

    /**
     * Get headers from URL using HEAD request
     *
     * @param string $url The URL to check
     * @return array|null Headers or null if failed
     */
    private function getUrlHeaders(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; VideoConverterBot/1.0)',
                'follow_location' => 0,  // Don't follow redirects for initial check
            ]
        ]);

        $headers = @get_headers($url, 1, $context);

        // If HEAD request failed, try GET request with early termination
        if ($headers === false) {
            // Some servers might not allow HEAD, so try GET with context
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; VideoConverterBot/1.0)',
                    'follow_location' => 0,
                    'max_redirects' => 0,
                ]
            ]);

            // Get only the headers by using "Range" header to request 0 bytes
            $context2 = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Range: bytes=0-0\r\n",
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; VideoConverterBot/1.0)',
                    'follow_location' => 0,
                ]
            ]);

            $headers = @get_headers($url, 1, $context2);
        }

        return $headers;
    }

    /**
     * Get content type from headers
     *
     * @param array $headers The headers array
     * @return string Content type
     */
    private function getContentTypeFromHeaders(array $headers): string
    {
        foreach ($headers as $header => $value) {
            if (strtolower($header) === 'content-type' || (is_string($header) && stripos($header, 'content-type') !== false)) {
                return is_array($value) ? $value[0] : $value;
            }
        }

        // If not found in associative array format, search in the values
        foreach ($headers as $header) {
            if (is_string($header) && stripos($header, 'content-type:') === 0) {
                return trim(substr($header, 13));
            }
        }

        return '';
    }
}