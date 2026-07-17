<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeviceToken extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'installation_uuid',
        'fcm_token',
        'token_hash',
        'platform',
        'app_key',
        'app_flavor',
        'app_version_name',
        'app_version_code',
        'device_name',
        'os_version',
        'locale',
        'timezone',
        'notifications_enabled',
        'is_active',
        'last_registered_at',
        'last_seen_at',
        'last_success_at',
        'last_error_at',
        'last_error_code',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'fcm_token' => 'encrypted',
            'notifications_enabled' => 'boolean',
            'is_active' => 'boolean',
            'app_version_code' => 'integer',
            'last_registered_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
