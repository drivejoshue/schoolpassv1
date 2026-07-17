<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Roles globalmente reconocidos por SchoolPass.
     */
    private const VALID_ROLES = [
        'superadmin',
        'school_admin',
        'director',
        'prefect',
        'kiosk',
        'guardian',
        'student',
    ];

    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        if (! $user) {
            return $this->unauthenticatedResponse($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate middleware configuration
        |--------------------------------------------------------------------------
        |
        | Evita que un error tipográfico en routes/web.php o routes/api.php
        | produzca una autorización ambigua.
        |
        */

        $invalidRoles = array_diff($roles, self::VALID_ROLES);

        if ($invalidRoles !== []) {
            report(new \RuntimeException(
                'RoleMiddleware recibió roles inválidos: '.
                implode(', ', $invalidRoles)
            ));

            return $this->forbiddenResponse(
                $request,
                'La ruta tiene una configuración de permisos inválida.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | User status
        |--------------------------------------------------------------------------
        */

        if ($user->status !== 'active') {
            $user->currentAccessToken()?->delete();

            return $this->forbiddenResponse(
                $request,
                'Tu cuenta está inactiva o bloqueada.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Role authorization
        |--------------------------------------------------------------------------
        */

        if (! in_array($user->role, $roles, true)) {
            return $this->forbiddenResponse(
                $request,
                'No tienes permiso para acceder a esta sección.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | School validation
        |--------------------------------------------------------------------------
        |
        | Por el momento todos los usuarios operativos deben pertenecer a una
        | institución. Si posteriormente existe un superadmin global sin
        | school_id, se deberá crear una excepción explícita.
        |
        */

        if (! $user->school_id) {
            return $this->forbiddenResponse(
                $request,
                'El usuario no tiene una institución asignada.'
            );
        }

        $schoolStatus = DB::table('schools')
            ->where('id', $user->school_id)
            ->value('status');

        if ($schoolStatus === null) {
            $user->currentAccessToken()?->delete();

            return $this->forbiddenResponse(
                $request,
                'La institución asignada no existe.'
            );
        }

        if ($schoolStatus !== 'active') {
            $user->currentAccessToken()?->delete();

            return $this->forbiddenResponse(
                $request,
                'La institución está suspendida o inactiva.'
            );
        }

        return $next($request);
    }

    private function unauthenticatedResponse(
        Request $request
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => false,
                'message' => 'No autenticado.',
            ], 401);
        }

        return redirect()
            ->route('login')
            ->with('error', 'Debes iniciar sesión.');
    }

    private function forbiddenResponse(
        Request $request,
        string $message
    ): JsonResponse|Response {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 403);
        }

        abort(403, $message);
    }
}