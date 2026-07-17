<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\Licensing\SchoolLicenseStateService;
use App\Services\SchoolAppConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class AppConfigController extends Controller
{
    public function __construct(
        private readonly SchoolAppConfigService $configService,
        private readonly SchoolLicenseStateService $licenseService,
    ) {
    }

    public function show(
        Request $request,
    ): JsonResponse|Response {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'ok' => false,
                'message' => 'No existe un usuario autenticado.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario está inactivo o bloqueado.',
            ], 403);
        }

        if ($user->school_id === null) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario no pertenece a una escuela.',
            ], 403);
        }

        $validated = $request->validate([
            'app' => [
                'nullable',
                'string',
                'in:staff,family',
            ],
        ]);

        $appType = $validated['app']
            ?? $this->resolveAppType(
                (string) $user->role,
            );

        if (
            ! $this->userCanUseApp(
                role: (string) $user->role,
                appType: $appType,
            )
        ) {
            return response()->json([
                'ok' => false,
                'message' => (
                    'El usuario no tiene acceso '
                    .'a esta aplicación.'
                ),
            ], 403);
        }

        try {
            $school = School::query()->find(
                (int) $user->school_id,
            );

            if ($school === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La escuela no existe.',
                ], 404);
            }

            if ($school->status !== 'active') {
                return response()->json([
                    'ok' => false,
                    'message' => (
                        'La institución se encuentra '
                        .'suspendida o inactiva.'
                    ),
                ], 403);
            }

            $payload = $this->configService
                ->apiPayload(
                    school: $school,
                    user: $user,
                    appType: $appType,
                );

            /*
             * Sustituye el resumen interno por el estado
             * completo y efectivo de la licencia.
             */
            $payload['license'] = $this->licenseService
                ->forSchoolId(
                    (int) $school->id,
                );

            $etag = $this->makeEtag($payload);

            $headers = [
                'ETag' => $etag,

                'Cache-Control' =>
                    'private, max-age=60, must-revalidate',

                /*
                 * Impide que un proxy reutilice la configuración
                 * de un usuario para otro.
                 */
                'Vary' => 'Authorization',
            ];

            if (
                $this->requestMatchesEtag(
                    request: $request,
                    etag: $etag,
                )
            ) {
                return response(
                    content: '',
                    status: 304,
                    headers: $headers,
                );
            }

            return response()->json(
                data: $payload,
                status: 200,
                headers: $headers,
            );
        } catch (Throwable $exception) {
            report($exception);

            $response = [
                'ok' => false,

                'message' => (
                    'No fue posible obtener la configuración '
                    .'de la aplicación.'
                ),
            ];

            if (
                app()->environment('local')
                || config('app.debug') === true
            ) {
                $response['debug'] = [
                    'exception' =>
                        get_class($exception),

                    'message' =>
                        $exception->getMessage(),

                    'file' =>
                        $exception->getFile(),

                    'line' =>
                        $exception->getLine(),
                ];
            }

            return response()->json(
                data: $response,
                status: 500,
            );
        }
    }

    private function resolveAppType(
        string $role,
    ): string {
        return in_array(
            $role,
            [
                'guardian',
                'student',
            ],
            true,
        )
            ? 'family'
            : 'staff';
    }

    private function userCanUseApp(
        string $role,
        string $appType,
    ): bool {
        return match ($appType) {
            'family' => in_array(
                $role,
                [
                    'guardian',
                    'student',
                ],
                true,
            ),

            'staff' => in_array(
                $role,
                [
                    'superadmin',
                    'school_admin',
                    'director',
                    'prefect',
                    'kiosk',
                ],
                true,
            ),

            default => false,
        };
    }

    /**
     * Genera un ETag con la respuesta completa.
     *
     * Como el payload ya no contiene generated_at, el hash
     * permanece estable mientras la configuración no cambie.
     */
    private function makeEtag(
        array $payload,
    ): string {
        $content = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION,
        );

        return '"'.hash(
            'sha256',
            $content,
        ).'"';
    }

    /**
     * Soporta ETag normal, ETag débil y listas de ETags.
     */
    private function requestMatchesEtag(
        Request $request,
        string $etag,
    ): bool {
        $header = $request->header(
            'If-None-Match',
        );

        if (
            $header === null
            || trim($header) === ''
        ) {
            return false;
        }

        foreach (
            explode(',', $header)
            as $candidate
        ) {
            $candidate = trim($candidate);

            if ($candidate === '*') {
                return true;
            }

            if ($candidate === $etag) {
                return true;
            }

            if (
                str_starts_with(
                    $candidate,
                    'W/',
                )
                && substr($candidate, 2) === $etag
            ) {
                return true;
            }
        }

        return false;
    }
}