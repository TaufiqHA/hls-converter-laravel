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
            'convertProgress' => 100
        ]);
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
