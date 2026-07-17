<?php

namespace App\Services\Licensing;

use App\Models\SchoolLicense;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SchoolLicenseService
{
    private const STAFF_ROLES = [
        'school_admin',
        'director',
        'prefect',
    ];

    public function __construct(
        private readonly SchoolFeatureResolver $featureResolver,
    ) {
    }

    public function current(int $schoolId): ?SchoolLicense
    {
        return SchoolLicense::query()
            ->with('plan')
            ->where('school_id', $schoolId)
            ->current()
            ->latest('id')
            ->first();
    }

    public function isActive(int $schoolId): bool
    {
        $schoolStatus = DB::table('schools')
            ->where('id', $schoolId)
            ->value('status');

        if ($schoolStatus !== 'active') {
            return false;
        }

        $license = $this->current($schoolId);

        return $license !== null && $this->isLicenseUsable($license);
    }

    public function isInTrial(int $schoolId): bool
    {
        $license = $this->current($schoolId);

        return $license !== null
            && $license->status === 'trial'
            && $this->dateHasNotPassed($license->trial_ends_at ?? $license->expires_at);
    }

    public function isExpired(int $schoolId): bool
    {
        $license = $this->current($schoolId);

        if ($license === null) {
            return true;
        }

        return ! $this->isLicenseUsable($license);
    }

    public function hasFeature(int $schoolId, string $featureKey): bool
    {
        return $this->isActive($schoolId)
            && $this->featureResolver->enabled($schoolId, $featureKey);
    }

    public function canCreateStudent(int $schoolId): bool
    {
        return $this->canCreateResource($schoolId, 'students');
    }

    public function canCreateDevice(int $schoolId): bool
    {
        return $this->canCreateResource($schoolId, 'devices');
    }

    public function canCreateStaffUser(int $schoolId): bool
    {
        return $this->canCreateResource($schoolId, 'staff');
    }

    public function canCreateCampus(int $schoolId): bool
    {
        return $this->canCreateResource($schoolId, 'campuses');
    }

    public function usageSummary(int $schoolId): array
    {
        $license = $this->current($schoolId);

        $usage = [
            'students' => (int) DB::table('students')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->count(),

            'devices' => (int) DB::table('access_devices')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->count(),

            'staff' => (int) DB::table('users')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->whereIn('role', self::STAFF_ROLES)
                ->count(),

            'campuses' => (int) DB::table('campuses')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->count(),
        ];

        $limits = [
            'students' => $license?->student_limit,
            'devices' => $license?->device_limit,
            'staff' => $license?->staff_limit,
            'campuses' => $license?->campus_limit,
        ];

        $result = [];

        foreach ($usage as $resource => $used) {
            $limit = $limits[$resource];
            $percent = $limit === null || $limit === 0
                ? null
                : round(($used / $limit) * 100, 1);

            $result[$resource] = [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $limit === null ? null : max(0, $limit - $used),
                'percent' => $percent,
                'level' => $this->usageLevel($percent),
                'can_create' => $this->isActive($schoolId)
                    && ($limit === null || $used < $limit),
            ];
        }

        return $result;
    }

    private function canCreateResource(int $schoolId, string $resource): bool
    {
        $summary = $this->usageSummary($schoolId);

        return (bool) ($summary[$resource]['can_create'] ?? false);
    }

    private function isLicenseUsable(SchoolLicense $license): bool
    {
        if (! $license->is_current) {
            return false;
        }

        $today = now()->startOfDay();

        if ($license->starts_at !== null && $license->starts_at->startOfDay()->gt($today)) {
            return false;
        }

        return match ($license->status) {
            'trial' => $this->dateHasNotPassed(
                $license->trial_ends_at ?? $license->expires_at
            ),
            'active' => $this->dateHasNotPassed($license->expires_at),
            'grace' => $this->dateHasNotPassed(
                $license->grace_ends_at ?? $license->expires_at
            ),
            default => false,
        };
    }

    private function dateHasNotPassed(?CarbonInterface $date): bool
    {
        return $date === null || $date->endOfDay()->gte(now());
    }

    private function usageLevel(?float $percent): string
    {
        if ($percent === null) {
            return 'unlimited';
        }

        if ($percent >= 100) {
            return 'blocked';
        }

        if ($percent >= 90) {
            return 'critical';
        }

        if ($percent >= 80) {
            return 'warning';
        }

        return 'normal';
    }
}
