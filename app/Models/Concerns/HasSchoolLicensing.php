<?php

namespace App\Models\Concerns;

use App\Models\SchoolFeature;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseEvent;
use App\Models\SchoolSetting;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasSchoolLicensing
{
    public function licenses(): HasMany
    {
        return $this->hasMany(SchoolLicense::class);
    }

    public function currentLicense(): HasOne
    {
        return $this->hasOne(SchoolLicense::class)
            ->where('is_current', true)
            ->latestOfMany('id');
    }

    public function featureOverrides(): HasMany
    {
        return $this->hasMany(SchoolFeature::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(SchoolSetting::class);
    }

    public function licenseEvents(): HasMany
    {
        return $this->hasMany(SchoolLicenseEvent::class);
    }
}