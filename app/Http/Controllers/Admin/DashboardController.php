<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Attendance\AttendancePeriodService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AttendancePeriodService $attendancePeriod
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless($user && $user->school_id, 403);

        $schoolId = (int) $user->school_id;

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->first();

        $timezone = $school?->timezone ?: config('app.timezone');

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'campus_id' => [
                'nullable',
                'integer',
                Rule::exists('campuses', 'id')->where('school_id', $schoolId),
            ],
            'level_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_levels', 'id')->where('school_id', $schoolId),
            ],
            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('school_groups', 'id')->where('school_id', $schoolId),
            ],
        ]);

        $today = Carbon::now($timezone)->startOfDay();

        $filters = [
            'date' => $validated['date'] ?? $today->toDateString(),
            'campus_id' => ! empty($validated['campus_id'])
                ? (int) $validated['campus_id']
                : null,
            'level_id' => ! empty($validated['level_id'])
                ? (int) $validated['level_id']
                : null,
            'group_id' => ! empty($validated['group_id'])
                ? (int) $validated['group_id']
                : null,
        ];

        $selectedDate = Carbon::parse(
            $filters['date'],
            $timezone
        )->startOfDay();

        try {
            $activeWindow = $this->attendancePeriod->attendanceWindow($schoolId);
            $activeCycle = $activeWindow['cycle'] ?? null;

            $cycleStartsOn = $activeCycle?->starts_on
                ? Carbon::parse($activeCycle->starts_on, $timezone)->startOfDay()
                : null;

            $cycleEndsOn = $activeCycle?->ends_on
                ? Carbon::parse($activeCycle->ends_on, $timezone)->endOfDay()
                : null;

            $now = Carbon::now($timezone);
            $cycleHasStarted = $cycleStartsOn ? $now->gte($cycleStartsOn) : false;
            $cycleHasEnded = $cycleEndsOn ? $now->gt($cycleEndsOn) : false;
            $cycleIsInForce = (bool) (
                $activeCycle
                && $cycleHasStarted
                && ! $cycleHasEnded
            );

            $dateInsideCycle = (bool) (
                $activeWindow
                && $selectedDate->betweenIncluded(
                    Carbon::parse($activeWindow['start'], $timezone)->startOfDay(),
                    Carbon::parse($activeWindow['end'], $timezone)->endOfDay()
                )
            );

            $dateIsFuture = $selectedDate->isAfter($today);

            $calendarDay = $this->calendarDay(
                schoolId: $schoolId,
                cycleId: $activeCycle?->id,
                date: $selectedDate->toDateString()
            );

            $isNoClassDay = $this->isNoClassDay($calendarDay);

            $attendanceRows = $activeCycle
                ? $this->attendanceRows(
                    schoolId: $schoolId,
                    cycleId: (int) $activeCycle->id,
                    date: $selectedDate,
                    filters: $filters,
                    timezone: $timezone,
                    dateInsideCycle: $dateInsideCycle,
                    dateIsFuture: $dateIsFuture,
                    isNoClassDay: $isNoClassDay
                )
                : collect();

            $attendanceSummary = $this->attendanceSummary($attendanceRows);

            $activeStudents = DB::table('students')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->count();

            $enrolledStudents = $activeCycle
                ? $this->enrolledStudentsCount(
                    schoolId: $schoolId,
                    cycleId: (int) $activeCycle->id,
                    date: $selectedDate->toDateString()
                )
                : 0;

            $activeGroups = $activeCycle
                ? DB::table('school_groups')
                    ->where('school_id', $schoolId)
                    ->where('academic_cycle_id', $activeCycle->id)
                    ->where('status', 'active')
                    ->count()
                : 0;

            $activeGuardians = DB::table('guardians')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->count();

            $activeDevices = DB::table('access_devices')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->count();

            $onlineDevices = Schema::hasColumn('access_devices', 'last_seen_at')
                ? DB::table('access_devices')
                    ->where('school_id', $schoolId)
                    ->where('status', 'active')
                    ->where('last_seen_at', '>=', Carbon::now($timezone)->subMinutes(10))
                    ->count()
                : 0;

            $selectedLogs = $this->filteredLogBase(
                schoolId: $schoolId,
                filters: $filters
            )->whereDate('al.scanned_at', $selectedDate->toDateString());

            $stats = [
                'students' => $activeStudents,
                'enrolled_students' => $enrolledStudents,
                'unenrolled_students' => max(0, $activeStudents - $enrolledStudents),
                'guardians' => $activeGuardians,
                'groups' => $activeGroups,
                'devices' => $activeDevices,
                'online_devices' => $onlineDevices,
                'logs_selected' => (clone $selectedLogs)->count(),
                'denied_selected' => (clone $selectedLogs)
                    ->where('al.decision', 'denied')
                    ->count(),
                'duplicates_selected' => (clone $selectedLogs)
                    ->where(function ($query): void {
                        $query
                            ->where('al.event_status', 'duplicate')
                            ->orWhere('al.decision', 'duplicate');
                    })
                    ->count(),
                ...$attendanceSummary,
            ];

            $recentLogs = $this->recentLogs(
                schoolId: $schoolId,
                date: $selectedDate->toDateString(),
                filters: $filters
            );

            $weeklyTrend = $activeCycle
                ? $this->weeklyTrend(
                    schoolId: $schoolId,
                    cycleId: (int) $activeCycle->id,
                    selectedDate: $selectedDate,
                    activeWindow: $activeWindow,
                    filters: $filters,
                    timezone: $timezone
                )
                : [];

            $groupActivity = $this->groupActivity($attendanceRows);

            $campuses = $this->campuses($schoolId, $activeCycle?->id);
            $levels = $this->levels($schoolId, $activeCycle?->id);
            $groups = $this->groups(
                schoolId: $schoolId,
                cycleId: $activeCycle?->id,
                campusId: $filters['campus_id'],
                levelId: $filters['level_id']
            );

            $dbError = null;
        } catch (Throwable $exception) {
            report($exception);

            $activeCycle = null;
            $cycleHasStarted = false;
            $cycleHasEnded = false;
            $cycleIsInForce = false;
            $dateInsideCycle = false;
            $dateIsFuture = false;
            $calendarDay = null;
            $isNoClassDay = false;

            $stats = [
                'students' => 0,
                'enrolled_students' => 0,
                'unenrolled_students' => 0,
                'guardians' => 0,
                'groups' => 0,
                'devices' => 0,
                'online_devices' => 0,
                'logs_selected' => 0,
                'denied_selected' => 0,
                'duplicates_selected' => 0,
                ...$this->emptyAttendanceSummary(),
            ];

            $recentLogs = collect();
            $weeklyTrend = [];
            $groupActivity = collect();
            $campuses = collect();
            $levels = collect();
            $groups = collect();

            $dbError = app()->environment('local')
                ? $exception->getMessage()
                : 'No fue posible cargar la información del dashboard.';
        }

        return view('admin.dashboard', [
            'school' => $school,
            'filters' => $filters,
            'stats' => $stats,
            'recentLogs' => $recentLogs,
            'weeklyTrend' => $weeklyTrend,
            'groupActivity' => $groupActivity,
            'campuses' => $campuses,
            'levels' => $levels,
            'groups' => $groups,
            'activeCycle' => $activeCycle,
            'cycleHasStarted' => $cycleHasStarted,
            'cycleHasEnded' => $cycleHasEnded,
            'cycleIsInForce' => $cycleIsInForce,
            'dateInsideCycle' => $dateInsideCycle,
            'dateIsFuture' => $dateIsFuture,
            'calendarDay' => $calendarDay,
            'isNoClassDay' => $isNoClassDay,
            'dbError' => $dbError,
        ]);
    }

    private function attendanceRows(
        int $schoolId,
        int $cycleId,
        Carbon $date,
        array $filters,
        string $timezone,
        bool $dateInsideCycle,
        bool $dateIsFuture,
        bool $isNoClassDay
    ): Collection {
        $dateString = $date->toDateString();
        $weekday = $date->dayOfWeekIso;
        $dateIsToday = $date->isSameDay(Carbon::now($timezone)->startOfDay());

        return DB::table('student_enrollments as se')
            ->join('students as s', function ($join) use ($schoolId): void {
                $join->on('s.id', '=', 'se.student_id')
                    ->where('s.school_id', '=', $schoolId);
            })
            ->join('school_groups as sg', function ($join) use ($schoolId, $cycleId): void {
                $join->on('sg.id', '=', 'se.school_group_id')
                    ->where('sg.school_id', '=', $schoolId)
                    ->where('sg.academic_cycle_id', '=', $cycleId);
            })
            ->join('campuses as c', function ($join) use ($schoolId): void {
                $join->on('c.id', '=', 'se.campus_id')
                    ->where('c.school_id', '=', $schoolId);
            })
            ->leftJoin('academic_levels as al', 'al.id', '=', 'sg.academic_level_id')
            ->leftJoin('group_access_schedules as gas', function ($join) use ($schoolId, $weekday): void {
                $join->on('gas.group_id', '=', 'sg.id')
                    ->where('gas.school_id', '=', $schoolId)
                    ->where('gas.weekday', '=', $weekday)
                    ->where('gas.status', '=', 'active');
            })
            ->leftJoin('daily_attendance as da', function ($join) use ($schoolId, $dateString): void {
                $join->on('da.student_id', '=', 'se.student_id')
                    ->where('da.school_id', '=', $schoolId)
                    ->where('da.date', '=', $dateString);
            })
            ->leftJoin('access_logs as entry_log', 'entry_log.id', '=', 'da.entry_log_id')
            ->leftJoin('access_logs as exit_log', 'exit_log.id', '=', 'da.exit_log_id')
            ->where('se.school_id', $schoolId)
            ->where('se.academic_cycle_id', $cycleId)
            ->where('se.status', 'active')
            ->where('s.status', 'active')
            ->whereDate('se.enrolled_on', '<=', $dateString)
            ->where(function ($query) use ($dateString): void {
                $query
                    ->whereNull('se.withdrawn_on')
                    ->orWhereDate('se.withdrawn_on', '>=', $dateString);
            })
            ->when(
                $filters['campus_id'],
                fn ($query, $campusId) => $query->where('se.campus_id', $campusId)
            )
            ->when(
                $filters['level_id'],
                fn ($query, $levelId) => $query->where('sg.academic_level_id', $levelId)
            )
            ->when(
                $filters['group_id'],
                fn ($query, $groupId) => $query->where('se.school_group_id', $groupId)
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
                'entry_log.event_status as entry_event_status',
                'exit_log.event_status as exit_event_status',
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
                $row->has_exit = ! empty($row->exit_at);
                $row->is_early_exit = $row->exit_event_status === 'early_exit'
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
                    $row->final_status = $dateIsToday
                        && $row->late_until
                        && Carbon::now($timezone)->format('H:i:s') <= $row->late_until
                            ? 'pending'
                            : 'absent';
                    return $row;
                }

                $row->final_status = $this->normalizeAttendanceStatus($row);
                return $row;
            });
    }

    private function normalizeAttendanceStatus(object $row): string
    {
        if (in_array($row->entry_event_status, ['on_time', 'late', 'very_late'], true)) {
            return $row->entry_event_status;
        }

        if (in_array($row->raw_attendance_status, ['on_time', 'late', 'very_late'], true)) {
            if ($row->raw_attendance_status === 'late' && (int) $row->minutes_late > 20) {
                return 'very_late';
            }

            return $row->raw_attendance_status;
        }

        return $this->statusFromMinutes((int) $row->minutes_late);
    }

    private function attendanceSummary(Collection $rows): array
    {
        $present = $rows
            ->whereIn('final_status', ['on_time', 'late', 'very_late'])
            ->count();

        $onTime = $rows->where('final_status', 'on_time')->count();

        $eligible = $rows
            ->filter(fn ($row): bool => ! in_array(
                $row->final_status,
                ['no_class', 'outside_cycle', 'pending'],
                true
            ))
            ->count();

        return [
            'considered_students' => $rows->count(),
            'present' => $present,
            'on_time' => $onTime,
            'late' => $rows->where('final_status', 'late')->count(),
            'very_late' => $rows->where('final_status', 'very_late')->count(),
            'absent' => $rows->where('final_status', 'absent')->count(),
            'pending' => $rows->where('final_status', 'pending')->count(),
            'no_class' => $rows->where('final_status', 'no_class')->count(),
            'outside_cycle' => $rows->where('final_status', 'outside_cycle')->count(),
            'exited' => $rows->filter(fn ($row): bool => (bool) $row->has_exit)->count(),
            'early_exit' => $rows->filter(fn ($row): bool => (bool) $row->is_early_exit)->count(),
            'eligible' => $eligible,
            'attendance_rate' => $eligible > 0
                ? round(($present / $eligible) * 100, 1)
                : 0.0,
            'punctuality_rate' => $present > 0
                ? round(($onTime / $present) * 100, 1)
                : 0.0,
        ];
    }

    private function emptyAttendanceSummary(): array
    {
        return [
            'considered_students' => 0,
            'present' => 0,
            'on_time' => 0,
            'late' => 0,
            'very_late' => 0,
            'absent' => 0,
            'pending' => 0,
            'no_class' => 0,
            'outside_cycle' => 0,
            'exited' => 0,
            'early_exit' => 0,
            'eligible' => 0,
            'attendance_rate' => 0.0,
            'punctuality_rate' => 0.0,
        ];
    }

    private function weeklyTrend(
        int $schoolId,
        int $cycleId,
        Carbon $selectedDate,
        array $activeWindow,
        array $filters,
        string $timezone
    ): array {
        $result = [];
        $cursor = $selectedDate->copy()->subDays(6)->startOfDay();
        $lastDay = $selectedDate->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $calendarDay = $this->calendarDay(
                schoolId: $schoolId,
                cycleId: $cycleId,
                date: $cursor->toDateString()
            );

            $insideCycle = $cursor->betweenIncluded(
                Carbon::parse($activeWindow['start'], $timezone)->startOfDay(),
                Carbon::parse($activeWindow['end'], $timezone)->endOfDay()
            );

            $rows = $this->attendanceRows(
                schoolId: $schoolId,
                cycleId: $cycleId,
                date: $cursor->copy(),
                filters: $filters,
                timezone: $timezone,
                dateInsideCycle: $insideCycle,
                dateIsFuture: $cursor->isAfter(Carbon::now($timezone)->startOfDay()),
                isNoClassDay: $this->isNoClassDay($calendarDay)
            );

            $summary = $this->attendanceSummary($rows);

            $result[] = [
                'date' => $cursor->toDateString(),
                'label' => ucfirst($cursor->locale('es')->isoFormat('ddd')),
                'day' => $cursor->format('d/m'),
                'present' => $summary['present'],
                'late' => $summary['late'] + $summary['very_late'],
                'absent' => $summary['absent'],
                'early_exit' => $summary['early_exit'],
            ];

            $cursor->addDay();
        }

        return $result;
    }

    private function groupActivity(Collection $rows): Collection
    {
        return $rows
            ->groupBy('group_id')
            ->map(function (Collection $groupRows): object {
                $summary = $this->attendanceSummary($groupRows);
                $first = $groupRows->first();

                return (object) [
                    'group_id' => $first->group_id,
                    'group_name' => $first->group_name,
                    'level_name' => $first->level_name,
                    'campus_name' => $first->campus_name,
                    'total' => $summary['considered_students'],
                    'present' => $summary['present'],
                    'on_time' => $summary['on_time'],
                    'late' => $summary['late'],
                    'very_late' => $summary['very_late'],
                    'absent' => $summary['absent'],
                    'pending' => $summary['pending'],
                    'exited' => $summary['exited'],
                    'early_exit' => $summary['early_exit'],
                    'attendance_rate' => $summary['attendance_rate'],
                    'punctuality_rate' => $summary['punctuality_rate'],
                ];
            })
            ->sortBy(fn ($row): string => sprintf(
                '%s|%s|%s',
                $row->campus_name,
                $row->level_name,
                $row->group_name
            ))
            ->values();
    }

    private function recentLogs(
        int $schoolId,
        string $date,
        array $filters
    ): Collection {
        $deviceColumn = $this->deviceLogColumn();

        return $this->filteredLogBase($schoolId, $filters)
            ->leftJoin('academic_cycles as cycle', 'cycle.id', '=', 'al.academic_cycle_id')
            ->leftJoin('areas as a', function ($join) use ($schoolId): void {
                $join->on('a.id', '=', 'al.area_id')
                    ->where('a.school_id', '=', $schoolId);
            })
            ->leftJoin('access_devices as d', 'd.id', '=', 'al.'.$deviceColumn)
            ->leftJoin('guardians as guardian', 'guardian.id', '=', 'al.guardian_id')
            ->leftJoin('users as operator', 'operator.id', '=', 'al.user_id')
            ->whereDate('al.scanned_at', $date)
            ->select([
                'al.id',
                'al.event_type',
                'al.event_status',
                'al.decision',
                'al.reason',
                'al.source',
                'al.performed_for',
                'al.scanned_at',
                's.student_code',
                's.first_name',
                's.last_name',
                's.photo_url',
                DB::raw('CONCAT(COALESCE(s.first_name, ""), " ", COALESCE(s.last_name, "")) as student_name'),
                DB::raw('COALESCE(historical_group.name, current_group.name) as group_name'),
                'cycle.name as cycle_name',
                'a.name as area_name',
                'd.name as device_name',
                DB::raw('CONCAT(COALESCE(guardian.first_name, ""), " ", COALESCE(guardian.last_name, "")) as guardian_name'),
                'operator.name as operator_name',
            ])
            ->orderByDesc('al.scanned_at')
            ->limit(12)
            ->get();
    }

    private function filteredLogBase(int $schoolId, array $filters): Builder
    {
        return DB::table('access_logs as al')
            ->leftJoin('students as s', function ($join) use ($schoolId): void {
                $join->on('s.id', '=', 'al.student_id')
                    ->where('s.school_id', '=', $schoolId);
            })
            ->leftJoin('school_groups as historical_group', 'historical_group.id', '=', 'al.school_group_id')
            ->leftJoin('school_groups as current_group', 'current_group.id', '=', 's.current_group_id')
            ->where('al.school_id', $schoolId)
            ->when($filters['group_id'], function ($query, $groupId): void {
                $query->where(function ($inner) use ($groupId): void {
                    $inner
                        ->where('historical_group.id', $groupId)
                        ->orWhere('current_group.id', $groupId);
                });
            })
            ->when($filters['campus_id'], function ($query, $campusId): void {
                $query->where(function ($inner) use ($campusId): void {
                    $inner
                        ->where('historical_group.campus_id', $campusId)
                        ->orWhere('current_group.campus_id', $campusId);
                });
            })
            ->when($filters['level_id'], function ($query, $levelId): void {
                $query->where(function ($inner) use ($levelId): void {
                    $inner
                        ->where('historical_group.academic_level_id', $levelId)
                        ->orWhere('current_group.academic_level_id', $levelId);
                });
            });
    }

    private function enrolledStudentsCount(
        int $schoolId,
        int $cycleId,
        string $date
    ): int {
        return DB::table('student_enrollments as se')
            ->join('students as s', 's.id', '=', 'se.student_id')
            ->where('se.school_id', $schoolId)
            ->where('se.academic_cycle_id', $cycleId)
            ->where('se.status', 'active')
            ->where('s.status', 'active')
            ->whereDate('se.enrolled_on', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('se.withdrawn_on')
                    ->orWhereDate('se.withdrawn_on', '>=', $date);
            })
            ->distinct()
            ->count('se.student_id');
    }

    private function calendarDay(
        int $schoolId,
        ?int $cycleId,
        string $date
    ): ?object {
        if (! $cycleId) {
            return null;
        }

        return DB::table('school_calendar_days')
            ->where('school_id', $schoolId)
            ->where('academic_cycle_id', $cycleId)
            ->where('date', $date)
            ->where('status', 'active')
            ->first();
    }

    private function isNoClassDay(?object $calendarDay): bool
    {
        return (bool) (
            $calendarDay
            && in_array(
                $calendarDay->type,
                ['holiday', 'vacation', 'suspension', 'technical_council', 'no_class'],
                true
            )
        );
    }

    private function statusFromMinutes(int $minutesLate): string
    {
        if ($minutesLate <= 0) {
            return 'on_time';
        }

        return $minutesLate <= 20 ? 'late' : 'very_late';
    }

    private function campuses(int $schoolId, ?int $cycleId): Collection
    {
        if (! $cycleId) {
            return collect();
        }

        return DB::table('campuses as c')
            ->join('school_groups as sg', 'sg.campus_id', '=', 'c.id')
            ->where('c.school_id', $schoolId)
            ->where('c.status', 'active')
            ->where('sg.school_id', $schoolId)
            ->where('sg.academic_cycle_id', $cycleId)
            ->where('sg.status', 'active')
            ->select(['c.id', 'c.name'])
            ->distinct()
            ->orderBy('c.name')
            ->get();
    }

    private function levels(int $schoolId, ?int $cycleId): Collection
    {
        if (! $cycleId) {
            return collect();
        }

        return DB::table('academic_levels as al')
            ->join('school_groups as sg', 'sg.academic_level_id', '=', 'al.id')
            ->where('al.school_id', $schoolId)
            ->where('al.status', 'active')
            ->where('sg.school_id', $schoolId)
            ->where('sg.academic_cycle_id', $cycleId)
            ->where('sg.status', 'active')
            ->select(['al.id', 'al.name', 'al.sort_order'])
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
    ): Collection {
        if (! $cycleId) {
            return collect();
        }

        return DB::table('school_groups as sg')
            ->join('campuses as c', 'c.id', '=', 'sg.campus_id')
            ->leftJoin('academic_levels as al', 'al.id', '=', 'sg.academic_level_id')
            ->where('sg.school_id', $schoolId)
            ->where('sg.academic_cycle_id', $cycleId)
            ->where('sg.status', 'active')
            ->when($campusId, fn ($query, $value) => $query->where('sg.campus_id', $value))
            ->when($levelId, fn ($query, $value) => $query->where('sg.academic_level_id', $value))
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

    private function deviceLogColumn(): string
    {
        return Schema::hasColumn('access_logs', 'device_id')
            ? 'device_id'
            : 'access_device_id';
    }
}
