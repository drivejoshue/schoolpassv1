<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SchoolLicenseStateService
{
    /**
     * Obtiene el estado efectivo de la licencia de una escuela.
     */
    public function forSchoolId(int $schoolId): array
    {
        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->first();

        if ($school === null) {
            return $this->blockedState(
                schoolId: $schoolId,
                status: 'school_not_found',
                code: 'SCHOOL_NOT_FOUND',
                message: 'La escuela no existe.'
            );
        }

        if ($school->status === 'suspended') {
            return $this->blockedState(
                schoolId: $schoolId,
                status: 'suspended',
                code: 'SCHOOL_SUSPENDED',
                message: 'La escuela está suspendida.'
            );
        }

        if ($school->status === 'cancelled') {
            return $this->blockedState(
                schoolId: $schoolId,
                status: 'cancelled',
                code: 'SCHOOL_CANCELLED',
                message: 'La escuela está cancelada.'
            );
        }

        if ($school->status !== 'active') {
            return $this->blockedState(
                schoolId: $schoolId,
                status: (string) $school->status,
                code: 'SCHOOL_INACTIVE',
                message: 'La escuela no está activa.'
            );
        }

        $license = DB::table('school_licenses as licenses')
            ->leftJoin(
                'subscription_plans as plans',
                'plans.id',
                '=',
                'licenses.subscription_plan_id'
            )
            ->where('licenses.school_id', $schoolId)
            ->where('licenses.is_current', true)
            ->latest('licenses.id')
            ->select([
                'licenses.*',
                'plans.code as plan_code',
                'plans.name as plan_name',
            ])
            ->first();

        if ($license === null) {
            return $this->blockedState(
                schoolId: $schoolId,
                status: 'no_license',
                code: 'LICENSE_NOT_FOUND',
                message: 'La escuela no tiene una licencia vigente.'
            );
        }

        $today = CarbonImmutable::today();

        $startsAt = $this->date($license->starts_at);
        $expiresAt = $this->date($license->expires_at);
        $trialEndsAt = $this->date($license->trial_ends_at);
        $graceEndsAt = $this->date($license->grace_ends_at);

        $storedStatus = (string) $license->status;

        $effectiveStatus = $this->resolveEffectiveStatus(
            storedStatus: $storedStatus,
            today: $today,
            expiresAt: $expiresAt,
            trialEndsAt: $trialEndsAt,
            graceEndsAt: $graceEndsAt
        );

        /*
         * Una licencia activa vencida puede entrar virtualmente
         * al periodo de gracia aunque todavía no haya corrido
         * el comando nocturno.
         */
        if (
            $storedStatus === 'active'
            && $effectiveStatus === 'grace'
            && $graceEndsAt === null
            && $expiresAt !== null
        ) {
            $graceEndsAt = $expiresAt->addDays(
                $this->graceDays()
            );
        }

        $referenceDate = match ($effectiveStatus) {
            'trial' => $trialEndsAt ?? $expiresAt,
            'active' => $expiresAt,
            'grace' => $graceEndsAt,
            default => $expiresAt
                ?? $trialEndsAt
                ?? $graceEndsAt,
        };

        $daysRemaining = $this->daysRemaining(
            today: $today,
            referenceDate: $referenceDate
        );

        $warningLevel = $this->warningLevel(
            status: $effectiveStatus,
            daysRemaining: $daysRemaining
        );

        $accessAllowed = in_array(
            $effectiveStatus,
            [
                'trial',
                'active',
                'grace',
            ],
            true
        );

        return [
            'school_id' => $schoolId,
            'license_id' => (int) $license->id,

            'status' => $effectiveStatus,
            'stored_status' => $storedStatus,
            'code' => $this->statusCode(
                $effectiveStatus
            ),

            'plan_code' => $license->plan_code,
            'plan_name' => $license->plan_name,

            'billing_cycle' => $license->billing_cycle,

            'starts_at' => $startsAt?->toDateString(),
            'expires_at' => $expiresAt?->toDateString(),
            'trial_ends_at' => $trialEndsAt?->toDateString(),
            'grace_ends_at' => $graceEndsAt?->toDateString(),

            'days_remaining' => $daysRemaining,

            'warning_level' => $warningLevel,
            'show_warning' => $warningLevel !== 'none',

            'show_modal' => in_array(
                $warningLevel,
                [
                    'warning',
                    'critical',
                    'grace',
                    'expired',
                    'suspended',
                    'cancelled',
                    'no_license',
                ],
                true
            ),

            'access_allowed' => $accessAllowed,
            'operations_allowed' => $accessAllowed,

            'auto_renew' => (bool) $license->auto_renew,

            'contract_price' => $license->contract_price !== null
                ? (float) $license->contract_price
                : null,

            'currency' => $license->currency ?: 'MXN',

            'limits' => [
                'students' => $license->student_limit !== null
                    ? (int) $license->student_limit
                    : null,

                'devices' => $license->device_limit !== null
                    ? (int) $license->device_limit
                    : null,

                'staff' => $license->staff_limit !== null
                    ? (int) $license->staff_limit
                    : null,

                'campuses' => $license->campus_limit !== null
                    ? (int) $license->campus_limit
                    : null,
            ],

            'message' => $this->message(
                status: $effectiveStatus,
                daysRemaining: $daysRemaining,
                expiresAt: $expiresAt,
                graceEndsAt: $graceEndsAt
            ),

            'renewal_contact' => [
                'email' => $school->support_email
                    ?: config(
                        'schoolpass.license.support_email'
                    ),

                'phone' => $school->contact_phone
                    ?: config(
                        'schoolpass.license.support_phone'
                    ),

                'whatsapp' => $school->whatsapp_number
                    ?: config(
                        'schoolpass.license.support_whatsapp'
                    ),
            ],
        ];
    }

    private function resolveEffectiveStatus(
        string $storedStatus,
        CarbonImmutable $today,
        ?CarbonImmutable $expiresAt,
        ?CarbonImmutable $trialEndsAt,
        ?CarbonImmutable $graceEndsAt,
    ): string {
        if (
            in_array(
                $storedStatus,
                [
                    'suspended',
                    'cancelled',
                    'expired',
                ],
                true
            )
        ) {
            return $storedStatus;
        }

        if ($storedStatus === 'trial') {
            $trialExpiration = $trialEndsAt
                ?? $expiresAt;

            if (
                $trialExpiration !== null
                && $trialExpiration->lt($today)
            ) {
                return 'expired';
            }

            return 'trial';
        }

        if ($storedStatus === 'grace') {
            if (
                $graceEndsAt === null
                || $graceEndsAt->lt($today)
            ) {
                return 'expired';
            }

            return 'grace';
        }

        if ($storedStatus === 'active') {
            if (
                $expiresAt === null
                || $expiresAt->gte($today)
            ) {
                return 'active';
            }

            $effectiveGraceEnd = $graceEndsAt
                ?? $expiresAt->addDays(
                    $this->graceDays()
                );

            if (
                $this->graceDays() > 0
                && $effectiveGraceEnd->gte($today)
            ) {
                return 'grace';
            }

            return 'expired';
        }

        return $storedStatus;
    }

    private function warningLevel(
        string $status,
        ?int $daysRemaining,
    ): string {
        return match ($status) {
            'suspended' => 'suspended',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            'grace' => 'grace',
            'no_license' => 'no_license',

            'trial',
            'active' => $this->warningForDays(
                $daysRemaining
            ),

            default => 'none',
        };
    }

    private function warningForDays(
        ?int $daysRemaining,
    ): string {
        if ($daysRemaining === null) {
            return 'none';
        }

        if ($daysRemaining <= $this->criticalDays()) {
            return 'critical';
        }

        if ($daysRemaining <= $this->modalDays()) {
            return 'warning';
        }

        if ($daysRemaining <= $this->infoDays()) {
            return 'info';
        }

        return 'none';
    }

    private function message(
        string $status,
        ?int $daysRemaining,
        ?CarbonImmutable $expiresAt,
        ?CarbonImmutable $graceEndsAt,
    ): string {
        return match ($status) {
            'trial' => $daysRemaining !== null
                ? "El periodo de prueba termina en {$daysRemaining} días."
                : 'La escuela está en periodo de prueba.',

            'active' => $daysRemaining !== null
                ? "La licencia vence en {$daysRemaining} días."
                : 'La licencia está activa.',

            'grace' => $graceEndsAt !== null
                ? 'La licencia está en periodo de gracia hasta '
                    .$graceEndsAt->format('d/m/Y').'.'
                : 'La licencia está en periodo de gracia.',

            'expired' => $expiresAt !== null
                ? 'La licencia venció el '
                    .$expiresAt->format('d/m/Y').'.'
                : 'La licencia está vencida.',

            'suspended' => 'La licencia está suspendida.',

            'cancelled' => 'La licencia está cancelada.',

            'no_license' => (
                'La escuela no tiene una licencia vigente.'
            ),

            default => (
                'El estado de la licencia requiere revisión.'
            ),
        };
    }

    private function statusCode(
        string $status,
    ): string {
        return match ($status) {
            'active' => 'LICENSE_ACTIVE',
            'trial' => 'LICENSE_TRIAL',
            'grace' => 'LICENSE_GRACE',
            'expired' => 'LICENSE_EXPIRED',
            'suspended' => 'LICENSE_SUSPENDED',
            'cancelled' => 'LICENSE_CANCELLED',
            'no_license' => 'LICENSE_NOT_FOUND',
            default => 'LICENSE_INVALID',
        };
    }

    private function blockedState(
        int $schoolId,
        string $status,
        string $code,
        string $message,
    ): array {
        return [
            'school_id' => $schoolId,
            'license_id' => null,

            'status' => $status,
            'stored_status' => $status,
            'code' => $code,

            'plan_code' => null,
            'plan_name' => null,
            'billing_cycle' => null,

            'starts_at' => null,
            'expires_at' => null,
            'trial_ends_at' => null,
            'grace_ends_at' => null,

            'days_remaining' => null,
            'warning_level' => $status,
            'show_warning' => true,
            'show_modal' => true,

            'access_allowed' => false,
            'operations_allowed' => false,

            'auto_renew' => false,
            'contract_price' => null,
            'currency' => 'MXN',

            'limits' => [
                'students' => null,
                'devices' => null,
                'staff' => null,
                'campuses' => null,
            ],

            'message' => $message,

            'renewal_contact' => [
                'email' => config(
                    'schoolpass.license.support_email'
                ),

                'phone' => config(
                    'schoolpass.license.support_phone'
                ),

                'whatsapp' => config(
                    'schoolpass.license.support_whatsapp'
                ),
            ],
        ];
    }

    private function date(
        mixed $value,
    ): ?CarbonImmutable {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return CarbonImmutable::parse(
            (string) $value
        )->startOfDay();
    }

    private function daysRemaining(
        CarbonImmutable $today,
        ?CarbonImmutable $referenceDate,
    ): ?int {
        if ($referenceDate === null) {
            return null;
        }

        return (int) $today->diffInDays(
            $referenceDate,
            false
        );
    }

    private function graceDays(): int
    {
        return max(
            0,
            (int) config(
                'schoolpass.license.grace_days',
                7
            )
        );
    }

    private function infoDays(): int
    {
        return max(
            1,
            (int) config(
                'schoolpass.license.info_warning_days',
                30
            )
        );
    }

    private function modalDays(): int
    {
        return max(
            1,
            (int) config(
                'schoolpass.license.modal_warning_days',
                15
            )
        );
    }

    private function criticalDays(): int
    {
        return max(
            1,
            (int) config(
                'schoolpass.license.critical_warning_days',
                7
            )
        );
    }
}