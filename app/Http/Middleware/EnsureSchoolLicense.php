<?php

namespace App\Http\Middleware;

use App\Services\Licensing\SchoolLicenseStateService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolLicense
{
    public function __construct(
        private readonly SchoolLicenseStateService $licenseStateService,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        /*
         * Superadmin global: no pertenece a una escuela y no debe bloquearse.
         */
        if (
            $user->role === 'superadmin'
            && $user->school_id === null
        ) {
            return $next($request);
        }

        if ($user->school_id === null) {
            return $this->deny(
                request: $request,
                state: [
                    'code' => 'SCHOOL_CONTEXT_MISSING',
                    'status' => 'invalid',
                    'message' => (
                        'El usuario no tiene una escuela asignada.'
                    ),
                    'access_allowed' => false,
                ]
            );
        }

        $state = $request->attributes->get(
            'school_license_state'
        );

        if (! is_array($state)) {
            $state = $this->licenseStateService
                ->forSchoolId(
                    (int) $user->school_id
                );

            $request->attributes->set(
                'school_license_state',
                $state
            );
        }

        View::share(
            'schoolLicenseContext',
            $state
        );

        /*
         * Estas rutas deben seguir funcionando aun cuando la licencia
         * esté vencida o suspendida.
         */
        if (
            in_array(
                $request->route()?->getName(),
                [
                    'admin.license.show',
                    'license.blocked',
                    'logout',
                    'support.impersonation.stop',
                    'api.v1.app.config',
                    'api.v1.auth.logout',
                    'api.v1.auth.change-password',
                    'api.v1.me',
                ],
                true
            )
        ) {
            return $next($request);
        }

        if (
            (bool) ($state['access_allowed'] ?? false)
        ) {
            return $next($request);
        }

        return $this->deny(
            request: $request,
            state: $state
        );
    }

    private function deny(
        Request $request,
        array $state,
    ): Response {
        if (
            $request->expectsJson()
            || $request->is('api/*')
        ) {
            return response()->json([
                'ok' => false,
                'code' => $state['code']
                    ?? 'LICENSE_BLOCKED',
                'message' => $state['message']
                    ?? 'La licencia no permite continuar.',
                'license' => [
                    'status' => $state['status']
                        ?? null,
                    'expires_at' => $state['expires_at']
                        ?? null,
                    'grace_ends_at' => $state['grace_ends_at']
                        ?? null,
                    'access_allowed' => false,
                ],
            ], 403);
        }

        $role = (string) $request->user()?->role;

        if (
            in_array(
                $role,
                [
                    'school_admin',
                    'director',
                ],
                true
            )
        ) {
            return redirect()
                ->route('admin.license.show')
                ->with(
                    'error',
                    $state['message']
                        ?? 'La licencia no permite continuar.'
                );
        }

        return redirect()
            ->route('license.blocked');
    }
}