<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GroupScheduleController extends Controller
{
    public function index(
        Request $request
    ): View {
        $schoolId = $this->schoolId(
            $request
        );

        $activeCycle = $this->activeCycle(
            $schoolId
        );

        $groups = collect();

        if ($activeCycle) {
            $groups = DB::table(
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
                    $activeCycle->id
                )
                ->select([
                    'sg.id',
                    'sg.name',
                    'sg.grade_label',
                    'sg.status',
                    'al.name as level_name',
                    'c.name as campus_name',

                    DB::raw(
                        "(
                            SELECT COUNT(*)
                            FROM student_enrollments se
                            WHERE se.school_id = sg.school_id
                            AND se.academic_cycle_id = sg.academic_cycle_id
                            AND se.school_group_id = sg.id
                            AND se.status = 'active'
                        ) as students_count"
                    ),

                    DB::raw(
                        "(
                            SELECT COUNT(*)
                            FROM group_access_schedules gas
                            WHERE gas.group_id = sg.id
                            AND gas.status = 'active'
                        ) as active_schedules_count"
                    ),
                ])
                ->orderBy(
                    'al.sort_order'
                )
                ->orderBy('sg.name')
                ->get();
        }

        return view('admin.groups.index', [
            'groups' => $groups,
            'activeCycle' => $activeCycle,
        ]);
    }

    public function edit(
        Request $request,
        int $group
    ): View {
        $schoolId = $this->schoolId(
            $request
        );

        $activeCycle = $this->activeCycle(
            $schoolId
        );

        abort_unless(
            $activeCycle,
            404,
            'No existe un ciclo activo.'
        );

        $groupRow = DB::table(
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
            )
            ->where(
                'sg.academic_cycle_id',
                $activeCycle->id
            )
            ->where(
                'sg.id',
                $group
            )
            ->select([
                'sg.*',
                'al.name as level_name',
            ])
            ->firstOrFail();

        $schedules = DB::table(
            'group_access_schedules'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'group_id',
                $group
            )
            ->get()
            ->keyBy('weekday');

        return view(
            'admin.groups.schedules',
            [
                'groupRow' => $groupRow,
                'schedules' => $schedules,
                'weekdays' => $this->weekdays(),
                'activeCycle' => $activeCycle,
            ]
        );
    }

    public function update(
        Request $request,
        int $group
    ): RedirectResponse {
        $schoolId = $this->schoolId(
            $request
        );

        $activeCycle = $this->activeCycle(
            $schoolId
        );

        if (! $activeCycle) {
            return back()->withErrors([
                'schedule' =>
                    'No existe un ciclo activo.',
            ]);
        }

        $groupRow = DB::table(
            'school_groups'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'academic_cycle_id',
                $activeCycle->id
            )
            ->where(
                'id',
                $group
            )
            ->firstOrFail();



        $data = $request->validate([
                    'auto_transition_minutes' => [
    'required',
    'integer',
    'min:0',
    'max:120',
],

            'active_weekdays' => [
                'nullable',
                'array',
            ],

            'active_weekdays.*' => [
                'integer',
                'between:1,7',
            ],

            'entry_time' => [
                'required',
                'array',
            ],

            'entry_time.*' => [
                'required',
                'date_format:H:i',
            ],

            'grace_until' => [
                'required',
                'array',
            ],

            'grace_until.*' => [
                'required',
                'date_format:H:i',
            ],

            'late_until' => [
                'required',
                'array',
            ],

            'late_until.*' => [
                'required',
                'date_format:H:i',
            ],

            'exit_time' => [
                'required',
                'array',
            ],

            'exit_time.*' => [
                'required',
                'date_format:H:i',
            ],
        ]);

        $activeWeekdays = collect(
            $data['active_weekdays'] ?? []
        )
            ->map(
                fn ($day): int =>
                    (int) $day
            )
            ->values()
            ->all();

        foreach (
            range(1, 7) as $weekday
        ) {
            $entryTime =
                $data['entry_time'][$weekday]
                ?? null;

            $graceUntil =
                $data['grace_until'][$weekday]
                ?? null;

            $lateUntil =
                $data['late_until'][$weekday]
                ?? null;

            $exitTime =
                $data['exit_time'][$weekday]
                ?? null;

            if (
                ! $entryTime
                || ! $graceUntil
                || ! $lateUntil
                || ! $exitTime
            ) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'schedule' =>
                            'Todos los horarios deben estar completos.',
                    ]);
            }

            if (! (
                $entryTime <= $graceUntil
                && $graceUntil <= $lateUntil
                && $lateUntil <= $exitTime
            )) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'schedule' =>
                            'El orden debe ser: entrada ≤ tolerancia ≤ límite de retardo ≤ salida.',
                    ]);
            }
        }

       DB::transaction(
    function () use (
        $schoolId,
        $groupRow,
        $data,
        $activeWeekdays
    ): void {
        DB::table('school_groups')
            ->where('school_id', $schoolId)
            ->where('id', $groupRow->id)
            ->update([
                'auto_transition_minutes' =>
                    (int) $data['auto_transition_minutes'],

                'updated_at' => now(),
            ]);

        foreach (
            range(1, 7) as $weekday
        ) {
            DB::table(
                'group_access_schedules'
            )->updateOrInsert(
                [
                    'school_id' =>
                        $schoolId,

                    'group_id' =>
                        $groupRow->id,

                    'weekday' =>
                        $weekday,
                ],
                [
                    'entry_time' =>
                        $data[
                            'entry_time'
                        ][$weekday],

                    'grace_until' =>
                        $data[
                            'grace_until'
                        ][$weekday],

                    'late_until' =>
                        $data[
                            'late_until'
                        ][$weekday],

                    'exit_time' =>
                        $data[
                            'exit_time'
                        ][$weekday],

                    'status' =>
                        in_array(
                            $weekday,
                            $activeWeekdays,
                            true
                        )
                            ? 'active'
                            : 'inactive',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]
            );
        }
    },
    3
);

        return redirect()
            ->route(
                'admin.groups.schedules.edit',
                $groupRow->id
            )
            ->with(
                'success',
                'Horarios actualizados correctamente.'
            );
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

    private function weekdays(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }
}