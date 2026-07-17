<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class SchoolAppConfigService
{
    public const SETTING_KEY = 'mobile_app';

    /**
     * Configuración predeterminada.
     *
     * Se utiliza cuando la escuela todavía no ha publicado
     * una configuración o cuando aparecen campos nuevos.
     */
    public function defaults(School $school): array
    {
        return [
            'config_version' => 1,

            /*
             * Identidad compartida por Staff y Familia.
             *
             * Los flavors podrán tener logo y colores de respaldo,
             * pero después del login estos valores enviados por el
             * backend tendrán prioridad.
             */
            'identity' => [
                'app_name' => $school->name,

                'short_name' => Str::limit(
                    $school->name,
                    30,
                    '',
                ),

                'logo_path' => $school->logo_path,

                'welcome_message' =>
                    'Bienvenido a SchoolPass',

                'welcome_image_path' => null,

                'primary_color' =>
                    $school->primary_color
                    ?: '#2563EB',

                'secondary_color' =>
                    $school->secondary_color
                    ?: '#0F172A',

                'accent_color' => '#06B6D4',

                /*
                 * Configuración visual que Kotlin utilizará para
                 * construir los ColorScheme claro y oscuro.
                 */
                'theme' => [
                    'default_mode' => 'system',

                    'allow_user_override' => true,

                    'light' => [
                        'background_color' => '#F6F8FC',
                        'surface_color' => '#FFFFFF',
                        'on_surface_color' => '#172033',
                    ],

                    'dark' => [
                        'background_color' => '#101318',
                        'surface_color' => '#191D24',
                        'on_surface_color' => '#F2F4F8',
                    ],
                ],
            ],

            'support' => [
                'email' =>
                    $school->support_email
                    ?: $school->contact_email,

                'phone' =>
                    $school->contact_phone,

                'whatsapp' =>
                    $school->whatsapp_number,
            ],

            'attendance' => [
                'check_in_enabled' => true,
                'check_out_enabled' => true,

                'late_tolerance_minutes' => 10,

                'early_exit_enabled' => true,

                'early_exit_requires_authorization' =>
                    true,

                'observations_enabled' => true,

                'temporary_passes_enabled' => true,
            ],

            'credentials' => [
                'qr_enabled' => true,

                'qr_mode' => 'fixed',

                'nfc_enabled' => false,

                'printed_credential_enabled' => true,

                'temporary_pass_minutes' => 30,
            ],

            'notifications' => [
                'entry' => true,
                'exit' => true,
                'late' => true,
                'absence' => true,
                'early_exit' => true,
                'denied_access' => true,
            ],

            /*
             * Valores predeterminados para SchoolPass Staff.
             *
             * Más adelante access/bootstrap podrá sobrescribir
             * algunos valores según el dispositivo asignado.
             */
            'staff' => [
                'qr_scan_enabled' => true,

                'manual_search_enabled' => true,

                'recent_access_enabled' => true,

                'show_student_photo' => true,

                'sound_enabled' => true,

                'vibration_enabled' => true,

                'auto_reset_seconds' => 3,

                'default_event_type' => 'entry',

                'camera_facing' => 'back',
            ],

            /*
             * Elementos visibles principalmente en SchoolPass Familia.
             *
             * La configuración solicitada por la escuela se combina
             * después con las funciones permitidas por la licencia.
             */
            'navigation' => [
                'notices' => true,

                'attendance_history' => true,

                'digital_credential' => true,

                'authorizations' => true,

                'payments' => false,

                'grades' => false,
            ],
        ];
    }

    /**
     * Obtiene la configuración efectiva de una escuela.
     *
     * array_replace_recursive permite agregar campos nuevos sin
     * invalidar configuraciones antiguas ya almacenadas.
     */
    public function get(School $school): array
    {
        $defaults = $this->defaults($school);

        $raw = DB::table('school_settings')
            ->where('school_id', $school->id)
            ->where('key', self::SETTING_KEY)
            ->value('value_json');

        if ($raw === null) {
            return $defaults;
        }

        $stored = is_array($raw)
            ? $raw
            : json_decode(
                (string) $raw,
                true,
            );

        if (! is_array($stored)) {
            return $defaults;
        }

        return array_replace_recursive(
            $defaults,
            $stored,
        );
    }

    /**
     * Publica la configuración.
     *
     * @throws JsonException
     */
    public function save(
        School $school,
        array $config,
        int $actorId,
    ): array {
        $existingId = DB::table('school_settings')
            ->where('school_id', $school->id)
            ->where('key', self::SETTING_KEY)
            ->value('id');

        $current = $this->get($school);

        /*
         * No reemplazamos todo el documento a ciegas.
         *
         * Esto permite conservar campos que todavía no sean
         * editables desde el panel.
         */
        $config = array_replace_recursive(
            $current,
            $config,
        );

        $config['config_version'] =
            $existingId === null
                ? 1
                : max(
                    1,
                    (int) (
                        $current['config_version']
                        ?? 0
                    ) + 1,
                );

        $encoded = json_encode(
            $config,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
        );

        DB::transaction(function () use (
            $school,
            $actorId,
            $existingId,
            $encoded,
        ): void {
            if ($existingId !== null) {
                DB::table('school_settings')
                    ->where('id', $existingId)
                    ->update([
                        'value_json' => $encoded,

                        'is_public' => true,

                        'updated_by' => $actorId,

                        'updated_at' => now(),
                    ]);

                return;
            }

            DB::table('school_settings')
                ->insert([
                    'school_id' => $school->id,

                    'key' => self::SETTING_KEY,

                    'value_json' => $encoded,

                    'is_public' => true,

                    'created_by' => $actorId,

                    'updated_by' => $actorId,

                    'created_at' => now(),

                    'updated_at' => now(),
                ]);
        });

        return $config;
    }

    /**
     * Contrato definitivo consumido por Android.
     */
    public function apiPayload(
        School $school,
        User $user,
        string $appType,
    ): array {
        $config = $this->get($school);

        $license = $this->currentLicense(
            (int) $school->id,
        );

        $features = $this->effectiveFeatures(
            schoolId: (int) $school->id,
            license: $license,
        );

        $identity = $config['identity'];
        $staff = $config['staff'];
        $navigation = $config['navigation'];

        /*
         * Algunas licencias antiguas podrían no incluir estas
         * claves en features_snapshot.
         *
         * Para las funciones nucleares usamos true como fallback.
         */
        $attendanceEnabled = $this->featureEnabled(
            features: $features,
            key: 'attendance',
            default: true,
        );

        $notificationsEnabled =
            $this->featureEnabled(
                features: $features,
                key: 'notifications',
                default: true,
            );

        $guardianAppEnabled =
            $this->featureEnabled(
                features: $features,
                key: 'guardian_app',
                default: true,
            );

        return [
            'ok' => true,

            /*
             * Información de la aplicación que hizo la solicitud.
             */
            'app' => [
                'type' => $appType,

                'key' =>
                    $appType === 'family'
                        ? 'schoolpass_family'
                        : 'schoolpass_staff',

                'product_name' =>
                    $appType === 'family'
                        ? 'SchoolPass Familia'
                        : 'SchoolPass Staff',

                'config_version' =>
                    (int) $config['config_version'],
            ],

            /*
             * Información institucional.
             */
            'school' => [
                'id' => (int) $school->id,

                'slug' => $school->slug,

                'name' => $school->name,

                'legal_name' =>
                    $school->legal_name,

                'short_name' =>
                    $identity['short_name'],

                'timezone' =>
                    $school->timezone
                    ?: config(
                        'app.timezone',
                        'America/Mexico_City',
                    ),
            ],

            /*
             * Marca y tema visual.
             *
             * Estos campos serán la fuente de los DTO de Kotlin.
             */
            'branding' => [
                'app_name' =>
                    $identity['app_name'],

                'logo_url' =>
                    $this->assetUrl(
                        $identity['logo_path'],
                    ),

                'welcome_message' =>
                    $identity['welcome_message'],

                'welcome_image_url' =>
                    $this->assetUrl(
                        $identity[
                            'welcome_image_path'
                        ],
                    ),

                'colors' => [
                    'primary' =>
                        $identity[
                            'primary_color'
                        ],

                    'secondary' =>
                        $identity[
                            'secondary_color'
                        ],

                    'accent' =>
                        $identity[
                            'accent_color'
                        ],
                ],

                'theme' =>
                    $identity['theme'],
            ],

            /*
             * Usuario autenticado.
             */
            'user' => [
                'id' => (int) $user->id,

                'name' => $user->name,

                'email' => $user->email,

                'role' => $user->role,

                'school_id' =>
                    (int) $user->school_id,
            ],

            'support' =>
                $config['support'],

            'attendance' =>
                $config['attendance'],

            'credentials' =>
                $config['credentials'],

            'notifications' =>
                $config['notifications'],

            /*
             * Configuración efectiva de SchoolPass Staff.
             *
             * La app no debe depender de valores hardcodeados.
             */
            'access' => [
                'qr_scan_enabled' =>
                    (bool) $staff[
                        'qr_scan_enabled'
                    ]
                    && (bool) $config[
                        'credentials'
                    ]['qr_enabled']
                    && $attendanceEnabled,

                'manual_search_enabled' =>
                    (bool) $staff[
                        'manual_search_enabled'
                    ]
                    && $attendanceEnabled,

                'recent_access_enabled' =>
                    (bool) $staff[
                        'recent_access_enabled'
                    ]
                    && $attendanceEnabled,

                'show_student_photo' =>
                    (bool) $staff[
                        'show_student_photo'
                    ],

                'sound_enabled' =>
                    (bool) $staff[
                        'sound_enabled'
                    ],

                'vibration_enabled' =>
                    (bool) $staff[
                        'vibration_enabled'
                    ],

                'auto_reset_seconds' =>
                    (int) $staff[
                        'auto_reset_seconds'
                    ],

                'default_event_type' =>
                    $staff[
                        'default_event_type'
                    ],

                'camera_facing' =>
                    $staff[
                        'camera_facing'
                    ],
            ],

            /*
             * Navegación solicitada por la escuela combinada con
             * las funciones autorizadas por la licencia.
             */
            'navigation' => [
                'notices' =>
                    (bool) $navigation[
                        'notices'
                    ]
                    && $notificationsEnabled,

                'attendance_history' =>
                    (bool) $navigation[
                        'attendance_history'
                    ]
                    && $attendanceEnabled,

                'digital_credential' =>
                    (bool) $navigation[
                        'digital_credential'
                    ]
                    && $guardianAppEnabled,

                'authorizations' =>
                    (bool) $navigation[
                        'authorizations'
                    ]
                    && $this->featureEnabled(
                        features: $features,
                        key:
                            'exit_authorizations',
                        default: false,
                    ),

                'payments' =>
                    (bool) $navigation[
                        'payments'
                    ]
                    && $this->featureEnabled(
                        features: $features,
                        key: 'payments',
                        default: false,
                    ),

                'grades' =>
                    (bool) $navigation[
                        'grades'
                    ]
                    && $this->featureEnabled(
                        features: $features,
                        key: 'grades',
                        default: false,
                    ),
            ],

            'features' => $features,

            /*
             * Este resumen permanece por compatibilidad.
             *
             * AppConfigController lo sustituirá por el estado
             * completo de SchoolLicenseStateService.
             */
            'license' => [
                'status' =>
                    $license?->status
                    ?? 'unlicensed',

                'plan_code' =>
                    $license?->plan_code,

                'plan_name' =>
                    $license?->plan_name,

                'starts_at' =>
                    $license?->starts_at,

                'expires_at' =>
                    $license?->expires_at,

                'trial_ends_at' =>
                    $license?->trial_ends_at,

                'grace_ends_at' =>
                    $license?->grace_ends_at,

                'access_allowed' =>
                    $this->accessAllowed(
                        school: $school,
                        license: $license,
                    ),
            ],
        ];
    }

    /**
     * Obtiene las funciones permitidas por plan y las
     * personalizaciones de la escuela.
     */
    public function effectiveFeatures(
        int $schoolId,
        ?object $license = null,
    ): array {
        $license ??=
            $this->currentLicense($schoolId);

        $snapshot = [];

        if ($license?->features_snapshot) {
            $decoded = json_decode(
                (string) $license
                    ->features_snapshot,
                true,
            );

            if (is_array($decoded)) {
                $snapshot = array_map(
                    static fn (
                        mixed $value,
                    ): bool => (bool) $value,
                    $decoded,
                );
            }
        }

        $overrides =
            DB::table('school_features')
                ->where(
                    'school_id',
                    $schoolId,
                )
                ->where(
                    function ($query): void {
                        $query
                            ->whereNull(
                                'starts_at',
                            )
                            ->orWhere(
                                'starts_at',
                                '<=',
                                now(),
                            );
                    },
                )
                ->where(
                    function ($query): void {
                        $query
                            ->whereNull(
                                'expires_at',
                            )
                            ->orWhere(
                                'expires_at',
                                '>=',
                                now(),
                            );
                    },
                )
                ->get([
                    'feature_key',
                    'is_enabled',
                ]);

        foreach ($overrides as $override) {
            $snapshot[
                $override->feature_key
            ] = (bool) $override
                ->is_enabled;
        }

        ksort($snapshot);

        return $snapshot;
    }

    /**
     * Obtiene la licencia marcada como actual.
     */
    public function currentLicense(
        int $schoolId,
    ): ?object {
        return DB::table(
            'school_licenses as licenses',
        )
            ->leftJoin(
                'subscription_plans as plans',
                'plans.id',
                '=',
                'licenses.subscription_plan_id',
            )
            ->where(
                'licenses.school_id',
                $schoolId,
            )
            ->where(
                'licenses.is_current',
                true,
            )
            ->latest('licenses.id')
            ->first([
                'licenses.*',

                'plans.code as plan_code',

                'plans.name as plan_name',
            ]);
    }

    /**
     * Convierte una ruta de Storage en una URL absoluta.
     */
    public function assetUrl(
        ?string $path,
    ): ?string {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (
            Str::startsWith(
                $path,
                [
                    'http://',
                    'https://',
                ],
            )
        ) {
            return $path;
        }

        $storageUrl =
            Storage::disk('public')
                ->url(
                    ltrim($path, '/'),
                );

        return Str::startsWith(
            $storageUrl,
            [
                'http://',
                'https://',
            ],
        )
            ? $storageUrl
            : url($storageUrl);
    }

    /**
     * Determina si la licencia permite operar.
     */
    private function accessAllowed(
        School $school,
        ?object $license,
    ): bool {
        if (
            $school->status !== 'active'
            || $license === null
        ) {
            return false;
        }

        if (
            ! in_array(
                $license->status,
                [
                    'trial',
                    'active',
                    'grace',
                ],
                true,
            )
        ) {
            return false;
        }

        $today = now()->startOfDay();

        if (
            $license->status === 'trial'
            && $license->trial_ends_at
                !== null
            && Carbon::parse(
                $license->trial_ends_at,
            )
                ->endOfDay()
                ->lt($today)
        ) {
            return false;
        }

        if (
            $license->status === 'grace'
            && $license->grace_ends_at
                !== null
            && Carbon::parse(
                $license->grace_ends_at,
            )
                ->endOfDay()
                ->lt($today)
        ) {
            return false;
        }

        if (
            $license->status === 'active'
            && $license->expires_at
                !== null
            && Carbon::parse(
                $license->expires_at,
            )
                ->endOfDay()
                ->lt($today)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Lee una función de licencia sin romper planes antiguos.
     */
    private function featureEnabled(
        array $features,
        string $key,
        bool $default,
    ): bool {
        return array_key_exists(
            $key,
            $features,
        )
            ? (bool) $features[$key]
            : $default;
    }
}