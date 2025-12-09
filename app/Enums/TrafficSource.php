<?php

namespace App\Enums;

enum TrafficSource: string
{
    case DIRECT = 'direct';
    case EMBED = 'embed';
    case SOCIAL = 'social';
    case SEARCH = 'search';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::DIRECT => 'Direct',
            self::EMBED => 'Embed',
            self::SOCIAL => 'Social',
            self::SEARCH => 'Search',
            self::OTHER => 'Other',
        };
    }
}