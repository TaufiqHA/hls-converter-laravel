<?php

namespace App\Enums;

enum VideoStatus: string
{
    case UPLOADING = 'uploading';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::UPLOADING => 'Uploading',
            self::QUEUED => 'Queued',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }
}