<?php

namespace App\Services\Licensing;

use App\Models\SchoolFeature;
use App\Models\SchoolLicense;
use App\Models\SubscriptionPlanFeature;

class SchoolFeatureResolver
{
    public function enabled(int $schoolId, string $featureKey, bool $default = false): bool
    {
        $override = SchoolFeature::query()
            ->where('school_id', $schoolId)
            ->where('feature_key', $featureKey)
            ->effective()
            ->first();

        if ($override !== null) {
            return (bool) $override->is_enabled;
        }

        $license = SchoolLicense::query()
            ->with('plan')
            ->where('school_id', $schoolId)
            ->current()
            ->latest('id')
            ->first();

        if ($license === null) {
            return $default;
        }

        $snapshot = $license->features_snapshot ?? [];

        if (array_key_exists($featureKey, $snapshot)) {
            return filter_var($snapshot[$featureKey], FILTER_VALIDATE_BOOL);
        }

        if ($license->subscription_plan_id === null) {
            return $default;
        }

        $planFeature = SubscriptionPlanFeature::query()
            ->where('subscription_plan_id', $license->subscription_plan_id)
            ->where('feature_key', $featureKey)
            ->first();

        return $planFeature === null
            ? $default
            : (bool) $planFeature->is_enabled;
    }

    public function configuration(int $schoolId, string $featureKey): array
    {
        $override = SchoolFeature::query()
            ->where('school_id', $schoolId)
            ->where('feature_key', $featureKey)
            ->effective()
            ->first();

        if ($override !== null && is_array($override->configuration_json)) {
            return $override->configuration_json;
        }

        $license = SchoolLicense::query()
            ->where('school_id', $schoolId)
            ->current()
            ->latest('id')
            ->first();

        if ($license?->subscription_plan_id === null) {
            return [];
        }

        return SubscriptionPlanFeature::query()
            ->where('subscription_plan_id', $license->subscription_plan_id)
            ->where('feature_key', $featureKey)
            ->value('configuration_json') ?? [];
    }
}
