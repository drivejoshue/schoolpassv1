<?php

namespace App\Http\Controllers\Sysadmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SupportImpersonation;
use App\Models\User;
use App\Services\Auditing\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class SupportImpersonationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function start(
        Request $request,
        School $school,
    ): RedirectResponse {
        if (
            $request->session()->has(
                'support_impersonation'
            )
        ) {
            return back()->with(
                'error',
                'Ya existe una sesión de soporte activa.'
            );
        }

        $sysadmin = $request->user();

        if (
            $sysadmin === null
            || $sysadmin->role !== 'superadmin'
            || $sysadmin->school_id !== null
        ) {
            abort(403);
        }

        $data = $request->validate([
            'target_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where(
                                'school_id',
                                $school->id
                            )
                            ->whereIn(
                                'role',
                                [
                                    'school_admin',
                                    'director',
                                ]
                            )
                            ->where('status', 'active')
                    ),
            ],

            'reason' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],
        ]);

        $target = User::query()
            ->whereKey($data['target_user_id'])
            ->where('school_id', $school->id)
            ->whereIn(
                'role',
                [
                    'school_admin',
                    'director',
                ]
            )
            ->where('status', 'active')
            ->firstOrFail();

        $durationMinutes = max(
            5,
            min(
                240,
                (int) config(
                    'schoolpass.support.impersonation_minutes',
                    60
                )
            )
        );

        try {
            $impersonation = DB::transaction(
                function () use (
                    $request,
                    $school,
                    $sysadmin,
                    $target,
                    $data,
                    $durationMinutes,
                ): SupportImpersonation {
                    $startedAt = now();
                    $expiresAt = now()->addMinutes(
                        $durationMinutes
                    );

                    $impersonation =
                        SupportImpersonation::query()
                            ->create([
                                'sysadmin_user_id' =>
                                    $sysadmin->id,

                                'school_id' =>
                                    $school->id,

                                'target_user_id' =>
                                    $target->id,

                                'started_at' =>
                                    $startedAt,

                                'expires_at' =>
                                    $expiresAt,

                                'ended_at' =>
                                    null,

                                'ended_reason' =>
                                    null,

                                'ip_address' =>
                                    $request->ip(),

                                'ended_ip_address' =>
                                    null,

                                'reason' =>
                                    $data['reason'],
                            ]);

                    $this->auditLogger->record(
                        action:
                            'support_impersonation_started',

                        schoolId:
                            $school->id,

                        actorId:
                            $sysadmin->id,

                        actorType:
                            'superadmin',

                        entityType:
                            User::class,

                        entityId:
                            $target->id,

                        oldValues:
                            [],

                        newValues: [
                            'impersonation_id' =>
                                $impersonation->id,

                            'target_name' =>
                                $target->name,

                            'target_role' =>
                                $target->role,

                            'reason' =>
                                $data['reason'],

                            'started_at' =>
                                $startedAt
                                    ->toIso8601String(),

                            'expires_at' =>
                                $expiresAt
                                    ->toIso8601String(),
                        ],

                        request:
                            $request,
                    );

                    return $impersonation;
                }
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'No fue posible iniciar soporte: '
                .$exception->getMessage()
            );
        }

        Auth::login($target);

        $request->session()->regenerate();

        $request->session()->put(
            'support_impersonation',
            [
                'id' =>
                    $impersonation->id,

                'sysadmin_user_id' =>
                    $sysadmin->id,

                'sysadmin_name' =>
                    $sysadmin->name,

                'school_id' =>
                    $school->id,

                'school_name' =>
                    $school->name,

                'target_user_id' =>
                    $target->id,

                'target_user_name' =>
                    $target->name,

                'started_at' =>
                    $impersonation
                        ->started_at
                        ->toIso8601String(),

                'expires_at' =>
                    $impersonation
                        ->expires_at
                        ->toIso8601String(),

                'return_url' =>
                    route(
                        'sysadmin.schools.show',
                        $school
                    ),
            ]
        );

        return redirect()
            ->route('admin.dashboard')
            ->with(
                'status',
                'Sesión de soporte iniciada para '
                .$school->name.'.'
            );
    }

    public function stop(
        Request $request,
    ): RedirectResponse {
        $context = $request->session()->get(
            'support_impersonation'
        );

        if (! is_array($context)) {
            abort(403);
        }

        $impersonation =
            SupportImpersonation::query()
                ->whereKey($context['id'])
                ->whereNull('ended_at')
                ->first();

        if ($impersonation === null) {
            throw new RuntimeException(
                'La sesión de soporte ya no está activa.'
            );
        }

        $sysadmin = User::query()
            ->whereKey(
                $context['sysadmin_user_id']
            )
            ->whereNull('school_id')
            ->where('role', 'superadmin')
            ->where('status', 'active')
            ->first();

        if ($sysadmin === null) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with(
                    'error',
                    'No fue posible recuperar la sesión '
                    .'del superadministrador.'
                );
        }

        $returnUrl = $context['return_url']
            ?? route(
                'sysadmin.schools.show',
                $context['school_id']
            );

        DB::transaction(
            function () use (
                $request,
                $impersonation,
                $sysadmin,
            ): void {
                $impersonation->update([
                    'ended_at' => now(),
                    'ended_reason' => 'manual',
                    'ended_ip_address' =>
                        $request->ip(),
                ]);

                $this->auditLogger->record(
                    action:
                        'support_impersonation_ended',

                    schoolId:
                        $impersonation->school_id,

                    actorId:
                        $sysadmin->id,

                    actorType:
                        'superadmin',

                    entityType:
                        User::class,

                    entityId:
                        $impersonation->target_user_id,

                    oldValues: [
                        'started_at' =>
                            $impersonation
                                ->started_at
                                ?->toIso8601String(),

                        'expires_at' =>
                            $impersonation
                                ->expires_at
                                ?->toIso8601String(),
                    ],

                    newValues: [
                        'impersonation_id' =>
                            $impersonation->id,

                        'ended_at' =>
                            now()->toIso8601String(),

                        'ended_reason' =>
                            'manual',
                    ],

                    request:
                        $request,
                );
            }
        );

        Auth::login($sysadmin);

        $request->session()->regenerate();

        $request->session()->forget(
            'support_impersonation'
        );

        return redirect($returnUrl)->with(
            'status',
            'Terminaste la sesión de soporte.'
        );
    }
}