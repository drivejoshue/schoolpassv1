<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Attendance\AttendancePeriodService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    public function __construct(
        private readonly AttendancePeriodService $attendancePeriod
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        abort_unless($schoolId > 0, 403);

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->first();

        $timezone = $school?->timezone ?: config('app.timezone');

        $validated = $request->validate([
            'date' => ['nullable', 'date'],

            'campus_id' => [
                'nullable',
                'integer',
                Rule::exists('campuses', 'id')
                    ->where('school_id', $schoolId),
            ],

            'level_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_levels', 'id')
                    ->where('school_id', $schoolId),
            ],

            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('school_groups', 'id')
                    ->where('school_id', $schoolId),
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'present',
                    'on_time',
                    'late',
                    'very_late',
                    'absent',
                    'pending',
                    'no_class',
                    'outside_cycle',
                    'exited',
                    'early_exit',
                ]),
            ],

            'student' => [
                'nullable',
                'string',
                'max:120',
            ],
        ]);

        $today = Carbon::now($timezone)->startOfDay();

        $filters = [
            'date' => $validated['date']
                ?? $today->toDateString(),

            'campus_id' => ! empty($validated['campus_id'])
                ? (int) $validated['campus_id']
                : null,

            'level_id' => ! empty($validated['level_id'])
                ? (int) $validated['level_id']
                : null,

            'group_id' => ! empty($validated['group_id'])
                ? (int) $validated['group_id']
                : null,

            'status' => $validated['status']
                ?? null,

            'student' => trim(
                (string) ($validated['student'] ?? '')
            ),
        ];

        $selectedDate = Carbon::parse(
            $filters['date'],
            $timezone
        )->startOfDay();

        $date = $selectedDate->toDateString();
        $dateIsFuture = $selectedDate->isAfter($today);
        $dateIsToday = $selectedDate->isSameDay($today);
        $weekday = $selectedDate->dayOfWeekIso;

        $activeWindow = $this
            ->attendancePeriod
            ->attendanceWindow($schoolId);

        $activeCycle = $activeWindow['cycle']
            ?? null;

        $dateInsideCycle = $activeWindow !== null
            && $selectedDate->betweenIncluded(
                Carbon::parse($activeWindow['start'], $timezone),
                Carbon::parse($activeWindow['end'], $timezone)
            );

        $calendarDay = null;

        if ($activeCycle) {
            $calendarDay = DB::table('school_calendar_days')
                ->where('school_id', $schoolId)
                ->where('academic_cycle_id', $activeCycle->id)
                ->where('date', $date)
                ->where('status', 'active')
                ->first();
        }

        $isNoClassDay = (bool) (
            $calendarDay
            && in_array(
                $calendarDay->type,
                [
                    'holiday',
                    'vacation',
                    'suspension',
                    'technical_council',
                    'no_class',
                ],
                true
            )
        );

        $allRows = collect();

        if ($activeCycle) {
            $allRows = DB::table('student_enrollments as se')
                ->join('students as s', function ($join) use ($schoolId): void {
                    $join->on('s.id', '=', 'se.student_id')
                        ->where('s.school_id', '=', $schoolId);
                })
                ->join('school_groups as sg', function ($join) use (
                    $schoolId,
                    $activeCycle
                ): void {
                    $join->on('sg.id', '=', 'se.school_group_id')
                        ->where('sg.school_id', '=', $schoolId)
                        ->where(
                            'sg.academic_cycle_id',
                            '=',
                            $activeCycle->id
                        );
                })
                ->join('campuses as c', function ($join) use ($schoolId): void {
                    $join->on('c.id', '=', 'se.campus_id')
                        ->where('c.school_id', '=', $schoolId);
                })
                ->leftJoin(
                    'academic_levels as al',
                    'al.id',
                    '=',
                    'sg.academic_level_id'
                )
                ->leftJoin(
                    'group_access_schedules as gas',
                    function ($join) use (
                        $schoolId,
                        $weekday
                    ): void {
                        $join->on('gas.group_id', '=', 'sg.id')
                            ->where('gas.school_id', '=', $schoolId)
                            ->where('gas.weekday', '=', $weekday)
                            ->where('gas.status', '=', 'active');
                    }
                )
                ->leftJoin(
                    'daily_attendance as da',
                    function ($join) use (
                        $schoolId,
                        $date
                    ): void {
                        $join->on(
                            'da.student_id',
                            '=',
                            'se.student_id'
                        )
                            ->where(
                                'da.school_id',
                                '=',
                                $schoolId
                            )
                            ->where(
                                'da.date',
                                '=',
                                $date
                            );
                    }
                )

                // Log exacto de entrada.
                ->leftJoin(
                    'access_logs as entry_log',
                    'entry_log.id',
                    '=',
                    'da.entry_log_id'
                )
                ->leftJoin(
                    'guardians as entry_guardian',
                    'entry_guardian.id',
                    '=',
                    'entry_log.guardian_id'
                )
                ->leftJoin(
                    'access_devices as entry_device',
                    'entry_device.id',
                    '=',
                    'entry_log.access_device_id'
                )
                ->leftJoin(
                    'users as entry_user',
                    'entry_user.id',
                    '=',
                    'entry_log.user_id'
                )

                // Log exacto de salida.
                ->leftJoin(
                    'access_logs as exit_log',
                    'exit_log.id',
                    '=',
                    'da.exit_log_id'
                )
                ->leftJoin(
                    'guardians as exit_guardian',
                    'exit_guardian.id',
                    '=',
                    'exit_log.guardian_id'
                )
                ->leftJoin(
                    'access_devices as exit_device',
                    'exit_device.id',
                    '=',
                    'exit_log.access_device_id'
                )
                ->leftJoin(
                    'users as exit_user',
                    'exit_user.id',
                    '=',
                    'exit_log.user_id'
                )

                ->where('se.school_id', $schoolId)
                ->where(
                    'se.academic_cycle_id',
                    $activeCycle->id
                )
                ->where('se.status', 'active')
                ->where('s.status', 'active')
                ->whereDate('se.enrolled_on', '<=', $date)
                ->where(function ($query) use ($date): void {
                    $query
                        ->whereNull('se.withdrawn_on')
                        ->orWhereDate(
                            'se.withdrawn_on',
                            '>=',
                            $date
                        );
                })
                ->when(
                    $filters['campus_id'],
                    fn ($query, $campusId) =>
                        $query->where(
                            'se.campus_id',
                            $campusId
                        )
                )
                ->when(
                    $filters['level_id'],
                    fn ($query, $levelId) =>
                        $query->where(
                            'sg.academic_level_id',
                            $levelId
                        )
                )
                ->when(
                    $filters['group_id'],
                    fn ($query, $groupId) =>
                        $query->where(
                            'se.school_group_id',
                            $groupId
                        )
                )
                ->when(
                    $filters['student'] !== '',
                    function ($query) use ($filters): void {
                        $search = $filters['student'];

                        $query->where(
                            function ($inner) use ($search): void {
                                $inner
                                    ->where(
                                        's.student_code',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        's.first_name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        's.last_name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhereRaw(
                                        "CONCAT(
                                            s.first_name,
                                            ' ',
                                            s.last_name
                                        ) LIKE ?",
                                        ["%{$search}%"]
                                    );
                            }
                        );
                    }
                )
                ->select([
                    's.id as student_id',
                    's.student_code',
                    's.first_name',
                    's.last_name',
                    's.photo_url',

                    'c.id as campus_id',
                    'c.name as campus_name',

                    'al.id as level_id',
                    'al.name as level_name',
                    'al.sort_order as level_sort_order',

                    'sg.id as group_id',
                    'sg.name as group_name',
                    'sg.grade_label',

                    'gas.id as schedule_id',
                    'gas.entry_time as scheduled_entry_time',
                    'gas.grace_until',
                    'gas.late_until',
                    'gas.exit_time as scheduled_exit_time',

                    'da.id as attendance_id',
                    'da.attendance_status as raw_attendance_status',
                    'da.entry_at',
                    'da.exit_at',
                    'da.minutes_late',
                    'da.entry_log_id',
                    'da.exit_log_id',

                    'entry_log.event_status as entry_event_status',
                    'entry_log.decision as entry_decision',
                    'entry_log.source as entry_source',
                    'entry_log.reader_type as entry_reader_type',
                    'entry_log.performed_for as entry_performed_for',
                    'entry_log.reason as entry_reason',
                    'entry_log.notes as entry_notes',

                    'entry_guardian.id as entry_guardian_id',
                    'entry_guardian.first_name as entry_guardian_first_name',
                    'entry_guardian.last_name as entry_guardian_last_name',

                    'entry_device.name as entry_device_name',
                    'entry_user.name as entry_user_name',
                    'entry_user.role as entry_user_role',

                    'exit_log.event_status as exit_event_status',
                    'exit_log.decision as exit_decision',
                    'exit_log.source as exit_source',
                    'exit_log.reader_type as exit_reader_type',
                    'exit_log.performed_for as exit_performed_for',
                    'exit_log.reason as exit_reason',
                    'exit_log.notes as exit_notes',

                    'exit_guardian.id as exit_guardian_id',
                    'exit_guardian.first_name as exit_guardian_first_name',
                    'exit_guardian.last_name as exit_guardian_last_name',

                    'exit_device.name as exit_device_name',
                    'exit_user.name as exit_user_name',
                    'exit_user.role as exit_user_role',
                ])
                ->orderBy('al.sort_order')
                ->orderBy('sg.name')
                ->orderBy('s.last_name')
                ->orderBy('s.first_name')
                ->get()
                ->map(function ($row) use (
                    $dateInsideCycle,
                    $dateIsFuture,
                    $dateIsToday,
                    $isNoClassDay,
                    $timezone
                ): object {
                    $row->entry_guardian_name = $this->fullName(
                        $row->entry_guardian_first_name,
                        $row->entry_guardian_last_name
                    );

                    $row->exit_guardian_name = $this->fullName(
                        $row->exit_guardian_first_name,
                        $row->exit_guardian_last_name
                    );

                    $row->has_exit = ! empty($row->exit_at);

                    $row->is_early_exit =
                        $row->exit_event_status === 'early_exit'
                        || $row->raw_attendance_status === 'early_exit';

                    if (! $dateInsideCycle || $dateIsFuture) {
                        $row->final_status = 'outside_cycle';

                        return $row;
                    }

                    if ($isNoClassDay || ! $row->schedule_id) {
                        $row->final_status = $row->attendance_id
                            ? $this->normalizeAttendanceStatus($row)
                            : 'no_class';

                        return $row;
                    }

                    if (! $row->attendance_id) {
                        if (
                            $dateIsToday
                            && $row->late_until
                            && Carbon::now($timezone)->format('H:i:s')
                                <= $row->late_until
                        ) {
                            $row->final_status = 'pending';
                        } else {
                            $row->final_status = 'absent';
                        }

                        return $row;
                    }

                    $row->final_status =
                        $this->normalizeAttendanceStatus($row);

                    return $row;
                });
        }

        /*
         * Los indicadores respetan fecha, plantel, nivel, grupo y alumno,
         * pero no se reducen al seleccionar un estado concreto.
         */
        $summary = $this->summary($allRows);

        $displayRows = $this->applyStatusFilter(
            $allRows,
            $filters['status']
        );

        $rows = $this->paginate(
            request: $request,
            rows: $displayRows,
            perPage: 25
        );

        return view('admin.reports.attendance', [
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters,

            'school' => $school,
            'activeCycle' => $activeCycle,
            'calendarDay' => $calendarDay,

            'isNoClassDay' => $isNoClassDay,
            'hasActiveCycle' => $activeCycle !== null,
            'dateInsideCycle' => $dateInsideCycle,
            'dateIsFuture' => $dateIsFuture,

            'campuses' => $this->campuses(
                schoolId: $schoolId,
                cycleId: $activeCycle?->id
            ),

            'levels' => $this->levels(
                schoolId: $schoolId,
                cycleId: $activeCycle?->id
            ),

            'groups' => $this->groups(
                schoolId: $schoolId,
                cycleId: $activeCycle?->id,
                campusId: $filters['campus_id'],
                levelId: $filters['level_id']
            ),

            'displayedTotal' => $displayRows->count(),
        ]);
    }

    private function normalizeAttendanceStatus(object $row): string
    {
        if (
            in_array(
                $row->entry_event_status,
                ['on_time', 'late', 'very_late'],
                true
            )
        ) {
            return $row->entry_event_status;
        }

        if (
            in_array(
                $row->raw_attendance_status,
                ['on_time', 'late', 'very_late'],
                true
            )
        ) {
            if (
                $row->raw_attendance_status === 'late'
                && (int) $row->minutes_late > 20
            ) {
                return 'very_late';
            }

            return $row->raw_attendance_status;
        }

        if (
            in_array(
                $row->raw_attendance_status,
                ['present', 'early_exit'],
                true
            )
        ) {
            return $this->statusFromMinutes(
                (int) $row->minutes_late
            );
        }

        return $this->statusFromMinutes(
            (int) $row->minutes_late
        );
    }

    private function applyStatusFilter(
        $rows,
        ?string $status
    ) {
        if (! $status) {
            return $rows->values();
        }

        return match ($status) {
            'present' => $rows
                ->whereIn(
                    'final_status',
                    ['on_time', 'late', 'very_late']
                )
                ->values(),

            'exited' => $rows
                ->filter(
                    fn ($row): bool => (bool) $row->has_exit
                )
                ->values(),

            'early_exit' => $rows
                ->filter(
                    fn ($row): bool => (bool) $row->is_early_exit
                )
                ->values(),

            default => $rows
                ->where('final_status', $status)
                ->values(),
        };
    }

    private function summary($rows): array
    {
        $present = $rows
            ->whereIn(
                'final_status',
                ['on_time', 'late', 'very_late']
            )
            ->count();

        $eligible = $rows
            ->filter(
                fn ($row): bool => ! in_array(
                    $row->final_status,
                    [
                        'no_class',
                        'outside_cycle',
                        'pending',
                    ],
                    true
                )
            )
            ->count();

        return [
            'total' => $rows->count(),

            'present' => $present,

            'on_time' => $rows
                ->where('final_status', 'on_time')
                ->count(),

            'late' => $rows
                ->where('final_status', 'late')
                ->count(),

            'very_late' => $rows
                ->where('final_status', 'very_late')
                ->count(),

            'absent' => $rows
                ->where('final_status', 'absent')
                ->count(),

            'pending' => $rows
                ->where('final_status', 'pending')
                ->count(),

            'no_class' => $rows
                ->where('final_status', 'no_class')
                ->count(),

            'outside_cycle' => $rows
                ->where('final_status', 'outside_cycle')
                ->count(),

            'exited' => $rows
                ->filter(
                    fn ($row): bool => (bool) $row->has_exit
                )
                ->count(),

            'early_exit' => $rows
                ->filter(
                    fn ($row): bool => (bool) $row->is_early_exit
                )
                ->count(),

            'eligible' => $eligible,

            'attendance_rate' => $eligible > 0
                ? round(($present / $eligible) * 100, 1)
                : 0.0,
        ];
    }

    private function paginate(
        Request $request,
        $rows,
        int $perPage
    ): LengthAwarePaginator {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            items: $rows
                ->forPage($page, $perPage)
                ->values(),
            total: $rows->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function statusFromMinutes(int $minutesLate): string
    {
        if ($minutesLate <= 0) {
            return 'on_time';
        }

        if ($minutesLate <= 20) {
            return 'late';
        }

        return 'very_late';
    }

    private function fullName(
        ?string $firstName,
        ?string $lastName
    ): ?string {
        $name = trim(
            trim((string) $firstName)
            .' '
            .trim((string) $lastName)
        );

        return $name !== ''
            ? $name
            : null;
    }

    private function campuses(
        int $schoolId,
        ?int $cycleId
    ) {
        if (! $cycleId) {
            return collect();
        }

        return DB::table('campuses as c')
            ->join(
                'school_groups as sg',
                'sg.campus_id',
                '=',
                'c.id'
            )
            ->where('c.school_id', $schoolId)
            ->where('c.status', 'active')
            ->where('sg.school_id', $schoolId)
            ->where('sg.academic_cycle_id', $cycleId)
            ->where('sg.status', 'active')
            ->select([
                'c.id',
                'c.name',
            ])
            ->distinct()
            ->orderBy('c.name')
            ->get();
    }

    private function levels(
        int $schoolId,
        ?int $cycleId
    ) {
        if (! $cycleId) {
            return collect();
        }

        return DB::table('academic_levels as al')
            ->join(
                'school_groups as sg',
                'sg.academic_level_id',
                '=',
                'al.id'
            )
            ->where('al.school_id', $schoolId)
            ->where('al.status', 'active')
            ->where('sg.school_id', $schoolId)
            ->where('sg.academic_cycle_id', $cycleId)
            ->where('sg.status', 'active')
            ->select([
                'al.id',
                'al.name',
                'al.sort_order',
            ])
            ->distinct()
            ->orderBy('al.sort_order')
            ->orderBy('al.name')
            ->get();
    }

    private function groups(
        int $schoolId,
        ?int $cycleId,
        ?int $campusId,
        ?int $levelId
    ) {
        if (! $cycleId) {
            return collect();
        }

        return DB::table('school_groups as sg')
            ->join(
                'campuses as c',
                'c.id',
                '=',
                'sg.campus_id'
            )
            ->leftJoin(
                'academic_levels as al',
                'al.id',
                '=',
                'sg.academic_level_id'
            )
            ->where('sg.school_id', $schoolId)
            ->where('sg.academic_cycle_id', $cycleId)
            ->where('sg.status', 'active')
            ->when(
                $campusId,
                fn ($query, $value) =>
                    $query->where('sg.campus_id', $value)
            )
            ->when(
                $levelId,
                fn ($query, $value) =>
                    $query->where(
                        'sg.academic_level_id',
                        $value
                    )
            )
            ->select([
                'sg.id',
                'sg.name',
                'sg.campus_id',
                'sg.academic_level_id',
                'c.name as campus_name',
                'al.name as level_name',
                'al.sort_order as level_sort_order',
            ])
            ->orderBy('c.name')
            ->orderBy('al.sort_order')
            ->orderBy('sg.name')
            ->get();
    }
}
