<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'monthly_price',
        'annual_price',
        'currency',
        'student_limit',
        'device_limit',
        'staff_limit',
        'campus_limit',
        'support_level',
        'is_custom_pricing',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'annual_price' => 'decimal:2',
            'student_limit' => 'integer',
            'device_limit' => 'integer',
            'staff_limit' => 'integer',
            'campus_limit' => 'integer',
            'is_custom_pricing' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(SubscriptionPlanFeature::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(SchoolLicense::class);
    }
}
