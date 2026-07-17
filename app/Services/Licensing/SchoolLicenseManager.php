<?php

namespace App\Services\Licensing;

use App\Models\School;
use App\Models\SchoolLicense;
use App\Models\SchoolLicenseEvent;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SchoolLicenseManager
{
    public function assign(
        School $school,
        SubscriptionPlan $plan,
        array $data,
        int $actorId,
    ): SchoolLicense {
        return DB::transaction(function () use (
            $school,
            $plan,
            $data,
            $actorId,
        ): SchoolLicense {
            $current = SchoolLicense::query()
                ->where('school_id', $school->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($current !== null) {
                $current->update([
                    'is_current' => false,
                    'updated_by' => $actorId,
                ]);
            }

            $startsAt = Carbon::parse($data['starts_at'])->startOfDay();

            if ($data['status'] === 'trial') {
                $trialEndsAt = $startsAt
                    ->copy()
                    ->addDays((int) $data['trial_days']);

                $expiresAt = $trialEndsAt;
            } else {
                $trialEndsAt = null;
                $expiresAt = $this->expiration(
                    $startsAt,
                    $data['billing_cycle'],
                    $data['expires_at'] ?? null,
                );
            }

            $license = SchoolLicense::query()->create([
                'school_id' => $school->id,
                'subscription_plan_id' => $plan->id,
                'status' => $data['status'],
                'billing_cycle' => $data['billing_cycle'],
                'starts_at' => $startsAt->toDateString(),
                'expires_at' => $expiresAt?->toDateString(),
                'trial_ends_at' => $trialEndsAt?->toDateString(),
                'grace_ends_at' => null,
                'cancelled_at' => null,

                'student_limit' => array_key_exists(
                    'student_limit',
                    $data
                ) ? $data['student_limit'] : $plan->student_limit,

                'device_limit' => array_key_exists(
                    'device_limit',
                    $data
                ) ? $data['device_limit'] : $plan->device_limit,

                'staff_limit' => array_key_exists(
                    'staff_limit',
                    $data
                ) ? $data['staff_limit'] : $plan->staff_limit,

                'campus_limit' => array_key_exists(
                    'campus_limit',
                    $data
                ) ? $data['campus_limit'] : $plan->campus_limit,

                'list_monthly_price' => $plan->monthly_price,
                'list_annual_price' => $plan->annual_price,
                'contract_price' => $this->contractPrice($plan, $data),
                'currency' => $plan->currency,
                'features_snapshot' => $this->featuresSnapshot($plan),
                'auto_renew' => (bool) ($data['auto_renew'] ?? false),
                'is_current' => true,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->event(
                school: $school,
                license: $license,
                eventType: $current ? 'plan_changed' : 'created',
                previousStatus: $current?->status,
                newStatus: $license->status,
                actorId: $actorId,
                metadata: [
                    'previous_license_id' => $current?->id,
                    'previous_plan_id' => $current?->subscription_plan_id,
                    'new_plan_id' => $plan->id,
                    'billing_cycle' => $license->billing_cycle,
                    'contract_price' => $license->contract_price,
                    'starts_at' => $license->starts_at?->toDateString(),
                    'expires_at' => $license->expires_at?->toDateString(),
                ],
            );

            return $license;
        });
    }

    public function renew(
        School $school,
        array $data,
        int $actorId,
    ): SchoolLicense {
        return DB::transaction(function () use (
            $school,
            $data,
            $actorId,
        ): SchoolLicense {
            $current = $this->currentForUpdate($school);
            $plan = $current->plan()->with('features')->first();

            if ($plan === null) {
                throw new RuntimeException(
                    'El plan de la licencia actual ya no existe.'
                );
            }

            $current->update([
                'is_current' => false,
                'updated_by' => $actorId,
            ]);

            $startsAt = $data['starts_at']
                ? Carbon::parse($data['starts_at'])->startOfDay()
                : ($current->expires_at?->copy()->addDay()->startOfDay()
                    ?? now()->startOfDay());

            $expiresAt = $this->expiration(
                $startsAt,
                $data['billing_cycle'],
                $data['expires_at'] ?? null,
            );

            $license = SchoolLicense::query()->create([
                'school_id' => $school->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $data['billing_cycle'],
                'starts_at' => $startsAt->toDateString(),
                'expires_at' => $expiresAt?->toDateString(),
                'trial_ends_at' => null,
                'grace_ends_at' => null,
                'cancelled_at' => null,

                'student_limit' => $current->student_limit,
                'device_limit' => $current->device_limit,
                'staff_limit' => $current->staff_limit,
                'campus_limit' => $current->campus_limit,

                'list_monthly_price' => $plan->monthly_price,
                'list_annual_price' => $plan->annual_price,
                'contract_price' => $data['contract_price'],
                'currency' => $current->currency ?: $plan->currency,
                'features_snapshot' => $current->features_snapshot
                    ?: $this->featuresSnapshot($plan),
                'auto_renew' => (bool) ($data['auto_renew'] ?? false),
                'is_current' => true,
                'notes' => $data['notes'] ?? $current->notes,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->event(
                school: $school,
                license: $license,
                eventType: 'renewed',
                previousStatus: $current->status,
                newStatus: 'active',
                actorId: $actorId,
                metadata: [
                    'previous_license_id' => $current->id,
                    'billing_cycle' => $license->billing_cycle,
                    'contract_price' => $license->contract_price,
                    'starts_at' => $license->starts_at?->toDateString(),
                    'expires_at' => $license->expires_at?->toDateString(),
                ],
            );

            return $license;
        });
    }

    public function extendTrial(
        School $school,
        int $days,
        int $actorId,
        ?string $reason,
    ): SchoolLicense {
        return DB::transaction(function () use (
            $school,
            $days,
            $actorId,
            $reason,
        ): SchoolLicense {
            $license = $this->currentForUpdate($school);

            if ($license->status !== 'trial') {
                throw new RuntimeException(
                    'La licencia actual no está en periodo de prueba.'
                );
            }

            $previousEnd = $license->trial_ends_at
                ?? $license->expires_at
                ?? now();

            $newEnd = Carbon::parse($previousEnd)
                ->startOfDay()
                ->addDays($days);

            $license->update([
                'trial_ends_at' => $newEnd->toDateString(),
                'expires_at' => $newEnd->toDateString(),
                'updated_by' => $actorId,
            ]);

            $this->event(
                school: $school,
                license: $license,
                eventType: 'trial_extended',
                previousStatus: 'trial',
                newStatus: 'trial',
                actorId: $actorId,
                metadata: [
                    'days' => $days,
                    'previous_end' => Carbon::parse($previousEnd)
                        ->toDateString(),
                    'new_end' => $newEnd->toDateString(),
                    'reason' => $reason,
                ],
            );

            return $license;
        });
    }

    public function updateLimits(
        School $school,
        array $limits,
        int $actorId,
    ): SchoolLicense {
        return DB::transaction(function () use (
            $school,
            $limits,
            $actorId,
        ): SchoolLicense {
            $license = $this->currentForUpdate($school);

            $previous = [
                'student_limit' => $license->student_limit,
                'device_limit' => $license->device_limit,
                'staff_limit' => $license->staff_limit,
                'campus_limit' => $license->campus_limit,
            ];

            $license->update([
                ...$limits,
                'updated_by' => $actorId,
            ]);

            $this->event(
                school: $school,
                license: $license,
                eventType: 'limits_changed',
                previousStatus: $license->status,
                newStatus: $license->status,
                actorId: $actorId,
                metadata: [
                    'previous' => $previous,
                    'new' => $limits,
                ],
            );

            return $license;
        });
    }

    public function changeStatus(
        School $school,
        string $status,
        string $eventType,
        int $actorId,
        ?string $reason = null,
    ): SchoolLicense {
        return DB::transaction(function () use (
            $school,
            $status,
            $eventType,
            $actorId,
            $reason,
        ): SchoolLicense {
            $license = $this->currentForUpdate($school);
            $previous = $license->status;

            $changes = [
                'status' => $status,
                'updated_by' => $actorId,
            ];

            if ($status === 'cancelled') {
                $changes['cancelled_at'] = now();
                $changes['auto_renew'] = false;
            }

            $license->update($changes);

            $this->event(
                school: $school,
                license: $license,
                eventType: $eventType,
                previousStatus: $previous,
                newStatus: $status,
                actorId: $actorId,
                metadata: ['reason' => $reason],
            );

            return $license;
        });
    }

    public function reactivate(
        School $school,
        int $actorId,
        ?string $reason,
    ): SchoolLicense {
        $license = $this->currentForUpdate($school);

        $status = $license->trial_ends_at !== null
            && $license->trial_ends_at->endOfDay()->gte(now())
                ? 'trial'
                : 'active';

        return $this->changeStatus(
            $school,
            $status,
            'reactivated',
            $actorId,
            $reason,
        );
    }

    private function expiration(
        Carbon $startsAt,
        string $billingCycle,
        ?string $customExpiresAt,
    ): ?Carbon {
        return match ($billingCycle) {
            'monthly' => $startsAt->copy()->addMonth()->subDay(),
            'annual' => $startsAt->copy()->addYear()->subDay(),
            'custom' => $customExpiresAt
                ? Carbon::parse($customExpiresAt)->startOfDay()
                : null,
            'trial' => null,
            default => throw new RuntimeException(
                'Ciclo de facturación inválido.'
            ),
        };
    }

    private function contractPrice(
        SubscriptionPlan $plan,
        array $data,
    ): mixed {
        if ($data['contract_price'] !== null) {
            return $data['contract_price'];
        }

        return match ($data['billing_cycle']) {
            'monthly' => $plan->monthly_price,
            'annual' => $plan->annual_price,
            'trial' => 0,
            default => null,
        };
    }

    private function featuresSnapshot(SubscriptionPlan $plan): array
    {
        $plan->loadMissing('features');

        return $plan->features
            ->mapWithKeys(fn ($feature): array => [
                $feature->feature_key => (bool) $feature->is_enabled,
            ])
            ->all();
    }

    private function currentForUpdate(School $school): SchoolLicense
    {
        $license = SchoolLicense::query()
            ->where('school_id', $school->id)
            ->where('is_current', true)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if ($license === null) {
            throw new RuntimeException(
                'La escuela no tiene una licencia actual.'
            );
        }

        return $license;
    }

    private function event(
        School $school,
        SchoolLicense $license,
        string $eventType,
        ?string $previousStatus,
        ?string $newStatus,
        int $actorId,
        array $metadata = [],
    ): void {
        SchoolLicenseEvent::query()->create([
            'school_id' => $school->id,
            'school_license_id' => $license->id,
            'event_type' => $eventType,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'metadata_json' => $metadata,
            'performed_by' => $actorId,
            'created_at' => now(),
        ]);
    }
}
