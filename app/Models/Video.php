<?php

namespace App\Models;

use App\Enums\VideoStatus;
use App\Enums\VideoProcessingPhase;
use App\Enums\VideoPrivacy;
use App\Enums\UploadType;
use App\Enums\StorageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Video model for the HLS Video Converter API
 * 
 * @property string $id
 * @property string|null $userId
 * @property bool $isGuestUpload
 * @property string $title
 * @property string|null $description
 * @property array $tags
 * @property string $originalFileName
 * @property string $originalFilePath
 * @property int $originalFileSize
 * @property string|null $hlsPath
 * @property string|null $hlsPlaylistUrl
 * @property array $qualityVariants
 * @property string|null $thumbnailPath
 * @property float $duration
 * @property array $resolution
 * @property int $fps
 * @property array $codec
 * @property string $status
 * @property string $processingPhase
 * @property int $processingProgress
 * @property int $downloadProgress
 * @property int $convertProgress
 * @property string|null $processingStartedAt
 * @property string|null $processingCompletedAt
 * @property array $watermark
 * @property array $subtitles
 * @property string $privacy
 * @property string|null $password
 * @property array $allowedDomains
 * @property bool $downloadEnabled
 * @property bool $embedEnabled
 * @property string|null $embedCode
 * @property int $views
 * @property int $uniqueViews
 * @property int $totalWatchTime
 * @property float $averageWatchTime
 * @property int $likes
 * @property int $dislikes
 * @property string $uploadType
 * @property string|null $remoteUrl
 * @property bool $isChunkedUpload
 * @property string|null $uploadSessionId
 * @property string $storageType
 * @property string|null $s3Key
 * @property string|null $s3Bucket
 * @property string|null $s3PublicUrl
 * @property string|null $errorMessage
 * @property int $retryCount
 * @property bool $isModerated
 * @property string $moderationStatus
 * @property string|null $moderationNotes
 * @property string|null $publishedAt
 * @property \Carbon\Carbon|null $createdAt
 * @property \Carbon\Carbon|null $updatedAt
 */
class Video extends Model
{
    use HasFactory;

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'createdAt';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updatedAt';

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
        'userId',
        'isGuestUpload',
        'title',
        'description',
        'tags',
        'originalFileName',
        'originalFilePath',
        'originalFileSize',
        'hlsPath',
        'hlsPlaylistUrl',
        'qualityVariants',
        'thumbnailPath',
        'duration',
        'resolution',
        'fps',
        'codec',
        'status',
        'processingPhase',
        'processingProgress',
        'downloadProgress',
        'convertProgress',
        'processingStartedAt',
        'processingCompletedAt',
        'watermark',
        'subtitles',
        'privacy',
        'password',
        'allowedDomains',
        'downloadEnabled',
        'embedEnabled',
        'embedCode',
        'views',
        'uniqueViews',
        'totalWatchTime',
        'averageWatchTime',
        'likes',
        'dislikes',
        'uploadType',
        'remoteUrl',
        'isChunkedUpload',
        'uploadSessionId',
        'storageType',
        's3Key',
        's3Bucket',
        's3PublicUrl',
        'errorMessage',
        'retryCount',
        'isModerated',
        'moderationStatus',
        'moderationNotes',
        'publishedAt',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'userId' => 'string',
        'isGuestUpload' => 'boolean',
        'title' => 'string',
        'description' => 'string',
        'tags' => 'array', // PostgreSQL array
        'originalFileName' => 'string',
        'originalFilePath' => 'string',
        'originalFileSize' => 'integer',
        'hlsPath' => 'string',
        'hlsPlaylistUrl' => 'string',
        'qualityVariants' => 'array', // JSONB
        'thumbnailPath' => 'string',
        'duration' => 'float',
        'resolution' => 'array', // JSONB
        'fps' => 'float',
        'codec' => 'array', // JSONB
        'status' => VideoStatus::class,
        'processingPhase' => 'string',
        'processingProgress' => 'integer',
        'downloadProgress' => 'integer',
        'convertProgress' => 'integer',
        'processingStartedAt' => 'date',
        'processingCompletedAt' => 'date',
        'watermark' => 'array', // JSONB
        'subtitles' => 'array', // JSONB
        'privacy' => VideoPrivacy::class,
        'password' => 'string',
        'allowedDomains' => 'array', // PostgreSQL array
        'downloadEnabled' => 'boolean',
        'embedEnabled' => 'boolean',
        'embedCode' => 'string',
        'views' => 'integer',
        'uniqueViews' => 'integer',
        'totalWatchTime' => 'integer',
        'averageWatchTime' => 'float',
        'likes' => 'integer',
        'dislikes' => 'integer',
        'uploadType' => UploadType::class,
        'remoteUrl' => 'string',
        'isChunkedUpload' => 'boolean',
        'uploadSessionId' => 'string',
        'storageType' => StorageType::class,
        's3Key' => 'string',
        's3Bucket' => 'string',
        's3PublicUrl' => 'string',
        'errorMessage' => 'string',
        'retryCount' => 'integer',
        'isModerated' => 'boolean',
        'moderationStatus' => 'string',
        'moderationNotes' => 'string',
        'publishedAt' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the processing phase attribute.
     *
     * @return \App\Enums\VideoProcessingPhase|null
     */
    public function getProcessingPhaseAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        // Try to create enum from value, fallback to a default value (FAILED) if invalid
        return \App\Enums\VideoProcessingPhase::tryFrom($value) ?? \App\Enums\VideoProcessingPhase::FAILED;
    }

    /**
     * Set the processing phase attribute.
     *
     * @param \App\Enums\VideoProcessingPhase|string|null $value
     * @return void
     */
    public function setProcessingPhaseAttribute($value)
    {
        if ($value instanceof \App\Enums\VideoProcessingPhase) {
            $this->attributes['processingPhase'] = $value->value;
        } else {
            $this->attributes['processingPhase'] = $value;
        }
    }


    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(Analytics::class, 'videoId', 'id');
    }
}