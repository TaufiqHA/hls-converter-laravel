<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Video;
use App\Services\VideoStatusService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Number of times the job may be attempted
    public $timeout = 3600; // Job timeout in seconds (1 hour)

    protected $videoId;

    /**
     * Create a new job instance.
     *
     * @param string $videoId The ID of the video to process
     * @return void
     */
    public function __construct(string $videoId)
    {
        $this->videoId = $videoId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $video = Video::find($this->videoId);
        
        if (!$video) {
            Log::error("Video not found for ID: {$this->videoId} in ProcessVideoJob");
            return;
        }

        $statusService = new VideoStatusService();

        try {
            // Start the processing
            $statusService->startProcessing($this->videoId);

            // In a real implementation, this would call the HLS converter service
            // For now, we'll simulate the conversion process
            $this->convertVideoToHls($video, $statusService);

            // Update status to completed
            $statusService->markAsCompleted($this->videoId, [
                'hlsPath' => "/storage/hls/{$this->videoId}",
                'hlsPlaylistUrl' => "/api/stream/{$video->userId}/{$this->videoId}/playlist.m3u8",
                'duration' => rand(60, 600), // Simulated duration
                'resolution' => ['width' => 1920, 'height' => 1080] // Simulated resolution
            ]);

            Log::info("Video {$this->videoId} processing completed successfully");
        } catch (\Exception $e) {
            // Mark as failed if there's an error
            $statusService->markAsFailed($this->videoId, $e->getMessage());
            Log::error("Video {$this->videoId} processing failed: " . $e->getMessage());
            
            // Re-throw the exception to handle retries
            throw $e;
        }
    }

    /**
     * Convert video to HLS format
     *
     * @param Video $video The video model
     * @param VideoStatusService $statusService The status service instance
     * @return void
     */
    private function convertVideoToHls(Video $video, VideoStatusService $statusService): void
    {
        // Check if this is a remote upload and download the file first
        if (in_array($video->uploadType, [\App\Enums\UploadType::REMOTE, \App\Enums\UploadType::GOOGLEDRIVE])) {
            $this->processRemoteUpload($video, $statusService);
        }

        // Initialize the HLS converter service
        $hlsConverter = new \App\Services\HLSConverterService();

        // Get video metadata using FFprobe
        $metadata = $hlsConverter->getVideoMetadata($video->originalFilePath);

        // Update conversion progress
        $statusService->updateProgress($this->videoId, 0, \App\Enums\VideoProcessingPhase::CONVERTING);

        // Check if input file exists before proceeding
        if (!Storage::exists($video->originalFilePath)) {
            throw new \Exception("Input video file does not exist: {$video->originalFilePath}");
        }

        // Update the video record with initial processing info
        $video->update([
            'processingPhase' => \App\Enums\VideoProcessingPhase::CONVERTING,
            'duration' => $metadata['duration'],
            'resolution' => $metadata['resolution'],
            'fps' => $metadata['fps']
        ]);

        $hardwareAccelerator = $this->getHardwareAccelerator();
        Log::info("Video {$this->videoId} using hardware accelerator: " . ($hardwareAccelerator ?? 'none (software)'));

        // Prepare conversion options
        $options = [
            'inputPath' => $video->originalFilePath,
            'outputDir' => "hls/{$video->id}",
            'userId' => $video->userId,
            'videoId' => $video->id,
            'qualities' => ['1080p', '720p', '480p'], // Default quality variants
            'hardwareAccelerator' => $hardwareAccelerator, // Get accelerator based on system capabilities
            'watermark' => $video->watermark ?? null,
            'subtitles' => $video->subtitles ?? null,
            'thumbnailInterval' => 10,
            'statusService' => $statusService // Pass the status service for progress updates
        ];

        // Perform the actual conversion
        $result = $hlsConverter->convertToHLS($options);

        if (!$result['success']) {
            throw new \Exception('HLS conversion failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        // Update with final results
        $video->update([
            'hlsPath' => $result['hlsDirectory'],
            'hlsPlaylistUrl' => $result['masterPlaylist'],
            'qualityVariants' => $result['qualityVariants'],
            'thumbnailPath' => $result['thumbnail'],
            'convertProgress' => 100,
            'storageType' => $result['storageType'] ?? 'local',
            's3PublicUrl' => $result['s3PublicUrl'] ?? null
        ]);
    }

    /**
     * Process remote upload by downloading the file from the remote URL or Google Drive
     *
     * @param Video $video The video model
     * @param VideoStatusService $statusService The status service instance
     * @return void
     * @throws \Exception
     */
    private function processRemoteUpload(Video $video, VideoStatusService $statusService): void
    {
        // Update progress to downloading phase
        $statusService->updateProgress($this->videoId, 0, \App\Enums\VideoProcessingPhase::DOWNLOADING);

        try {
            $remoteUploader = new \App\Services\RemoteUploaderService();

            if ($video->uploadType === \App\Enums\UploadType::GOOGLEDRIVE) {
                // Use the dedicated GoogleDriveService for downloading
                $googleDriveService = new \App\Services\GoogleDriveService();
                $result = $googleDriveService->downloadFromGoogleDrive($video, $video->remoteUrl);
            } else {
                // Use RemoteUploaderService for regular remote URLs
                $onProgress = function($progress, $downloaded, $total) use ($statusService) {
                    // Calculate overall progress (0-100), with downloading maxing at 90% before conversion begins
                    $downloadProgress = min(90, intval($progress * 0.9)); // 90% for download phase
                    $statusService->updateProgress($this->videoId, $downloadProgress, \App\Enums\VideoProcessingPhase::DOWNLOADING);
                };

                $result = $remoteUploader->downloadFromUrl($video->remoteUrl, $video->userId, $onProgress);

                // After successful download, update video record with file path and size
                $video->update([
                    'originalFilePath' => $result['filePath'],
                    'originalFileSize' => $result['fileSize']
                ]);

                // Update user's storage used with the actual file size
                $user = $video->user;
                $user->increment('storageUsed', $result['fileSize']);
            }

            // Update download progress to 100%
            $statusService->updateProgress($this->videoId, 100, \App\Enums\VideoProcessingPhase::DOWNLOADING);

            Log::info("Successfully downloaded remote file for video ID {$this->videoId} to {$result['filePath']}, Size: {$result['fileSize']} bytes");
        } catch (\Exception $e) {
            Log::error("Failed to download remote file: " . $e->getMessage());
            throw $e;
        }
    }


    /**
     * Get the appropriate hardware accelerator based on system capabilities
     *
     * @return string|null Hardware accelerator name or null if not available
     */
    private function getHardwareAccelerator(): ?string
    {
        // For now, force software encoding to avoid hardware acceleration issues
        // TODO: Re-enable hardware acceleration detection once properly tested
        Log::info("Using software encoding (hardware acceleration disabled)");
        return null; // Return null to use software encoding
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("ProcessVideoJob failed for video {$this->videoId}: " . $exception->getMessage());

        // Update the video status to failed
        $statusService = new VideoStatusService();
        $statusService->markAsFailed($this->videoId, $exception->getMessage());
    }
}

