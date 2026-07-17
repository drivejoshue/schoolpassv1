<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileAppReleasePolicy extends Model
{
    protected $fillable = [
        'app_key',
        'package_name',
        'latest_version_code',
        'latest_version_name',
        'minimum_supported_version_code',
        'force_update',
        'title',
        'message',
        'store_url',
        'published_at',
    ];

    protected $casts = [
        'latest_version_code' => 'integer',
        'minimum_supported_version_code' => 'integer',
        'force_update' => 'boolean',
        'published_at' => 'datetime',
    ];
}