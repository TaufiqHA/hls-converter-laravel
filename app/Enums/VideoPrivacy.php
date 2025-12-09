<?php

namespace App\Enums;

enum VideoPrivacy: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
    case UNLISTED = 'unlisted';
    case PASSWORD = 'password';

    public function label(): string
    {
        return match($this) {
            self::PUBLIC => 'Public',
            self::PRIVATE => 'Private',
            self::UNLISTED => 'Unlisted',
            self::PASSWORD => 'Password Protected',
        };
    }
}