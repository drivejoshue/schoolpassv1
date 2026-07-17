<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportImpersonation extends Model
{
    protected $fillable = [
        'sysadmin_user_id',
        'school_id',
        'target_user_id',
        'started_at',
        'expires_at',
        'ended_at',
        'ended_reason',
        'ip_address',
        'ended_ip_address',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function sysadmin(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'sysadmin_user_id'
        );
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(
            School::class
        );
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'target_user_id'
        );
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->ended_at === null
            && ! $this->isExpired();
    }
}