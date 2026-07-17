<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Access\AccessScanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccessScanController extends Controller
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

    public function __construct(
        private readonly AccessScanService $accessScanService,
    ) {
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $deviceUuid = trim((string) (
            $request->header('X-Device-UUID')
            ?: $request->query('device_uuid', '')
        ));

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->where('status', 'active')
            ->first();

        if (! $school) {
            return response()->json([
                'ok' => false,
                'message' => 'La institución está inactiva o no existe.',
            ], 403);
        }

        $device = null;
        $deviceAuthorized = false;
        $deviceMessage = null;

        if ($deviceUuid !== '') {
            $device = DB::table('access_devices')
                ->where('school_id', $schoolId)
                ->where('device_uuid', $deviceUuid)
                ->first();

            if (! $device) {
                $deviceMessage = 'Dispositivo no registrado.';
            } elseif ($device->status !== 'active') {
                $deviceMessage = 'Dispositivo bloqueado o inactivo.';
            } elseif (! $this->deviceBelongsToUser($device, $user)) {
                $deviceMessage = 'El dispositivo no pertenece a esta sesión.';
            } elseif (! $this->deviceRelationsAreValid(
                schoolId: $schoolId,
                device: $device,
            )) {
                $deviceMessage = 'El dispositivo tiene una configuración inválida.';
            } else {
                $deviceAuthorized = true;

                DB::table('access_devices')
                    ->where('school_id', $schoolId)
                    ->where('id', $device->id)
                    ->update([
                        'last_seen_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        } else {
            $deviceMessage = 'No se recibió el identificador del dispositivo.';
        }

        $campuses = DB::table('campuses')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ])
            ->map(fn (object $campus): array => [
                'id' => $campus->id,
                'name' => $campus->name,
            ])
            ->values();

        $areas = DB::table('areas')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get([
                'id',
                'campus_id',
                'name',
                'type',
            ])
            ->map(fn (object $area): array => [
                'id' => $area->id,
                'campus_id' => $area->campus_id,
                'name' => $area->name,
                'type' => $area->type,
            ])
            ->values();

        return response()->json([
            'ok' => true,

            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'status' => $school->status,
            ],

            'user' => [
                'id' => $user->id,
                'school_id' => $schoolId,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ],

            'device' => [
                'authorized' => $deviceAuthorized,
                'message' => $deviceMessage,
                'id' => $device?->id,
                'device_uuid' => $deviceUuid !== ''
                    ? $deviceUuid
                    : null,
                'name' => $device?->name,
                'status' => $device?->status,
                'type' => $device?->device_type,
                'mode' => $device?->mode,
                'camera_facing' => $this->resolveCameraFacing($device),
                'campus_id' => $device?->campus_id,
                'area_id' => $device?->area_id,
                'default_event_type' => $device?->default_event_type
                    ?? 'entry',
                'allow_manual_search' => $deviceAuthorized
                    && (bool) ($device?->allow_manual_search ?? false),
                    'allow_guardian_scan' => $deviceAuthorized
    && (bool) ($device?->allow_guardian_scan ?? false),

'guardian_exit_confirmation' => (string) (
    $device?->guardian_exit_confirmation ?? 'disabled'
),
                'show_student_photo' => (bool) (
                    $device?->show_student_photo ?? true
                ),
                'auto_reset_seconds' => (int) (
                    $device?->auto_reset_seconds ?? 3
                ),
            ],

            'campuses' => $campuses,
            'areas' => $areas,

           'modes' => [
    [
        'value' => 'auto',
        'label' => 'Automático',
    ],
    [
        'value' => 'entry',
        'label' => 'Entrada',
    ],
    [
        'value' => 'exit',
        'label' => 'Salida',
    ],
],
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => [
                'required',
                'string',
                'max:255',
            ],
            'device_uuid' => [
                'required',
                'string',
                'max:120',
            ],
            'event_type' => [
                'nullable',
              Rule::in([
    'auto',
    'entry',
    'exit',
    'access',
]),
            ],
            'scanned_at' => [
                'nullable',
                'date',
            ],
            'reader_type' => [
                'nullable',
                Rule::in([
                    'camera_qr',
                    'nfc',
                    'hardware',
                ]),
            ],
        ]);

        $data['token'] = trim($data['token']);
        $data['device_uuid'] = trim($data['device_uuid']);

        $result = $this->accessScanService->process(
            data: $data,
            user: $request->user(),
        );

        $httpCode = (int) ($result['http_code'] ?? 200);

        unset($result['http_code']);

      $result = ($result['scan_type'] ?? null) === 'guardian'
    ? $this->normalizeGuardianResponseForAndroid(
        result: $result,
        request: $request,
    )
    : $this->normalizeScanResponseForAndroid(
        result: $result,
        request: $request,
    );

        return response()->json(
            data: $result,
            status: $httpCode,
        );
    }


    public function guardianConfirm(Request $request): JsonResponse
{
    $data = $request->validate([
        'guardian_scan_token' => [
            'required',
            'string',
            'size:64',
        ],
       'student_selection_tokens' => [
    'required',
    'array',
    'min:1',
    'max:10',
],

'student_selection_tokens.*' => [
    'required',
    'string',
    'size:48',
    'distinct',
],
        'device_uuid' => [
            'required',
            'string',
            'max:120',
        ],
        'scanned_at' => [
            'nullable',
            'date',
        ],
    ]);

    $data['guardian_scan_token'] = trim(
        $data['guardian_scan_token']
    );

    $data['device_uuid'] = trim(
        $data['device_uuid']
    );

    $result = $this->accessScanService
        ->processGuardianConfirmation(
            data: $data,
            user: $request->user(),
        );

    $httpCode = (int) ($result['http_code'] ?? 200);

    unset($result['http_code']);

    $result = $this->normalizeGuardianResponseForAndroid(
        result: $result,
        request: $request,
    );

    return response()->json(
        data: $result,
        status: $httpCode,
    );
}




   public function recent(Request $request): JsonResponse
{
    $data = $request->validate([
        'limit' => [
            'nullable',
            'integer',
            'min:1',
            'max:50',
        ],

        'date' => [
            'nullable',
            'date_format:Y-m-d',
        ],
    ]);

    $user = $request->user();
    $schoolId = (int) $user->school_id;

    $limit = (int) (
        $data['limit']
        ?? 20
    );

    $date = $data['date']
        ?? now(
            config('app.timezone')
        )->toDateString();

    $items = DB::table(
        'access_logs as log'
    )
        ->leftJoin(
            'students as student',
            function ($join) use (
                $schoolId
            ): void {
                $join
                    ->on(
                        'student.id',
                        '=',
                        'log.student_id'
                    )
                    ->where(
                        'student.school_id',
                        '=',
                        $schoolId
                    );
            }
        )
        ->leftJoin(
            'school_groups as school_group',
            function ($join) use (
                $schoolId
            ): void {
                $join
                    ->on(
                        'school_group.id',
                        '=',
                        'log.school_group_id'
                    )
                    ->where(
                        'school_group.school_id',
                        '=',
                        $schoolId
                    );
            }
        )
        ->leftJoin(
            'guardians as guardian',
            function ($join) use (
                $schoolId
            ): void {
                $join
                    ->on(
                        'guardian.id',
                        '=',
                        'log.guardian_id'
                    )
                    ->where(
                        'guardian.school_id',
                        '=',
                        $schoolId
                    );
            }
        )
        ->leftJoin(
            'student_guardians as relation',
            function ($join): void {
                $join
                    ->on(
                        'relation.student_id',
                        '=',
                        'log.student_id'
                    )
                    ->on(
                        'relation.guardian_id',
                        '=',
                        'log.guardian_id'
                    );
            }
        )
        ->where(
            'log.school_id',
            $schoolId
        )
        ->whereDate(
            'log.scanned_at',
            $date
        )
        ->orderByDesc(
            'log.scanned_at'
        )
        ->limit($limit)
        ->get([
            'log.id',
            'log.event_type',
            'log.event_status',
            'log.decision',
            'log.scanned_at',
            'log.source',
            'log.reader_type',
            'log.performed_for',

            'student.id as student_id',
            'student.student_code',
            'student.first_name',
            'student.last_name',
            'student.photo_url',

            'school_group.name as group_name',

            'guardian.id as guardian_id',
            'guardian.first_name as guardian_first_name',
            'guardian.last_name as guardian_last_name',
            'guardian.photo_url as guardian_photo_url',

            'relation.relationship',
        ])
        ->map(function (
            object $row
        ) use ($request): array {
            $guardianPayload = null;

            if ($row->guardian_id !== null) {
                $guardianPayload = [
                    'name' => trim(
                        ($row->guardian_first_name
                            ?? '')
                        .' '
                        .($row->guardian_last_name
                            ?? '')
                    ),

                    'photo_url' =>
                        $this
                            ->normalizePhotoUrlForAndroid(
                                photoUrl:
                                    $row
                                        ->guardian_photo_url,
                                request: $request,
                            ),

                    'relationship' =>
                        $row->relationship,
                ];
            }

            return [
                'id' => $row->id,

                'event_type' =>
                    $row->event_type,

                'status' =>
                    $row->event_status,

                'decision' =>
                    $row->decision,

                'status_label' =>
                    $this->labelForStatus(
                        $row->event_status
                    ),

                'color' =>
                    $this->androidColorForStatus(
                        status:
                            $row->event_status,
                        decision:
                            $row->decision,
                    ),

                'sound' =>
                    $this->androidSoundForStatus(
                        status:
                            $row->event_status,
                        decision:
                            $row->decision,
                    ),

                'source' =>
                    $row->source,

                'reader_type' =>
                    $row->reader_type,

                'performed_for' =>
                    $row->performed_for,

                'scanned_at' =>
                    $row->scanned_at,

                'hour' =>
                    CarbonImmutable::parse(
                        $row->scanned_at
                    )->format('H:i'),

                'student' => [
                    'id' =>
                        $row->student_id,

                    'code' =>
                        $row->student_code,

                    'name' => trim(
                        ($row->first_name ?? '')
                        .' '
                        .($row->last_name ?? '')
                    ),

                    'group' =>
                        $row->group_name,

                    'photo_url' =>
                        $this
                            ->normalizePhotoUrlForAndroid(
                                photoUrl:
                                    $row->photo_url,
                                request: $request,
                            ),
                ],

                'guardian' =>
                    $guardianPayload,
            ];
        })
        ->values();

    return response()->json([
        'ok' => true,
        'date' => $date,
        'count' => $items->count(),
        'items' => $items,
    ]);
}

    public function searchStudents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => [
                'nullable',
                'string',
                'max:120',
            ],
            'group_id' => [
                'nullable',
                'integer',
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
            'device_uuid' => [
                'required',
                'string',
                'max:120',
            ],
        ]);

        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $device = $this->authorizedManualDevice(
            schoolId: $schoolId,
            user: $user,
            deviceUuid: trim($data['device_uuid']),
        );

        if (! $device) {
            return response()->json([
                'ok' => false,
                'message' => 'Dispositivo no autorizado para búsqueda manual.',
            ], 403);
        }

        $q = trim((string) ($data['q'] ?? ''));
        $limit = (int) ($data['limit'] ?? 20);

        if (! empty($data['group_id'])) {
            $groupExists = DB::table('school_groups')
                ->where('school_id', $schoolId)
                ->where('id', (int) $data['group_id'])
                ->where('status', 'active')
                ->exists();

            if (! $groupExists) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El grupo no pertenece a la institución.',
                ], 422);
            }
        }

        $students = DB::table('students as student')
            ->leftJoin('school_groups as school_group', function ($join) use (
                $schoolId
            ): void {
                $join
                    ->on(
                        'school_group.id',
                        '=',
                        'student.current_group_id'
                    )
                    ->where('school_group.school_id', '=', $schoolId);
            })
            ->where('student.school_id', $schoolId)
            ->where('student.campus_id', $device->campus_id)
            ->where('student.status', 'active')
            ->when(
                ! empty($data['group_id']),
                fn ($query) => $query->where(
                    'student.current_group_id',
                    (int) $data['group_id']
                )
            )
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($subquery) use ($q): void {
                    $subquery
                        ->where(
                            'student.student_code',
                            'like',
                            "%{$q}%"
                        )
                        ->orWhere(
                            'student.first_name',
                            'like',
                            "%{$q}%"
                        )
                        ->orWhere(
                            'student.last_name',
                            'like',
                            "%{$q}%"
                        )
                        ->orWhereRaw(
                            "CONCAT(student.first_name, ' ', student.last_name) LIKE ?",
                            ["%{$q}%"]
                        );
                });
            })
            ->orderBy('student.first_name')
            ->orderBy('student.last_name')
            ->limit($limit)
            ->get([
                'student.id',
                'student.student_code',
                'student.first_name',
                'student.last_name',
                'student.photo_url',
                'student.current_group_id',
                'school_group.name as group_name',
            ])
            ->map(function (object $student) use ($request): array {
                return [
                    'id' => $student->id,
                    'code' => $student->student_code,
                    'name' => trim(
                        $student->first_name
                        .' '
                        .$student->last_name
                    ),
                    'photo_url' => $this->normalizePhotoUrlForAndroid(
                        photoUrl: $student->photo_url,
                        request: $request,
                    ),
                    'group' => [
                        'id' => $student->current_group_id,
                        'name' => $student->group_name,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'count' => $students->count(),
            'items' => $students,
        ]);
    }


public function studentGuardians(
    Request $request,
    int $student,
): JsonResponse {
    $data = $request->validate([
        'device_uuid' => [
            'required',
            'string',
            'max:120',
        ],
        'event_type' => [
            'required',
            Rule::in(['entry', 'exit']),
        ],
    ]);

    $user = $request->user();
    $schoolId = (int) $user->school_id;

    $device = $this->authorizedManualDevice(
        schoolId: $schoolId,
        user: $user,
        deviceUuid: trim($data['device_uuid']),
    );

    if (! $device) {
        return response()->json([
            'ok' => false,
            'message' => 'Dispositivo no autorizado para búsqueda manual.',
        ], 403);
    }

    $studentRecord = DB::table('students as student')
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
        ->where('student.school_id', $schoolId)
        ->where('student.campus_id', $device->campus_id)
        ->where('student.id', $student)
        ->where('student.status', 'active')
        ->first([
            'student.id',
            'student.requires_guardian_scan_override',
            'school_group.requires_guardian_scan',
        ]);

    if (! $studentRecord) {
        return response()->json([
            'ok' => false,
            'message' => 'Alumno no encontrado o fuera del plantel asignado.',
        ], 404);
    }

    $requiresGuardianScan = $studentRecord
        ->requires_guardian_scan_override !== null
            ? (bool) $studentRecord->requires_guardian_scan_override
            : (bool) (
                $studentRecord->requires_guardian_scan
                ?? false
            );

    $permissionColumn = $data['event_type'] === 'entry'
        ? 'student_guardian.can_drop_off'
        : 'student_guardian.can_pick_up';

    $today = now(
        config('app.timezone')
    )->toDateString();

    $guardians = DB::table(
        'student_guardians as student_guardian'
    )
        ->join(
            'guardians as guardian',
            function ($join) use ($schoolId): void {
                $join
                    ->on(
                        'guardian.id',
                        '=',
                        'student_guardian.guardian_id'
                    )
                    ->where(
                        'guardian.school_id',
                        '=',
                        $schoolId
                    )
                    ->where(
                        'guardian.status',
                        '=',
                        'active'
                    );
            }
        )
        ->where(
            'student_guardian.student_id',
            $studentRecord->id
        )
        ->where(
            'student_guardian.status',
            'active'
        )
        ->where($permissionColumn, true)
        ->where(function ($query) use ($today): void {
            $query
                ->whereNull(
                    'student_guardian.valid_from'
                )
                ->orWhereDate(
                    'student_guardian.valid_from',
                    '<=',
                    $today
                );
        })
        ->where(function ($query) use ($today): void {
            $query
                ->whereNull(
                    'student_guardian.valid_until'
                )
                ->orWhereDate(
                    'student_guardian.valid_until',
                    '>=',
                    $today
                );
        })
        ->orderByDesc(
            'student_guardian.is_primary'
        )
        ->orderBy('guardian.first_name')
        ->orderBy('guardian.last_name')
        ->get([
            'guardian.id',
            'guardian.first_name',
            'guardian.last_name',
            'guardian.photo_url',
            'guardian.status',
            'student_guardian.relationship',
            'student_guardian.is_primary',
            'student_guardian.can_drop_off',
            'student_guardian.can_pick_up',
        ])
        ->map(function (object $guardian) use (
            $request
        ): array {
            return [
                'id' => (int) $guardian->id,

                'name' => trim(
                    $guardian->first_name
                    .' '
                    .$guardian->last_name
                ),

                'photo_url' =>
                    $this->normalizePhotoUrlForAndroid(
                        photoUrl:
                            $guardian->photo_url,
                        request: $request,
                    ),

                'status' =>
                    $guardian->status,

                'relationship' =>
                    $guardian->relationship,

                'is_primary' =>
                    (bool) $guardian->is_primary,

                'can_drop_off' =>
                    (bool) $guardian->can_drop_off,

                'can_pick_up' =>
                    (bool) $guardian->can_pick_up,
            ];
        })
        ->values();

    return response()->json([
        'ok' => true,

        'student_id' =>
            (int) $studentRecord->id,

        'event_type' =>
            $data['event_type'],

        'requires_guardian_scan' =>
            $requiresGuardianScan,

        'count' =>
            $guardians->count(),

        'items' =>
            $guardians,
    ]);
}


   public function manual(Request $request): JsonResponse
{
    $data = $request->validate([
        'student_id' => [
            'required',
            'integer',
        ],

        'guardian_id' => [
            'nullable',
            'integer',
        ],

        'event_type' => [
            'required',
            Rule::in(['entry', 'exit']),
        ],

        'reason' => [
            'required',
            'string',
            'max:255',
        ],

        'device_uuid' => [
            'required',
            'string',
            'max:120',
        ],

        'scanned_at' => [
            'nullable',
            'date',
        ],
    ]);

    $data['device_uuid'] = trim(
        $data['device_uuid']
    );

    $data['reason'] = trim(
        $data['reason']
    );

    $data['guardian_id'] = isset(
        $data['guardian_id']
    )
        ? (int) $data['guardian_id']
        : null;

    $result = $this->accessScanService
        ->processManual(
            data: $data,
            user: $request->user(),
        );

    $httpCode = (int) (
        $result['http_code']
        ?? 200
    );

    unset($result['http_code']);

    $result = $this
        ->normalizeScanResponseForAndroid(
            result: $result,
            request: $request,
        );

    return response()->json(
        data: $result,
        status: $httpCode,
    );
}

    private function authorizedManualDevice(
        int $schoolId,
        object $user,
        string $deviceUuid,
    ): ?object {
        if ($deviceUuid === '') {
            return null;
        }

        $device = DB::table('access_devices')
            ->where('school_id', $schoolId)
            ->where('device_uuid', $deviceUuid)
            ->where('status', 'active')
            ->where('allow_manual_search', true)
            ->first();

        if (! $device) {
            return null;
        }

        if (! $this->deviceBelongsToUser($device, $user)) {
            return null;
        }

        if (! $this->deviceRelationsAreValid(
            schoolId: $schoolId,
            device: $device,
        )) {
            return null;
        }

        return $device;
    }

    private function deviceBelongsToUser(
        object $device,
        object $user,
    ): bool {
        if (
            in_array(
                $user->role,
                self::ADMIN_ROLES,
                true
            )
        ) {
            return true;
        }

        if (
            in_array(
                $user->role,
                self::ASSIGNED_DEVICE_ROLES,
                true
            )
        ) {
            return (int) $device->assigned_to_user_id
                === (int) $user->id;
        }

        return false;
    }

    private function deviceRelationsAreValid(
        int $schoolId,
        object $device,
    ): bool {
        $campusExists = DB::table('campuses')
            ->where('school_id', $schoolId)
            ->where('id', $device->campus_id)
            ->where('status', 'active')
            ->exists();

        if (! $campusExists) {
            return false;
        }

        if ($device->area_id === null) {
            return $device->mode !== 'restricted_access';
        }

        return DB::table('areas')
            ->where('school_id', $schoolId)
            ->where('campus_id', $device->campus_id)
            ->where('id', $device->area_id)
            ->where('status', 'active')
            ->exists();
    }

    private function resolveCameraFacing(?object $device): string
    {
        if (! $device) {
            return 'back';
        }

        $deviceType = strtolower(
            (string) ($device->device_type ?? '')
        );

        $cameraFacing = strtolower(
            (string) ($device->camera_facing ?? '')
        );

        if ($deviceType === 'prefect_app') {
            return 'back';
        }

        if ($deviceType === 'kiosk') {
            return in_array(
                $cameraFacing,
                ['front', 'back', 'auto'],
                true
            )
                ? $cameraFacing
                : 'front';
        }

        return in_array(
            $cameraFacing,
            ['front', 'back', 'auto'],
            true
        )
            ? $cameraFacing
            : 'back';
    }

    private function normalizeScanResponseForAndroid(
        array $result,
        Request $request,
    ): array {
        $status = $result['status'] ?? null;
        $decision = $result['decision'] ?? null;

        if (
            isset($result['student'])
            && is_array($result['student'])
        ) {
            $result['student'] = [
                'id' => $result['student']['id'] ?? null,
                'code' => $result['student']['code']
                    ?? $result['student']['student_code']
                    ?? null,
                'name' => $result['student']['name'] ?? null,
                'group' => $result['student']['group'] ?? null,
                'photo_url' => $this->normalizePhotoUrlForAndroid(
                    photoUrl: $result['student']['photo_url'] ?? null,
                    request: $request,
                ),
            ];
        }

        if (
    isset($result['guardian'])
    && is_array($result['guardian'])
) {
    $result['guardian']['photo_url'] =
        $this->normalizePhotoUrlForAndroid(
            photoUrl:
                $result['guardian']['photo_url']
                ?? null,
            request: $request,
        );
}

        $result['color'] = $result['color']
            ?? $this->androidColorForStatus(
                status: $status,
                decision: $decision,
            );

        $result['sound'] = $result['sound']
            ?? $this->androidSoundForStatus(
                status: $status,
                decision: $decision,
            );

        return $result;
    }


    private function normalizeGuardianResponseForAndroid(
    array $result,
    Request $request,
): array {
    if (
        isset($result['guardian'])
        && is_array($result['guardian'])
    ) {
        $result['guardian']['photo_url'] =
            $this->normalizePhotoUrlForAndroid(
                photoUrl: $result['guardian']['photo_url'] ?? null,
                request: $request,
            );
    }

    if (
        isset($result['students'])
        && is_array($result['students'])
    ) {
        $result['students'] = collect($result['students'])
            ->map(function (array $student) use ($request): array {
                $student['photo_url'] =
                    $this->normalizePhotoUrlForAndroid(
                        photoUrl: $student['photo_url'] ?? null,
                        request: $request,
                    );

                return $student;
            })
            ->values()
            ->all();
    }

    if (
        isset($result['results'])
        && is_array($result['results'])
    ) {
        $result['results'] = collect($result['results'])
            ->map(function (array $item) use ($request): array {
                if (
                    isset($item['student'])
                    && is_array($item['student'])
                ) {
                    $item['student']['photo_url'] =
                        $this->normalizePhotoUrlForAndroid(
                            photoUrl: $item['student']['photo_url'] ?? null,
                            request: $request,
                        );
                }

                return $item;
            })
            ->values()
            ->all();
    }

    $status = $result['status'] ?? null;
    $decision = $result['decision'] ?? null;

    $result['color'] = $result['color']
        ?? $this->androidColorForStatus(
            status: $status,
            decision: $decision,
        );

    $result['sound'] = $result['sound']
        ?? $this->androidSoundForStatus(
            status: $status,
            decision: $decision,
        );

    return $result;
}





    private function normalizePhotoUrlForAndroid(
        ?string $photoUrl,
        Request $request,
    ): ?string {
        $photoUrl = trim((string) $photoUrl);

        if ($photoUrl === '') {
            return null;
        }

        if (str_starts_with($photoUrl, 'https://')) {
            return $photoUrl;
        }

        if (str_starts_with($photoUrl, 'http://')) {
            return $this->forceHttpsIfNeeded(
                url: $photoUrl,
                request: $request,
            );
        }

        $cleanPath = ltrim($photoUrl, '/');

        return $this->forceHttpsIfNeeded(
            url: url($cleanPath),
            request: $request,
        );
    }

    private function forceHttpsIfNeeded(
        string $url,
        Request $request,
    ): string {
        $host = $request->getHost();

        $forwardedProto = strtolower(
            (string) $request->header('X-Forwarded-Proto')
        );

        $shouldForceHttps = $request->isSecure()
            || $forwardedProto === 'https'
            || str_contains($host, 'ngrok-free.dev')
            || str_contains($host, 'ngrok.app')
            || str_contains($host, 'ngrok.io');

        if (
            $shouldForceHttps
            && str_starts_with($url, 'http://')
        ) {
            return 'https://'.substr($url, 7);
        }

        return $url;
    }

    private function androidColorForStatus(
        ?string $status,
        ?string $decision = null,
    ): string {
        if ($decision === 'denied') {
            return 'red';
        }

        if (
            $decision === 'duplicate'
            || $status === 'duplicate'
        ) {
            return 'blue';
        }

        return match ($status) {
            'on_time',
            'allowed',
            'manual',
            'present',
            'normal_exit' => 'green',

            'late',
            'very_late' => 'yellow',

            'early_exit' => 'orange',

            'denied',
            'revoked',
            'expired',
            'blocked',
            'not_found',
            'invalid_credential' => 'red',
            'guardian_required' => 'red',

            default => 'gray',
        };
    }

    private function androidSoundForStatus(
        ?string $status,
        ?string $decision = null,
    ): string {
        if ($decision === 'denied') {
            return 'error';
        }

        if (
            $decision === 'duplicate'
            || $status === 'duplicate'
        ) {
            return 'warning';
        }

        return match ($status) {
            'on_time',
            'allowed',
            'manual',
            'present',
            'normal_exit' => 'success',

            'late',
            'very_late',
            'early_exit' => 'warning',

            'denied',
            'revoked',
            'expired',
            'blocked',
            'not_found',
            'invalid_credential' => 'error',
            'guardian_required' => 'error',

            default => 'neutral',
        };
    }

    private function labelForStatus(?string $status): string
    {
        return match ($status) {
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Extemporáneo',
            'manual' => 'Manual',
            'duplicate' => 'Duplicado',
            'allowed' => 'Permitido',
            'denied' => 'Denegado',
            'normal_exit' => 'Salida',
            'early_exit' => 'Salida anticipada',
            'invalid_credential' => 'Credencial inválida',
            'guardian_required' => 'Tutor requerido',
            default => 'Registrado',
        };
    }
}