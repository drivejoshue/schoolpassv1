<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FamilyDeviceController extends Controller
{
    private const APP_KEY = 'schoolpass_family';

    public function store(Request $request): JsonResponse
    {
        $user = $this->familyUserOrFail($request);

        $data = $request->validate([
            'installation_uuid' => [
                'required',
                'string',
                'max:120',
            ],
            'fcm_token' => [
                'required',
                'string',
                'max:8192',
            ],
            'platform' => [
                'nullable',
                Rule::in(['android', 'ios', 'web']),
            ],
            'app_key' => [
                'nullable',
                Rule::in([self::APP_KEY]),
            ],
            'app_flavor' => [
                'nullable',
                'string',
                'max:80',
            ],
            'app_version_name' => [
                'nullable',
                'string',
                'max:40',
            ],
            'app_version_code' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:150',
            ],
            'os_version' => [
                'nullable',
                'string',
                'max:80',
            ],
            'locale' => [
                'nullable',
                'string',
                'max:20',
            ],
            'timezone' => [
                'nullable',
                'string',
                'max:80',
            ],
            'notifications_enabled' => [
                'nullable',
                'boolean',
            ],
        ]);

        $appKey = $data['app_key'] ?? self::APP_KEY;
        $installationUuid = trim($data['installation_uuid']);
        $fcmToken = trim($data['fcm_token']);
        $tokenHash = hash('sha256', $fcmToken);

        $device = DB::transaction(function () use (
            $user,
            $data,
            $appKey,
            $installationUuid,
            $fcmToken,
            $tokenHash
        ): UserDeviceToken {
            /*
             * Un token FCM debe estar activo en una sola instalación.
             *
             * Se desactivan registros anteriores que tengan el mismo token,
             * excepto el registro exacto que estamos actualizando.
             */
            UserDeviceToken::query()
                ->where('token_hash', $tokenHash)
                ->where(function ($query) use (
                    $user,
                    $appKey,
                    $installationUuid
                ): void {
                    $query
                        ->where('user_id', '!=', $user->id)
                        ->orWhere('app_key', '!=', $appKey)
                        ->orWhere('installation_uuid', '!=', $installationUuid);
                })
                ->update([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            /*
             * También desactivamos otros tokens históricos de esta misma
             * instalación. Así solo queda activo el token más reciente.
             */
            UserDeviceToken::query()
                ->where('user_id', $user->id)
                ->where('school_id', $user->school_id)
                ->where('installation_uuid', $installationUuid)
                ->where('app_key', $appKey)
                ->where('token_hash', '!=', $tokenHash)
                ->update([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            $device = UserDeviceToken::query()->firstOrNew([
                'user_id' => $user->id,
                'installation_uuid' => $installationUuid,
                'app_key' => $appKey,
            ]);

            $device->fill([
                'school_id' => $user->school_id,
                'fcm_token' => $fcmToken,
                'token_hash' => $tokenHash,
                'platform' => $data['platform'] ?? 'android',
                'app_flavor' => $data['app_flavor'] ?? null,
                'app_version_name' => $data['app_version_name'] ?? null,
                'app_version_code' => $data['app_version_code'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'locale' => $data['locale'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'notifications_enabled' => $data['notifications_enabled'] ?? true,
                'is_active' => true,
                'last_registered_at' => now(),
                'last_seen_at' => now(),
                'last_error_at' => null,
                'last_error_code' => null,
                'revoked_at' => null,
            ]);

            $device->save();

            return $device->fresh();
        });

        return response()->json([
            'ok' => true,
            'message' => 'Dispositivo registrado para notificaciones.',
            'device' => $this->devicePayload($device),
        ]);
    }

    public function destroyCurrent(Request $request): JsonResponse
    {
        $user = $this->familyUserOrFail($request);

        $data = $request->validate([
            'installation_uuid' => [
                'required',
                'string',
                'max:120',
            ],
            'app_key' => [
                'nullable',
                Rule::in([self::APP_KEY]),
            ],
        ]);

        $updated = UserDeviceToken::query()
            ->where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->where('installation_uuid', trim($data['installation_uuid']))
            ->where('app_key', $data['app_key'] ?? self::APP_KEY)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'message' => $updated > 0
                ? 'Dispositivo desvinculado.'
                : 'El dispositivo ya estaba desvinculado.',
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $user = $this->familyUserOrFail($request);

        $data = $request->validate([
            'installation_uuid' => [
                'required',
                'string',
                'max:120',
            ],
            'app_key' => [
                'nullable',
                Rule::in([self::APP_KEY]),
            ],
            'notifications_enabled' => [
                'required',
                'boolean',
            ],
        ]);

        $updated = UserDeviceToken::query()
            ->where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->where('installation_uuid', trim($data['installation_uuid']))
            ->where('app_key', $data['app_key'] ?? self::APP_KEY)
            ->where('is_active', true)
            ->update([
                'notifications_enabled' => $data['notifications_enabled'],
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Dispositivo activo no registrado.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Preferencias actualizadas.',
            'notifications_enabled' => (bool) $data['notifications_enabled'],
        ]);
    }

    private function familyUserOrFail(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        if (
            ! $user
            || ! in_array($user->role, ['guardian', 'student'], true)
        ) {
            throw new AuthorizationException(
                'Usuario no autorizado para registrar este dispositivo.'
            );
        }

        if (
            ! $user->school_id
            || $user->status !== 'active'
        ) {
            throw new AuthorizationException(
                'Usuario sin institución activa.'
            );
        }

        return $user;
    }

    private function devicePayload(UserDeviceToken $device): array
    {
        return [
            'id' => $device->id,
            'installation_uuid' => $device->installation_uuid,
            'platform' => $device->platform,
            'app_key' => $device->app_key,
            'app_flavor' => $device->app_flavor,
            'app_version_name' => $device->app_version_name,
            'app_version_code' => $device->app_version_code,
            'notifications_enabled' => $device->notifications_enabled,
            'is_active' => $device->is_active,
            'last_registered_at' => $device
                ->last_registered_at
                ?->toIso8601String(),
            'last_seen_at' => $device
                ->last_seen_at
                ?->toIso8601String(),
        ];
    }
}