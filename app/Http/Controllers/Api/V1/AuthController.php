<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use stdClass;
use Throwable;

class AuthController extends Controller
{
    /**
     * Cantidad máxima de tokens vigentes conservados por usuario.
     *
     * Esto evita que pruebas, reinstalaciones o logins repetidos acumulen
     * indefinidamente registros en personal_access_tokens.
     */
    private const MAX_ACTIVE_TOKENS_PER_USER = 10;

    /**
     * Duración predeterminada del token móvil.
     */
    private const TOKEN_EXPIRATION_DAYS = 90;

    /**
     * Roles que pueden iniciar sesión mediante esta API.
     */
    private const API_ROLES = [
        'superadmin',
        'school_admin',
        'director',
        'prefect',
        'kiosk',
        'guardian',
        'student',
    ];

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:120',
            ],
        ]);

        $email = Str::lower(trim($data['email']));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        if (! in_array($user->role, self::API_ROLES, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'El tipo de usuario no tiene acceso a esta aplicación.',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'ok' => false,
                'message' => 'Usuario inactivo o bloqueado.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | School validation
        |--------------------------------------------------------------------------
        |
        | Actualmente todos los usuarios API requieren una escuela asignada,
        | incluido superadmin. Cuando se cree un superadministrador global,
        | deberá separarse ese flujo explícitamente.
        |
        */

        if (! $user->school_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Usuario sin institución asignada.',
            ], 403);
        }

        $school = DB::table('schools')
            ->select([
                'id',
                'name',
                'slug',
                'status',
            ])
            ->where('id', $user->school_id)
            ->first();

        if (! $school) {
            return response()->json([
                'ok' => false,
                'message' => 'La institución asignada no existe.',
            ], 403);
        }

        if ($school->status !== 'active') {
            return response()->json([
                'ok' => false,
                'message' => 'La institución está suspendida o inactiva.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Assigned access device
        |--------------------------------------------------------------------------
        */

        $assignedDevices = $this->assignedDevicesForUser($user);

        if (
            $this->requiresAssignedDevice($user->role)
            && $assignedDevices->isEmpty()
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Este usuario no tiene un dispositivo activo asignado.',
            ], 403);
        }

        if (
            $this->requiresAssignedDevice($user->role)
            && $assignedDevices->count() > 1
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Este usuario tiene más de un dispositivo activo asignado. Corrige la asignación desde el panel.',
            ], 409);
        }

        /** @var stdClass|null $assignedDevice */
        $assignedDevice = $assignedDevices->first();

        $abilities = $this->abilitiesForRole($user->role);

        if ($abilities === []) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario no tiene permisos API configurados.',
            ], 403);
        }

        $deviceName = $this->resolveDeviceName(
            role: $user->role,
            requestedDeviceName: $data['device_name'] ?? null,
        );

        try {
            $plainTextToken = DB::transaction(function () use (
                $user,
                $deviceName,
                $abilities
            ): string {
                /*
                 * Elimina tokens que ya vencieron físicamente.
                 *
                 * Sanctum deja de aceptar tokens vencidos, pero el registro
                 * permanece en la tabla hasta que se elimina.
                 */
                $user->tokens()
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->delete();

                $newToken = $user->createToken(
                    $deviceName,
                    $abilities,
                    now()->addDays(self::TOKEN_EXPIRATION_DAYS),
                );

                $this->enforceTokenLimit(
                    user: $user,
                    preserveTokenId: $newToken->accessToken->id,
                );

                return $newToken->plainTextToken;
            });

            /*
             * Actualiza automáticamente contraseñas almacenadas con una
             * configuración de hash anterior.
             */
            if (Hash::needsRehash($user->password)) {
                $user->forceFill([
                    'password' => Hash::make($data['password']),
                ])->save();
            }
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible crear la sesión. Intenta nuevamente.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'token_type' => 'Bearer',
            'access_token' => $plainTextToken,
            'expires_at' => now()
                ->addDays(self::TOKEN_EXPIRATION_DAYS)
                ->toIso8601String(),

            'user' => $this->userPayload($user),

            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'status' => $school->status,
            ],

            'device' => $this->devicePayload($assignedDevice),

            'abilities' => $abilities,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Sesión no válida.',
            ], 401);
        }

        if ($user->status !== 'active') {
            $request->user()
                ?->currentAccessToken()
                ?->delete();

            return response()->json([
                'ok' => false,
                'message' => 'El usuario está inactivo o bloqueado.',
            ], 403);
        }

        if (! $user->school_id) {
            $request->user()
                ?->currentAccessToken()
                ?->delete();

            return response()->json([
                'ok' => false,
                'message' => 'Usuario sin institución asignada.',
            ], 403);
        }

        $school = DB::table('schools')
            ->select([
                'id',
                'name',
                'slug',
                'status',
            ])
            ->where('id', $user->school_id)
            ->first();

        if (! $school || $school->status !== 'active') {
            $request->user()
                ?->currentAccessToken()
                ?->delete();

            return response()->json([
                'ok' => false,
                'message' => 'La institución está suspendida o inactiva.',
            ], 403);
        }

        $assignedDevices = $this->assignedDevicesForUser($user);

        if (
            $this->requiresAssignedDevice($user->role)
            && $assignedDevices->isEmpty()
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario ya no tiene un dispositivo activo asignado.',
            ], 403);
        }

        if (
            $this->requiresAssignedDevice($user->role)
            && $assignedDevices->count() > 1
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'El usuario tiene más de un dispositivo activo asignado.',
            ], 409);
        }

        /** @var stdClass|null $assignedDevice */
        $assignedDevice = $assignedDevices->first();

        $currentToken = $user->currentAccessToken();

        return response()->json([
            'ok' => true,

            'user' => $this->userPayload($user),

            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'status' => $school->status,
            ],

            'device' => $this->devicePayload($assignedDevice),

            'abilities' => $currentToken instanceof PersonalAccessToken
                ? ($currentToken->abilities ?? [])
                : [],

            'token' => $currentToken instanceof PersonalAccessToken
                ? [
                    'name' => $currentToken->name,
                    'last_used_at' => $currentToken->last_used_at?->toIso8601String(),
                    'expires_at' => $currentToken->expires_at?->toIso8601String(),
                ]
                : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
{
    $data = $request->validate([
        'installation_uuid' => [
            'nullable',
            'string',
            'max:120',
        ],
        'app_key' => [
            'nullable',
            'string',
            'max:80',
        ],
    ]);

    $user = $request->user();
    $currentToken = $user?->currentAccessToken();

    DB::transaction(function () use (
        $user,
        $currentToken,
        $data
    ): void {
        if (
            $user
            && ! empty($data['installation_uuid'])
        ) {
            DB::table('user_device_tokens')
                ->where('school_id', $user->school_id)
                ->where('user_id', $user->id)
                ->where(
                    'installation_uuid',
                    trim($data['installation_uuid'])
                )
                ->where(
                    'app_key',
                    $data['app_key'] ?? 'schoolpass_family'
                )
                ->update([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'last_seen_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        }
    });

    return response()->json([
        'ok' => true,
        'message' => 'Sesión cerrada correctamente.',
    ]);
}

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
            ],
        ]);

        /** @var User|null $user */
        $user = $request->user();

        if (
            ! $user
            || ! Hash::check($data['current_password'], $user->password)
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'La contraseña actual no es correcta.',
                'errors' => [
                    'current_password' => [
                        'La contraseña actual no es correcta.',
                    ],
                ],
            ], 422);
        }

        $currentTokenId = $user->currentAccessToken() instanceof PersonalAccessToken
            ? $user->currentAccessToken()->id
            : null;

        DB::transaction(function () use (
            $user,
            $data,
            $currentTokenId
        ): void {
            $user->forceFill([
                'password' => Hash::make($data['password']),
            ])->save();

            /*
             * Se conservan únicamente la sesión desde la que se hizo
             * el cambio y se revocan todas las demás.
             */
            $user->tokens()
                ->when(
                    $currentTokenId !== null,
                    fn ($query) => $query->where('id', '!=', $currentTokenId),
                )
                ->delete();
        });

        return response()->json([
            'ok' => true,
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    /**
     * Obtiene todos los dispositivos de acceso activos asignados al usuario.
     */
    private function assignedDevicesForUser(User $user)
    {
        return DB::table('access_devices')
            ->where('school_id', $user->school_id)
            ->where('assigned_to_user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
    }

    private function requiresAssignedDevice(string $role): bool
    {
        return in_array($role, [
            'prefect',
            'kiosk',
        ], true);
    }

    private function abilitiesForRole(string $role): array
    {
        return match ($role) {
            'superadmin' => [
                '*',
            ],

            'school_admin', 'director' => [
                'admin:read',
                'admin:write',
                'access:scan',
                'students:read',
                'students:write',
                'guardians:read',
                'guardians:write',
                'notices:read',
                'notices:write',
                'reports:read',
                'devices:read',
                'devices:write',
            ],

            'prefect' => [
                'access:scan',
                'students:read',
            ],

            'kiosk' => [
                'access:scan',
            ],

            'guardian' => [
                'family:read',
                'family:devices',
            ],

            'student' => [
                'student:read',
                'family:devices',
            ],

            default => [],
        };
    }

    private function resolveDeviceName(
        string $role,
        ?string $requestedDeviceName,
    ): string {
        $requestedDeviceName = trim((string) $requestedDeviceName);

        if ($requestedDeviceName !== '') {
            return Str::limit(
                strip_tags($requestedDeviceName),
                120,
                '',
            );
        }

        return match ($role) {
            'guardian' => 'schoolpass-family',
            'student' => 'schoolpass-student',
            'prefect' => 'schoolpass-staff',
            'kiosk' => 'schoolpass-kiosk',
            'school_admin', 'director', 'superadmin' => 'schoolpass-admin',
            default => 'schoolpass-mobile',
        };
    }

    private function enforceTokenLimit(
        User $user,
        int $preserveTokenId,
    ): void {
        $tokenIdsToKeep = $user->tokens()
            ->orderByDesc('id')
            ->limit(self::MAX_ACTIVE_TOKENS_PER_USER)
            ->pluck('id');

        if (! $tokenIdsToKeep->contains($preserveTokenId)) {
            $tokenIdsToKeep->push($preserveTokenId);
        }

        $user->tokens()
            ->whereNotIn('id', $tokenIdsToKeep->all())
            ->delete();
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'school_id' => $user->school_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
        ];
    }

    private function devicePayload(?object $device): ?array
    {
        if (! $device) {
            return null;
        }

        return [
            'id' => $device->id,
            'name' => $device->name,
            'device_uuid' => $device->device_uuid,
            'platform' => $device->platform,
            'device_type' => $device->device_type,
            'campus_id' => $device->campus_id,
            'area_id' => $device->area_id,
            'mode' => $device->mode,
            'default_event_type' => $device->default_event_type,
            'allow_manual_search' => (bool) $device->allow_manual_search,
            'show_student_photo' => (bool) $device->show_student_photo,
            'camera_facing' => $device->camera_facing ?? 'back',
            'auto_reset_seconds' => (int) $device->auto_reset_seconds,
        ];
    }
}