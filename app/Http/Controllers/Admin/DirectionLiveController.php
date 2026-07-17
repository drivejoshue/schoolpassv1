<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Attendance\AttendancePeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DirectionLiveController extends Controller
{
    public function __construct(
        private readonly AttendancePeriodService $attendancePeriod
    ) {
    }

    public function index(Request $request): View
{
    $schoolId = $this->schoolId($request);
    $filters = $this->validatedFilters($request, $schoolId);

    $school = DB::table('schools')
        ->where('id', $schoolId)
        ->firstOrFail();

    $activeCycle = $this->activeCycle($schoolId);

    return view('admin.direction-live.direction-live', [
        'school' => $school,
        'filters' => $filters,

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
    ]);
}

    public function data(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);
        $filters = $this->filters($request, $schoolId);
        $school = DB::table('schools')->where('id', $schoolId)->firstOrFail();
        $timezone = $school->timezone ?: config('app.timezone');
        $now = Carbon::now($timezone);
        $date = $now->toDateString();

        $window = $this->attendancePeriod->attendanceWindow($schoolId);
        $cycle = $window['cycle'] ?? null;
        $insideCycle = $window !== null
            && $now->copy()->startOfDay()->betweenIncluded(
                Carbon::parse($window['start'], $timezone)->startOfDay(),
                Carbon::parse($window['end'], $timezone)->endOfDay()
            );

        $calendarDay = $cycle
            ? DB::table('school_calendar_days')
                ->where('school_id', $schoolId)
                ->where('academic_cycle_id', $cycle->id)
                ->where('date', $date)
                ->where('status', 'active')
                ->first()
            : null;

        $noClassDay = $calendarDay && in_array(
            $calendarDay->type,
            ['holiday', 'vacation', 'suspension', 'technical_council', 'no_class'],
            true
        );

        $rows = $cycle
            ? $this->attendanceRows(
                $schoolId,
                (int) $cycle->id,
                $now,
                $filters,
                $insideCycle,
                (bool) $noClassDay
            )
            : collect();

        $summary = $this->summary($rows);
        $groups = $this->groupSummary($rows);
        $activity = $this->recentActivity($schoolId, $date, $filters);

        $activeDevices = DB::table('access_devices')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->count();

        $onlineDevices = DB::table('access_devices')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('last_seen_at', '>=', $now->copy()->subMinutes(10))
            ->count();

        return response()->json([
            'school' => [
                'name' => $school->name,
                'timezone' => $timezone,
            ],
            'clock' => [
                'date' => $now->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'),
                'time' => $now->format('H:i:s'),
            ],
            'cycle' => [
                'exists' => $cycle !== null,
                'name' => $cycle?->name,
                'inside_cycle' => $insideCycle,
                'no_class_day' => (bool) $noClassDay,
                'calendar_title' => $calendarDay?->title,
            ],
            'summary' => [
                ...$summary,
                'active_devices' => $activeDevices,
                'online_devices' => $onlineDevices,
            ],
            'groups' => $groups->map(fn (object $group) => [
                'id' => (int) $group->group_id,
                'name' => $group->group_name,
                'level' => $group->level_name,
                'campus' => $group->campus_name,
                'total' => (int) $group->total,
                'present' => (int) $group->present,
                'on_time' => (int) $group->on_time,
                'late' => (int) $group->late,
                'very_late' => (int) $group->very_late,
                'absent' => (int) $group->absent,
                'pending' => (int) $group->pending,
                'exited' => (int) $group->exited,
                'early_exit' => (int) $group->early_exit,
                'attendance_rate' => (float) $group->attendance_rate,
            ])->values(),
            'activity' => $activity->map(fn (object $log) => [
                'id' => (int) $log->id,
                'student_name' => trim((string) $log->student_name) ?: 'Sin alumno',
                'student_code' => $log->student_code ?: '—',
                'photo_url' => $log->photo_url,
                'group_name' => $log->group_name ?: 'Sin grupo',
                'event_type' => $log->event_type,
                'event_status' => $log->event_status,
                'decision' => $log->decision,
                'source' => $log->source,
                'guardian_name' => trim((string) $log->guardian_name) ?: null,
                'device_name' => $log->device_name ?: 'Sin dispositivo',
                'operator_name' => $log->operator_name ?: 'Sin operador',
                'reason' => $log->reason,
                'time' => Carbon::parse($log->scanned_at, $timezone)->format('H:i:s'),
            ])->values(),
            'meta' => [
                'generated_at' => $now->toIso8601String(),
                'refresh_seconds' => 15,
            ],
        ])->header(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, max-age=0'
        );
    }

    private function attendanceRows(
        int $schoolId,
        int $cycleId,
        Carbon $now,
        array $filters,
        bool $insideCycle,
        bool $noClassDay
    ): Collection {
        $date = $now->toDateString();
        $weekday = $now->dayOfWeekIso;

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
            ->join('campuses as c', 'c.id', '=', 'se.campus_id')
            ->leftJoin('academic_levels as al', 'al.id', '=', 'sg.academic_level_id')
            ->leftJoin('group_access_schedules as gas', function ($join) use ($schoolId, $weekday): void {
                $join->on('gas.group_id', '=', 'sg.id')
                    ->where('gas.school_id', '=', $schoolId)
                    ->where('gas.weekday', '=', $weekday)
                    ->where('gas.status', '=', 'active');
            })
            ->leftJoin('daily_attendance as da', function ($join) use ($schoolId, $date): void {
                $join->on('da.student_id', '=', 'se.student_id')
                    ->where('da.school_id', '=', $schoolId)
                    ->where('da.date', '=', $date);
            })
            ->leftJoin('access_logs as entry_log', 'entry_log.id', '=', 'da.entry_log_id')
            ->leftJoin('access_logs as exit_log', 'exit_log.id', '=', 'da.exit_log_id')
            ->where('se.school_id', $schoolId)
            ->where('se.academic_cycle_id', $cycleId)
            ->where('se.status', 'active')
            ->where('s.status', 'active')
            ->whereDate('se.enrolled_on', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('se.withdrawn_on')
                    ->orWhereDate('se.withdrawn_on', '>=', $date);
            })
            ->when($filters['campus_id'], fn ($query, $id) => $query->where('se.campus_id', $id))
            ->when($filters['level_id'], fn ($query, $id) => $query->where('sg.academic_level_id', $id))
            ->when($filters['group_id'], fn ($query, $id) => $query->where('se.school_group_id', $id))
            ->select([
                'c.name as campus_name',
                'al.name as level_name',
                'sg.id as group_id',
                'sg.name as group_name',
                'gas.id as schedule_id',
                'gas.late_until',
                'da.id as attendance_id',
                'da.attendance_status as raw_status',
                'da.entry_at',
                'da.exit_at',
                'da.minutes_late',
                'entry_log.event_status as entry_status',
                'exit_log.event_status as exit_status',
            ])
            ->get()
            ->map(function ($row) use ($now, $insideCycle, $noClassDay): object {
                $row->has_exit = ! empty($row->exit_at);
                $row->is_early_exit = $row->exit_status === 'early_exit'
                    || $row->raw_status === 'early_exit';

                if (! $insideCycle) {
                    $row->final_status = 'outside_cycle';
                    return $row;
                }

                if ($noClassDay || ! $row->schedule_id) {
                    $row->final_status = $row->attendance_id
                        ? $this->normalizedStatus($row)
                        : 'no_class';
                    return $row;
                }

                if (! $row->attendance_id) {
                    $row->final_status = $row->late_until
                        && $now->format('H:i:s') <= $row->late_until
                        ? 'pending'
                        : 'absent';
                    return $row;
                }

                $row->final_status = $this->normalizedStatus($row);
                return $row;
            });
    }

    private function normalizedStatus(object $row): string
    {
        if (in_array($row->entry_status, ['on_time', 'late', 'very_late'], true)) {
            return $row->entry_status;
        }

        if (in_array($row->raw_status, ['on_time', 'late', 'very_late'], true)) {
            return $row->raw_status === 'late' && (int) $row->minutes_late > 20
                ? 'very_late'
                : $row->raw_status;
        }

        return $this->statusFromMinutes((int) $row->minutes_late);
    }

    private function summary(Collection $rows): array
    {
        $present = $rows->whereIn('final_status', ['on_time', 'late', 'very_late'])->count();
        $eligible = $rows->filter(fn ($row) => ! in_array(
            $row->final_status,
            ['pending', 'no_class', 'outside_cycle'],
            true
        ))->count();

        return [
            'total' => $rows->count(),
            'present' => $present,
            'on_time' => $rows->where('final_status', 'on_time')->count(),
            'late' => $rows->where('final_status', 'late')->count(),
            'very_late' => $rows->where('final_status', 'very_late')->count(),
            'absent' => $rows->where('final_status', 'absent')->count(),
            'pending' => $rows->where('final_status', 'pending')->count(),
            'no_class' => $rows->where('final_status', 'no_class')->count(),
            'exited' => $rows->filter(fn ($row) => (bool) $row->has_exit)->count(),
            'early_exit' => $rows->filter(fn ($row) => (bool) $row->is_early_exit)->count(),
            'attendance_rate' => $eligible > 0
                ? round(($present / $eligible) * 100, 1)
                : 0.0,
        ];
    }

    private function groupSummary(Collection $rows): Collection
    {
        return $rows->groupBy('group_id')
            ->map(function (Collection $groupRows): object {
                $summary = $this->summary($groupRows);
                $first = $groupRows->first();

                return (object) [
                    'group_id' => $first->group_id,
                    'group_name' => $first->group_name,
                    'level_name' => $first->level_name,
                    'campus_name' => $first->campus_name,
                    ...$summary,
                ];
            })
            ->sortBy([
                ['campus_name', 'asc'],
                ['level_name', 'asc'],
                ['group_name', 'asc'],
            ])
            ->values();
    }

    private function recentActivity(int $schoolId, string $date, array $filters): Collection
    {
        return DB::table('access_logs as log')
            ->leftJoin('students as s', 's.id', '=', 'log.student_id')
            ->leftJoin('school_groups as historical_group', 'historical_group.id', '=', 'log.school_group_id')
            ->leftJoin('school_groups as current_group', 'current_group.id', '=', 's.current_group_id')
            ->leftJoin('guardians as guardian', 'guardian.id', '=', 'log.guardian_id')
            ->leftJoin('access_devices as device', 'device.id', '=', 'log.access_device_id')
            ->leftJoin('users as operator', 'operator.id', '=', 'log.user_id')
            ->where('log.school_id', $schoolId)
            ->whereDate('log.scanned_at', $date)
            ->when($filters['group_id'], function ($query, $id): void {
                $query->where(fn ($inner) => $inner
                    ->where('historical_group.id', $id)
                    ->orWhere('current_group.id', $id));
            })
            ->when($filters['campus_id'], function ($query, $id): void {
                $query->where(fn ($inner) => $inner
                    ->where('historical_group.campus_id', $id)
                    ->orWhere('current_group.campus_id', $id));
            })
            ->when($filters['level_id'], function ($query, $id): void {
                $query->where(fn ($inner) => $inner
                    ->where('historical_group.academic_level_id', $id)
                    ->orWhere('current_group.academic_level_id', $id));
            })
            ->select([
                'log.id',
                'log.event_type',
                'log.event_status',
                'log.decision',
                'log.source',
                'log.reason',
                'log.scanned_at',
                's.student_code',
                's.photo_url',
                DB::raw('CONCAT(COALESCE(s.first_name, ""), " ", COALESCE(s.last_name, "")) as student_name'),
                DB::raw('COALESCE(historical_group.name, current_group.name) as group_name'),
                DB::raw('CONCAT(COALESCE(guardian.first_name, ""), " ", COALESCE(guardian.last_name, "")) as guardian_name'),
                'device.name as device_name',
                'operator.name as operator_name',
            ])
            ->orderByDesc('log.scanned_at')
            ->limit(18)
            ->get();
    }

    private function filters(Request $request, int $schoolId): array
    {
        $data = $request->validate([
            'campus_id' => ['nullable', 'integer', Rule::exists('campuses', 'id')->where('school_id', $schoolId)],
            'level_id' => ['nullable', 'integer', Rule::exists('academic_levels', 'id')->where('school_id', $schoolId)],
            'group_id' => ['nullable', 'integer', Rule::exists('school_groups', 'id')->where('school_id', $schoolId)],
        ]);

        return [
            'campus_id' => ! empty($data['campus_id']) ? (int) $data['campus_id'] : null,
            'level_id' => ! empty($data['level_id']) ? (int) $data['level_id'] : null,
            'group_id' => ! empty($data['group_id']) ? (int) $data['group_id'] : null,
        ];
    }

    private function activeCycle(int $schoolId): ?object
    {
        return DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->first();
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
            ->when($campusId, fn ($query, $id) => $query->where('sg.campus_id', $id))
            ->when($levelId, fn ($query, $id) => $query->where('sg.academic_level_id', $id))
            ->select([
                'sg.id',
                'sg.name',
                'c.name as campus_name',
                'al.name as level_name',
                'al.sort_order',
            ])
            ->orderBy('c.name')
            ->orderBy('al.sort_order')
            ->orderBy('sg.name')
            ->get();
    }

    private function statusFromMinutes(int $minutesLate): string
    {
        if ($minutesLate <= 0) {
            return 'on_time';
        }

        return $minutesLate <= 20
            ? 'late'
            : 'very_late';
    }


private function validatedFilters(
    Request $request,
    int $schoolId
): array {
    $validated = $request->validate([
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
    ]);

    return [
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
}


    private function schoolId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user && $user->school_id, 403);

        return (int) $user->school_id;
    }


}
