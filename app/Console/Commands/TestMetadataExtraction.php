<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HLSConverterService;
use Illuminate\Support\Facades\Storage;

class TestMetadataExtraction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:metadata-extraction {video?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test video metadata extraction functionality';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing video metadata extraction functionality...');
        
        $hlsConverter = new HLSConverterService();
        
        // If a video path is provided as argument, use it. Otherwise, create a simple test file.
        $videoPath = $this->argument('video');
        
        if (!$videoPath) {
            $this->line('No video file provided, creating a test video...');
            
            // Create a test video file using FFmpeg (if it's available)
            $ffmpegPath = $hlsConverter->getFFmpegPath();
            
            if (!file_exists($ffmpegPath)) {
                $this->error("FFmpeg not found at: {$ffmpegPath}");
                return 1;
            }
            
            $testVideoPath = 'test_videos/test_video.mp4';
            $fullPath = storage_path('app/' . $testVideoPath);
            
            // Create a simple test video using ffmpeg
            $cmd = $ffmpegPath . ' -f lavfi -i testsrc=size=1920x1080:rate=1 -vf hue=s=0 -t 5 -c:v libx264 -pix_fmt yuv420p -c:a aac -y ' . escapeshellarg($fullPath);
            
            $this->info("Creating test video with command: {$cmd}");
            
            $output = [];
            $returnCode = 0;
            exec($cmd . ' 2>&1', $output, $returnCode);
            
            if ($returnCode !== 0) {
                $this->error("Failed to create test video. FFmpeg output: " . implode("\n", $output));
                return 1;
            }
            
            $this->info("Test video created successfully: {$fullPath}");
            $videoPath = $testVideoPath;
        }
        
        // Check if the video file exists in storage
        // Note: If video path was passed as an argument, check if it exists as-is
        // If it's the auto-generated test video, also check that it exists
        if (!Storage::exists($videoPath)) {
            // If we created a test video, check the exact path
            if (!$this->argument('video')) {
                // This is the test video we created, let's use the correct path
                $this->error("Video file does not exist: {$videoPath}");
                return 1;
            } else {
                $this->error("Video file does not exist: {$videoPath}");
                return 1;
            }
        }
        
        $this->line("Extracting metadata from: {$videoPath}");
        
        try {
            $metadata = $hlsConverter->getVideoMetadata($videoPath);
            
            $this->info('Video metadata extracted successfully:');
            $this->line("- Duration: {$metadata['duration']} seconds");
            $this->line("- Resolution: {$metadata['resolution']['width']}x{$metadata['resolution']['height']}");
            $this->line("- File Size: {$metadata['fileSize']} bytes");
            $this->line("- Format: {$metadata['format']}");
            $this->line("- FPS: {$metadata['fps']}");
            
            $this->info('Metadata extraction test completed successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error("Error extracting metadata: " . $e->getMessage());
            return 1;
        }
    }
}