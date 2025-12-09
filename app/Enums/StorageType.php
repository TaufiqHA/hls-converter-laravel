<?php

namespace App\Enums;

enum StorageType: string
{
    case LOCAL = 'local';
    case S3 = 's3';
    case MINIO = 'minio';

    public function label(): string
    {
        return match($this) {
            self::LOCAL => 'Local',
            self::S3 => 'S3',
            self::MINIO => 'MinIO',
        };
    }
}