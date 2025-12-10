<?php

namespace App\Models;

// Instead of using the built-in Laravel User model, we'll extend the Authenticatable class
use App\Enums\UserRole;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * User model for the HLS Video Converter API
 *
 * @property string $id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string $role
 * @property int $storageUsed
 * @property int $storageLimit
 * @property bool $isActive
 * @property string|null $apiKey
 * @property string|null $lastLoginAt
 * @property bool $adsDisabled
 * @property \Carbon\Carbon|null $createdAt
 * @property \Carbon\Carbon|null $updatedAt
 */
class User extends Authenticatable
{
    use HasFactory, HasApiTokens;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    public $timestamps = true;

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

    // Temporarily manage timestamps manually since the DB doesn't have proper defaults
    protected $fillable = [
        'id',
        'username',
        'email',
        'password',
        'role',
        'storageUsed',
        'storageLimit',
        'isActive',
        'apiKey',
        'lastLoginAt',
        'adsDisabled',
        'createdAt',
        'updatedAt',
    ];

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

    // /**
    //  * The attributes that are mass assignable.
    //  *
    //  * @var array
    //  */
    // protected $fillable = [
    //     'id',
    //     'username',
    //     'email',
    //     'password',
    //     'role',
    //     'storageUsed',
    //     'storageLimit',
    //     'isActive',
    //     'apiKey',
    //     'lastLoginAt',
    //     'adsDisabled',
    // ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'username' => 'string',
        'email' => 'string',
        'password' => 'string',
        'role' => UserRole::class,
        'storageUsed' => 'integer',
        'storageLimit' => 'integer',
        'isActive' => 'boolean',
        'apiKey' => 'string',
        'lastLoginAt' => 'date',
        'adsDisabled' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    // Relationships
    public function settings(): HasOne
    {
        return $this->hasOne(Setting::class, 'userId', 'id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'userId', 'id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(Analytics::class, 'userId', 'id');
    }
}