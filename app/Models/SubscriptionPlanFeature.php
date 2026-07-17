<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanFeature extends Model
{
    protected $fillable = [
        'subscription_plan_id',
        'feature_key',
        'is_enabled',
        'configuration_json',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'configuration_json' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
