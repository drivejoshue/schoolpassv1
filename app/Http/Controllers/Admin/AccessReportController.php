<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AccessReportController extends Controller
{
    public function index(
        Request $request
    ): View {
        $schoolId = (int) $request
            ->user()
            ->school_id;

        $filters = [
            'from' => $request->query(
                'from',
                now()->toDateString()
            ),

            'to' => $request->query(
                'to',
                now()->toDateString()
            ),

            'student_id' =>
                $request->query('student_id'),

            'group_id' =>
                $request->query('group_id'),

            'cycle_id' =>
                $request->query('cycle_id'),

            'area_id' =>
                $request->query('area_id'),

            'device_id' =>
                $request->query('device_id'),

            'event_type' =>
                $request->query('event_type'),

            'event_status' =>
                $request->query('event_status'),
        ];

        $logs = $this->baseQuery(
            schoolId: $schoolId,
            filters: $filters
        )
            ->select([
                'access_logs.id',
                'access_logs.event_type',
                'access_logs.event_status',
                'access_logs.decision',
                'access_logs.action',
                'access_logs.reason',
                'access_logs.source',
                'access_logs.reader_type',
                'access_logs.minutes_late',
                'access_logs.scanned_at',

                'access_logs.academic_cycle_id',
                'access_logs.student_enrollment_id',
                'access_logs.school_group_id',

                'students.id as student_id',
                'students.student_code',
                'students.first_name',
                'students.last_name',
                'students.photo_url',

                DB::raw(
                    'COALESCE(
                        historical_group.name,
                        current_group.name
                    ) as group_name'
                ),

                DB::raw(
                    'COALESCE(
                        historical_level.name,
                        current_level.name
                    ) as level_name'
                ),

                'academic_cycles.name as cycle_name',

                'areas.name as area_name',
                'areas.type as area_type',

                'access_devices.name as device_name',
                'access_devices.device_type',
            ])
            ->orderByDesc(
                'access_logs.scanned_at'
            )
            ->paginate(30)
            ->withQueryString();

        return view(
            'admin.reports.access',
            [
                'logs' =>
                    $logs,

                'summary' =>
                    $this->summary(
                        $schoolId,
                        $filters
                    ),

                'filters' =>
                    $filters,

                'students' =>
                    $this->students(
                        $schoolId
                    ),

                'groups' =>
                    $this->groups(
                        $schoolId,
                        $filters['cycle_id']
                            ? (int) $filters[
                                'cycle_id'
                            ]
                            : null
                    ),

                'cycles' =>
                    $this->cycles(
                        $schoolId
                    ),

                'areas' =>
                    $this->areas(
                        $schoolId
                    ),

                'devices' =>
                    $this->devices(
                        $schoolId
                    ),
            ]
        );
    }

    private function baseQuery(
        int $schoolId,
        array $filters
    ): Builder {
        $from = Carbon::parse(
            $filters['from']
            ?: now()->toDateString()
        )->startOfDay();

        $to = Carbon::parse(
            $filters['to']
            ?: now()->toDateString()
        )->endOfDay();

        $deviceColumn =
            $this->deviceLogColumn();

        return DB::table('access_logs')
            ->leftJoin(
                'students',
                'students.id',
                '=',
                'access_logs.student_id'
            )
            ->leftJoin(
                'academic_cycles',
                'academic_cycles.id',
                '=',
                'access_logs.academic_cycle_id'
            )
            ->leftJoin(
                'school_groups as historical_group',
                'historical_group.id',
                '=',
                'access_logs.school_group_id'
            )
            ->leftJoin(
                'academic_levels as historical_level',
                'historical_level.id',
                '=',
                'historical_group.academic_level_id'
            )
            ->leftJoin(
                'school_groups as current_group',
                'current_group.id',
                '=',
                'students.current_group_id'
            )
            ->leftJoin(
                'academic_levels as current_level',
                'current_level.id',
                '=',
                'current_group.academic_level_id'
            )
            ->leftJoin(
                'areas',
                'areas.id',
                '=',
                'access_logs.area_id'
            )
            ->leftJoin(
                'access_devices',
                'access_devices.id',
                '=',
                'access_logs.'.$deviceColumn
            )
            ->where(
                'access_logs.school_id',
                $schoolId
            )
            ->whereBetween(
                'access_logs.scanned_at',
                [
                    $from,
                    $to,
                ]
            )
            ->when(
                $filters['student_id'],
                function (
                    $query,
                    $studentId
                ): void {
                    $query->where(
                        'access_logs.student_id',
                        $studentId
                    );
                }
            )
            ->when(
                $filters['cycle_id'],
                function (
                    $query,
                    $cycleId
                ): void {
                    $query->where(
                        'access_logs.academic_cycle_id',
                        $cycleId
                    );
                }
            )
            ->when(
                $filters['group_id'],
                function (
                    $query,
                    $groupId
                ): void {
                    /*
                     * Registros nuevos:
                     * usa el grupo histórico guardado en el log.
                     *
                     * Registros antiguos:
                     * conserva fallback al grupo actual.
                     */
                    $query->where(
                        function ($inner) use (
                            $groupId
                        ): void {
                            $inner
                                ->where(
                                    'access_logs.school_group_id',
                                    $groupId
                                )
                                ->orWhere(
                                    function ($legacy) use (
                                        $groupId
                                    ): void {
                                        $legacy
                                            ->whereNull(
                                                'access_logs.school_group_id'
                                            )
                                            ->where(
                                                'students.current_group_id',
                                                $groupId
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->when(
                $filters['area_id'],
                function (
                    $query,
                    $areaId
                ): void {
                    $query->where(
                        'access_logs.area_id',
                        $areaId
                    );
                }
            )
            ->when(
                $filters['device_id'],
                function (
                    $query,
                    $deviceId
                ) use (
                    $deviceColumn
                ): void {
                    $query->where(
                        'access_logs.'
                        .$deviceColumn,
                        $deviceId
                    );
                }
            )
            ->when(
                $filters['event_type'],
                function (
                    $query,
                    $eventType
                ): void {
                    $query->where(
                        'access_logs.event_type',
                        $eventType
                    );
                }
            )
            ->when(
                $filters['event_status'],
                function (
                    $query,
                    $eventStatus
                ): void {
                    $query->where(
                        'access_logs.event_status',
                        $eventStatus
                    );
                }
            );
    }

    private function summary(
        int $schoolId,
        array $filters
    ): array {
        $base = $this->baseQuery(
            $schoolId,
            $filters
        );

        return [
            'total' =>
                (clone $base)->count(),

            'entries' =>
                (clone $base)
                    ->where(
                        'access_logs.event_type',
                        'entry'
                    )
                    ->count(),

            'exits' =>
                (clone $base)
                    ->where(
                        'access_logs.event_type',
                        'exit'
                    )
                    ->count(),

            'access' =>
                (clone $base)
                    ->where(
                        'access_logs.event_type',
                        'access'
                    )
                    ->count(),

            'allowed' =>
                (clone $base)
                    ->where(
                        'access_logs.decision',
                        'allowed'
                    )
                    ->count(),

            'denied' =>
                (clone $base)
                    ->where(
                        'access_logs.decision',
                        'denied'
                    )
                    ->count(),

            'duplicates' =>
                (clone $base)
                    ->where(
                        'access_logs.event_status',
                        'duplicate'
                    )
                    ->count(),

            'late' =>
                (clone $base)
                    ->whereIn(
                        'access_logs.event_status',
                        [
                            'late',
                            'very_late',
                        ]
                    )
                    ->count(),

            'on_time' =>
                (clone $base)
                    ->where(
                        'access_logs.event_status',
                        'on_time'
                    )
                    ->count(),
        ];
    }

    private function students(
        int $schoolId
    ) {
        $activeCycle = DB::table(
            'academic_cycles'
        )
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->first();

        return DB::table('students as s')
            ->leftJoin(
                'student_enrollments as se',
                function ($join) use (
                    $activeCycle
                ): void {
                    $join->on(
                        'se.student_id',
                        '=',
                        's.id'
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
                'school_groups as sg',
                'sg.id',
                '=',
                'se.school_group_id'
            )
            ->where(
                's.school_id',
                $schoolId
            )
            ->select([
                's.id',
                's.student_code',
                's.first_name',
                's.last_name',
                'sg.name as group_name',
            ])
            ->orderBy('s.last_name')
            ->orderBy('s.first_name')
            ->get();
    }

    private function groups(
        int $schoolId,
        ?int $cycleId
    ) {
        $query = DB::table(
            'school_groups as sg'
        )
            ->leftJoin(
                'academic_levels as al',
                'al.id',
                '=',
                'sg.academic_level_id'
            )
            ->where(
                'sg.school_id',
                $schoolId
            );

        if ($cycleId) {
            $query->where(
                'sg.academic_cycle_id',
                $cycleId
            );
        } else {
            $activeCycleId = DB::table(
                'academic_cycles'
            )
                ->where(
                    'school_id',
                    $schoolId
                )
                ->where(
                    'status',
                    'active'
                )
                ->where(
                    'is_active',
                    true
                )
                ->value('id');

            if ($activeCycleId) {
                $query->where(
                    'sg.academic_cycle_id',
                    $activeCycleId
                );
            }
        }

        return $query
            ->select([
                'sg.id',
                'sg.name',
                'al.name as level_name',
            ])
            ->orderBy('al.sort_order')
            ->orderBy('sg.name')
            ->get();
    }

    private function cycles(
        int $schoolId
    ) {
        return DB::table(
            'academic_cycles'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->orderByDesc('starts_on')
            ->get([
                'id',
                'name',
                'status',
                'is_active',
                'starts_on',
                'ends_on',
            ]);
    }

    private function areas(
        int $schoolId
    ) {
        return DB::table('areas')
            ->where(
                'school_id',
                $schoolId
            )
            ->orderBy('name')
            ->get();
    }

    private function devices(
        int $schoolId
    ) {
        return DB::table(
            'access_devices'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->orderBy('name')
            ->get();
    }

    private function deviceLogColumn(): string
    {
        if (
            Schema::hasColumn(
                'access_logs',
                'device_id'
            )
        ) {
            return 'device_id';
        }

        return 'access_device_id';
    }
}