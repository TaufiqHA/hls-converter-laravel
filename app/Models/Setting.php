<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Setting model for the HLS Video Converter API
 * 
 * @property string $id
 * @property string $userId
 * @property array $playerSettings
 * @property array $adsSettings
 * @property array $defaultWatermark
 * @property bool $defaultDownloadEnabled
 * @property array $googleDriveSettings
 * @property array $subtitleSettings
 * @property array $websiteSettings
 * @property array $ffmpegSettings
 * @property array $s3Settings
 * @property array $r2Settings
 * @property array $redisSettings
 * @property array $rateLimitSettings
 * @property array $corsSettings
 * @property array $analyticsSettings
 * @property array $securitySettings
 * @property array $emailSettings
 * @property \Carbon\Carbon|null $createdAt
 * @property \Carbon\Carbon|null $updatedAt
 */
class Setting extends Model
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
        'playerSettings',
        'adsSettings',
        'defaultWatermark',
        'defaultDownloadEnabled',
        'googleDriveSettings',
        'subtitleSettings',
        'websiteSettings',
        'ffmpegSettings',
        's3Settings',
        'r2Settings',
        'redisSettings',
        'rateLimitSettings',
        'corsSettings',
        'analyticsSettings',
        'securitySettings',
        'emailSettings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'userId' => 'string',
        'playerSettings' => 'array', // JSONB
        'adsSettings' => 'array', // JSONB
        'defaultWatermark' => 'array', // JSONB
        'defaultDownloadEnabled' => 'boolean',
        'googleDriveSettings' => 'array', // JSONB
        'subtitleSettings' => 'array', // JSONB
        'websiteSettings' => 'array', // JSONB
        'ffmpegSettings' => 'array', // JSONB
        's3Settings' => 'array', // JSONB
        'r2Settings' => 'array', // JSONB
        'redisSettings' => 'array', // JSONB
        'rateLimitSettings' => 'array', // JSONB
        'corsSettings' => 'array', // JSONB
        'analyticsSettings' => 'array', // JSONB
        'securitySettings' => 'array', // JSONB
        'emailSettings' => 'array', // JSONB
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }
}