<?php

namespace App\Enums;

enum UploadType: string
{
    case DIRECT = 'direct';
    case REMOTE = 'remote';
    case CHUNKED = 'chunked';
    case GOOGLEDRIVE = 'googledrive';

    public function label(): string
    {
        return match($this) {
            self::DIRECT => 'Direct',
            self::REMOTE => 'Remote',
            self::CHUNKED => 'Chunked',
            self::GOOGLEDRIVE => 'Google Drive',
        };
    }
}