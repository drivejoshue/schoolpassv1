<?php

namespace App\Http\Middleware;

use App\Models\SupportImpersonation;
use App\Models\User;
use App\Services\Auditing\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnforceSupportImpersonationExpiry
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $context = $request->session()->get(
            'support_impersonation'
        );

        if (! is_array($context)) {
            return $next($request);
        }

        if (
            empty($context['id'])
            || empty($context['sysadmin_user_id'])
            || empty($context['target_user_id'])
        ) {
            $request->session()->forget(
                'support_impersonation'
            );

            return $next($request);
        }

        $impersonation = SupportImpersonation::query()
            ->whereKey((int) $context['id'])
            ->first();

        if (
            $impersonation === null
            || $impersonation->ended_at !== null
        ) {
            return $this->restoreSysadmin(
                request: $request,
                context: $context,
                message: 'La sesión de soporte ya terminó.'
            );
        }

        if (
            $impersonation->expires_at === null
            || $impersonation->expires_at->isFuture()
        ) {
            return $next($request);
        }

        DB::transaction(
            function () use (
                $request,
                $context,
                $impersonation,
            ): void {
                $locked = SupportImpersonation::query()
                    ->whereKey($impersonation->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    $locked === null
                    || $locked->ended_at !== null
                ) {
                    return;
                }

                $locked->update([
                    'ended_at' => now(),
                    'ended_reason' => 'expired',
                    'ended_ip_address' => $request->ip(),
                ]);

                $this->auditLogger->record(
                    action: 'support_impersonation_expired',
                    schoolId: $locked->school_id,
                    actorId: $locked->sysadmin_user_id,
                    actorType: 'superadmin',
                    entityType: User::class,
                    entityId: $locked->target_user_id,
                    oldValues: [
                        'started_at' =>
                            $locked->started_at?->toIso8601String(),

                        'expires_at' =>
                            $locked->expires_at?->toIso8601String(),
                    ],
                    newValues: [
                        'impersonation_id' => $locked->id,
                        'ended_at' => now()->toIso8601String(),
                        'ended_reason' => 'expired',
                    ],
                    request: $request,
                );
            }
        );

        return $this->restoreSysadmin(
            request: $request,
            context: $context,
            message: (
                'La sesión de soporte alcanzó su tiempo máximo '
                .'y fue cerrada automáticamente.'
            )
        );
    }

    private function restoreSysadmin(
        Request $request,
        array $context,
        string $message,
    ): Response {
        $sysadmin = User::query()
            ->whereKey(
                (int) $context['sysadmin_user_id']
            )
            ->whereNull('school_id')
            ->where('role', 'superadmin')
            ->where('status', 'active')
            ->first();

        $request->session()->forget(
            'support_impersonation'
        );

        if ($sysadmin === null) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', $message);
        }

        Auth::login($sysadmin);

        $request->session()->regenerate();

        $returnUrl = $context['return_url']
            ?? route('sysadmin.dashboard');

        return redirect($returnUrl)->with(
            'warning',
            $message
        );
    }
}