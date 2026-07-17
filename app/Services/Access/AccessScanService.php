<?php

namespace App\Services\Access;

use App\Jobs\SendUserNotificationPush;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AccessScanService
{
    private const ADMIN_ROLES = [
        'superadmin',
        'school_admin',
        'director',
    ];

    private const ASSIGNED_DEVICE_ROLES = [
        'prefect',
        'kiosk',
    ];

    private const ALLOWED_READER_TYPES = [
        'camera_qr',
        'manual',
        'nfc',
        'hardware',
    ];

    /**
     * Procesa escaneos mediante QR, NFC o lector.
     */
    public function process(array $data, object $user): array
    {
        $userValidation = $this->validateUser($user);

        if ($userValidation !== null) {
            return $userValidation;
        }

        try {
            return DB::transaction(function () use ($data, $user): array {
                $schoolId = (int) $user->school_id;

                $scannedAt = $this->resolveScannedAt(
                    $data['scanned_at'] ?? null
                );

                $deviceResult = $this->resolveAuthorizedDevice(
                    schoolId: $schoolId,
                    user: $user,
                    deviceUuid: trim((string) ($data['device_uuid'] ?? '')),
                    requireManualPermission: false,
                );

                if (! $deviceResult['ok']) {
                    return $deviceResult;
                }

                $device = $deviceResult['device'];
                $area = $deviceResult['area'];

                $readerType = $this->resolveReaderType(
                    $data['reader_type'] ?? null
                );

                $token = trim((string) ($data['token'] ?? ''));

                if ($token === '') {
                    return [
                        'ok' => false,
                        'http_code' => 422,
                        'decision' => 'denied',
                        'status' => 'invalid_credential',
                        'message' => 'La credencial está vacía.',
                    ];
                }

                $tokenHash = hash('sha256', $token);

               $credential = DB::table('student_credentials')
    ->where('school_id', $schoolId)
    ->where('token_hash', $tokenHash)
    ->first();

if (! $credential) {
    return $this->prepareGuardianScan(
        schoolId: $schoolId,
        device: $device,
        area: $area,
        user: $user,
        tokenHash: $tokenHash,
        requestedEventType: $data['event_type'] ?? null,
        scannedAt: $scannedAt,
        readerType: $readerType,
    );
}

if ($credential->status !== 'active') {
    return [
        'ok' => false,
        'http_code' => 422,
        'decision' => 'denied',
        'status' => 'invalid_credential',
        'message' => 'Credencial inválida o inactiva.',
    ];
}

                if (
                    $credential->expires_at !== null
                    && Carbon::parse($credential->expires_at)->isPast()
                ) {
                    return [
                        'ok' => false,
                        'http_code' => 422,
                        'decision' => 'denied',
                        'status' => 'expired',
                        'message' => 'La credencial está vencida.',
                    ];
                }

                $student = DB::table('students')
                    ->where('school_id', $schoolId)
                    ->where('id', $credential->student_id)
                    ->where('status', 'active')
                    ->first();

                if (! $student) {
                    return [
                        'ok' => false,
                        'http_code' => 403,
                        'decision' => 'denied',
                        'status' => 'inactive_student',
                        'message' => 'Alumno no activo o no disponible.',
                    ];
                }

               $eventMode = $this->resolveEventType(
    requestedEventType:
        $data['event_type'] ?? null,
    device: $device,
);

$eventType =
    $this->resolveAutomaticStudentEventType(
        eventMode: $eventMode,
        schoolId: $schoolId,
        student: $student,
        scannedAt: $scannedAt,
    );

$result = $this->processStudentEvent(
    schoolId: $schoolId,
    device: $device,
    area: $area,
    student: $student,
    credential: $credential,
    user: $user,
    eventType: $eventType,
    scannedAt: $scannedAt,
    source: $device->device_type === 'kiosk'
        ? 'kiosk'
        : 'qr',
    readerType: $readerType,
    reason: null,
    manual: false,
);

$result['scan_type'] = 'student';
$result['requires_selection'] = false;

return $result;
            }, 3);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'http_code' => 500,
                'decision' => 'denied',
                'status' => 'server_error',
                'message' => 'No se pudo procesar el escaneo.',
                'debug' => app()->environment('local')
                    ? $exception->getMessage()
                    : null,
            ];
        }
    }

    /**
     * Procesa una entrada o salida manual desde SchoolPass Staff.
     */
   public function processManual(
    array $data,
    object $user,
): array {
    $userValidation = $this->validateUser(
        $user
    );

    if ($userValidation !== null) {
        return $userValidation;
    }

    try {
        return DB::transaction(
            function () use (
                $data,
                $user
            ): array {
                $schoolId =
                    (int) $user->school_id;

                $deviceResult =
                    $this->resolveAuthorizedDevice(
                        schoolId: $schoolId,
                        user: $user,
                        deviceUuid: trim(
                            (string) (
                                $data['device_uuid']
                                ?? ''
                            )
                        ),
                        requireManualPermission:
                            true,
                    );

                if (! $deviceResult['ok']) {
                    return $deviceResult;
                }

                $device =
                    $deviceResult['device'];

                $area =
                    $deviceResult['area'];

                $student = DB::table(
                    'students'
                )
                    ->where(
                        'school_id',
                        $schoolId
                    )
                    ->where(
                        'campus_id',
                        $device->campus_id
                    )
                    ->where(
                        'id',
                        (int) $data['student_id']
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->first();

                if (! $student) {
                    return [
                        'ok' => false,
                        'http_code' => 404,
                        'decision' => 'denied',
                        'status' => 'not_found',
                        'message' =>
                            'Alumno no encontrado.',
                    ];
                }

                $scannedAt =
                    $this->resolveScannedAt(
                        $data['scanned_at']
                        ?? null
                    );

                $eventType = (string) (
                    $data['event_type']
                    ?? ''
                );

                if (
                    ! in_array(
                        $eventType,
                        ['entry', 'exit'],
                        true
                    )
                ) {
                    return [
                        'ok' => false,
                        'http_code' => 422,
                        'decision' => 'denied',
                        'status' =>
                            'invalid_event',
                        'message' =>
                            'Movimiento no válido.',
                    ];
                }

                /*
                 * Calculamos la regla efectiva
                 * del alumno.
                 */
                $group = null;

                if (
                    $student->current_group_id
                    !== null
                ) {
                    $group = DB::table(
                        'school_groups'
                    )
                        ->where(
                            'school_id',
                            $schoolId
                        )
                        ->where(
                            'id',
                            $student
                                ->current_group_id
                        )
                        ->first();
                }

                $requiresGuardianScan =
                    $student
                        ->requires_guardian_scan_override
                        !== null
                            ? (bool) $student
                                ->requires_guardian_scan_override
                            : (bool) (
                                $group
                                    ?->requires_guardian_scan
                                ?? false
                            );

                $guardianId = isset(
                    $data['guardian_id']
                )
                    ? (int) $data[
                        'guardian_id'
                    ]
                    : null;

                $guardian = null;
                $relationship = null;

                /*
                 * En modo obligatorio no se permite
                 * registro manual sin tutor.
                 */
                if (
                    $requiresGuardianScan
                    && $guardianId === null
                ) {
                    return [
                        'ok' => false,
                        'http_code' => 422,
                        'decision' => 'denied',
                        'status' =>
                            'guardian_required',
                        'message' =>
                            'Este alumno requiere seleccionar un tutor autorizado.',
                    ];
                }

                /*
                 * Si Android envió tutor,
                 * validamos formalmente la relación.
                 */
                if ($guardianId !== null) {
                    $permissionColumn =
                        $eventType === 'entry'
                            ? 'can_drop_off'
                            : 'can_pick_up';

                    $relation = DB::table(
                        'student_guardians'
                    )
                        ->where(
                            'student_id',
                            $student->id
                        )
                        ->where(
                            'guardian_id',
                            $guardianId
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->where(
                            $permissionColumn,
                            true
                        )
                        ->where(
                            function (
                                $query
                            ) use (
                                $scannedAt
                            ): void {
                                $query
                                    ->whereNull(
                                        'valid_from'
                                    )
                                    ->orWhereDate(
                                        'valid_from',
                                        '<=',
                                        $scannedAt
                                            ->toDateString()
                                    );
                            }
                        )
                        ->where(
                            function (
                                $query
                            ) use (
                                $scannedAt
                            ): void {
                                $query
                                    ->whereNull(
                                        'valid_until'
                                    )
                                    ->orWhereDate(
                                        'valid_until',
                                        '>=',
                                        $scannedAt
                                            ->toDateString()
                                    );
                            }
                        )
                        ->first();

                    if (! $relation) {
                        return [
                            'ok' => false,
                            'http_code' => 403,
                            'decision' =>
                                'denied',
                            'status' =>
                                'guardian_not_authorized',
                            'message' =>
                                $eventType ===
                                'entry'
                                    ? 'La persona seleccionada no está autorizada para entregar al alumno.'
                                    : 'La persona seleccionada no está autorizada para recoger al alumno.',
                        ];
                    }

                    $guardian = DB::table(
                        'guardians'
                    )
                        ->where(
                            'school_id',
                            $schoolId
                        )
                        ->where(
                            'id',
                            $guardianId
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->first();

                    if (! $guardian) {
                        return [
                            'ok' => false,
                            'http_code' => 403,
                            'decision' =>
                                'denied',
                            'status' =>
                                'inactive_guardian',
                            'message' =>
                                'El tutor está inactivo o no disponible.',
                        ];
                    }

                    $relationship =
                        $relation->relationship;
                }

                $result =
                    $this->processStudentEvent(
                        schoolId: $schoolId,
                        device: $device,
                        area: $area,
                        student: $student,
                        credential: null,
                        user: $user,
                        eventType: $eventType,
                        scannedAt: $scannedAt,
                        source: 'manual',
                        readerType: 'manual',
                        reason: trim(
                            (string) (
                                $data['reason']
                                ?? ''
                            )
                        ),
                        manual: true,
                    );

                /*
                 * Asociamos al tutor incluso si
                 * fue un duplicado o una denegación
                 * que haya generado access_log.
                 */
                if (
                    $guardian !== null
                    && ! empty(
                        $result['log_id']
                    )
                ) {
                    DB::table('access_logs')
                        ->where(
                            'school_id',
                            $schoolId
                        )
                        ->where(
                            'id',
                            (int) $result[
                                'log_id'
                            ]
                        )
                        ->update([
                            'guardian_id' =>
                                (int) $guardian->id,

                            /*
                             * No hubo QR.
                             */
                            'guardian_credential_id'
                                => null,

                            'performed_for' =>
                                'guardian',

                            'updated_at' =>
                                now(),
                        ]);
                }

                $result['scan_type'] =
                    'manual';

                $result['performed_for'] =
                    $guardian !== null
                        ? 'guardian'
                        : 'self';

                if ($guardian !== null) {
                    $result['guardian'] = [
                        'name' => trim(
                            $guardian->first_name
                            .' '
                            .$guardian->last_name
                        ),

                        'photo_url' =>
                            $guardian->photo_url
                                ? asset(
                                    ltrim(
                                        $guardian
                                            ->photo_url,
                                        '/'
                                    )
                                )
                                : null,

                        'relationship' =>
                            $relationship,
                    ];
                }

                return $result;
            },
            3
        );
    } catch (Throwable $exception) {
        report($exception);

        return [
            'ok' => false,
            'http_code' => 500,
            'decision' => 'denied',
            'status' => 'server_error',
            'message' =>
                'No se pudo registrar el acceso manual.',

            'debug' =>
                app()->environment(
                    'local'
                )
                    ? $exception
                        ->getMessage()
                    : null,
        ];
    }
}


private function prepareGuardianScan(
    int $schoolId,
    object $device,
    ?object $area,
    object $user,
    string $tokenHash,
    ?string $requestedEventType,
    Carbon $scannedAt,
    string $readerType,
): array {
    if (! (bool) ($device->allow_guardian_scan ?? false)) {
        return [
            'ok' => false,
            'http_code' => 403,
            'decision' => 'denied',
            'status' => 'guardian_scan_disabled',
            'message' => 'Este dispositivo no permite credenciales de tutor.',
        ];
    }

    if ($device->mode === 'restricted_access') {
        return [
            'ok' => false,
            'http_code' => 422,
            'decision' => 'denied',
            'status' => 'guardian_not_allowed_for_area',
            'message' => 'La credencial de tutor no puede utilizarse para acceder a esta área.',
        ];
    }

    $guardianCredential = DB::table('guardian_credentials')
        ->where('school_id', $schoolId)
        ->where('token_hash', $tokenHash)
        ->first();

    if (! $guardianCredential) {
        return [
            'ok' => false,
            'http_code' => 422,
            'decision' => 'denied',
            'status' => 'invalid_credential',
            'message' => 'Credencial inválida o inactiva.',
        ];
    }

    if ($guardianCredential->status !== 'active') {
        return [
            'ok' => false,
            'http_code' => 422,
            'decision' => 'denied',
            'status' => 'invalid_credential',
            'message' => 'La credencial del tutor está inactiva.',
        ];
    }

    if (
        $guardianCredential->expires_at !== null
        && Carbon::parse($guardianCredential->expires_at)->isPast()
    ) {
        return [
            'ok' => false,
            'http_code' => 422,
            'decision' => 'denied',
            'status' => 'expired',
            'message' => 'La credencial del tutor está vencida.',
        ];
    }

    $guardian = DB::table('guardians')
        ->where('school_id', $schoolId)
        ->where('id', $guardianCredential->guardian_id)
        ->where('status', 'active')
        ->first();

    if (! $guardian) {
        return [
            'ok' => false,
            'http_code' => 403,
            'decision' => 'denied',
            'status' => 'inactive_guardian',
            'message' => 'El tutor está inactivo o no disponible.',
        ];
    }

    $eventType = $this->resolveEventType(
        requestedEventType: $requestedEventType,
        device: $device,
    );

if (
    ! in_array(
        $eventType,
        [
            'auto',
            'entry',
            'exit',
        ],
        true
    )
) {        return [
            'ok' => false,
            'http_code' => 422,
            'decision' => 'denied',
            'status' => 'invalid_event',
            'message' => 'La credencial de tutor solo puede utilizarse para entrada o salida.',
        ];
    }

   $permissionColumn = match ($eventType) {
    'entry' => 'sg.can_drop_off',
    'exit' => 'sg.can_pick_up',
    default => null,
};

    $students = DB::table('student_guardians as sg')
        ->join('students as student', function ($join) use ($schoolId): void {
            $join
                ->on('student.id', '=', 'sg.student_id')
                ->where('student.school_id', '=', $schoolId)
                ->where('student.status', '=', 'active');
        })
        ->leftJoin(
            'school_groups as school_group',
            function ($join) use ($schoolId): void {
                $join
                    ->on(
                        'school_group.id',
                        '=',
                        'student.current_group_id'
                    )
                    ->where(
                        'school_group.school_id',
                        '=',
                        $schoolId
                    );
            }
        )
        ->where('sg.guardian_id', $guardian->id)
        ->where('sg.status', 'active')
       ->when(
    $permissionColumn !== null,
    fn ($query) =>
        $query->where(
            $permissionColumn,
            true
        ),
    fn ($query) =>
        $query->where(
            function ($permissionQuery): void {
                $permissionQuery
                    ->where(
                        'sg.can_drop_off',
                        true
                    )
                    ->orWhere(
                        'sg.can_pick_up',
                        true
                    );
            }
        )
)
        ->where(function ($query) use ($scannedAt): void {
            $date = $scannedAt->toDateString();

            $query
                ->whereNull('sg.valid_from')
                ->orWhereDate('sg.valid_from', '<=', $date);
        })
        ->where(function ($query) use ($scannedAt): void {
            $date = $scannedAt->toDateString();

            $query
                ->whereNull('sg.valid_until')
                ->orWhereDate('sg.valid_until', '>=', $date);
        })
        ->orderBy('student.first_name')
        ->orderBy('student.last_name')
        ->get([
            'student.id',
            'student.student_code',
            'student.first_name',
            'student.last_name',
            'student.photo_url',
            'student.current_group_id',
            'student.requires_guardian_scan_override',
            'school_group.name as group_name',
            'school_group.requires_guardian_scan',
            'sg.relationship',
            'sg.can_drop_off',
            'sg.can_pick_up',
        ]);

    if ($students->isEmpty()) {
        return [
            'ok' => false,
            'http_code' => 403,
            'decision' => 'denied',
            'status' => 'no_authorized_students',
            'message' => $eventType === 'entry'
                ? 'El tutor no tiene alumnos autorizados para entregar.'
                : 'El tutor no tiene alumnos autorizados para recoger.',
        ];
    }

    $scanToken = Str::random(64);

    /*
     * Cada alumno recibe un token temporal independiente.
     * Android nunca envía student_id.
     */
    $studentSelections = $students->mapWithKeys(
        function (object $student): array {
            return [
                Str::random(48) => (int) $student->id,
            ];
        }
    );

    Cache::put(
        $this->guardianScanCacheKey($scanToken),
        [
            'school_id' => $schoolId,
            'user_id' => (int) $user->id,
            'device_id' => (int) $device->id,
            'device_uuid' => (string) $device->device_uuid,
            'area_id' => $area?->id,
            'guardian_id' => (int) $guardian->id,
            'guardian_credential_id' => (int) $guardianCredential->id,
            'event_type' => $eventType,
            'reader_type' => $readerType,
            'scanned_at' => $scannedAt->toIso8601String(),

            /*
             * Mapa interno:
             * selection_token => student_id real.
             */
            'student_selections' => $studentSelections->all(),
        ],
        now()->addMinutes(2),
    );

    $singleStudent = $students->count() === 1;

  $studentPayloads = $students
    ->map(function (object $student) use (
        $eventType,
        $studentSelections,
        $schoolId,
        $scannedAt
    ): array {
        $selectionToken = $studentSelections->search(
            (int) $student->id,
            true
        );

        /*
         * En modo automático resolvemos el movimiento
         * individualmente para cada alumno.
         */
        $studentEventType =
            $this->resolveAutomaticStudentEventType(
                eventMode: $eventType,
                schoolId: $schoolId,
                student: $student,
                scannedAt: $scannedAt,
            );

        return [
            'selection_token' =>
                $selectionToken !== false
                    ? (string) $selectionToken
                    : null,

            'code' =>
                $student->student_code,

            'name' => trim(
                $student->first_name
                .' '
                .$student->last_name
            ),

            'photo_url' =>
                $student->photo_url
                    ? asset(
                        ltrim(
                            $student->photo_url,
                            '/'
                        )
                    )
                    : null,

            'group' => [
                'name' =>
                    $student->group_name,
            ],

            'relationship' =>
                $student->relationship,

            /*
             * Evento efectivo que corresponde
             * actualmente a este alumno.
             */
            'event_type' =>
                $studentEventType,

            'allowed_action' =>
                $studentEventType === 'entry'
                    ? 'drop_off'
                    : 'pick_up',

            'requires_guardian_scan' => (bool) (
                $student
                    ->requires_guardian_scan_override
                ?? $student
                    ->requires_guardian_scan
                ?? false
            ),
        ];
    })
    ->values()
    ->all();

    return [
        'ok' => true,
        'http_code' => 200,
        'decision' => 'selection_required',
        'status' => 'guardian_detected',
        'scan_type' => 'guardian',
        'requires_selection' => true,
        'single_student' => $singleStudent,
        'auto_select' => $singleStudent,

        'message' => $singleStudent
            ? 'Confirma el alumno.'
            : 'Selecciona uno o más alumnos.',

        'guardian_scan_token' => $scanToken,
        'expires_in_seconds' => 120,
        'event_type' => $eventType,

        'guardian' => [
            'name' => trim(
                $guardian->first_name
                .' '
                .$guardian->last_name
            ),

            'photo_url' => $guardian->photo_url
                ? asset(
                    ltrim(
                        $guardian->photo_url,
                        '/'
                    )
                )
                : null,

            'status' => $guardian->status,
        ],

        'students' => $studentPayloads,

        'default_selection_tokens' => $singleStudent
            ? $studentSelections->keys()->values()->all()
            : [],
    ];
}

/**
 * Confirma una entrada o salida iniciada con QR de tutor.
 */
public function processGuardianConfirmation(
    array $data,
    object $user,
): array {
    $userValidation = $this->validateUser($user);

    if ($userValidation !== null) {
        return $userValidation;
    }

    try {
        return DB::transaction(function () use ($data, $user): array {
            $scanToken = trim(
                (string) ($data['guardian_scan_token'] ?? '')
            );

            $cacheKey = $this->guardianScanCacheKey($scanToken);
            $context = Cache::get($cacheKey);

            if (! is_array($context)) {
                return [
                    'ok' => false,
                    'http_code' => 410,
                    'decision' => 'denied',
                    'status' => 'guardian_scan_expired',
                    'message' => 'La selección del tutor venció. Escanea nuevamente el QR.',
                ];
            }

            $schoolId = (int) $user->school_id;

            if (
                (int) ($context['school_id'] ?? 0) !== $schoolId
                || (int) ($context['user_id'] ?? 0) !== (int) $user->id
            ) {
                return [
                    'ok' => false,
                    'http_code' => 403,
                    'decision' => 'denied',
                    'status' => 'guardian_scan_invalid_context',
                    'message' => 'La selección no pertenece a esta sesión.',
                ];
            }

            $deviceResult = $this->resolveAuthorizedDevice(
                schoolId: $schoolId,
                user: $user,
                deviceUuid: trim((string) ($data['device_uuid'] ?? '')),
                requireManualPermission: false,
            );

            if (! $deviceResult['ok']) {
                return $deviceResult;
            }

            $device = $deviceResult['device'];
            $area = $deviceResult['area'];

            if ((int) $device->id !== (int) ($context['device_id'] ?? 0)) {
                return [
                    'ok' => false,
                    'http_code' => 403,
                    'decision' => 'denied',
                    'status' => 'guardian_scan_device_mismatch',
                    'message' => 'La selección fue iniciada desde otro dispositivo.',
                ];
            }

            if (! (bool) ($device->allow_guardian_scan ?? false)) {
                return [
                    'ok' => false,
                    'http_code' => 403,
                    'decision' => 'denied',
                    'status' => 'guardian_scan_disabled',
                    'message' => 'Este dispositivo no permite credenciales de tutor.',
                ];
            }

            $guardianCredential = DB::table('guardian_credentials')
                ->where('school_id', $schoolId)
                ->where(
                    'id',
                    (int) ($context['guardian_credential_id'] ?? 0)
                )
                ->where('guardian_id', (int) $context['guardian_id'])
                ->where('status', 'active')
                ->first();

            if (! $guardianCredential) {
                return [
                    'ok' => false,
                    'http_code' => 403,
                    'decision' => 'denied',
                    'status' => 'invalid_credential',
                    'message' => 'La credencial del tutor ya no está disponible.',
                ];
            }

            if (
                $guardianCredential->expires_at !== null
                && Carbon::parse($guardianCredential->expires_at)->isPast()
            ) {
                return [
                    'ok' => false,
                    'http_code' => 403,
                    'decision' => 'denied',
                    'status' => 'expired',
                    'message' => 'La credencial del tutor está vencida.',
                ];
            }

            $guardian = DB::table('guardians')
                ->where('school_id', $schoolId)
                ->where('id', (int) $context['guardian_id'])
                ->where('status', 'active')
                ->first();

            if (! $guardian) {
                return [
                    'ok' => false,
                    'http_code' => 403,
                    'decision' => 'denied',
                    'status' => 'inactive_guardian',
                    'message' => 'El tutor está inactivo o no disponible.',
                ];
            }

            $requestedSelectionTokens = collect(
    $data['student_selection_tokens'] ?? []
)
    ->map(fn ($token): string => trim((string) $token))
    ->filter(fn (string $token): bool => $token !== '')
    ->unique()
    ->values();

if ($requestedSelectionTokens->isEmpty()) {
    return [
        'ok' => false,
        'http_code' => 422,
        'decision' => 'denied',
        'status' => 'student_selection_required',
        'message' => 'Selecciona al menos un alumno.',
    ];
}

$availableSelections = collect(
    $context['student_selections'] ?? []
);

$invalidSelection = $requestedSelectionTokens
    ->contains(
        fn (string $token): bool => ! $availableSelections->has($token)
    );

if ($invalidSelection) {
    return [
        'ok' => false,
        'http_code' => 403,
        'decision' => 'denied',
        'status' => 'invalid_student_selection',
        'message' => 'La selección de alumnos no es válida.',
    ];
}

$requestedStudentIds = $requestedSelectionTokens
    ->map(
        fn (string $token): int =>
            (int) $availableSelections->get($token)
    )
    ->filter(fn (int $id): bool => $id > 0)
    ->unique()
    ->values();

         $eventMode = strtolower(
    trim(
        (string) (
            $context['event_type']
            ?? 'entry'
        )
    )
);

$scannedAt = isset($data['scanned_at'])
    ? $this->resolveScannedAt(
        $data['scanned_at']
    )
    : Carbon::parse(
        (string) $context['scanned_at'],
        config('app.timezone')
    );

$results = [];

foreach ($requestedStudentIds as $studentId) {
    /*
     * Primero cargamos al alumno porque en modo auto
     * necesitamos su grupo, horario y asistencia.
     */
    $student = DB::table('students')
        ->where('school_id', $schoolId)
        ->where('id', $studentId)
        ->where('status', 'active')
        ->first();

    if (! $student) {
        $results[] = [
            'student_id' => $studentId,
            'ok' => false,
            'decision' => 'denied',
            'event_type' => null,
            'status' => 'inactive_student',
            'message' =>
                'Alumno inactivo o no disponible.',
        ];

        continue;
    }

    /*
     * Convierte auto en entry o exit para este alumno.
     * Dos hijos del mismo tutor pueden obtener eventos
     * diferentes por pertenecer a grupos distintos.
     */
    $studentEventType =
        $this->resolveAutomaticStudentEventType(
            eventMode: $eventMode,
            schoolId: $schoolId,
            student: $student,
            scannedAt: $scannedAt,
        );

    if (
        ! in_array(
            $studentEventType,
            ['entry', 'exit'],
            true
        )
    ) {
        $results[] = [
            'student_id' => $studentId,
            'ok' => false,
            'decision' => 'denied',
            'event_type' => $studentEventType,
            'status' => 'invalid_event',
            'message' =>
                'No se pudo determinar si corresponde entrada o salida.',
        ];

        continue;
    }

    /*
     * El permiso también depende del evento real,
     * no del modo auto.
     */
    $permissionColumn =
        $studentEventType === 'entry'
            ? 'can_drop_off'
            : 'can_pick_up';

    $relation = DB::table(
        'student_guardians'
    )
        ->where(
            'student_id',
            $studentId
        )
        ->where(
            'guardian_id',
            $guardian->id
        )
        ->where(
            'status',
            'active'
        )
        ->where(
            $permissionColumn,
            true
        )
        ->where(
            function ($query) use (
                $scannedAt
            ): void {
                $query
                    ->whereNull('valid_from')
                    ->orWhereDate(
                        'valid_from',
                        '<=',
                        $scannedAt
                            ->toDateString()
                    );
            }
        )
        ->where(
            function ($query) use (
                $scannedAt
            ): void {
                $query
                    ->whereNull('valid_until')
                    ->orWhereDate(
                        'valid_until',
                        '>=',
                        $scannedAt
                            ->toDateString()
                    );
            }
        )
        ->first();

    if (! $relation) {
        $results[] = [
            'student_id' => $studentId,
            'ok' => false,
            'decision' => 'denied',
            'event_type' =>
                $studentEventType,
            'status' =>
                'guardian_not_authorized',

            'message' =>
                $studentEventType === 'entry'
                    ? 'El tutor no está autorizado para entregar a este alumno.'
                    : 'El tutor no está autorizado para recoger a este alumno.',
        ];

        continue;
    }

    $result =
        $this->processStudentEvent(
            schoolId: $schoolId,
            device: $device,
            area: $area,
            student: $student,
            credential: null,
            user: $user,

            /*
             * Nunca enviar "auto" a processStudentEvent().
             */
            eventType:
                $studentEventType,

            scannedAt: $scannedAt,
            source: 'guardian_qr',

            readerType: (string) (
                $context['reader_type']
                ?? 'camera_qr'
            ),

            reason: null,
            manual: false,
        );

    if (! empty($result['log_id'])) {
        DB::table('access_logs')
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'id',
                (int) $result['log_id']
            )
            ->update([
                'guardian_id' =>
                    (int) $guardian->id,

                'guardian_credential_id' =>
                    (int) $guardianCredential->id,

                'performed_for' =>
                    'guardian',

                'updated_at' =>
                    now(),
            ]);
    }

    $result['scan_type'] =
        'guardian';

    $result['guardian'] = [
        'name' => trim(
            $guardian->first_name
            .' '
            .$guardian->last_name
        ),

        'photo_url' =>
            $guardian->photo_url
                ? asset(
                    ltrim(
                        $guardian->photo_url,
                        '/'
                    )
                )
                : null,

        'relationship' =>
            $relation->relationship,
    ];

    /*
     * No exponer identificadores internos.
     */
    if (
        isset($result['student'])
        && is_array($result['student'])
    ) {
        unset(
            $result['student']['id'],
            $result['student']['group_id'],
            $result['student']['cycle_id'],
            $result['student']['enrollment_id'],
        );
    }

    $results[] = $result;
}

            Cache::forget($cacheKey);

            $allowedCount = collect($results)
                ->where('ok', true)
                ->count();

            $deniedCount = count($results) - $allowedCount;

            return [
                'ok' => $allowedCount > 0,
                'http_code' => $allowedCount > 0 ? 200 : 403,
                'decision' => $deniedCount === 0
                    ? 'allowed'
                    : ($allowedCount > 0 ? 'partial' : 'denied'),
                'status' => $deniedCount === 0
                    ? 'guardian_confirmed'
                    : ($allowedCount > 0
                        ? 'guardian_partially_confirmed'
                        : 'guardian_denied'),
                'scan_type' => 'guardian',
                'requires_selection' => false,
               'event_type' => $eventMode,
                'message' => $deniedCount === 0
                    ? 'Movimiento registrado correctamente.'
                    : ($allowedCount > 0
                        ? 'Algunos alumnos fueron procesados y otros no.'
                        : 'No se pudo registrar ningún alumno.'),
               'guardian' => [
    'name' => trim(
        $guardian->first_name.' '.$guardian->last_name
    ),
    'photo_url' => $guardian->photo_url
        ? asset(ltrim($guardian->photo_url, '/'))
        : null,
],
                'summary' => [
                    'total' => count($results),
                    'allowed' => $allowedCount,
                    'denied' => $deniedCount,
                ],
                'results' => $results,
            ];
        }, 3);
    } catch (Throwable $exception) {
        report($exception);

        return [
            'ok' => false,
            'http_code' => 500,
            'decision' => 'denied',
            'status' => 'server_error',
            'message' => 'No se pudo confirmar el movimiento del tutor.',
            'debug' => app()->environment('local')
                ? $exception->getMessage()
                : null,
        ];
    }
}

    /**
     * Valida que el usuario pueda operar sobre su escuela.
     */
    private function validateUser(?object $user): ?array
    {
        if (! $user) {
            return [
                'ok' => false,
                'http_code' => 401,
                'decision' => 'denied',
                'message' => 'Usuario no autenticado.',
            ];
        }

        if (! $user->school_id) {
            return [
                'ok' => false,
                'http_code' => 403,
                'decision' => 'denied',
                'message' => 'Usuario sin institución asignada.',
            ];
        }

        if ($user->status !== 'active') {
            return [
                'ok' => false,
                'http_code' => 403,
                'decision' => 'denied',
                'message' => 'Usuario inactivo o bloqueado.',
            ];
        }

        $schoolIsActive = DB::table('schools')
            ->where('id', $user->school_id)
            ->where('status', 'active')
            ->exists();

        if (! $schoolIsActive) {
            return [
                'ok' => false,
                'http_code' => 403,
                'decision' => 'denied',
                'message' => 'La institución está suspendida o inactiva.',
            ];
        }

        return null;
    }

    /**
     * Obtiene y valida el dispositivo dentro de la escuela actual.
     */
    private function resolveAuthorizedDevice(
        int $schoolId,
        object $user,
        string $deviceUuid,
        bool $requireManualPermission,
    ): array {
        if ($deviceUuid === '') {
            return [
                'ok' => false,
                'http_code' => 422,
                'decision' => 'denied',
                'status' => 'device_required',
                'message' => 'No se recibió el identificador del dispositivo.',
            ];
        }

        $device = DB::table('access_devices')
            ->where('school_id', $schoolId)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if (! $device) {
            return [
                'ok' => false,
                'http_code' => 404,
                'decision' => 'denied',
                'status' => 'device_not_found',
                'message' => 'Dispositivo no registrado.',
            ];
        }

        if ($device->status !== 'active') {
            return [
                'ok' => false,
                'http_code' => 403,
                'decision' => 'denied',
                'status' => 'device_inactive',
                'message' => 'Dispositivo bloqueado o inactivo.',
            ];
        }

        $campusIsValid = DB::table('campuses')
            ->where('id', $device->campus_id)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->exists();

        if (! $campusIsValid) {
            return [
                'ok' => false,
                'http_code' => 422,
                'decision' => 'denied',
                'status' => 'invalid_campus',
                'message' => 'El dispositivo no tiene un plantel válido.',
            ];
        }

        $isAdminRole = in_array(
            $user->role,
            self::ADMIN_ROLES,
            true
        );

        $requiresAssignment = in_array(
            $user->role,
            self::ASSIGNED_DEVICE_ROLES,
            true
        );

        if (
            ! $isAdminRole
            && $requiresAssignment
            && (int) $device->assigned_to_user_id !== (int) $user->id
        ) {
            return [
                'ok' => false,
                'http_code' => 403,
                'decision' => 'denied',
                'status' => 'device_not_assigned',
                'message' => 'Este dispositivo no pertenece a la sesión actual.',
            ];
        }

        if (
            $requireManualPermission
            && ! (bool) $device->allow_manual_search
        ) {
            return [
                'ok' => false,
                'http_code' => 403,
                'decision' => 'denied',
                'status' => 'manual_not_allowed',
                'message' => 'Este dispositivo no permite registros manuales.',
            ];
        }

        $area = null;

        if ($device->area_id !== null) {
            $area = DB::table('areas')
                ->where('id', $device->area_id)
                ->where('school_id', $schoolId)
                ->where('campus_id', $device->campus_id)
                ->where('status', 'active')
                ->first();

            if (! $area) {
                return [
                    'ok' => false,
                    'http_code' => 422,
                    'decision' => 'denied',
                    'status' => 'invalid_area',
                    'message' => 'El dispositivo tiene un área inválida.',
                ];
            }
        }

        if (
            $device->mode === 'restricted_access'
            && ! $area
        ) {
            return [
                'ok' => false,
                'http_code' => 422,
                'decision' => 'denied',
                'status' => 'area_required',
                'message' => 'El dispositivo no tiene un área configurada.',
            ];
        }

        DB::table('access_devices')
            ->where('id', $device->id)
            ->where('school_id', $schoolId)
            ->update([
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'ok' => true,
            'device' => $device,
            'area' => $area,
        ];
    }

  private function processStudentEvent(
    int $schoolId,
    object $device,
    ?object $area,
    object $student,
    ?object $credential,
    object $user,
    string $eventType,
    Carbon $scannedAt,
    string $source,
    string $readerType,
    ?string $reason,
    bool $manual,
): array {
    /*
     * Bloquea al alumno para evitar escaneos simultáneos.
     */
    $lockedStudent = DB::table('students')
        ->where('school_id', $schoolId)
        ->where('id', $student->id)
        ->where('status', 'active')
        ->lockForUpdate()
        ->first();

    if (! $lockedStudent) {
        return [
            'ok' => false,
            'http_code' => 403,
            'decision' => 'denied',
            'status' => 'inactive_student',
            'message' => 'Acceso denegado: el alumno está inactivo o no disponible.',
        ];
    }

    /*
     * Se obtiene el ciclo operativo de la escuela.
     */
    $activeCycle = DB::table('academic_cycles')
        ->where('school_id', $schoolId)
        ->where('is_active', true)
        ->where('status', 'active')
        ->lockForUpdate()
        ->first();

    if (! $activeCycle) {
        return $this->denyStudentEvent(
            schoolId: $schoolId,
            device: $device,
            area: $area,
            student: $lockedStudent,
            credential: $credential,
            user: $user,
            eventType: $eventType,
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            status: 'no_active_cycle',
            message: 'Acceso denegado: la institución no tiene un ciclo escolar activo.',
            reason: 'No existe un ciclo escolar activo.'
        );
    }

    $cycleStartsOn = Carbon::parse(
    $activeCycle->starts_on
)->startOfDay();

$cycleEndsOn = Carbon::parse(
    $activeCycle->ends_on
)->endOfDay();

if ($scannedAt->lt($cycleStartsOn)) {
    return $this->denyStudentEvent(
        schoolId: $schoolId,
        device: $device,
        area: $area,
        student: $lockedStudent,
        credential: $credential,
        user: $user,
        eventType: $eventType,
        scannedAt: $scannedAt,
        source: $source,
        readerType: $readerType,
        status: 'cycle_not_started',
        message: 'Acceso denegado: el ciclo escolar activo todavía no comienza.',
        reason: 'El ciclo '
            .$activeCycle->name
            .' inicia el '
            .$cycleStartsOn->format('d/m/Y')
            .'.'
    );
}

if ($scannedAt->gt($cycleEndsOn)) {
    return $this->denyStudentEvent(
        schoolId: $schoolId,
        device: $device,
        area: $area,
        student: $lockedStudent,
        credential: $credential,
        user: $user,
        eventType: $eventType,
        scannedAt: $scannedAt,
        source: $source,
        readerType: $readerType,
        status: 'cycle_ended',
        message: 'Acceso denegado: el ciclo escolar activo ya finalizó.',
        reason: 'El ciclo '
            .$activeCycle->name
            .' finalizó el '
            .$cycleEndsOn->format('d/m/Y')
            .'.'
    );
}

    /*
     * La inscripción del ciclo activo es la fuente oficial
     * para grupo, plantel y horarios.
     */
    $enrollment = DB::table('student_enrollments as se')
        ->leftJoin(
            'school_groups as sg',
            'sg.id',
            '=',
            'se.school_group_id'
        )
        ->where('se.school_id', $schoolId)
        ->where('se.student_id', $lockedStudent->id)
        ->where(
            'se.academic_cycle_id',
            $activeCycle->id
        )
        ->select([
            'se.id',
            'se.status',
            'se.school_group_id',
            'se.campus_id',
            'se.enrolled_on',
            'se.completed_on',
            'se.withdrawn_on',

            'sg.id as group_id',
            'sg.name as group_name',
            'sg.status as group_status',
            'sg.campus_id as group_campus_id',
            'sg.academic_cycle_id as group_cycle_id',
             'sg.requires_guardian_scan',
        ])
        ->lockForUpdate()
        ->first();

    if (! $enrollment) {
        return $this->denyStudentEvent(
            schoolId: $schoolId,
            device: $device,
            area: $area,
            student: $lockedStudent,
            credential: $credential,
            user: $user,
            eventType: $eventType,
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            status: 'student_not_enrolled',
            message: 'Acceso denegado: el alumno no está inscrito en el ciclo vigente.',
            reason: 'Alumno sin inscripción en el ciclo '.$activeCycle->name.'.'
        );
    }

    if ($enrollment->status !== 'active') {
        $statusLabel = match ($enrollment->status) {
            'withdrawn' => 'dado de baja',
            'graduated' => 'marcado como egresado',
            'completed' => 'con inscripción concluida',
            'not_reenrolled' => 'no reinscrito',
            'transferred' => 'transferido',
            default => 'sin inscripción vigente',
        };

        return $this->denyStudentEvent(
            schoolId: $schoolId,
            device: $device,
            area: $area,
            student: $lockedStudent,
            credential: $credential,
            user: $user,
            eventType: $eventType,
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            status: 'inactive_enrollment',
            message: 'Acceso denegado: el alumno está '.$statusLabel.'.',
            reason: 'Estado de inscripción: '.$enrollment->status.'.'
        );
    }

    if (
        ! $enrollment->group_id
        || $enrollment->group_status !== 'active'
        || (int) $enrollment->group_cycle_id
            !== (int) $activeCycle->id
    ) {
        return $this->denyStudentEvent(
            schoolId: $schoolId,
            device: $device,
            area: $area,
            student: $lockedStudent,
            credential: $credential,
            user: $user,
            eventType: $eventType,
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            status: 'invalid_enrollment_group',
            message: 'Acceso denegado: la inscripción no tiene un grupo válido en el ciclo vigente.',
            reason: 'Grupo inexistente, inactivo o perteneciente a otro ciclo.'
        );
    }

    /*
     * El plantel se toma de la inscripción y del grupo,
     * no únicamente de students.campus_id.
     */
    $enrollmentCampusId = (int) (
        $enrollment->campus_id
        ?: $enrollment->group_campus_id
    );

    if (
        $enrollmentCampusId
        !== (int) $device->campus_id
    ) {
        return $this->denyStudentEvent(
            schoolId: $schoolId,
            device: $device,
            area: $area,
            student: $lockedStudent,
            credential: $credential,
            user: $user,
            eventType: $eventType,
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            status: 'different_campus',
            message: 'Acceso denegado: el alumno pertenece a otro plantel.',
            reason: 'El plantel de la inscripción no coincide con el dispositivo.'
        );
    }

    /*
     * Creamos el alumno operativo para que el resto del servicio
     * utilice el grupo y plantel de la inscripción vigente.
     */
    $operationalStudent = clone $lockedStudent;

    $operationalStudent->current_group_id =
        (int) $enrollment->group_id;

    $operationalStudent->campus_id =
        $enrollmentCampusId;

    $operationalStudent->active_cycle_id =
        (int) $activeCycle->id;

    $operationalStudent->active_cycle_name =
        $activeCycle->name;

    $operationalStudent->active_enrollment_id =
        (int) $enrollment->id;

        /*
 * Regla efectiva de tutor:
 * - El override individual tiene prioridad.
 * - Si es NULL, se usa la configuración del grupo.
 */
$requiresGuardianScan = $lockedStudent
    ->requires_guardian_scan_override !== null
        ? (bool) $lockedStudent->requires_guardian_scan_override
        : (bool) ($enrollment->requires_guardian_scan ?? false);

$operationalStudent->requires_guardian_scan =
    $requiresGuardianScan;



    /*
 * La restricción solo aplica a entrada y salida.
 *
 * No bloqueamos:
 * - Escaneo mediante tutor.
 * - Registro manual realizado por prefecto.
 * - Acceso a áreas internas.
 */
if (
    $requiresGuardianScan
    && in_array($eventType, ['entry', 'exit'], true)
    && $source !== 'guardian_qr'
    && ! $manual
) {
    return $this->denyStudentEvent(
        schoolId: $schoolId,
        device: $device,
        area: $area,
        student: $operationalStudent,
        credential: $credential,
        user: $user,
        eventType: $eventType,
        scannedAt: $scannedAt,
        source: $source,
        readerType: $readerType,
        status: 'guardian_required',
        message: 'Este alumno requiere la credencial de un tutor autorizado.',
        reason: 'El grupo o el alumno tiene activado el control obligatorio mediante tutor.',
    );
}





    /*
     * Acceso a áreas internas.
     */
    if (
        $eventType === 'access'
        || $device->mode === 'restricted_access'
    ) {
        return $this->registerAreaAccess(
            schoolId: $schoolId,
            device: $device,
            area: $area,
            student: $operationalStudent,
            credential: $credential,
            user: $user,
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            reason: $reason,
        );
    }

    /*
     * Registro de entrada.
     */
    if ($eventType === 'entry') {
        return $this->registerEntry(
            schoolId: $schoolId,
            device: $device,
            area: $area,
            student: $operationalStudent,
            credential: $credential,
            user: $user,
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            reason: $reason,
            manual: $manual,
        );
    }

    /*
     * Registro de salida.
     */
    if ($eventType === 'exit') {
        return $this->registerExit(
            schoolId: $schoolId,
            device: $device,
            area: $area,
            student: $operationalStudent,
            credential: $credential,
            user: $user,
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            reason: $reason,
            manual: $manual,
        );
    }

    return $this->denyStudentEvent(
        schoolId: $schoolId,
        device: $device,
        area: $area,
        student: $operationalStudent,
        credential: $credential,
        user: $user,
        eventType: $eventType,
        scannedAt: $scannedAt,
        source: $source,
        readerType: $readerType,
        status: 'invalid_event',
        message: 'Acceso denegado: el tipo de evento no es válido.',
        reason: 'Tipo de evento inválido.'
    );
}


private function denyStudentEvent(
    int $schoolId,
    object $device,
    ?object $area,
    object $student,
    ?object $credential,
    object $user,
    string $eventType,
    Carbon $scannedAt,
    string $source,
    string $readerType,
    string $status,
    string $message,
    string $reason,
): array {
    $safeEventType = in_array(
        $eventType,
        [
            'entry',
            'exit',
            'access',
        ],
        true
    )
        ? $eventType
        : 'access';

    $logId = $this->writeLog(
        schoolId: $schoolId,
        campusId: (int) $device->campus_id,
        areaId: $area?->id,
        deviceId: (int) $device->id,
        studentId: (int) $student->id,
        credentialId: $credential?->id,
        userId: (int) $user->id,
        eventType: $safeEventType,
        eventStatus: $status,
        decision: 'denied',
        scannedAt: $scannedAt,
        source: $source,
        readerType: $readerType,
        action: 'none',
        minutesLate: null,
        reason: $reason,
        notes: $message,
    );

    return [
        'ok' => false,
        'http_code' => 403,
        'decision' => 'denied',
        'event_type' => $safeEventType,
        'status' => $status,
        'message' => $message,
        'reason' => $reason,
        'log_id' => $logId,

        'student' => $this->studentPayload(
            schoolId: $schoolId,
            student: $student,
        ),

        'lock' => [
            'enabled' => (bool) $device->can_unlock,
            'action' => 'none',
            'relay_pulse_ms' => 0,
        ],
    ];
}

    private function registerEntry(
        int $schoolId,
        object $device,
        ?object $area,
        object $student,
        ?object $credential,
        object $user,
        Carbon $scannedAt,
        string $source,
        string $readerType,
        ?string $reason,
        bool $manual,
    ): array {
        $date = $scannedAt->toDateString();

        $existing = DB::table('daily_attendance')
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->whereDate('date', $date)
            ->lockForUpdate()
            ->first();

        if ($existing && $existing->entry_at) {
            $logId = $this->writeLog(
                schoolId: $schoolId,
                campusId: (int) $device->campus_id,
                areaId: $area?->id,
                deviceId: (int) $device->id,
                studentId: (int) $student->id,
                credentialId: $credential?->id,
                userId: (int) $user->id,
                eventType: 'entry',
                eventStatus: 'duplicate',
                decision: 'duplicate',
                scannedAt: $scannedAt,
                source: $source,
                readerType: $readerType,
                reason: $reason ?: 'Entrada ya registrada',
                notes: $reason,
            );

            return [
                'ok' => true,
                'http_code' => 200,
                'decision' => 'duplicate',
                'event_type' => 'entry',
                'status' => 'duplicate',
                'message' => 'El alumno ya tenía entrada registrada.',
                'log_id' => $logId,
                'student' => $this->studentPayload(
                    schoolId: $schoolId,
                    student: $student,
                ),
                'attendance' => [
                    'entry_at' => Carbon::parse(
                        $existing->entry_at
                    )->format('H:i'),
                    'minutes_late' => (int) (
                        $existing->minutes_late ?? 0
                    ),
                ],
            ];
        }

        [$entryStatus, $minutesLate] = $this->resolveEntryStatus(
            schoolId: $schoolId,
            groupId: (int) $student->current_group_id,
            scannedAt: $scannedAt,
        );

        $eventStatus = $manual
            ? 'manual'
            : $entryStatus;

        $message = $manual
            ? 'Entrada manual registrada.'
            : match ($entryStatus) {
                'on_time' => 'Entrada registrada.',
                'late' => 'Entrada registrada con retardo.',
                'very_late' => 'Entrada extemporánea registrada.',
                default => 'Entrada registrada.',
            };

        $logId = $this->writeLog(
            schoolId: $schoolId,
            campusId: (int) $device->campus_id,
            areaId: $area?->id,
            deviceId: (int) $device->id,
            studentId: (int) $student->id,
            credentialId: $credential?->id,
            userId: (int) $user->id,
            eventType: 'entry',
            eventStatus: $eventStatus,
            decision: 'allowed',
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            minutesLate: $minutesLate,
            reason: $reason,
            notes: $reason,
        );

        $attendanceStatus = $entryStatus === 'on_time'
            ? 'present'
            : 'late';

        if ($existing) {
            DB::table('daily_attendance')
                ->where('id', $existing->id)
                ->where('school_id', $schoolId)
                ->update([
                    'campus_id' => $device->campus_id,
                    'group_id' => $student->current_group_id,
                    'entry_log_id' => $logId,
                    'attendance_status' => $attendanceStatus,
                    'entry_at' => $scannedAt,
                    'minutes_late' => $minutesLate,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('daily_attendance')->insert([
                'school_id' => $schoolId,
                'campus_id' => $device->campus_id,
                'student_id' => $student->id,
                'group_id' => $student->current_group_id,
                'date' => $date,
                'entry_log_id' => $logId,
                'exit_log_id' => null,
                'attendance_status' => $attendanceStatus,
                'entry_at' => $scannedAt,
                'exit_at' => null,
                'minutes_late' => $minutesLate,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->createGuardianNotifications(
            schoolId: $schoolId,
            studentId: (int) $student->id,
            type: $entryStatus === 'on_time' ? 'entry' : 'late',
            title: $entryStatus === 'on_time'
                ? 'Entrada registrada'
                : 'Retardo registrado',
            body: "{$student->first_name} registró entrada a las {$scannedAt->format('H:i')}.",
            referenceType: 'access_log',
            referenceId: $logId,
        );

        return [
            'ok' => true,
            'http_code' => 200,
            'decision' => 'allowed',
            'event_type' => 'entry',
            'status' => $eventStatus,
            'attendance_status' => $attendanceStatus,
            'message' => $message,
            'log_id' => $logId,
            'student' => $this->studentPayload(
                schoolId: $schoolId,
                student: $student,
            ),
            'attendance' => [
                'entry_at' => $scannedAt->format('H:i'),
                'minutes_late' => $minutesLate,
            ],
        ];
    }

    private function registerExit(
        int $schoolId,
        object $device,
        ?object $area,
        object $student,
        ?object $credential,
        object $user,
        Carbon $scannedAt,
        string $source,
        string $readerType,
        ?string $reason,
        bool $manual,
    ): array {
        $date = $scannedAt->toDateString();

        $existing = DB::table('daily_attendance')
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->whereDate('date', $date)
            ->lockForUpdate()
            ->first();

        if ($existing && $existing->exit_at) {
            $logId = $this->writeLog(
                schoolId: $schoolId,
                campusId: (int) $device->campus_id,
                areaId: $area?->id,
                deviceId: (int) $device->id,
                studentId: (int) $student->id,
                credentialId: $credential?->id,
                userId: (int) $user->id,
                eventType: 'exit',
                eventStatus: 'duplicate',
                decision: 'duplicate',
                scannedAt: $scannedAt,
                source: $source,
                readerType: $readerType,
                reason: $reason ?: 'Salida ya registrada',
                notes: $reason,
            );

            return [
                'ok' => true,
                'http_code' => 200,
                'decision' => 'duplicate',
                'event_type' => 'exit',
                'status' => 'duplicate',
                'message' => 'El alumno ya tenía salida registrada.',
                'log_id' => $logId,
                'student' => $this->studentPayload(
                    schoolId: $schoolId,
                    student: $student,
                ),
                'attendance' => [
                    'exit_at' => Carbon::parse(
                        $existing->exit_at
                    )->format('H:i'),
                ],
            ];
        }

        $exitStatus = $this->resolveExitStatus(
            schoolId: $schoolId,
            groupId: (int) $student->current_group_id,
            scannedAt: $scannedAt,
        );

        $eventStatus = $manual
            ? 'manual'
            : $exitStatus;

        $logId = $this->writeLog(
            schoolId: $schoolId,
            campusId: (int) $device->campus_id,
            areaId: $area?->id,
            deviceId: (int) $device->id,
            studentId: (int) $student->id,
            credentialId: $credential?->id,
            userId: (int) $user->id,
            eventType: 'exit',
            eventStatus: $eventStatus,
            decision: 'allowed',
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            reason: $reason,
            notes: $reason,
        );

        $attendanceStatus = $existing?->attendance_status
            ?? 'partial';

        if (
            $exitStatus === 'early_exit'
            && $existing
            && $existing->entry_at
        ) {
            $attendanceStatus = 'early_exit';
        }

        if ($existing) {
            DB::table('daily_attendance')
                ->where('id', $existing->id)
                ->where('school_id', $schoolId)
                ->update([
                    'exit_log_id' => $logId,
                    'exit_at' => $scannedAt,
                    'attendance_status' => $attendanceStatus,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('daily_attendance')->insert([
                'school_id' => $schoolId,
                'campus_id' => $device->campus_id,
                'student_id' => $student->id,
                'group_id' => $student->current_group_id,
                'date' => $date,
                'entry_log_id' => null,
                'exit_log_id' => $logId,
                'attendance_status' => 'partial',
                'entry_at' => null,
                'exit_at' => $scannedAt,
                'minutes_late' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->createGuardianNotifications(
            schoolId: $schoolId,
            studentId: (int) $student->id,
            type: 'exit',
            title: $exitStatus === 'early_exit'
                ? 'Salida anticipada'
                : 'Salida registrada',
            body: "{$student->first_name} registró salida a las {$scannedAt->format('H:i')}.",
            referenceType: 'access_log',
            referenceId: $logId,
        );

        return [
            'ok' => true,
            'http_code' => 200,
            'decision' => 'allowed',
            'event_type' => 'exit',
            'status' => $eventStatus,
            'attendance_status' => $attendanceStatus,
            'message' => $manual
                ? 'Salida manual registrada.'
                : (
                    $exitStatus === 'early_exit'
                        ? 'Salida anticipada registrada.'
                        : 'Salida registrada.'
                ),
            'log_id' => $logId,
            'student' => $this->studentPayload(
                schoolId: $schoolId,
                student: $student,
            ),
            'attendance' => [
                'exit_at' => $scannedAt->format('H:i'),
            ],
        ];
    }

    private function registerAreaAccess(
        int $schoolId,
        object $device,
        ?object $area,
        object $student,
        ?object $credential,
        object $user,
        Carbon $scannedAt,
        string $source,
        string $readerType,
        ?string $reason,
    ): array {
        $allowed = $this->isAllowedInArea(
            schoolId: $schoolId,
            area: $area,
            student: $student,
            scannedAt: $scannedAt,
        );

        $logId = $this->writeLog(
            schoolId: $schoolId,
            campusId: (int) $device->campus_id,
            areaId: $area?->id,
            deviceId: (int) $device->id,
            studentId: (int) $student->id,
            credentialId: $credential?->id,
            userId: (int) $user->id,
            eventType: 'access',
            eventStatus: $allowed ? 'allowed' : 'denied',
            decision: $allowed ? 'allowed' : 'denied',
            scannedAt: $scannedAt,
            source: $source,
            readerType: $readerType,
            action: $allowed && (bool) $device->can_unlock
                ? 'unlock'
                : 'none',
            reason: $allowed
                ? $reason
                : ($reason ?: 'Sin permiso para esta área'),
            notes: $reason,
        );

        return [
            'ok' => $allowed,
            'http_code' => $allowed ? 200 : 403,
            'decision' => $allowed ? 'allowed' : 'denied',
            'event_type' => 'access',
            'status' => $allowed ? 'allowed' : 'denied',
            'message' => $allowed
                ? 'Acceso autorizado.'
                : 'Acceso denegado.',
            'log_id' => $logId,
            'student' => $this->studentPayload(
                schoolId: $schoolId,
                student: $student,
            ),
            'area' => [
                'id' => $area?->id,
                'name' => $area?->name,
                'type' => $area?->type,
            ],
            'lock' => [
                'enabled' => (bool) $device->can_unlock,
                'action' => $allowed && (bool) $device->can_unlock
                    ? 'unlock'
                    : 'none',
                'relay_pulse_ms' => $allowed && (bool) $device->can_unlock
                    ? 1200
                    : 0,
            ],
        ];
    }


    private function resolveAutomaticStudentEventType(
    string $eventMode,
    int $schoolId,
    object $student,
    Carbon $scannedAt,
): string {
    if ($eventMode !== 'auto') {
        return $eventMode;
    }

    $groupId = (int) (
        $student->current_group_id
        ?? 0
    );

    if ($groupId <= 0) {
        return 'entry';
    }

    $group = DB::table('school_groups')
        ->where('school_id', $schoolId)
        ->where('id', $groupId)
        ->where('status', 'active')
        ->first([
            'id',
            'auto_transition_minutes',
        ]);

    if (! $group) {
        return 'entry';
    }

    $schedule = DB::table(
        'group_access_schedules'
    )
        ->where('school_id', $schoolId)
        ->where('group_id', $groupId)
        ->where(
            'weekday',
            $scannedAt->isoWeekday()
        )
        ->where('status', 'active')
        ->first([
            'entry_time',
            'grace_until',
            'late_until',
            'exit_time',
        ]);

    if (! $schedule) {
        return 'entry';
    }

    $attendance = DB::table(
        'daily_attendance'
    )
        ->where('school_id', $schoolId)
        ->where(
            'student_id',
            (int) $student->id
        )
        ->whereDate(
            'date',
            $scannedAt->toDateString()
        )
        ->first([
            'entry_at',
            'exit_at',
        ]);

    /*
     * Nunca registrar una salida sin que exista
     * una entrada previa.
     */
    if (
        ! $attendance
        || $attendance->entry_at === null
    ) {
        return 'entry';
    }

    /*
     * Si ya registró salida, dejamos que registerExit()
     * responda como duplicado.
     */
    if ($attendance->exit_at !== null) {
        return 'exit';
    }

    $transitionMinutes = max(
        0,
        min(
            120,
            (int) (
                $group->auto_transition_minutes
                ?? 30
            )
        )
    );

    $exitStartsAt = Carbon::parse(
        $scannedAt->toDateString()
        .' '
        .$schedule->exit_time,
        config('app.timezone')
    )->subMinutes(
        $transitionMinutes
    );

    return $scannedAt->greaterThanOrEqualTo(
        $exitStartsAt
    )
        ? 'exit'
        : 'entry';
}



private function resolveEventType(
    ?string $requestedEventType,
    object $device,
): string {
    if ($device->mode === 'restricted_access') {
        return 'access';
    }

    $deviceDefault = strtolower(
        trim(
            (string) (
                $device->default_event_type
                ?? 'entry'
            )
        )
    );

    if ($device->device_type === 'kiosk') {
        return in_array(
            $deviceDefault,
            [
                'auto',
                'entry',
                'exit',
            ],
            true
        )
            ? $deviceDefault
            : 'entry';
    }

    $requested = strtolower(
        trim(
            (string) $requestedEventType
        )
    );

    if (
        in_array(
            $requested,
            [
                'auto',
                'entry',
                'exit',
            ],
            true
        )
    ) {
        return $requested;
    }

    return in_array(
        $deviceDefault,
        [
            'auto',
            'entry',
            'exit',
        ],
        true
    )
        ? $deviceDefault
        : 'entry';
}
    private function resolveReaderType(?string $readerType): string
    {
        $readerType = trim((string) $readerType);

        if (
            $readerType === ''
            || ! in_array(
                $readerType,
                self::ALLOWED_READER_TYPES,
                true
            )
        ) {
            return 'camera_qr';
        }

        return $readerType;
    }

    private function resolveScannedAt(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            return now(config('app.timezone'));
        }

        $scannedAt = Carbon::parse(
            $value,
            config('app.timezone')
        );

        /*
         * No se permiten horas futuras.
         * Se usa la hora del servidor ante relojes incorrectos.
         */
        if ($scannedAt->greaterThan(now()->addMinute())) {
            return now(config('app.timezone'));
        }

        return $scannedAt;
    }

    private function isAllowedInArea(
        int $schoolId,
        ?object $area,
        object $student,
        Carbon $scannedAt,
    ): bool {
        if (! $area) {
            return true;
        }

        if (
            ! in_array(
                $area->type,
                ['restricted', 'lab', 'storage'],
                true
            )
        ) {
            return true;
        }

        $time = $scannedAt->format('H:i:s');
        $weekday = $scannedAt->isoWeekday();

        return DB::table('area_access_rules')
            ->where('school_id', $schoolId)
            ->where('area_id', $area->id)
            ->where('status', 'active')
            ->where(function ($query) use ($weekday): void {
                $query
                    ->whereNull('weekday')
                    ->orWhere('weekday', $weekday);
            })
            ->where(function ($query) use ($time): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhere(function ($subquery) use ($time): void {
                        $subquery
                            ->where('starts_at', '<=', $time)
                            ->where('ends_at', '>=', $time);
                    });
            })
            ->where(function ($query) use ($student): void {
                $query
                    ->where(function ($subquery) use ($student): void {
                        $subquery
                            ->where('applies_to_type', 'student')
                            ->where('applies_to_id', $student->id);
                    })
                    ->orWhere(function ($subquery) use ($student): void {
                        $subquery
                            ->where('applies_to_type', 'group')
                            ->where(
                                'applies_to_id',
                                $student->current_group_id
                            );
                    });
            })
            ->exists();
    }

   private function resolveEntryStatus(
    int $schoolId,
    int $groupId,
    Carbon $scannedAt,
): array {
    $schedule = DB::table('group_access_schedules')
        ->where('school_id', $schoolId)
        ->where('group_id', $groupId)
        ->where('weekday', $scannedAt->isoWeekday())
        ->where('status', 'active')
        ->first();

    if (! $schedule) {
        return ['on_time', 0];
    }

    $scanTime = $scannedAt->format('H:i:s');

    if ($scanTime <= $schedule->grace_until) {
        return ['on_time', 0];
    }

    $entryAt = Carbon::parse(
        $scannedAt->toDateString()
        .' '
        .$schedule->entry_time,
        config('app.timezone')
    );

    /*
     * Carbon puede devolver minutos con decimales.
     * Para asistencia usamos minutos completos transcurridos.
     */
    $minutesLate = max(
        0,
        (int) floor(
            $entryAt->diffInMinutes(
                $scannedAt,
                false
            )
        )
    );

    if ($scanTime <= $schedule->late_until) {
        return ['late', $minutesLate];
    }

    return ['very_late', $minutesLate];
}

    private function resolveExitStatus(
        int $schoolId,
        int $groupId,
        Carbon $scannedAt,
    ): string {
        $schedule = DB::table('group_access_schedules')
            ->where('school_id', $schoolId)
            ->where('group_id', $groupId)
            ->where('weekday', $scannedAt->isoWeekday())
            ->where('status', 'active')
            ->first();

        if (! $schedule) {
            return 'normal_exit';
        }

        return $scannedAt->format('H:i:s') < $schedule->exit_time
            ? 'early_exit'
            : 'normal_exit';
    }

   private function writeLog(
    int $schoolId,
    int $campusId,
    ?int $areaId,
    int $deviceId,
    ?int $studentId,
    ?int $credentialId,
    int $userId,
    string $eventType,
    string $eventStatus,
    string $decision,
    Carbon $scannedAt,
    string $source = 'qr',
    string $readerType = 'camera_qr',
    string $action = 'none',
    ?int $minutesLate = null,
    ?string $reason = null,
    ?string $notes = null,
): int {
    $academicCycleId = null;
    $studentEnrollmentId = null;
    $schoolGroupId = null;

    /*
     * Guardamos una fotografía académica del momento del escaneo.
     *
     * De esta forma, aunque el alumno cambie de grupo en el futuro,
     * el acceso histórico seguirá mostrando el grupo correcto.
     */
    if ($studentId !== null) {
       $cycle = DB::table('academic_cycles')
    ->where('school_id', $schoolId)
    ->where('status', 'active')
    ->where('is_active', true)
    ->first();

        if ($cycle) {
            $enrollment = DB::table(
                'student_enrollments'
            )
                ->where('school_id', $schoolId)
                ->where('student_id', $studentId)
                ->where(
                    'academic_cycle_id',
                    $cycle->id
                )
                ->first();

            $academicCycleId = (int) $cycle->id;

            if ($enrollment) {
                $studentEnrollmentId =
                    (int) $enrollment->id;

                $schoolGroupId =
                    $enrollment->school_group_id !== null
                        ? (int) $enrollment->school_group_id
                        : null;
            }
        }
    }

    return DB::table('access_logs')
        ->insertGetId([
            'school_id' =>
                $schoolId,

            'campus_id' =>
                $campusId,

            'area_id' =>
                $areaId,

            'access_device_id' =>
                $deviceId,

            'student_id' =>
                $studentId,

            'academic_cycle_id' =>
                $academicCycleId,

            'student_enrollment_id' =>
                $studentEnrollmentId,

            'school_group_id' =>
                $schoolGroupId,

            'credential_id' =>
                $credentialId,

            'user_id' =>
                $userId,

            'event_type' =>
                $eventType,

            'event_status' =>
                $eventStatus,

            'decision' =>
                $decision,

            'action' =>
                $action,

            'scanned_at' =>
                $scannedAt,

            'source' =>
                $source,

            'reader_type' =>
                $readerType,

            'minutes_late' =>
                $minutesLate,

            'reason' =>
                $reason,

            'notes' =>
                $notes,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
}


    private function createGuardianNotifications(
        int $schoolId,
        int $studentId,
        string $type,
        string $title,
        string $body,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        $guardians = DB::table('student_guardians as sg')
            ->join('guardians as g', function ($join): void {
                $join
                    ->on('g.id', '=', 'sg.guardian_id')
                    ->where('g.status', '=', 'active');
            })
            ->where('g.school_id', $schoolId)
            ->where('sg.student_id', $studentId)
            ->where('sg.status', 'active')
            ->where('sg.can_receive_notifications', true)
            ->get([
                'g.id as guardian_id',
                'g.user_id',
            ]);

        foreach ($guardians as $guardian) {
            if (! $guardian->user_id) {
                continue;
            }

            $notificationId = DB::table('notifications')->insertGetId([
                'school_id' => $schoolId,
                'guardian_id' => $guardian->guardian_id,
                'student_id' => $studentId,
                'user_id' => $guardian->user_id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'status' => 'pending',
                'push_status' => 'pending',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            SendUserNotificationPush::dispatch($notificationId)
                ->afterCommit();
        }
    }

    private function studentPayload(
    int $schoolId,
    object $student,
): array {
    $group = null;

    if (
        isset($student->current_group_id)
        && $student->current_group_id !== null
    ) {
        $group = DB::table('school_groups')
            ->where('school_id', $schoolId)
            ->where(
                'id',
                $student->current_group_id
            )
            ->first();
    }

    return [
        'id' => (int) $student->id,

        'name' => trim(
            $student->first_name
            .' '
            .$student->last_name
        ),

        'student_code' =>
            $student->student_code,

        'group_id' =>
            $group?->id
                ? (int) $group->id
                : null,

        'group' =>
            $group?->name,

        'cycle_id' =>
            isset($student->active_cycle_id)
                ? (int) $student->active_cycle_id
                : null,

        'cycle' =>
            $student->active_cycle_name
            ?? null,

        'enrollment_id' =>
            isset(
                $student->active_enrollment_id
            )
                ? (int) $student
                    ->active_enrollment_id
                : null,

        'photo_url' =>
            $student->photo_url
                ? asset(
                    ltrim(
                        $student->photo_url,
                        '/'
                    )
                )
                : null,
    ];
}



private function resolveAutoTransitionMinutes(
    int $schoolId,
): int {
    $value = DB::table('school_settings')
        ->where('school_id', $schoolId)
        ->where(
            'key',
            'attendance_auto_transition_minutes'
        )
        ->value('value');

    return max(
        0,
        min(
            120,
            (int) ($value ?? 30)
        )
    );
}

private function guardianScanCacheKey(string $scanToken): string
{
    return 'schoolpass:guardian-scan:'.$scanToken;
}


}