<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles video conversion to HLS format with adaptive bitrate streaming.
 *
 * Key Features:
 * - Converts videos to HLS format with multiple quality variants (1080p, 720p, 480p, 360p)
 * - Supports hardware acceleration (NVIDIA NVENC, Intel QSV, AMD AMF)
 * - Generates master playlist for adaptive streaming
 * - Creates thumbnail grids (2x2 format) or single thumbnails
 * - Uploads HLS files to S3-compatible storage
 * - Supports watermarking and subtitle burning
 * - Parallel encoding for multiple quality variants
 */
class HLSConverterService
{
    protected $ffmpegPath;
    protected $supportedQualities = ['1080p', '720p', '480p', '360p'];
    protected $hardwareAcceleration = ['nvenc', 'qsv', 'amf'];

    public function getFFmpegPath(): string
    {
        return $this->ffmpegPath;
    }

    public function __construct()
    {
        // Get FFmpeg path from settings or use default
        $this->ffmpegPath = config('app.ffmpeg_path', env('FFMPEG_PATH', '/usr/bin/ffmpeg'));
    }

    /**
     * Main conversion method to HLS format
     * 
     * @param array $options Conversion options
     * Options include:
     * - inputPath: Path to input video file
     * - outputDir: Directory where HLS files should be saved
     * - videoId: Video ID for S3 path
     * - userId: User ID for S3 path
     * - qualities: Array of quality variants to generate ['1080p', '720p', ...]
     * - hardwareAccelerator: Hardware accelerator to use ('nvenc', 'qsv', 'amf')
     * - watermark: Watermark settings
     * - subtitles: Subtitle settings
     * - thumbnailInterval: Interval for thumbnail generation (seconds)
     * - statusService: Instance of VideoStatusService for status updates (optional)
     * @return array Result with success status and quality variants
     */
    public function convertToHLS(array $options): array
    {
        $inputPath = $options['inputPath'] ?? null;
        $outputDir = $options['outputDir'] ?? null;
        $videoId = $options['videoId'] ?? null;
        $userId = $options['userId'] ?? null;
        $qualities = $options['qualities'] ?? ['1080p', '720p', '480p'];
        $hardwareAccelerator = $options['hardwareAccelerator'] ?? null;
        $watermark = $options['watermark'] ?? null;
        $subtitles = $options['subtitles'] ?? null;
        $thumbnailInterval = $options['thumbnailInterval'] ?? 10;
        $statusService = $options['statusService'] ?? null; // Optional status service for progress updates

        if (!$inputPath || !$outputDir) {
            throw new \InvalidArgumentException('Input path and output directory are required');
        }

        // Create output directory if it doesn't exist
        if (!Storage::exists($outputDir)) {
            Storage::makeDirectory($outputDir, 0755, true);
        }

        $qualityVariants = [];
        $tempDir = "temp/" . Str::random(20);

        // Create temporary directory for processing
        if (!Storage::exists($tempDir)) {
            Storage::makeDirectory($tempDir);
        }

        // Calculate progress increment for each quality processed
        $progressPerQuality = count($qualities) > 0 ? 75 / count($qualities) : 0; // 75% for conversion, 25% for other processing
        $currentProgress = 0;

        // Generate quality variants in parallel simulation
        foreach ($qualities as $quality) {
            $result = $this->convertSingleQuality([
                'inputPath' => $inputPath,
                'outputDir' => $outputDir,
                'quality' => $quality,
                'hardwareAccelerator' => $hardwareAccelerator,
                'watermark' => $watermark,
                'subtitles' => $subtitles,
                'tempDir' => $tempDir
            ]);

            if ($result['success']) {
                $qualityVariants[] = [
                    'resolution' => $quality,
                    'url' => $result['segmentFile'],
                    'enabled' => $result['enabled'],
                    'bitrate' => $result['bitrate'] ?? 0,
                    'size' => $result['size'] ?? 0
                ];
            } else {
                Log::error("Failed to convert quality {$quality} for video ID {$videoId}: " . ($result['error'] ?? 'Unknown error'));
            }

            // Update progress if status service is provided
            $currentProgress += $progressPerQuality;
            if ($statusService && $videoId) {
                $statusService->updateProgress($videoId, (int)$currentProgress, \App\Enums\VideoProcessingPhase::CONVERTING);
            }
        }

        // Clean up temporary directory
        if (Storage::exists($tempDir)) {
            Storage::deleteDirectory($tempDir);
        }

        // Check if any quality variant was successfully created
        if (empty($qualityVariants)) {
            return [
                'success' => false,
                'error' => 'No quality variants were successfully created',
                'masterPlaylist' => null,
                'qualityVariants' => [],
                'thumbnail' => null,
                'hlsDirectory' => $outputDir
            ];
        }

        // Generate master playlist (takes ~10% of remaining progress)
        $masterPlaylistPath = $outputDir . '/playlist.m3u8';
        $this->generateMasterPlaylist($outputDir, $qualityVariants);

        if ($statusService && $videoId) {
            $statusService->updateProgress($videoId, min(90, (int)($currentProgress + 10)), \App\Enums\VideoProcessingPhase::CONVERTING);
        }

        // Generate thumbnail (takes ~5% of remaining progress)
        $thumbnailPath = $this->generateThumbnail($inputPath, $outputDir, $thumbnailInterval);

        if ($statusService && $videoId) {
            $statusService->updateProgress($videoId, min(95, (int)($currentProgress + 15)), \App\Enums\VideoProcessingPhase::CONVERTING);
        }

        if ($thumbnailPath === null) {
            Log::warning("Thumbnail generation failed for video ID: {$videoId}");
        }

        if ($statusService && $videoId) {
            $statusService->updateProgress($videoId, 100, \App\Enums\VideoProcessingPhase::CONVERTING);
        }

        return [
            'success' => true,
            'masterPlaylist' => $masterPlaylistPath,
            'qualityVariants' => $qualityVariants,
            'thumbnail' => $thumbnailPath,
            'hlsDirectory' => $outputDir,
            'storageType' => 'local',
            's3PublicUrl' => null
        ];
    }

    /**
     * Converts video to single quality variant
     *
     * @param array $options Conversion options for single quality
     * @return array Result with success status and file information
     */
    public function convertSingleQuality(array $options): array
    {
        $inputPath = $options['inputPath'] ?? null;
        $outputDir = $options['outputDir'] ?? null;
        $quality = $options['quality'] ?? '720p';
        $hardwareAccelerator = $options['hardwareAccelerator'] ?? null;
        $watermark = $options['watermark'] ?? null;
        $subtitles = $options['subtitles'] ?? null;
        $tempDir = $options['tempDir'] ?? null;

        if (!$inputPath || !$outputDir) {
            return ['success' => false, 'error' => 'Input path and output directory are required'];
        }

        // Create output directory if it doesn't exist
        if (!Storage::exists($outputDir)) {
            Storage::makeDirectory($outputDir, 0755, true);
        }

        // Determine target resolution and bitrate based on quality
        $resolutionMap = [
            '1080p' => ['width' => 1920, 'height' => 1080, 'bitrate' => '5000k'],
            '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2800k'],
            '480p' => ['width' => 854, 'height' => 480, 'bitrate' => '1400k'],
            '360p' => ['width' => 640, 'height' => 360, 'bitrate' => '800k']
        ];

        if (!isset($resolutionMap[$quality])) {
            return ['success' => false, 'error' => 'Unsupported quality: ' . $quality];
        }

        $target = $resolutionMap[$quality];

        // Build FFmpeg command
        $segmentFile = $outputDir . '/' . strtolower(str_replace('p', '', $quality)) . '.m3u8';

        // Start building the command with input
        $cmd = $this->ffmpegPath . ' -i ' . escapeshellarg(Storage::path($inputPath));

        // Add hardware acceleration if specified
        if ($hardwareAccelerator) {
            switch ($hardwareAccelerator) {
                case 'nvenc':
                    $cmd .= ' -c:v h264_nvenc';
                    break;
                case 'qsv':
                    $cmd .= ' -c:v h264_qsv';
                    break;
                case 'amf':
                    $cmd .= ' -c:v h264_amf';
                    break;
                default:
                    $cmd .= ' -c:v libx264'; // fallback to software encoding
                    break;
            }
        } else {
            $cmd .= ' -c:v libx264'; // Use software encoding by default
        }

        // Add scaling to target resolution
        $cmd .= ' -vf scale=' . $target['width'] . ':' . $target['height'];

        // Add bitrate
        $cmd .= ' -b:v ' . $target['bitrate'];

        // Add audio codec
        $cmd .= ' -c:a aac -b:a 128k';

        // Build the video filter chain
        $videoFilters = [];

        // Add scaling to target resolution
        $videoFilters[] = 'scale=' . $target['width'] . ':' . $target['height'];

        // Add watermark if specified
        if ($watermark && $this->isWatermarkValid($watermark)) {
            $watermarkPath = $watermark['imagePath'] ?? null;
            $position = $watermark['position'] ?? 'bottom-right';

            if ($watermarkPath && Storage::exists($watermarkPath)) {
                // For complex video filter with watermark
                $watermarkCmd = '';

                // Determine watermark position
                switch ($position) {
                    case 'top-left':
                        $watermarkCmd = 'overlay=10:10';
                        break;
                    case 'top-right':
                        $watermarkCmd = 'overlay=main_w-overlay_w-10:10';
                        break;
                    case 'bottom-left':
                        $watermarkCmd = 'overlay=10:main_h-overlay_h-10';
                        break;
                    case 'bottom-right':
                    default:
                        $watermarkCmd = 'overlay=main_w-overlay_w-10:main_h-overlay_h-10';
                        break;
                }

                // Build filter complex for watermark
                $cmd .= ' -i ' . escapeshellarg(Storage::path($watermarkPath));
                $videoFilters[] = "scale2ref=iw*0.15:ih*0.15[wm][vid];[vid][wm]" . $watermarkCmd;
            }
        }

        // Add subtitles if specified
        $hasSubtitles = false;
        if ($subtitles && !empty($subtitles)) {
            foreach ($subtitles as $subtitle) {
                if (isset($subtitle['filePath']) && Storage::exists($subtitle['filePath'])) {
                    $subtitleFile = Storage::path($subtitle['filePath']);
                    // Add subtitles to the filter chain
                    $videoFilters[] = 'subtitles=' . str_replace("'", "'\\\\''", $subtitleFile);
                    $hasSubtitles = true;
                    break; // Only one subtitle file for simplicity
                }
            }
        }

        // Apply the video filter chain if we have filters
        if (!empty($videoFilters)) {
            $cmd .= ' -vf ' . implode(',', $videoFilters);
        }

        // Add HLS specific parameters
        $cmd .= ' -f hls';
        $cmd .= ' -hls_time 10'; // 10 second segments
        $cmd .= ' -hls_playlist_type vod'; // Video on demand playlist
        $cmd .= ' -hls_segment_filename ' . escapeshellarg(Storage::path($outputDir . '/' . strtolower(str_replace('p', '', $quality)) . '_%03d.ts'));

        // Add the output file
        $cmd .= ' ' . escapeshellarg(Storage::path($segmentFile));

        // Execute the command
        $output = [];
        $returnCode = 0;
        $errorOutput = [];

        try {
            exec($cmd . ' 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                $errorOutput = $output;
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'FFmpeg execution failed: ' . $e->getMessage(),
                'command' => $cmd
            ];
        }

        if ($returnCode !== 0) {
            return [
                'success' => false,
                'error' => 'FFmpeg conversion failed with return code: ' . $returnCode . '. Error output: ' . implode("\n", $errorOutput),
                'command' => $cmd
            ];
        }

        // Check if the output file was created
        if (!Storage::exists($segmentFile)) {
            return [
                'success' => false,
                'error' => 'Output HLS playlist file was not created',
                'command' => $cmd
            ];
        }

        // Get the actual file size, with safe exception handling
        try {
            $fileSize = Storage::size($segmentFile) ?: 0;
        } catch (\Exception $e) {
            Log::warning("Could not retrieve file size for {$segmentFile}: " . $e->getMessage());
            $fileSize = 0;
        }

        return [
            'success' => true,
            'segmentFile' => $segmentFile,
            'enabled' => true,
            'bitrate' => $target['bitrate'],
            'size' => $fileSize,
            'resolution' => $quality,
            'command' => $cmd
        ];
    }

    /**
     * Generates master playlist for adaptive streaming
     * 
     * @param string $outputDir Directory where HLS segments are stored
     * @param array $qualities Array of quality variants
     * @return string Path to the master playlist
     */
    public function generateMasterPlaylist(string $outputDir, array $qualities): string
    {
        $playlistContent = "#EXTM3U\n";
        $playlistContent .= "#EXT-X-VERSION:3\n";

        foreach ($qualities as $quality) {
            if ($quality['enabled']) {
                $bandwidthMap = [
                    '1080p' => 5000000,
                    '720p' => 2800000,
                    '480p' => 1400000,
                    '360p' => 800000
                ];

                $bitrate = $bandwidthMap[$quality['resolution']] ?? 2800000;
                $playlistContent .= "#EXT-X-STREAM-INF:BANDWIDTH={$bitrate},RESOLUTION=1920x1080\n";
                $playlistContent .= basename($quality['url']) . "\n";
            }
        }

        $masterPlaylistPath = $outputDir . '/playlist.m3u8';
        Storage::put($masterPlaylistPath, $playlistContent);

        return $masterPlaylistPath;
    }

    /**
     * Generates video thumbnails
     *
     * @param string $inputPath Path to input video file
     * @param string $outputDir Directory where thumbnails should be saved
     * @param int $interval Interval for thumbnail extraction in seconds
     * @return string|null Path to the thumbnail file
     */
    public function generateThumbnail(string $inputPath, string $outputDir, int $interval = 10): ?string
    {
        // Create output directory if it doesn't exist
        if (!Storage::exists($outputDir)) {
            Storage::makeDirectory($outputDir, 0755, true);
        }

        // Extract a thumbnail at 10% of the video duration to get a representative frame
        // First get video duration using getVideoMetadata
        $metadata = $this->getVideoMetadata($inputPath);
        $duration = $metadata['duration'] ?? 0;

        // Calculate the time for thumbnail extraction (10% into the video, but cap at 10 seconds max)
        $seekTime = min(max(1, $duration * 0.1), 10);

        // Define the thumbnail file path
        $thumbnailFilename = $outputDir . '/thumbnail_' . time() . '.jpg';

        // Build FFmpeg command to extract a single frame
        $cmd = $this->ffmpegPath . ' -i ' . escapeshellarg(Storage::path($inputPath)) .
               ' -ss ' . number_format($seekTime, 3) .
               ' -vframes 1 ' .
               ' -f image2 ' .
               ' -c:v mjpeg ' .
               ' -q:v 5 ' . // Quality factor (1-31, where 1 is best)
               escapeshellarg(Storage::path($thumbnailFilename));

        // Execute the command
        $output = [];
        $returnCode = 0;

        try {
            exec($cmd . ' 2>&1', $output, $returnCode);
        } catch (\Exception $e) {
            Log::error('Thumbnail generation failed: ' . $e->getMessage());
            return null;
        }

        if ($returnCode !== 0) {
            Log::error('FFmpeg thumbnail extraction failed: ' . implode("\n", $output));
            return null;
        }

        // Check if the thumbnail file was created
        if (!Storage::exists($thumbnailFilename)) {
            Log::error('Thumbnail file was not created: ' . $thumbnailFilename);
            return null;
        }

        return $thumbnailFilename;
    }

    /**
     * Gets video metadata (duration, resolution)
     *
     * @param string $inputPath Path to input video file
     * @return array Video metadata including duration and resolution
     */
    public function getVideoMetadata(string $inputPath): array
    {
        // Use ffprobe to get actual video metadata
        $ffprobePath = str_replace('ffmpeg', 'ffprobe', $this->ffmpegPath);

        $cmd = $ffprobePath . ' -v quiet -print_format json -show_format -show_streams ' . escapeshellarg(Storage::path($inputPath));

        $output = [];
        $returnCode = 0;

        try {
            exec($cmd . ' 2>&1', $output, $returnCode);
        } catch (\Exception $e) {
            Log::error('FFprobe execution failed: ' . $e->getMessage());

            // Return fallback values
            // Use a safer approach to get file size that doesn't throw exceptions
            try {
                $fileSize = Storage::size($inputPath) ?: 0;
            } catch (\Exception $e2) {
                Log::warning("Could not retrieve file size for {$inputPath}: " . $e2->getMessage());
                $fileSize = 0;
            }

            return [
                'duration' => 0,
                'resolution' => ['width' => 0, 'height' => 0],
                'fileSize' => $fileSize,
                'size' => '0x0',
                'format' => 'unknown',
                'fps' => 30
            ];
        }

        if ($returnCode !== 0) {
            Log::error('FFprobe command failed: ' . $cmd);

            // Return fallback values
            // Use a safer approach to get file size that doesn't throw exceptions
            try {
                $fileSize = Storage::size($inputPath) ?: 0;
            } catch (\Exception $e) {
                Log::warning("Could not retrieve file size for {$inputPath}: " . $e->getMessage());
                $fileSize = 0;
            }

            return [
                'duration' => 0,
                'resolution' => ['width' => 0, 'height' => 0],
                'fileSize' => $fileSize,
                'size' => '0x0',
                'format' => 'unknown',
                'fps' => 30
            ];
        }

        $jsonOutput = implode("\n", $output);

        if (empty($jsonOutput)) {
            Log::error('FFprobe returned empty output');

            // Return fallback values
            // Use a safer approach to get file size that doesn't throw exceptions
            try {
                $fileSize = Storage::size($inputPath) ?: 0;
            } catch (\Exception $e) {
                Log::warning("Could not retrieve file size for {$inputPath}: " . $e->getMessage());
                $fileSize = 0;
            }

            return [
                'duration' => 0,
                'resolution' => ['width' => 0, 'height' => 0],
                'fileSize' => $fileSize,
                'size' => '0x0',
                'format' => 'unknown',
                'fps' => 30
            ];
        }

        $metadata = json_decode($jsonOutput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('FFprobe JSON decode error: ' . json_last_error_msg());

            // Return fallback values
            // Use a safer approach to get file size that doesn't throw exceptions
            try {
                $fileSize = Storage::size($inputPath) ?: 0;
            } catch (\Exception $e) {
                Log::warning("Could not retrieve file size for {$inputPath}: " . $e->getMessage());
                $fileSize = 0;
            }

            return [
                'duration' => 0,
                'resolution' => ['width' => 0, 'height' => 0],
                'fileSize' => $fileSize,
                'size' => '0x0',
                'format' => 'unknown',
                'fps' => 30
            ];
        }

        // Extract format information
        $format = $metadata['format'] ?? [];
        $streams = $metadata['streams'] ?? [];

        // Find video stream
        $videoStream = null;
        foreach ($streams as $stream) {
            if (isset($stream['codec_type']) && $stream['codec_type'] === 'video') {
                $videoStream = $stream;
                break;
            }
        }

        $duration = floatval($format['duration'] ?? 0);
        $fileSize = intval($format['size'] ?? 0);
        $formatName = $format['format_name'] ?? 'unknown';

        // Extract video stream information
        if ($videoStream) {
            $width = intval($videoStream['width'] ?? 0);
            $height = intval($videoStream['height'] ?? 0);

            // Extract FPS
            $avgFrameRate = $videoStream['avg_frame_rate'] ?? '30/1';
            $fpsParts = explode('/', $avgFrameRate);
            $fps = count($fpsParts) === 2 && $fpsParts[1] != 0 ? intval($fpsParts[0]) / intval($fpsParts[1]) : 30;
            $fps = round($fps, 2);
        } else {
            $width = 0;
            $height = 0;
            $fps = 30;
        }

        return [
            'duration' => $duration, // in seconds
            'resolution' => [
                'width' => $width,
                'height' => $height
            ],
            'fileSize' => $fileSize,
            'size' => $width . 'x' . $height,
            'format' => $formatName,
            'fps' => $fps
        ];
    }

    /**
     * Deletes HLS files (both local and S3)
     * 
     * @param string $hlsPath Path to HLS directory
     * @param array $options Options for deletion
     * @return bool Success status
     */
    public function deleteHLSFiles(string $hlsPath, array $options = []): bool
    {
        $deleteFromS3 = $options['deleteFromS3'] ?? false;
        $videoId = $options['videoId'] ?? null;

        try {
            // Delete local HLS files
            if (Storage::exists($hlsPath)) {
                Storage::deleteDirectory($hlsPath);
            }

            // If S3 deletion is requested, delegate to S3 service
            if ($deleteFromS3 && $videoId) {
                $s3Service = new S3StorageService(true); // Initialize from database config
                $s3Service->deleteHLSFiles($videoId);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Error deleting HLS files from {$hlsPath}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get supported quality options
     * 
     * @return array Array of supported quality options
     */
    public function getSupportedQualities(): array
    {
        return $this->supportedQualities;
    }

    /**
     * Get supported hardware accelerators
     * 
     * @return array Array of supported hardware accelerators
     */
    public function getHardwareAccelerationOptions(): array
    {
        return $this->hardwareAcceleration;
    }

    /**
     * Check if watermark configuration is valid
     *
     * @param array $watermark Watermark configuration
     * @return bool Whether the watermark is valid
     */
    private function isWatermarkValid(array $watermark): bool
    {
        return isset($watermark['imagePath']) &&
               !empty($watermark['imagePath']) &&
               isset($watermark['position']);
    }

    /**
     * Check if hardware acceleration is available by testing basic functionality
     *
     * @param string $accelerator Hardware accelerator to check
     * @return bool Whether the accelerator is supported and available
     */
    public function isHardwareAccelerationAvailable(string $accelerator): bool
    {
        if (!in_array(strtolower($accelerator), $this->hardwareAcceleration)) {
            return false;
        }

        // Test the hardware acceleration by running a simple ffmpeg command
        $testCmd = '';
        switch ($accelerator) {
            case 'nvenc':
                // Test NVENC by checking for CUDA availability
                $testCmd = $this->ffmpegPath . ' -f lavfi -i testsrc=duration=1:size=320x240:rate=1 -c:v h264_nvenc -f null - 2>&1 | head -20';
                break;
            case 'qsv':
                $testCmd = $this->ffmpegPath . ' -h encoder=h264_qsv 2>&1';
                break;
            case 'amf':
                $testCmd = $this->ffmpegPath . ' -h encoder=h264_amf 2>&1';
                break;
            default:
                return false;
        }

        $output = [];
        $returnCode = 0;

        try {
            exec($testCmd, $output, $returnCode);

            // For NVENC, check if CUDA error occurs
            $outputStr = implode("\n", $output);
            if ($accelerator === 'nvenc') {
                Log::info("NVENC test result - Return code: {$returnCode}, Output contains 'libcuda.so.1': " . (strpos($outputStr, 'libcuda.so.1') !== false ? 'YES' : 'NO'));
                // If CUDA library error or "Cannot load libcuda.so.1" appears, hardware is not available
                $isAvailable = $returnCode === 0 && strpos($outputStr, 'libcuda.so.1') === false && strpos($outputStr, 'cuda') === false;
                Log::info("NVENC availability: " . ($isAvailable ? 'AVAILABLE' : 'NOT AVAILABLE'));
                return $isAvailable;
            }

            // Check if the encoder is available in the output
            return $returnCode === 0 && strpos($outputStr, 'encoder') !== false;
        } catch (\Exception $e) {
            Log::error("Error checking hardware acceleration availability: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process watermark addition to video
     *
     * @param string $inputPath Input video path
     * @param string $watermarkPath Watermark image path
     * @param string $outputPath Output path for watermarked video
     * @param array $options Watermark options (position, opacity, size)
     * @return bool Success status
     */
    public function addWatermark(string $inputPath, string $watermarkPath, string $outputPath, array $options = []): bool
    {
        // Check if input files exist
        if (!Storage::exists($inputPath) || !Storage::exists($watermarkPath)) {
            Log::error("Input or watermark file does not exist");
            return false;
        }

        // Create output directory if it doesn't exist
        $outputDir = dirname($outputPath);
        if (!Storage::exists($outputDir)) {
            Storage::makeDirectory($outputDir, 0755, true);
        }

        // Get watermark options
        $position = $options['position'] ?? 'bottom-right';
        $opacity = $options['opacity'] ?? 1.0;
        $size = $options['size'] ?? '15%';

        // Build FFmpeg command for adding watermark
        $cmd = $this->ffmpegPath .
               ' -i ' . escapeshellarg(Storage::path($inputPath)) .
               ' -i ' . escapeshellarg(Storage::path($watermarkPath));

        // Determine overlay position coordinates
        $overlayCmd = '';
        switch ($position) {
            case 'top-left':
                $overlayCmd = 'overlay=10:10';
                break;
            case 'top-right':
                $overlayCmd = 'overlay=main_w-overlay_w-10:10';
                break;
            case 'bottom-left':
                $overlayCmd = 'overlay=10:main_h-overlay_h-10';
                break;
            case 'bottom-right':
            default:
                $overlayCmd = 'overlay=main_w-overlay_w-10:main_h-overlay_h-10';
                break;
        }

        // If opacity is less than 1, we need to add alpha channel manipulation
        if ($opacity < 1.0) {
            $cmd .= ' -filter_complex "[1][0]scale2ref=iw*' . $size . ':ih*' . $size . '[wm][vid];[wm]format=rgba,colorchannelmixer=aa=' . $opacity . '[alpha];[vid][alpha]overlay' . $overlayCmd . '" -c:v libx264 -c:a copy ' . escapeshellarg(Storage::path($outputPath));
        } else {
            // Simple overlay without alpha adjustment
            $cmd .= ' -filter_complex "[1][0]scale2ref=iw*' . $size . ':ih*' . $size . '[wm][vid];[vid][wm]overlay' . $overlayCmd . '" -c:v libx264 -c:a copy ' . escapeshellarg(Storage::path($outputPath));
        }

        // Execute the command
        $output = [];
        $returnCode = 0;

        try {
            exec($cmd . ' 2>&1', $output, $returnCode);
        } catch (\Exception $e) {
            Log::error("FFmpeg watermark addition failed: " . $e->getMessage());
            return false;
        }

        if ($returnCode !== 0) {
            Log::error("FFmpeg watermark addition failed with return code: " . $returnCode . ". Error: " . implode("\n", $output));
            return false;
        }

        // Check if output file was created
        if (!Storage::exists($outputPath)) {
            Log::error("Watermarked output file was not created: " . $outputPath);
            return false;
        }

        return true;
    }

    /**
     * Burn subtitles into video
     *
     * @param string $inputPath Input video path
     * @param string $subtitlePath Subtitle file path
     * @param string $outputPath Output path for subtitled video
     * @return bool Success status
     */
    public function burnSubtitles(string $inputPath, string $subtitlePath, string $outputPath): bool
    {
        // Check if input files exist
        if (!Storage::exists($inputPath) || !Storage::exists($subtitlePath)) {
            Log::error("Input or subtitle file does not exist");
            return false;
        }

        // Create output directory if it doesn't exist
        $outputDir = dirname($outputPath);
        if (!Storage::exists($outputDir)) {
            Storage::makeDirectory($outputDir, 0755, true);
        }

        // Build FFmpeg command to burn subtitles
        $cmd = $this->ffmpegPath .
               ' -i ' . escapeshellarg(Storage::path($inputPath)) .
               ' -vf subtitles=' . escapeshellarg(Storage::path($subtitlePath)) .
               ' -c:a copy ' .
               escapeshellarg(Storage::path($outputPath));

        // Execute the command
        $output = [];
        $returnCode = 0;

        try {
            exec($cmd . ' 2>&1', $output, $returnCode);
        } catch (\Exception $e) {
            Log::error("FFmpeg subtitle burning failed: " . $e->getMessage());
            return false;
        }

        if ($returnCode !== 0) {
            Log::error("FFmpeg subtitle burning failed with return code: " . $returnCode . ". Error: " . implode("\n", $output));
            return false;
        }

        // Check if output file was created
        if (!Storage::exists($outputPath)) {
            Log::error("Subtitled output file was not created: " . $outputPath);
            return false;
        }

        return true;
    }
}
