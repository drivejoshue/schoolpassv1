<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolLicense extends Model
{
    protected $fillable = [
        'school_id',
        'subscription_plan_id',
        'status',
        'billing_cycle',
        'starts_at',
        'expires_at',
        'trial_ends_at',
        'grace_ends_at',
        'cancelled_at',
        'student_limit',
        'device_limit',
        'staff_limit',
        'campus_limit',
        'list_monthly_price',
        'list_annual_price',
        'contract_price',
        'currency',
        'features_snapshot',
        'auto_renew',
        'is_current',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'expires_at' => 'date',
            'trial_ends_at' => 'date',
            'grace_ends_at' => 'date',
            'cancelled_at' => 'datetime',
            'student_limit' => 'integer',
            'device_limit' => 'integer',
            'staff_limit' => 'integer',
            'campus_limit' => 'integer',
            'list_monthly_price' => 'decimal:2',
            'list_annual_price' => 'decimal:2',
            'contract_price' => 'decimal:2',
            'features_snapshot' => 'array',
            'auto_renew' => 'boolean',
            'is_current' => 'boolean',
        ];
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SchoolLicenseEvent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
