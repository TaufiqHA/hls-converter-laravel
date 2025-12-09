<?php

namespace App\Models;

use App\Enums\TrafficSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Analytics model for the HLS Video Converter API
 * 
 * @property string $id
 * @property string $videoId
 * @property string|null $userId
 * @property string $sessionId
 * @property string|null $ipAddress
 * @property string|null $userAgent
 * @property array $device
 * @property string|null $country
 * @property string|null $city
 * @property string|null $region
 * @property string|null $referrer
 * @property string $source
 * @property int $watchTime
 * @property float $completionRate
 * @property string|null $quality
 * @property array $events
 * @property bool $isComplete
 * @property bool $liked
 * @property bool $shared
 * @property string|null $startedAt
 * @property string|null $endedAt
 * @property \Carbon\Carbon|null $createdAt
 * @property \Carbon\Carbon|null $updatedAt
 */
class Analytics extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'videoId',
        'userId',
        'sessionId',
        'ipAddress',
        'userAgent',
        'device',
        'country',
        'city',
        'region',
        'referrer',
        'source',
        'watchTime',
        'completionRate',
        'quality',
        'events',
        'isComplete',
        'liked',
        'shared',
        'startedAt',
        'endedAt',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'videoId' => 'string',
        'userId' => 'string',
        'sessionId' => 'string',
        'ipAddress' => 'string', // PostgreSQL inet type would be handled as string
        'userAgent' => 'string',
        'device' => 'array', // JSONB
        'country' => 'string',
        'city' => 'string',
        'region' => 'string',
        'referrer' => 'string',
        'source' => TrafficSource::class,
        'watchTime' => 'integer',
        'completionRate' => 'float',
        'quality' => 'string',
        'events' => 'array', // JSONB
        'isComplete' => 'boolean',
        'liked' => 'boolean',
        'shared' => 'boolean',
        'startedAt' => 'date',
        'endedAt' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'videoId', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }
}