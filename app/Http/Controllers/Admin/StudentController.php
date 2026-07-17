<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $schoolId = $this->schoolId($request);

        $search = trim(
            (string) $request->query(
                'search',
                ''
            )
        );

        $activeCycle = $this->activeCycle(
            $schoolId
        );

        $studentsQuery = DB::table(
            'students as s'
        )
            ->leftJoin(
                'student_enrollments as se',
                function ($join) use (
                    $schoolId,
                    $activeCycle
                ): void {
                    $join->on(
                        'se.student_id',
                        '=',
                        's.id'
                    )
                        ->where(
                            'se.school_id',
                            '=',
                            $schoolId
                        );

                    if ($activeCycle) {
                        $join->where(
                            'se.academic_cycle_id',
                            '=',
                            $activeCycle->id
                        );
                    } else {
                        $join->whereRaw('1 = 0');
                    }
                }
            )
            ->leftJoin(
                'school_groups as current_group',
                'current_group.id',
                '=',
                's.current_group_id'
            )
            ->leftJoin(
                'school_groups as enrolled_group',
                'enrolled_group.id',
                '=',
                'se.school_group_id'
            )
            ->leftJoin(
                'academic_levels as current_level',
                'current_level.id',
                '=',
                'current_group.academic_level_id'
            )
            ->leftJoin(
                'academic_levels as enrolled_level',
                'enrolled_level.id',
                '=',
                'enrolled_group.academic_level_id'
            )
            ->where(
                's.school_id',
                $schoolId
            )
            ->when(
                $search !== '',
                function ($query) use (
                    $search
                ): void {
                    $query->where(
                        function ($inner) use (
                            $search
                        ): void {
                            $inner
                                ->where(
                                    's.first_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    's.last_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    's.student_code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'enrolled_group.name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'current_group.name',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->select([
                's.*',

                'se.id as active_enrollment_id',
                'se.status as enrollment_status',
                'se.enrollment_type',
                'se.enrolled_on',

                DB::raw(
                    'COALESCE(
                        enrolled_group.name,
                        current_group.name
                    ) as group_name'
                ),

                DB::raw(
                    'COALESCE(
                        enrolled_level.name,
                        current_level.name
                    ) as level_name'
                ),

                DB::raw(
                    'CASE
                        WHEN se.id IS NOT NULL
                        THEN enrolled_group.id
                        ELSE current_group.id
                    END as displayed_group_id'
                ),

                DB::raw(
                    "(
                        SELECT COUNT(*)
                        FROM student_credentials sc
                        WHERE sc.student_id = s.id
                        AND sc.status = 'active'
                    ) as active_credentials_count"
                ),
            ])
            ->orderBy('s.last_name')
            ->orderBy('s.first_name');

        $students = $studentsQuery
            ->paginate(15)
            ->withQueryString();

        return view('admin.students.index', [
            'students' => $students,
            'search' => $search,
            'activeCycle' => $activeCycle,
        ]);
    }

    public function create(
        Request $request
    ): View {
        $schoolId = $this->schoolId(
            $request
        );

        $activeCycle = $this->activeCycle(
            $schoolId
        );

        $groups = $activeCycle
            ? $this->groupsForCycle(
                schoolId: $schoolId,
                cycleId: (int) $activeCycle->id
            )
            : collect();

        return view('admin.students.create', [
            'groups' => $groups,
            'activeCycle' => $activeCycle,
        ]);
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $schoolId = $this->schoolId(
            $request
        );

        $activeCycle = $this->activeCycle(
            $schoolId
        );

        if (! $activeCycle) {
            return back()
                ->withInput()
                ->withErrors([
                    'cycle' =>
                        'No hay un ciclo escolar activo. Activa un ciclo antes de registrar alumnos.',
                ]);
        }

        $data = $request->validate([
            'student_code' => [
                'required',
                'string',
                'max:50',

                Rule::unique(
                    'students',
                    'student_code'
                )->where(
                    'school_id',
                    $schoolId
                ),
            ],

            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:150',
            ],

            'current_group_id' => [
                'required',
                'integer',
            ],

            'status' => [
                'required',

                Rule::in([
                    'active',
                    'suspended',
                    'temporary',
                ]),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $group = $this->groupForCycle(
            schoolId: $schoolId,
            cycleId: (int) $activeCycle->id,
            groupId: (int) $data[
                'current_group_id'
            ]
        );

        try {
            $studentId = DB::transaction(
                function () use (
                    $request,
                    $schoolId,
                    $activeCycle,
                    $group,
                    $data
                ): int {
                    $studentId = DB::table(
                        'students'
                    )->insertGetId([
                        'school_id' =>
                            $schoolId,

                        'campus_id' =>
                            $group->campus_id,

                        'current_group_id' =>
                            $group->id,

                        'student_code' =>
                            trim(
                                $data[
                                    'student_code'
                                ]
                            ),

                        'first_name' =>
                            trim(
                                $data[
                                    'first_name'
                                ]
                            ),

                        'last_name' =>
                            trim(
                                $data[
                                    'last_name'
                                ]
                            ),

                        'status' =>
                            $data['status'],

                        'notes' =>
                            $data['notes'] ?? null,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                    $enrollmentId = DB::table(
                        'student_enrollments'
                    )->insertGetId([
                        'school_id' =>
                            $schoolId,

                        'student_id' =>
                            $studentId,

                        'academic_cycle_id' =>
                            $activeCycle->id,

                        'school_group_id' =>
                            $group->id,

                        'campus_id' =>
                            $group->campus_id,

                        'previous_enrollment_id' =>
                            null,

                        'status' =>
                            'active',

                        'enrollment_type' =>
                            'new',

                        'enrolled_on' =>
                            now()->toDateString(),

                        'completed_on' =>
                            null,

                        'withdrawn_on' =>
                            null,

                        'withdrawal_reason' =>
                            null,

                        'notes' =>
                            'Alta realizada desde gestión de alumnos.',

                        'created_by_user_id' =>
                            $request->user()->id,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                    DB::table(
                        'student_group_movements'
                    )->insert([
                        'school_id' =>
                            $schoolId,

                        'student_id' =>
                            $studentId,

                        'academic_cycle_id' =>
                            $activeCycle->id,

                        'enrollment_id' =>
                            $enrollmentId,

                        'from_group_id' =>
                            null,

                        'to_group_id' =>
                            $group->id,

                        'movement_type' =>
                            'initial_assignment',

                        'effective_on' =>
                            now()->toDateString(),

                        'reason' =>
                            'Alta inicial del alumno',

                        'notes' =>
                            null,

                        'created_by_user_id' =>
                            $request->user()->id,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                    return $studentId;
                },
                3
            );
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'student' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'admin.students.show',
                $studentId
            )
            ->with(
                'success',
                'Alumno registrado e inscrito en el ciclo activo.'
            );
    }

   public function show(
    Request $request,
    int $student
): View {
    $schoolId = $this->schoolId($request);

    $activeCycle = $this->activeCycle($schoolId);

    /*
     * Datos permanentes del alumno.
     *
     * El grupo guardado directamente en students se conserva como
     * respaldo, pero la inscripción del ciclo activo tendrá prioridad.
     */
    $studentRow = DB::table('students as s')
        ->leftJoin(
            'school_groups as fallback_group',
            'fallback_group.id',
            '=',
            's.current_group_id'
        )
        ->leftJoin(
            'academic_levels as fallback_level',
            'fallback_level.id',
            '=',
            'fallback_group.academic_level_id'
        )
        ->leftJoin(
            'campuses as fallback_campus',
            'fallback_campus.id',
            '=',
            's.campus_id'
        )
        ->where('s.school_id', $schoolId)
        ->where('s.id', $student)
        ->select([
            's.*',

            'fallback_group.id as fallback_group_id',
            'fallback_group.name as fallback_group_name',
            'fallback_group.grade_label as fallback_grade_label',
            'fallback_group.academic_cycle_id as fallback_cycle_id',

            'fallback_level.name as fallback_level_name',
            'fallback_campus.name as fallback_campus_name',
        ])
        ->firstOrFail();

    /*
     * Inscripción correspondiente al ciclo actualmente operativo.
     */
    $activeEnrollment = null;

    if ($activeCycle) {
        $activeEnrollment = DB::table(
            'student_enrollments as se'
        )
            ->leftJoin(
                'school_groups as sg',
                'sg.id',
                '=',
                'se.school_group_id'
            )
            ->leftJoin(
                'academic_levels as al',
                'al.id',
                '=',
                'sg.academic_level_id'
            )
            ->leftJoin(
                'campuses as c',
                'c.id',
                '=',
                'se.campus_id'
            )
            ->where('se.school_id', $schoolId)
            ->where('se.student_id', $student)
            ->where(
                'se.academic_cycle_id',
                $activeCycle->id
            )
            ->select([
                'se.id',
                'se.school_id',
                'se.student_id',
                'se.academic_cycle_id',
                'se.school_group_id',
                'se.campus_id',
                'se.previous_enrollment_id',
                'se.status',
                'se.enrollment_type',
                'se.enrolled_on',
                'se.completed_on',
                'se.withdrawn_on',
                'se.withdrawal_reason',
                'se.notes',

                'sg.name as group_name',
                'sg.grade_label',
                'sg.status as group_status',

                'al.name as level_name',
                'c.name as campus_name',
            ])
            ->first();
    }

    /*
     * Datos mostrados en la cabecera.
     *
     * La inscripción activa tiene prioridad sobre current_group_id.
     */
    $studentRow->display_group_id =
        $activeEnrollment->school_group_id
        ?? $studentRow->fallback_group_id;

    $studentRow->group_name =
        $activeEnrollment->group_name
        ?? $studentRow->fallback_group_name;

    $studentRow->grade_label =
        $activeEnrollment->grade_label
        ?? $studentRow->fallback_grade_label;

    $studentRow->level_name =
        $activeEnrollment->level_name
        ?? $studentRow->fallback_level_name;

    $studentRow->campus_name =
        $activeEnrollment->campus_name
        ?? $studentRow->fallback_campus_name;

    /*
     * Historial de inscripciones y ciclos.
     */
    $enrollments = DB::table(
        'student_enrollments as se'
    )
        ->join(
            'academic_cycles as ac',
            'ac.id',
            '=',
            'se.academic_cycle_id'
        )
        ->leftJoin(
            'school_groups as sg',
            'sg.id',
            '=',
            'se.school_group_id'
        )
        ->leftJoin(
            'academic_levels as al',
            'al.id',
            '=',
            'sg.academic_level_id'
        )
        ->leftJoin(
            'campuses as c',
            'c.id',
            '=',
            'se.campus_id'
        )
        ->where('se.school_id', $schoolId)
        ->where('se.student_id', $student)
        ->orderByDesc('ac.starts_on')
        ->orderByDesc('se.id')
        ->get([
            'se.id',
            'se.academic_cycle_id',
            'se.school_group_id',
            'se.status',
            'se.enrollment_type',
            'se.enrolled_on',
            'se.completed_on',
            'se.withdrawn_on',
            'se.withdrawal_reason',
            'se.notes',

            'ac.name as cycle_name',
            'ac.starts_on as cycle_starts_on',
            'ac.ends_on as cycle_ends_on',
            'ac.status as cycle_status',
            'ac.is_active as cycle_is_active',

            'sg.name as group_name',
            'sg.grade_label',
            'al.name as level_name',
            'c.name as campus_name',
        ]);

    /*
     * Historial de cambios de grupo.
     */
    $movements = collect();

    if (Schema::hasTable('student_group_movements')) {
        $movements = DB::table(
            'student_group_movements as sgm'
        )
            ->join(
                'academic_cycles as ac',
                'ac.id',
                '=',
                'sgm.academic_cycle_id'
            )
            ->leftJoin(
                'school_groups as from_group',
                'from_group.id',
                '=',
                'sgm.from_group_id'
            )
            ->leftJoin(
                'school_groups as to_group',
                'to_group.id',
                '=',
                'sgm.to_group_id'
            )
            ->leftJoin(
                'users as u',
                'u.id',
                '=',
                'sgm.created_by_user_id'
            )
            ->where('sgm.school_id', $schoolId)
            ->where('sgm.student_id', $student)
            ->orderByDesc('sgm.effective_on')
            ->orderByDesc('sgm.id')
            ->get([
                'sgm.id',
                'sgm.academic_cycle_id',
                'sgm.enrollment_id',
                'sgm.from_group_id',
                'sgm.to_group_id',
                'sgm.movement_type',
                'sgm.effective_on',
                'sgm.reason',
                'sgm.notes',
                'sgm.created_at',

                'ac.name as cycle_name',
                'from_group.name as from_group_name',
                'to_group.name as to_group_name',
                'u.name as created_by_name',
            ]);
    }

    /*
     * Tutores vinculados y permisos.
     */
    $guardians = DB::table(
        'student_guardians as sg'
    )
        ->join(
            'guardians as g',
            'g.id',
            '=',
            'sg.guardian_id'
        )
        ->where('g.school_id', $schoolId)
        ->where('sg.student_id', $student)
        ->orderByDesc('sg.is_primary')
        ->orderBy('g.last_name')
        ->orderBy('g.first_name')
        ->get([
            'g.id',
            'g.first_name',
            'g.last_name',
            'g.phone',
            'g.email',
            'g.status',

            'sg.relationship',
            'sg.can_view_attendance',
            'sg.can_receive_notifications',
            'sg.can_authorize_exit',
            'sg.is_primary',
            'sg.status as relationship_status',
        ]);

    /*
     * Credenciales del alumno.
     */
    $credentials = DB::table(
        'student_credentials'
    )
        ->where('school_id', $schoolId)
        ->where('student_id', $student)
        ->orderByRaw("
            CASE status
                WHEN 'active' THEN 1
                WHEN 'revoked' THEN 2
                ELSE 3
            END
        ")
        ->orderByDesc('id')
        ->get();

    /*
     * Columnas variables de daily_attendance.
     */
    $attendanceStatusColumn = match (true) {
        Schema::hasColumn(
            'daily_attendance',
            'attendance_status'
        ) => 'attendance_status',

        Schema::hasColumn(
            'daily_attendance',
            'entry_status'
        ) => 'entry_status',

        Schema::hasColumn(
            'daily_attendance',
            'status'
        ) => 'status',

        default => null,
    };

    $attendanceEntryColumn = match (true) {
        Schema::hasColumn(
            'daily_attendance',
            'entry_at'
        ) => 'entry_at',

        Schema::hasColumn(
            'daily_attendance',
            'first_entry_at'
        ) => 'first_entry_at',

        Schema::hasColumn(
            'daily_attendance',
            'entry_time'
        ) => 'entry_time',

        default => null,
    };

    $attendanceExitColumn = match (true) {
        Schema::hasColumn(
            'daily_attendance',
            'exit_at'
        ) => 'exit_at',

        Schema::hasColumn(
            'daily_attendance',
            'last_exit_at'
        ) => 'last_exit_at',

        Schema::hasColumn(
            'daily_attendance',
            'exit_time'
        ) => 'exit_time',

        default => null,
    };

    $attendanceMinutesColumn = match (true) {
        Schema::hasColumn(
            'daily_attendance',
            'minutes_late'
        ) => 'minutes_late',

        Schema::hasColumn(
            'daily_attendance',
            'late_minutes'
        ) => 'late_minutes',

        default => null,
    };

    $attendanceSelect = [
        'id',
        'date',
    ];

    $attendanceSelect[] = $attendanceStatusColumn
        ? DB::raw(
            $attendanceStatusColumn
            .' as attendance_status'
        )
        : DB::raw(
            "'unknown' as attendance_status"
        );

    $attendanceSelect[] = $attendanceEntryColumn
        ? DB::raw(
            $attendanceEntryColumn
            .' as entry_at'
        )
        : DB::raw(
            'NULL as entry_at'
        );

    $attendanceSelect[] = $attendanceExitColumn
        ? DB::raw(
            $attendanceExitColumn
            .' as exit_at'
        )
        : DB::raw(
            'NULL as exit_at'
        );

    $attendanceSelect[] = $attendanceMinutesColumn
        ? DB::raw(
            $attendanceMinutesColumn
            .' as minutes_late'
        )
        : DB::raw(
            '0 as minutes_late'
        );

    $attendance = DB::table(
        'daily_attendance'
    )
        ->where('school_id', $schoolId)
        ->where('student_id', $student)
        ->orderByDesc('date')
        ->limit(15)
        ->get($attendanceSelect);

    /*
     * Bitácora real de entradas, salidas y accesos.
     */
    $accessLogs = collect();

    if (Schema::hasTable('access_logs')) {
        $deviceForeignKey = Schema::hasColumn(
            'access_logs',
            'access_device_id'
        )
            ? 'access_device_id'
            : (
                Schema::hasColumn(
                    'access_logs',
                    'device_id'
                )
                    ? 'device_id'
                    : null
            );

        $accessQuery = DB::table(
            'access_logs as log'
        )
            ->leftJoin(
                'areas as a',
                'a.id',
                '=',
                'log.area_id'
            )
            ->where('log.school_id', $schoolId)
            ->where('log.student_id', $student);

        if (
            $deviceForeignKey
            && Schema::hasTable('access_devices')
        ) {
            $accessQuery->leftJoin(
                'access_devices as d',
                'd.id',
                '=',
                'log.'.$deviceForeignKey
            );
        }

        $accessSelect = [
            'log.id',
            'log.scanned_at',

            Schema::hasColumn(
                'access_logs',
                'event_type'
            )
                ? 'log.event_type'
                : DB::raw(
                    "'access' as event_type"
                ),

            Schema::hasColumn(
                'access_logs',
                'event_status'
            )
                ? 'log.event_status'
                : DB::raw(
                    'NULL as event_status'
                ),

            Schema::hasColumn(
                'access_logs',
                'decision'
            )
                ? 'log.decision'
                : DB::raw(
                    'NULL as decision'
                ),

            Schema::hasColumn(
                'access_logs',
                'action'
            )
                ? 'log.action'
                : DB::raw(
                    'NULL as action'
                ),

            Schema::hasColumn(
                'access_logs',
                'reason'
            )
                ? 'log.reason'
                : DB::raw(
                    'NULL as reason'
                ),

            Schema::hasColumn(
                'access_logs',
                'source'
            )
                ? 'log.source'
                : DB::raw(
                    'NULL as source'
                ),

            'a.name as area_name',
        ];

        if (
            $deviceForeignKey
            && Schema::hasTable('access_devices')
        ) {
            $accessSelect[] =
                'd.name as device_name';
        } else {
            $accessSelect[] = DB::raw(
                'NULL as device_name'
            );
        }

        $accessLogs = $accessQuery
            ->orderByDesc('log.scanned_at')
            ->limit(25)
            ->get($accessSelect);
    }

    /*
     * Resumen operativo de la ficha.
     */
    $activeCredentialsCount = $credentials
        ->where('status', 'active')
        ->count();

    $attendanceSummary = [
        'present' => $attendance
            ->filter(
                fn ($row): bool =>
                    in_array(
                        $row->attendance_status,
                        [
                            'present',
                            'on_time',
                            'present_on_time',
                        ],
                        true
                    )
            )
            ->count(),

        'late' => $attendance
            ->where(
                'attendance_status',
                'late'
            )
            ->count(),

        'very_late' => $attendance
            ->filter(
                fn ($row): bool =>
                    in_array(
                        $row->attendance_status,
                        [
                            'very_late',
                            'extemporaneous',
                            'extemporaneo',
                        ],
                        true
                    )
            )
            ->count(),

        'absent' => $attendance
            ->where(
                'attendance_status',
                'absent'
            )
            ->count(),
    ];

    $accessSummary = [
        'total' => $accessLogs->count(),

        'entries' => $accessLogs
            ->where('event_type', 'entry')
            ->count(),

        'exits' => $accessLogs
            ->where('event_type', 'exit')
            ->count(),

        'denied' => $accessLogs
            ->filter(
                fn ($row): bool =>
                    $row->event_status === 'denied'
                    || $row->decision === 'denied'
                    || $row->action === 'denied'
            )
            ->count(),
    ];

    return view('admin.students.show', [
        'studentRow' => $studentRow,
        'activeCycle' => $activeCycle,
        'activeEnrollment' => $activeEnrollment,

        'enrollments' => $enrollments,
        'movements' => $movements,
        'guardians' => $guardians,
        'credentials' => $credentials,
        'attendance' => $attendance,
        'accessLogs' => $accessLogs,

        'activeCredentialsCount' =>
            $activeCredentialsCount,

        'attendanceSummary' =>
            $attendanceSummary,

        'accessSummary' =>
            $accessSummary,
    ]);
}

    public function manage(
        Request $request,
        int $student
    ): View {
        $schoolId = $this->schoolId(
            $request
        );

        $activeCycle = $this->activeCycle(
            $schoolId
        );

        $studentRow = DB::table(
            'students'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'id',
                $student
            )
            ->firstOrFail();

        $activeEnrollment = null;
        $groups = collect();

        if ($activeCycle) {
            $activeEnrollment = DB::table(
                'student_enrollments as se'
            )
                ->leftJoin(
                    'school_groups as sg',
                    'sg.id',
                    '=',
                    'se.school_group_id'
                )
                ->leftJoin(
                    'academic_levels as al',
                    'al.id',
                    '=',
                    'sg.academic_level_id'
                )
                ->where(
                    'se.school_id',
                    $schoolId
                )
                ->where(
                    'se.student_id',
                    $student
                )
                ->where(
                    'se.academic_cycle_id',
                    $activeCycle->id
                )
                ->select([
                    'se.*',
                    'sg.name as group_name',
                    'al.name as level_name',
                ])
                ->first();

            $groups = $this->groupsForCycle(
                schoolId: $schoolId,
                cycleId: (int) $activeCycle->id
            );
        }

        return view(
            'admin.students.manage',
            [
                'studentRow' => $studentRow,
                'activeCycle' => $activeCycle,
                'activeEnrollment' => $activeEnrollment,
                'groups' => $groups,
            ]
        );
    }

    public function updateEnrollment(
        Request $request,
        int $student
    ): RedirectResponse {
        $schoolId = $this->schoolId(
            $request
        );

        $activeCycle = $this->activeCycle(
            $schoolId
        );

        if (! $activeCycle) {
            return back()->withErrors([
                'cycle' =>
                    'No existe un ciclo activo.',
            ]);
        }

        $studentRow = DB::table(
            'students'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'id',
                $student
            )
            ->firstOrFail();

        $data = $request->validate([
            'action' => [
                'required',

                Rule::in([
                    'assign_group',
                    'suspend',
                    'reactivate',
                    'withdraw',
                    'graduate',
                ]),
            ],

            'group_id' => [
                'nullable',
                'integer',
            ],

            'effective_on' => [
                'required',
                'date',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        try {
            DB::transaction(
                function () use (
                    $request,
                    $schoolId,
                    $activeCycle,
                    $studentRow,
                    $data
                ): void {
                    $student = DB::table(
                        'students'
                    )
                        ->where(
                            'school_id',
                            $schoolId
                        )
                        ->where(
                            'id',
                            $studentRow->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                    $enrollment = DB::table(
                        'student_enrollments'
                    )
                        ->where(
                            'school_id',
                            $schoolId
                        )
                        ->where(
                            'student_id',
                            $student->id
                        )
                        ->where(
                            'academic_cycle_id',
                            $activeCycle->id
                        )
                        ->lockForUpdate()
                        ->first();

                    match ($data['action']) {
                        'assign_group' =>
                            $this->assignGroup(
                                request: $request,
                                schoolId: $schoolId,
                                activeCycle: $activeCycle,
                                student: $student,
                                enrollment: $enrollment,
                                data: $data
                            ),

                        'suspend' =>
                            $this->changeStudentStatus(
                                schoolId: $schoolId,
                                studentId: (int) $student->id,
                                status: 'suspended'
                            ),

                        'reactivate' =>
                            $this->reactivateStudent(
                                schoolId: $schoolId,
                                student: $student,
                                enrollment: $enrollment
                            ),

                        'withdraw' =>
                            $this->withdrawStudent(
                                schoolId: $schoolId,
                                student: $student,
                                enrollment: $enrollment,
                                data: $data
                            ),

                        'graduate' =>
                            $this->graduateStudent(
                                schoolId: $schoolId,
                                student: $student,
                                enrollment: $enrollment,
                                data: $data
                            ),
                    };
                },
                3
            );
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'management' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'admin.students.manage',
                $student
            )
            ->with(
                'success',
                'Gestión del alumno actualizada correctamente.'
            );
    }

    public function uploadPhoto(
        Request $request,
        int $student
    ): RedirectResponse {
        $schoolId = $this->schoolId(
            $request
        );

        $studentRow = DB::table(
            'students'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'id',
                $student
            )
            ->firstOrFail();

        $request->validate([
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $this->deleteExistingPhoto(
            $studentRow->photo_url
        );

        $extension = $request
            ->file('photo')
            ->getClientOriginalExtension();

        $filename =
            'student_'
            .$studentRow->id
            .'_'
            .Str::random(12)
            .'.'
            .$extension;

        $path = $request
            ->file('photo')
            ->storeAs(
                'students/school_'.$schoolId,
                $filename,
                'public'
            );

        DB::table('students')
            ->where(
                'id',
                $studentRow->id
            )
            ->where(
                'school_id',
                $schoolId
            )
            ->update([
                'photo_url' =>
                    Storage::url($path),

                'updated_at' =>
                    now(),
            ]);

        return back()->with(
            'success',
            'Foto del alumno actualizada correctamente.'
        );
    }

    public function removePhoto(
        Request $request,
        int $student
    ): RedirectResponse {
        $schoolId = $this->schoolId(
            $request
        );

        $studentRow = DB::table(
            'students'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'id',
                $student
            )
            ->firstOrFail();

        $this->deleteExistingPhoto(
            $studentRow->photo_url
        );

        DB::table('students')
            ->where(
                'id',
                $studentRow->id
            )
            ->where(
                'school_id',
                $schoolId
            )
            ->update([
                'photo_url' => null,
                'updated_at' => now(),
            ]);

        return back()->with(
            'success',
            'Foto del alumno eliminada correctamente.'
        );
    }

    private function assignGroup(
        Request $request,
        int $schoolId,
        object $activeCycle,
        object $student,
        ?object $enrollment,
        array $data
    ): void {
        if (empty($data['group_id'])) {
            throw new RuntimeException(
                'Debes seleccionar un grupo.'
            );
        }

        $group = $this->groupForCycle(
            schoolId: $schoolId,
            cycleId: (int) $activeCycle->id,
            groupId: (int) $data['group_id']
        );

        $fromGroupId = $enrollment
            ? $enrollment->school_group_id
            : null;

        if (
            $fromGroupId
            && (int) $fromGroupId
                === (int) $group->id
        ) {
            throw new RuntimeException(
                'El alumno ya pertenece a ese grupo.'
            );
        }

        if (! $enrollment) {
            $previousEnrollmentId = DB::table(
                'student_enrollments'
            )
                ->where(
                    'school_id',
                    $schoolId
                )
                ->where(
                    'student_id',
                    $student->id
                )
                ->orderByDesc(
                    'academic_cycle_id'
                )
                ->value('id');

            $enrollmentId = DB::table(
                'student_enrollments'
            )->insertGetId([
                'school_id' =>
                    $schoolId,

                'student_id' =>
                    $student->id,

                'academic_cycle_id' =>
                    $activeCycle->id,

                'school_group_id' =>
                    $group->id,

                'campus_id' =>
                    $group->campus_id,

                'previous_enrollment_id' =>
                    $previousEnrollmentId,

                'status' =>
                    'active',

                'enrollment_type' =>
                    $previousEnrollmentId
                        ? 'reenrollment'
                        : 'new',

                'enrolled_on' =>
                    $data['effective_on'],

                'completed_on' =>
                    null,

                'withdrawn_on' =>
                    null,

                'withdrawal_reason' =>
                    null,

                'notes' =>
                    $data['notes'] ?? null,

                'created_by_user_id' =>
                    $request->user()->id,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

            $movementType =
                'late_enrollment';
        } else {
            DB::table(
                'student_enrollments'
            )
                ->where(
                    'id',
                    $enrollment->id
                )
                ->update([
                    'school_group_id' =>
                        $group->id,

                    'campus_id' =>
                        $group->campus_id,

                    'status' =>
                        'active',

                    'withdrawn_on' =>
                        null,

                    'withdrawal_reason' =>
                        null,

                    'updated_at' =>
                        now(),
                ]);

            $enrollmentId =
                $enrollment->id;

            $movementType =
                'group_change';
        }

        DB::table('students')
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'id',
                $student->id
            )
            ->update([
                'current_group_id' =>
                    $group->id,

                'campus_id' =>
                    $group->campus_id,

                'status' =>
                    'active',

                'updated_at' =>
                    now(),
            ]);

        DB::table(
            'student_group_movements'
        )->insert([
            'school_id' =>
                $schoolId,

            'student_id' =>
                $student->id,

            'academic_cycle_id' =>
                $activeCycle->id,

            'enrollment_id' =>
                $enrollmentId,

            'from_group_id' =>
                $fromGroupId,

            'to_group_id' =>
                $group->id,

            'movement_type' =>
                $movementType,

            'effective_on' =>
                $data['effective_on'],

            'reason' =>
                $data['reason']
                ?? 'Cambio administrativo',

            'notes' =>
                $data['notes'] ?? null,

            'created_by_user_id' =>
                $request->user()->id,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    private function withdrawStudent(
        int $schoolId,
        object $student,
        ?object $enrollment,
        array $data
    ): void {
        if (! $enrollment) {
            throw new RuntimeException(
                'El alumno no tiene inscripción en el ciclo activo.'
            );
        }

        DB::table('student_enrollments')
            ->where(
                'id',
                $enrollment->id
            )
            ->update([
                'status' =>
                    'withdrawn',

                'withdrawn_on' =>
                    $data['effective_on'],

                'withdrawal_reason' =>
                    $data['reason']
                    ?? 'Baja administrativa',

                'updated_at' =>
                    now(),
            ]);

        $this->changeStudentStatus(
            schoolId: $schoolId,
            studentId: (int) $student->id,
            status: 'withdrawn'
        );
    }

    private function graduateStudent(
        int $schoolId,
        object $student,
        ?object $enrollment,
        array $data
    ): void {
        if (! $enrollment) {
            throw new RuntimeException(
                'El alumno no tiene inscripción en el ciclo activo.'
            );
        }

        DB::table('student_enrollments')
            ->where(
                'id',
                $enrollment->id
            )
            ->update([
                'status' =>
                    'graduated',

                'completed_on' =>
                    $data['effective_on'],

                'updated_at' =>
                    now(),
            ]);

        $this->changeStudentStatus(
            schoolId: $schoolId,
            studentId: (int) $student->id,
            status: 'graduated'
        );
    }

    private function reactivateStudent(
        int $schoolId,
        object $student,
        ?object $enrollment
    ): void {
        if ($enrollment) {
            DB::table(
                'student_enrollments'
            )
                ->where(
                    'id',
                    $enrollment->id
                )
                ->update([
                    'status' =>
                        'active',

                    'withdrawn_on' =>
                        null,

                    'withdrawal_reason' =>
                        null,

                    'updated_at' =>
                        now(),
                ]);
        }

        $this->changeStudentStatus(
            schoolId: $schoolId,
            studentId: (int) $student->id,
            status: 'active'
        );
    }

    private function changeStudentStatus(
        int $schoolId,
        int $studentId,
        string $status
    ): void {
        DB::table('students')
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'id',
                $studentId
            )
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
    }

    private function activeCycle(
        int $schoolId
    ): ?object {
        return DB::table(
            'academic_cycles'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                'status',
                'active'
            )
            ->first();
    }

    private function groupsForCycle(
        int $schoolId,
        int $cycleId
    ) {
        return DB::table(
            'school_groups as sg'
        )
            ->leftJoin(
                'academic_levels as al',
                'al.id',
                '=',
                'sg.academic_level_id'
            )
            ->leftJoin(
                'campuses as c',
                'c.id',
                '=',
                'sg.campus_id'
            )
            ->where(
                'sg.school_id',
                $schoolId
            )
            ->where(
                'sg.academic_cycle_id',
                $cycleId
            )
            ->where(
                'sg.status',
                'active'
            )
            ->orderBy('al.sort_order')
            ->orderBy('sg.name')
            ->get([
                'sg.id',
                'sg.name',
                'sg.grade_label',
                'sg.campus_id',
                'al.name as level_name',
                'c.name as campus_name',
            ]);
    }

    private function groupForCycle(
        int $schoolId,
        int $cycleId,
        int $groupId
    ): object {
        return DB::table(
            'school_groups'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'academic_cycle_id',
                $cycleId
            )
            ->where(
                'id',
                $groupId
            )
            ->where(
                'status',
                'active'
            )
            ->firstOrFail();
    }

    private function deleteExistingPhoto(
        ?string $photoUrl
    ): void {
        if (! $photoUrl) {
            return;
        }

        $oldPath = str_replace(
            '/storage/',
            '',
            $photoUrl
        );

        if (
            $oldPath
            && Storage::disk('public')
                ->exists($oldPath)
        ) {
            Storage::disk('public')
                ->delete($oldPath);
        }
    }

    private function schoolId(
        Request $request
    ): int {
        $user = $request->user();

        abort_unless(
            $user && $user->school_id,
            403
        );

        return (int) $user->school_id;
    }
}