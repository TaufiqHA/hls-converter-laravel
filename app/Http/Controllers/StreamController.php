<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Video;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class StreamController extends Controller
{
    /**
     * Handle HLS streaming requests for video files.
     * 
     * GET `/api/stream/:userId/:videoId/*` - Handles all HLS-related file requests
     * Access: Public (with security checks based on video privacy settings)
     * Function: Serves HLS files (M3U8, TS segments, subtitles) from storage
     *
     * Features:
     * - CORS support for cross-domain streaming
     * - Content type handling based on file extension
     * - URL rewriting for M3U8 files to use proxy URLs
     * - S3 integration (placeholder - would require S3-specific implementation)
     * - Security checks based on video privacy settings
     */
    public function streamFile(Request $request, $userId, $videoId, $file)
    {
        // Find the video by ID
        $video = Video::where('id', $videoId)
                     ->where('userId', $userId)
                     ->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        // Check video privacy
        if ($video->privacy === 'private' && $request->user()->id !== $userId) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // For embed requests, check if embed is enabled
        $referer = $request->headers->get('referer');
        if ($referer && !$video->embedEnabled) {
            return response()->json(['error' => 'Embedding is disabled for this video'], 403);
        }

        // Check allowed domains if domain restriction is enabled
        if (!empty($video->allowedDomains) && count($video->allowedDomains) > 0 && !empty($referer)) {
            $refererDomain = parse_url($referer, PHP_URL_HOST);
            if ($refererDomain && !in_array($refererDomain, $video->allowedDomains)) {
                return response()->json(['error' => 'Streaming not allowed from this domain'], 403);
            }
        }

        // Determine the full path based on storage type
        $fullPath = '';
        if ($video->storageType === 's3' && $video->s3Key) {
            // For S3, we would generate a signed URL
            // This is a simplified implementation - in real scenario, you might need to proxy the S3 content
            Log::warning('S3 streaming not fully implemented in this example');
            return response()->json(['error' => 'S3 streaming not implemented'], 501);
        } else {
            // For local storage
            $fullPath = $this->getLocalFilePath($video, $file);

            // If the exact path doesn't exist, try to find the file in common HLS locations
            if (!Storage::disk('public')->exists($fullPath)) {
                $fullPath = $this->findHlsFile($video, $file);
            }
        }

        if (empty($fullPath) || !Storage::disk('public')->exists($fullPath)) {
            Log::info("HLS file not found: {$fullPath}");
            Log::info("Video hlsPath: {$video->hlsPath}");
            return response()->json(['error' => 'File not found'], 404);
        }

        // Determine content type based on file extension
        $contentType = $this->getContentType($file);

        // Read file contents - use public disk as per the exists check
        $content = Storage::disk('public')->get($fullPath);
        
        // If it's an M3U8 file, potentially rewrite URLs to use proxy
        if (pathinfo($file, PATHINFO_EXTENSION) === 'm3u8') {
            $content = $this->rewriteM3U8Urls($content, $userId, $videoId);
        }
        
        // Set CORS headers for streaming
        $response = Response::make($content, 200)
            ->header('Content-Type', $contentType)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->header('Cache-Control', 'public, max-age=300'); // Cache for 5 minutes for better performance

        return $response;
    }

    /**
     * Determine content type based on file extension
     */
    private function getContentType($filename)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        switch (strtolower($extension)) {
            case 'm3u8':
                return 'application/vnd.apple.mpegurl';
            case 'ts':
                return 'video/mp2t';
            case 'mp4':
                return 'video/mp4';
            case 'vtt':
                return 'text/vtt';
            case 'srt':
                return 'application/x-subrip';
            default:
                return 'application/octet-stream';
        }
    }

    /**
     * Get the local file path based on video and requested file
     */
    private function getLocalFilePath($video, $file)
    {
        // Handle different file types based on the path structure
        if (str_starts_with($file, 'subtitles/')) {
            // Subtitle file: /subtitles/{language}.vtt or /subtitles/{language}.srt
            $subtitlePath = $file;
            $fullPath = "{$subtitlePath}";
        } elseif (str_contains($file, '/') && !str_starts_with($file, 'subtitles/')) {
            // Quality-specific file: {quality}/playlist.m3u8 or segment files
            $fullPath = "hls/{$video->id}/{$file}";
        } else {
            // Root-level file: master.m3u8, playlist.m3u8, or segment files
            $fullPath = "hls/{$video->id}/{$file}";
        }

        // Ensure the path doesn't start with / for storage disk
        $fullPath = ltrim($fullPath, '/');

        return $fullPath;
    }

    /**
     * Rewrite M3U8 URLs to use the proxy endpoint
     */
    private function rewriteM3U8Urls($content, $userId, $videoId)
    {
        $appUrl = config('app.url');

        // Replace relative paths in M3U8 files with absolute proxy URLs
        // This handles both .ts segment files and quality-specific playlists
        $content = preg_replace_callback('/^(.*\.ts|.*\.m3u8)$/m', function($matches) use ($userId, $videoId, $appUrl) {
            $filename = trim($matches[0]);
            if (!empty($filename) && !str_starts_with($filename, '#')) {
                return $appUrl . "/api/stream/{$userId}/{$videoId}/{$filename}";
            }
            return $matches[0];
        }, $content);

        // Also handle paths that might be prefixed with relative paths
        $content = preg_replace_callback('/(\/?)([^\/][^\\s\\n]*\.ts|[^\/][^\\s\\n]*\.m3u8)/', function($matches) use ($userId, $videoId, $appUrl) {
            $prefix = $matches[1];
            $filename = $matches[2];
            if (empty($prefix) && !str_starts_with($filename, '#')) {
                return $appUrl . "/api/stream/{$userId}/{$videoId}/{$filename}";
            }
            return $matches[0];
        }, $content);

        return $content;
    }

    /**
     * Find HLS file in various possible locations
     */
    private function findHlsFile($video, $requestedFile)
    {
        // Try different possible locations based on hlsPath from the video record
        $possiblePaths = [];

        // If video has hlsPath defined, try that first
        if ($video->hlsPath) {
            // Remove leading /uploads/ if it exists to get the relative path
            $hlsPath = $video->hlsPath;
            if (str_starts_with($hlsPath, 'uploads/')) {
                $hlsPath = substr($hlsPath, 8); // Remove 'uploads/'
            } elseif (str_starts_with($hlsPath, '/uploads/')) {
                $hlsPath = substr($hlsPath, 9); // Remove '/uploads/'
            }

            $possiblePaths[] = $hlsPath . '/' . $requestedFile;
        }

        // Common HLS path patterns
        $possiblePaths[] = "hls/{$video->id}/{$requestedFile}";
        $possiblePaths[] = "uploads/hls/{$video->id}/{$requestedFile}";
        $possiblePaths[] = "hls/{$video->id}/" . basename($requestedFile);

        foreach ($possiblePaths as $path) {
            if (Storage::disk('public')->exists($path)) {
                return $path;
            }
        }

        // If not found, return the original path as fallback
        return $this->getLocalFilePath($video, $requestedFile);
    }
}