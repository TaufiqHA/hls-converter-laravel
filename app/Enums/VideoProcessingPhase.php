<?php

namespace App\Enums;

enum VideoProcessingPhase: string
{
    case PENDING = 'pending';
    case DOWNLOADING = 'downloading';
    case CONVERTING = 'converting';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::DOWNLOADING => 'Downloading',
            self::CONVERTING => 'Converting',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }
}