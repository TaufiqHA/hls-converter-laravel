<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HLSConverterService;
use Illuminate\Support\Facades\Storage;

class TestHLSConversion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:hls-conversion {--ffmpeg-path=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test HLS conversion functionality';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing HLS conversion functionality...');
        
        // Update FFmpeg path if provided as option
        if ($this->option('ffmpeg-path')) {
            config(['app.ffmpeg_path' => $this->option('ffmpeg-path')]);
        }
        
        $hlsConverter = new HLSConverterService();
        
        // Test 1: Check if FFmpeg is available
        $this->line('1. Checking FFmpeg availability...');
        $ffmpegPath = $hlsConverter->getFFmpegPath();

        if (file_exists($ffmpegPath)) {
            $this->info("✓ FFmpeg found at: {$ffmpegPath}");
        } else {
            $this->error("✗ FFmpeg not found at: {$ffmpegPath}");
            return 1;
        }
        
        // Test 2: Check hardware accelerators
        $this->line('2. Checking hardware accelerators...');
        $accelerators = ['nvenc', 'qsv', 'amf'];
        
        foreach ($accelerators as $accelerator) {
            $available = $hlsConverter->isHardwareAccelerationAvailable($accelerator);
            if ($available) {
                $this->info("✓ {$accelerator} accelerator is available");
            } else {
                $this->line("- {$accelerator} accelerator is not available");
            }
        }
        
        // Test 3: Show supported qualities
        $this->line('3. Supported quality options:');
        $qualities = $hlsConverter->getSupportedQualities();
        foreach ($qualities as $quality) {
            $this->info("✓ {$quality}");
        }
        
        $this->info('HLS Conversion functionality test completed successfully!');
        return 0;
    }
}