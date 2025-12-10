<?php

namespace App\Services;

use App\Models\Video;
use App\Models\User;
use App\Jobs\ProcessVideoJob; // This would be a job that handles the actual video processing
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service to handle video status updates when videos are successfully uploaded
 * and need to be processed
 */
class VideoStatusService
{
    /**
     * Handle status update when a video is successfully uploaded
     * This method updates the video status from 'uploading' to 'queued' 
     * and adds it to the processing queue
     *
     * @param string $videoId The ID of the video that was uploaded
     * @return bool Success status
     */
    public function handleSuccessfulUpload(string $videoId): bool
    {
        try {
            $video = Video::find($videoId);
            
            if (!$video) {
                Log::error("Video not found for ID: {$videoId}");
                return false;
            }

            // For direct uploads, download is already complete (100%), but for remote uploads it's 0%
            $downloadProgress = ($video->uploadType === \App\Enums\UploadType::DIRECT) ? 100 : 0;

            // Update the video status to 'queued'
            $video->update([
                'status' => \App\Enums\VideoStatus::QUEUED,
                'processingPhase' => \App\Enums\VideoProcessingPhase::PENDING,
                'processingStartedAt' => null,
                'processingCompletedAt' => null,
                'processingProgress' => 0,
                'downloadProgress' => $downloadProgress,
                'convertProgress' => 0,
                'updatedAt' => now()
            ]);

            // Add video to processing queue
            $this->addToProcessingQueue($video);

            Log::info("Video {$videoId} status updated to queued and added to processing queue");
            return true;
        } catch (\Exception $e) {
            Log::error("Error handling successful upload for video {$videoId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add the video to the processing queue
     *
     * @param Video $video The video model instance
     * @return void
     */
    private function addToProcessingQueue(Video $video): void
    {
        // Dispatch the job to process the video
        ProcessVideoJob::dispatch($video->id);

        Log::info("Video {$video->id} has been added to the processing queue for conversion to HLS format");
    }

    /**
     * Update video processing progress
     *
     * @param string $videoId The ID of the video
     * @param int $progress Processing progress percentage (0-100)
     * @param string|\App\Enums\VideoProcessingPhase $phase Current processing phase
     * @param array $additionalData Additional data to update
     * @return bool Success status
     */
    public function updateProgress(string $videoId, int $progress, $phase = null, array $additionalData = []): bool
    {
        try {
            $video = Video::find($videoId);

            if (!$video) {
                Log::error("Video not found for ID: {$videoId}");
                return false;
            }

            $updateData = [
                'processingProgress' => $progress,
                'updatedAt' => now()
            ];

            if ($phase) {
                // Convert string phase to enum if necessary
                if (is_string($phase)) {
                    $phase = \App\Enums\VideoProcessingPhase::tryFrom($phase);
                }
                $updateData['processingPhase'] = $phase;
            }

            // Merge any additional data
            $updateData = array_merge($updateData, $additionalData);

            $video->update($updateData);

            $logPhase = is_object($phase) ? $phase->value : ($phase ?? 'unknown');
            Log::info("Video {$videoId} progress updated to {$progress}% in phase {$logPhase}");
            return true;
        } catch (\Exception $e) {
            Log::error("Error updating progress for video {$videoId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark video processing as completed
     *
     * @param string $videoId The ID of the video
     * @param array $completionData Additional completion data like HLS path, URL, etc.
     * @return bool Success status
     */
    public function markAsCompleted(string $videoId, array $completionData = []): bool
    {
        try {
            $video = Video::find($videoId);
            
            if (!$video) {
                Log::error("Video not found for ID: {$videoId}");
                return false;
            }

            $updateData = [
                'status' => 'completed',
                'processingPhase' => \App\Enums\VideoProcessingPhase::COMPLETED,
                'processingProgress' => 100,
                'processingCompletedAt' => now(),
                'updatedAt' => now()
            ];

            // Merge completion-specific data like HLS paths
            $updateData = array_merge($updateData, $completionData);

            $video->update($updateData);

            Log::info("Video {$videoId} marked as completed");
            return true;
        } catch (\Exception $e) {
            Log::error("Error marking video {$videoId} as completed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark video processing as failed
     *
     * @param string $videoId The ID of the video
     * @param string $errorMessage Error message to store
     * @param string|\App\Enums\VideoProcessingPhase $phase Phase where the error occurred
     * @return bool Success status
     */
    public function markAsFailed(string $videoId, string $errorMessage, $phase = 'converting'): bool
    {
        try {
            $video = Video::find($videoId);

            if (!$video) {
                Log::error("Video not found for ID: {$videoId}");
                return false;
            }

            // Convert string phase to enum if necessary
            if (is_string($phase)) {
                $phase = \App\Enums\VideoProcessingPhase::tryFrom($phase) ?? \App\Enums\VideoProcessingPhase::FAILED;
            }

            $video->update([
                'status' => \App\Enums\VideoStatus::FAILED,
                'processingPhase' => $phase,
                'errorMessage' => $errorMessage,
                'processingCompletedAt' => now(),
                'updatedAt' => now()
            ]);

            $logPhase = is_object($phase) ? $phase->value : 'unknown';
            Log::info("Video {$videoId} marked as failed in phase {$logPhase} with error: {$errorMessage}");
            return true;
        } catch (\Exception $e) {
            Log::error("Error marking video {$videoId} as failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a video is in a specific status
     *
     * @param string $videoId The ID of the video
     * @param string $status Status to check for
     * @return bool Whether the video is in the specified status
     */
    public function isStatus(string $videoId, string $status): bool
    {
        $video = Video::find($videoId);
        $enumStatus = \App\Enums\VideoStatus::tryFrom($status);
        return $video && $video->status === $enumStatus;
    }

    /**
     * Get current processing status of a video
     *
     * @param string $videoId The ID of the video
     * @return array Status information
     */
    public function getStatus(string $videoId): array
    {
        $video = Video::find($videoId);
        
        if (!$video) {
            return [
                'error' => 'Video not found',
                'status' => null
            ];
        }

        return [
            'id' => $video->id,
            'status' => $video->status,
            'processingPhase' => $video->processingPhase,
            'processingProgress' => $video->processingProgress,
            'downloadProgress' => $video->downloadProgress,
            'convertProgress' => $video->convertProgress,
            'queuePosition' => 0, // This would come from the queue system in a real implementation
            'processingStartedAt' => $video->processingStartedAt,
            'processingCompletedAt' => $video->processingCompletedAt,
            'errorMessage' => $video->errorMessage
        ];
    }

    /**
     * Move video from queued to processing status
     *
     * @param string $videoId The ID of the video
     * @return bool Success status
     */
    public function startProcessing(string $videoId): bool
    {
        try {
            $video = Video::find($videoId);
            
            if (!$video) {
                Log::error("Video not found for ID: {$videoId}");
                return false;
            }

            // Only update if video is in 'queued' status
            if ($video->status !== \App\Enums\VideoStatus::QUEUED) {
                Log::warning("Video {$videoId} is not in queued status, current status: {$video->status->value}");
                return false;
            }

            $video->update([
                'status' => \App\Enums\VideoStatus::PROCESSING,
                'processingPhase' => \App\Enums\VideoProcessingPhase::PENDING, // Could be 'downloading' or 'converting' depending on implementation
                'processingStartedAt' => $video->processingStartedAt ?? now(),
                'updatedAt' => now()
            ]);

            Log::info("Video {$videoId} started processing");
            return true;
        } catch (\Exception $e) {
            Log::error("Error starting processing for video {$videoId}: " . $e->getMessage());
            return false;
        }
    }
}