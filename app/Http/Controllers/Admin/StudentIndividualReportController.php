<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Attendance\AttendancePeriodService;

class StudentIndividualReportController extends Controller
{

    public function __construct(
    private readonly AttendancePeriodService $attendancePeriod
) {
}

    public function index(Request $request): View
    {
        $schoolId = $this->schoolId($request);
        $filters = $this->filters($request);

        $students = $this->studentOptions($schoolId);
        $report = null;

        if ($filters['student_id']) {
            $report = $this->buildReport(
                schoolId: $schoolId,
                studentId: $filters['student_id'],
                from: $filters['from'],
                to: $filters['to'],
            );
        }

        return view('admin.reports.student-individual', [
            'filters' => $filters,
            'students' => $students,
            'report' => $report,
        ]);
    }

    public function pdf(Request $request): Response
    {
        $schoolId = $this->schoolId($request);
        $filters = $this->filters($request, studentRequired: true);

        $report = $this->buildReport(
            schoolId: $schoolId,
            studentId: $filters['student_id'],
            from: $filters['from'],
            to: $filters['to'],
        );

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->first();

        $chartImage = $this->buildAttendanceChartPng(
            $report['daily_chart']
        );

        $pdf = Pdf::loadView(
            'admin.reports.student-individual-pdf',
            [
                'school' => $school,
                'filters' => $filters,
                'report' => $report,
                'chartImage' => $chartImage,
                'generatedAt' => now(),
            ]
        )
            ->setPaper('letter', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);

        $filename = sprintf(
            'reporte_alumno_%s_%s_%s.pdf',
            $report['student']->student_code,
            $filters['from'],
            $filters['to']
        );

        return $pdf->download($filename);
    }

    private function buildReport(
        int $schoolId,
        int $studentId,
        string $from,
        string $to
    ): array {
        $requestedFrom = Carbon::parse($from)->startOfDay();
        $requestedTo = Carbon::parse($to)->endOfDay();

        $activeWindow = $this->attendancePeriod
            ->attendanceWindow($schoolId);

        $effectiveRange = $this->attendancePeriod
            ->clampRange(
                schoolId: $schoolId,
                from: $requestedFrom,
                to: $requestedTo
            );

        $activeCycle = $activeWindow['cycle'] ?? null;
        $activeCycleId = $activeCycle?->id;

        $student = DB::table('students as s')
            ->leftJoin('student_enrollments as se', function ($join) use (
                $schoolId,
                $activeCycleId
            ): void {
                $join->on('se.student_id', '=', 's.id')
                    ->where('se.school_id', '=', $schoolId);

                if ($activeCycleId) {
                    $join->where(
                        'se.academic_cycle_id',
                        '=',
                        $activeCycleId
                    );
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->leftJoin('school_groups as enrollment_group', function (
                $join
            ) use ($schoolId): void {
                $join->on(
                    'enrollment_group.id',
                    '=',
                    'se.school_group_id'
                )->where(
                    'enrollment_group.school_id',
                    '=',
                    $schoolId
                );
            })
            ->leftJoin('academic_levels as enrollment_level', function (
                $join
            ): void {
                $join->on(
                    'enrollment_level.id',
                    '=',
                    'enrollment_group.academic_level_id'
                );
            })
            ->leftJoin('campuses as enrollment_campus', function (
                $join
            ) use ($schoolId): void {
                $join->on(
                    'enrollment_campus.id',
                    '=',
                    'se.campus_id'
                )->where(
                    'enrollment_campus.school_id',
                    '=',
                    $schoolId
                );
            })
            ->leftJoin('school_groups as current_group', function (
                $join
            ) use ($schoolId): void {
                $join->on(
                    'current_group.id',
                    '=',
                    's.current_group_id'
                )->where(
                    'current_group.school_id',
                    '=',
                    $schoolId
                );
            })
            ->leftJoin('academic_levels as current_level', function (
                $join
            ): void {
                $join->on(
                    'current_level.id',
                    '=',
                    'current_group.academic_level_id'
                );
            })
            ->leftJoin('campuses as current_campus', function (
                $join
            ) use ($schoolId): void {
                $join->on('current_campus.id', '=', 's.campus_id')
                    ->where(
                        'current_campus.school_id',
                        '=',
                        $schoolId
                    );
            })
            ->where('s.school_id', $schoolId)
            ->where('s.id', $studentId)
            ->select([
                's.id',
                's.student_code',
                's.first_name',
                's.last_name',
                's.photo_url',
                's.status',
                's.notes',

                'se.id as enrollment_id',
                'se.academic_cycle_id',
                'se.status as enrollment_status',
                'se.enrolled_on',
                'se.withdrawn_on',
                'se.completed_on',

                DB::raw(
                    'COALESCE(se.campus_id, s.campus_id) as campus_id'
                ),
                DB::raw(
                    'COALESCE(
                        enrollment_campus.name,
                        current_campus.name
                    ) as campus_name'
                ),
                DB::raw(
                    'COALESCE(
                        enrollment_group.id,
                        current_group.id
                    ) as group_id'
                ),
                DB::raw(
                    'COALESCE(
                        enrollment_group.name,
                        current_group.name
                    ) as group_name'
                ),
                DB::raw(
                    'COALESCE(
                        enrollment_group.grade_label,
                        current_group.grade_label
                    ) as grade_label'
                ),
                DB::raw(
                    'COALESCE(
                        enrollment_level.name,
                        current_level.name
                    ) as level_name'
                ),
            ])
            ->first();

        abort_unless($student, 404);

        /*
         * La ausencia solo puede inferirse mientras la inscripción estuvo
         * vigente dentro del ciclo y del rango solicitado.
         */
        if ($effectiveRange !== null && $student->enrollment_id) {
            $enrollmentStart = $student->enrolled_on
                ? Carbon::parse($student->enrolled_on)->startOfDay()
                : $effectiveRange['from']->copy()->startOfDay();

            $enrollmentEnd = $student->withdrawn_on
                ? Carbon::parse($student->withdrawn_on)->endOfDay()
                : ($student->completed_on
                    ? Carbon::parse($student->completed_on)->endOfDay()
                    : $effectiveRange['to']->copy()->endOfDay());

            $effectiveFrom = $effectiveRange['from']->copy()->startOfDay()
                ->max($enrollmentStart);

            $effectiveTo = $effectiveRange['to']->copy()->endOfDay()
                ->min($enrollmentEnd);

            $effectiveRange = $effectiveFrom->lte($effectiveTo)
                ? [
                    'from' => $effectiveFrom,
                    'to' => $effectiveTo,
                    'cycle' => $activeCycle,
                ]
                : null;
        } else {
            $effectiveRange = null;
        }

        $guardians = DB::table('student_guardians as sg')
            ->join('guardians as ga', function ($join) use (
                $schoolId
            ): void {
                $join->on('ga.id', '=', 'sg.guardian_id')
                    ->where('ga.school_id', '=', $schoolId);
            })
            ->where('sg.student_id', $studentId)
            ->where('sg.status', 'active')
            ->orderByDesc('sg.is_primary')
            ->orderBy('ga.last_name')
            ->orderBy('ga.first_name')
            ->get([
                'ga.id',
                'ga.first_name',
                'ga.last_name',
                'ga.phone',
                'ga.email',
                'sg.relationship',
                'sg.is_primary',
                'sg.can_view_attendance',
                'sg.can_receive_notifications',
                'sg.can_authorize_exit',
            ]);

        $attendance = $this->attendanceRows(
            schoolId: $schoolId,
            studentId: $studentId,
            requestedFrom: $requestedFrom,
            requestedTo: $requestedTo,
            effectiveRange: $effectiveRange
        );

        $accessLogs = $this->accessRows(
            schoolId: $schoolId,
            studentId: $studentId,
            from: $requestedFrom->toDateString(),
            to: $requestedTo->toDateString()
        );

        $summary = [
            'on_time' => $attendance
                ->where('final_status', 'on_time')->count(),
            'late' => $attendance
                ->where('final_status', 'late')->count(),
            'very_late' => $attendance
                ->where('final_status', 'very_late')->count(),
            'absent' => $attendance
                ->where('final_status', 'absent')->count(),
            'no_class' => $attendance
                ->where('final_status', 'no_class')->count(),
            'entries' => $accessLogs
                ->where('event_type', 'entry')->count(),
            'exits' => $accessLogs
                ->where('event_type', 'exit')->count(),
            'denied' => $accessLogs
                ->where('decision', 'denied')->count(),
            'early_exits' => $accessLogs
                ->where('event_status', 'early_exit')->count(),
        ];

        $classified = $summary['on_time']
            + $summary['late']
            + $summary['very_late'];

        $summary['punctuality_rate'] = $classified > 0
            ? round(($summary['on_time'] / $classified) * 100, 1)
            : 0.0;

        $summary['attendance_days'] = $summary['on_time']
            + $summary['late']
            + $summary['very_late'];

        $summary['incidents'] = $summary['late']
            + $summary['very_late']
            + $summary['absent']
            + $summary['denied']
            + $summary['early_exits'];

        return [
            'student' => $student,
            'guardians' => $guardians,
            'attendance' => $attendance,
            'access_logs' => $accessLogs,
            'summary' => $summary,
            'daily_chart' => $this->dailyChart(
                attendance: $attendance,
                from: $requestedFrom->toDateString(),
                to: $requestedTo->toDateString()
            ),
            'active_cycle' => $activeCycle,
            'has_active_cycle' => $activeWindow !== null,
            'has_enrollment' => $student->enrollment_id !== null,
            'has_effective_range' => $effectiveRange !== null,
            'effective_from' => $effectiveRange['from'] ?? null,
            'effective_to' => $effectiveRange['to'] ?? null,
        ];
    }

    private function attendanceRows(
    int $schoolId,
    int $studentId,
    Carbon $requestedFrom,
    Carbon $requestedTo,
    ?array $effectiveRange
): Collection {
    /*
     * Sin ciclo activo o sin intersección con el rango,
     * no se construye ninguna ausencia automática.
     */
    if ($effectiveRange === null) {
        return collect();
    }

    $start = $effectiveRange['from']
        ->copy()
        ->startOfDay();

    $end = $effectiveRange['to']
        ->copy()
        ->endOfDay();

    $statusSql = $this->attendanceStatusSql();

    $entrySql = $this->attendanceColumnSql(
        [
            'entry_time',
            'first_entry_at',
            'entry_at',
        ],
        'null'
    );

    $exitSql = $this->attendanceColumnSql(
        [
            'exit_time',
            'last_exit_at',
            'exit_at',
        ],
        'null'
    );

    $minutesSql = $this->attendanceColumnSql(
        [
            'minutes_late',
            'late_minutes',
        ],
        '0'
    );

    $attendance = DB::table(
        'daily_attendance as da'
    )
        ->where('da.school_id', $schoolId)
        ->where('da.student_id', $studentId)
        ->whereBetween(
            'da.date',
            [
                $start->toDateString(),
                $end->toDateString(),
            ]
        )
        ->select([
            'da.id',
            'da.date',

            DB::raw(
                $statusSql
                .' as attendance_status'
            ),

            DB::raw(
                $entrySql
                .' as entry_time'
            ),

            DB::raw(
                $exitSql
                .' as exit_time'
            ),

            DB::raw(
                $minutesSql
                .' as minutes_late'
            ),
        ])
        ->orderBy('da.date')
        ->get()
        ->keyBy('date');

    $calendarDays = DB::table(
        'school_calendar_days'
    )
        ->where('school_id', $schoolId)
        ->where('status', 'active')
        ->whereBetween(
            'date',
            [
                $start->toDateString(),
                $end->toDateString(),
            ]
        )
        ->get()
        ->keyBy('date');

    $rows = collect();
    $cursor = $start->copy()->startOfDay();

    while ($cursor->lte($end)) {
        $date = $cursor->toDateString();

        $calendarDay = $calendarDays->get($date);
        $record = $attendance->get($date);

        $isClassDay = $this->isClassDay(
            $cursor,
            $calendarDay
        );

        if (! $isClassDay) {
            $finalStatus = 'no_class';
        } elseif (! $record) {
            /*
             * Esta es la única situación donde se infiere ausencia:
             *
             * - la fecha está dentro del ciclo activo;
             * - el día ya ocurrió;
             * - es día de clase;
             * - no existe registro.
             */
            $finalStatus = 'absent';
        } else {
            $finalStatus = trim(
                (string) (
                    $record->attendance_status
                    ?? ''
                )
            );

            if ($finalStatus === '') {
                $finalStatus =
                    $this->statusFromMinutes(
                        (int) (
                            $record->minutes_late
                            ?? 0
                        )
                    );
            }
        }

        $rows->push((object) [
            'date' => $date,

            'weekday' => ucfirst(
                $cursor
                    ->locale('es')
                    ->dayName
            ),

            'attendance_id' =>
                $record?->id,

            'entry_time' =>
                $record?->entry_time,

            'exit_time' =>
                $record?->exit_time,

            'minutes_late' => (int) (
                $record?->minutes_late
                ?? 0
            ),

            'final_status' =>
                $finalStatus,

            'calendar_title' =>
                $calendarDay?->title,

            'calendar_type' =>
                $calendarDay?->type,
        ]);

        $cursor->addDay();
    }

    return $rows;
}

    private function accessRows(
        int $schoolId,
        int $studentId,
        string $from,
        string $to
    ): Collection {
        $deviceColumn = $this->deviceLogColumn();

        return DB::table('access_logs as al')
            ->leftJoin('areas as a', function ($join) use (
                $schoolId
            ): void {
                $join->on('a.id', '=', 'al.area_id')
                    ->where('a.school_id', '=', $schoolId);
            })
            ->leftJoin(
                'access_devices as ad',
                'ad.id',
                '=',
                'al.'.$deviceColumn
            )
            ->leftJoin(
                'academic_cycles as ac',
                'ac.id',
                '=',
                'al.academic_cycle_id'
            )
            ->leftJoin(
                'school_groups as historical_group',
                'historical_group.id',
                '=',
                'al.school_group_id'
            )
            ->leftJoin(
                'academic_levels as historical_level',
                'historical_level.id',
                '=',
                'historical_group.academic_level_id'
            )
            ->where('al.school_id', $schoolId)
            ->where('al.student_id', $studentId)
            ->whereBetween('al.scanned_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ])
            ->orderByDesc('al.scanned_at')
            ->get([
                'al.id',
                'al.academic_cycle_id',
                'al.student_enrollment_id',
                'al.school_group_id',
                'al.event_type',
                'al.event_status',
                'al.decision',
                'al.action',
                'al.reason',
                'al.source',
                'al.reader_type',
                'al.minutes_late',
                'al.scanned_at',
                'ac.name as cycle_name',
                'historical_group.name as group_name',
                'historical_level.name as level_name',
                'a.name as area_name',
                'ad.name as device_name',
            ]);
    }

    private function dailyChart(
        Collection $attendance,
        string $from,
        string $to
    ): array {
        /*
         * Gráfica semanal para que no quede ilegible
         * cuando se seleccionan varios meses.
         */
        return $attendance
            ->filter(
                fn ($row) => $row->final_status !== 'no_class'
            )
            ->groupBy(function ($row): string {
                return Carbon::parse($row->date)
                    ->startOfWeek()
                    ->toDateString();
            })
            ->map(function (
                Collection $rows,
                string $weekStart
            ): array {
                $start = Carbon::parse($weekStart);
                $end = $start->copy()->endOfWeek();

                return [
                    'week' => $weekStart,
                    'label' => sprintf(
                        '%s-%s',
                        $start->format('d/m'),
                        $end->format('d/m')
                    ),
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
                ];
            })
            ->values()
            ->all();
    }

    private function filters(
        Request $request,
        bool $studentRequired = false
    ): array {
        $validated = $request->validate([
            'student_id' => [
                $studentRequired ? 'required' : 'nullable',
                'integer',
            ],
            'from' => [
                'nullable',
                'date',
            ],
            'to' => [
                'nullable',
                'date',
            ],
        ]);

        $from = Carbon::parse(
            $validated['from']
                ?? now()->subDays(29)->toDateString()
        )->toDateString();

        $to = Carbon::parse(
            $validated['to']
                ?? now()->toDateString()
        )->toDateString();

        if (Carbon::parse($from)->gt(Carbon::parse($to))) {
            [$from, $to] = [$to, $from];
        }

        /*
         * Máximo un ciclo anual por consulta.
         */
        $maximumTo = Carbon::parse($from)
            ->addDays(365)
            ->toDateString();

        if (Carbon::parse($to)->gt(Carbon::parse($maximumTo))) {
            $to = $maximumTo;
        }

        return [
            'student_id' => ! empty($validated['student_id'])
                ? (int) $validated['student_id']
                : null,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function studentOptions(int $schoolId): Collection
    {
        $activeCycleId = DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->value('id');

        return DB::table('students as s')
            ->leftJoin('student_enrollments as se', function (
                $join
            ) use ($schoolId, $activeCycleId): void {
                $join->on('se.student_id', '=', 's.id')
                    ->where('se.school_id', '=', $schoolId);

                if ($activeCycleId) {
                    $join->where(
                        'se.academic_cycle_id',
                        '=',
                        $activeCycleId
                    );
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->leftJoin(
                'school_groups as enrollment_group',
                'enrollment_group.id',
                '=',
                'se.school_group_id'
            )
            ->leftJoin(
                'academic_levels as enrollment_level',
                'enrollment_level.id',
                '=',
                'enrollment_group.academic_level_id'
            )
            ->leftJoin(
                'school_groups as current_group',
                'current_group.id',
                '=',
                's.current_group_id'
            )
            ->leftJoin(
                'academic_levels as current_level',
                'current_level.id',
                '=',
                'current_group.academic_level_id'
            )
            ->where('s.school_id', $schoolId)
            ->where('s.status', 'active')
            ->orderByRaw(
                'COALESCE(enrollment_level.sort_order, current_level.sort_order)'
            )
            ->orderByRaw(
                'COALESCE(enrollment_group.name, current_group.name)'
            )
            ->orderBy('s.last_name')
            ->orderBy('s.first_name')
            ->get([
                's.id',
                's.student_code',
                's.first_name',
                's.last_name',
                'se.id as enrollment_id',
                'se.status as enrollment_status',
                DB::raw(
                    'COALESCE(
                        enrollment_group.name,
                        current_group.name
                    ) as group_name'
                ),
                DB::raw(
                    'COALESCE(
                        enrollment_level.name,
                        current_level.name
                    ) as level_name'
                ),
            ]);
    }

    private function attendanceStatusSql(): string
    {
        if (Schema::hasColumn('daily_attendance', 'status')) {
            return 'da.status';
        }

        if (Schema::hasColumn('daily_attendance', 'entry_status')) {
            return 'da.entry_status';
        }

        if (
            Schema::hasColumn(
                'daily_attendance',
                'attendance_status'
            )
        ) {
            return 'da.attendance_status';
        }

        if (Schema::hasColumn('daily_attendance', 'minutes_late')) {
            return "
                CASE
                    WHEN da.minutes_late IS NULL
                        THEN 'on_time'
                    WHEN da.minutes_late <= 0
                        THEN 'on_time'
                    WHEN da.minutes_late <= 20
                        THEN 'late'
                    ELSE 'very_late'
                END
            ";
        }

        return "'on_time'";
    }

    private function attendanceColumnSql(
        array $possibleColumns,
        string $fallback
    ): string {
        foreach ($possibleColumns as $column) {
            if (
                Schema::hasColumn(
                    'daily_attendance',
                    $column
                )
            ) {
                return 'da.'.$column;
            }
        }

        return $fallback;
    }

    private function deviceLogColumn(): string
    {
        return Schema::hasColumn('access_logs', 'device_id')
            ? 'device_id'
            : 'access_device_id';
    }

    private function isClassDay(
        Carbon $date,
        ?object $calendarDay
    ): bool {
        if ($calendarDay) {
            $type = (string) $calendarDay->type;

            if (in_array($type, [
                'holiday',
                'vacation',
                'suspension',
                'technical_council',
                'no_class',
            ], true)) {
                return false;
            }

            if (in_array($type, [
                'class',
                'school_day',
                'special_class',
                'makeup_class',
            ], true)) {
                return true;
            }
        }

        return ! $date->isWeekend();
    }

    private function statusFromMinutes(
        int $minutesLate
    ): string {
        if ($minutesLate <= 0) {
            return 'on_time';
        }

        if ($minutesLate <= 20) {
            return 'late';
        }

        return 'very_late';
    }

    private function schoolId(Request $request): int
    {
        $user = $request->user();

        abort_unless(
            $user && $user->school_id,
            403
        );

        return (int) $user->school_id;
    }

    private function buildAttendanceChartPng(
        array $rows
    ): string {
        if (! extension_loaded('gd')) {
            throw new RuntimeException(
                'La extensión GD es necesaria para generar la gráfica.'
            );
        }

        $width = 900;
        $height = 300;

        $image = imagecreatetruecolor(
            $width,
            $height
        );

        if ($image === false) {
            throw new RuntimeException(
                'No se pudo crear la gráfica.'
            );
        }

        $white = imagecolorallocate(
            $image,
            255,
            255,
            255
        );

        $grid = imagecolorallocate(
            $image,
            226,
            232,
            240
        );

        $text = imagecolorallocate(
            $image,
            51,
            65,
            85
        );

        $green = imagecolorallocate(
            $image,
            22,
            163,
            74
        );

        $yellow = imagecolorallocate(
            $image,
            245,
            158,
            11
        );

        $orange = imagecolorallocate(
            $image,
            234,
            88,
            12
        );

        $red = imagecolorallocate(
            $image,
            220,
            38,
            38
        );

        imagefill($image, 0, 0, $white);

        if ($rows === []) {
            imagestring(
                $image,
                5,
                300,
                140,
                'No hay datos para el periodo.',
                $text
            );

            return $this->pngDataUri($image);
        }

        $series = [
            [
                'key' => 'on_time',
                'label' => 'Puntual',
                'color' => $green,
            ],
            [
                'key' => 'late',
                'label' => 'Retardo',
                'color' => $yellow,
            ],
            [
                'key' => 'very_late',
                'label' => 'Extemp.',
                'color' => $orange,
            ],
            [
                'key' => 'absent',
                'label' => 'Ausente',
                'color' => $red,
            ],
        ];

        $left = 45;
        $right = 20;
        $top = 25;
        $bottom = 65;

        $chartWidth = $width - $left - $right;
        $chartHeight = $height - $top - $bottom;

        $maximum = 1;

        foreach ($rows as $row) {
            foreach ($series as $item) {
                $maximum = max(
                    $maximum,
                    (int) ($row[$item['key']] ?? 0)
                );
            }
        }

        $maximum = max(
            5,
            (int) ceil($maximum / 5) * 5
        );

        for ($step = 0; $step <= 5; $step++) {
            $ratio = $step / 5;
            $y = (int) round(
                $top
                + ($chartHeight * (1 - $ratio))
            );

            imageline(
                $image,
                $left,
                $y,
                $width - $right,
                $y,
                $grid
            );

            imagestring(
                $image,
                2,
                5,
                $y - 6,
                (string) round($maximum * $ratio),
                $text
            );
        }

        $groupWidth = $chartWidth
            / max(count($rows), 1);

        $barGap = 2;
        $barWidth = max(
            3,
            (int) floor(
                (($groupWidth * .75) - 6)
                / count($series)
            )
        );

        foreach ($rows as $index => $row) {
            $groupStart = $left
                + ($index * $groupWidth);

            $barsWidth = (
                $barWidth * count($series)
            ) + ($barGap * (count($series) - 1));

            $startX = (int) round(
                $groupStart
                + (($groupWidth - $barsWidth) / 2)
            );

            foreach ($series as $seriesIndex => $item) {
                $value = (int) (
                    $row[$item['key']] ?? 0
                );

                $barHeight = (int) round(
                    ($value / $maximum)
                    * $chartHeight
                );

                $x1 = $startX
                    + (
                        $seriesIndex
                        * ($barWidth + $barGap)
                    );

                $y1 = $top
                    + $chartHeight
                    - $barHeight;

                imagefilledrectangle(
                    $image,
                    $x1,
                    $y1,
                    $x1 + $barWidth,
                    $top + $chartHeight,
                    $item['color']
                );
            }

            $label = $this->asciiLabel(
                (string) $row['label'],
                14
            );

            $labelWidth = imagefontwidth(1)
                * strlen($label);

            imagestring(
                $image,
                1,
                (int) (
                    $groupStart
                    + ($groupWidth / 2)
                    - ($labelWidth / 2)
                ),
                $top + $chartHeight + 10,
                $label,
                $text
            );
        }

        $legendX = $left;
        $legendY = $height - 20;

        foreach ($series as $item) {
            imagefilledrectangle(
                $image,
                $legendX,
                $legendY,
                $legendX + 10,
                $legendY + 10,
                $item['color']
            );

            imagestring(
                $image,
                2,
                $legendX + 15,
                $legendY,
                $item['label'],
                $text
            );

            $legendX += 130;
        }

        return $this->pngDataUri($image);
    }

    private function pngDataUri(\GdImage $image): string
    {
        ob_start();

        imagepng($image, null, 6);

        $contents = ob_get_clean();

        imagedestroy($image);

        if (! is_string($contents)) {
            throw new RuntimeException(
                'No se pudo generar la imagen.'
            );
        }

        return 'data:image/png;base64,'
            .base64_encode($contents);
    }

    private function asciiLabel(
        string $value,
        int $length
    ): string {
        $ascii = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        );

        $ascii = is_string($ascii)
            ? $ascii
            : $value;

        return mb_strlen($ascii) > $length
            ? mb_substr($ascii, 0, $length - 1).'.'
            : $ascii;
    }
}