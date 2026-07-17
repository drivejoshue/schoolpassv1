<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class AcademicCycleController extends Controller
{
    public function index(): View
    {
        $schoolId = $this->schoolId();

        /*
         * Repara ciclos inconsistentes como:
         *
         * is_active = 1
         * status = draft
         *
         * La reparación se limita a la escuela autenticada.
         */
        $this->normalizeCycleStates($schoolId);

        $cycles = DB::table('academic_cycles as ac')
            ->where('ac.school_id', $schoolId)
            ->select([
                'ac.id',
                'ac.school_id',
                'ac.name',
                'ac.starts_on',
                'ac.ends_on',
                'ac.is_active',
                'ac.status',
                'ac.closed_at',
                'ac.notes',
                'ac.created_at',
                'ac.updated_at',

                DB::raw('(
                    SELECT COUNT(*)
                    FROM school_groups sg
                    WHERE sg.academic_cycle_id = ac.id
                    AND sg.school_id = ac.school_id
                ) as groups_count'),

                DB::raw('(
                    SELECT COUNT(*)
                    FROM school_groups sg
                    WHERE sg.academic_cycle_id = ac.id
                    AND sg.school_id = ac.school_id
                    AND sg.status = "active"
                ) as active_groups_count'),

                DB::raw('(
                    SELECT COUNT(*)
                    FROM school_calendar_days scd
                    WHERE scd.academic_cycle_id = ac.id
                    AND scd.school_id = ac.school_id
                ) as calendar_days_count'),
            ])
            ->orderByDesc('ac.is_active')
            ->orderByRaw("
                CASE ac.status
                    WHEN 'active' THEN 1
                    WHEN 'draft' THEN 2
                    WHEN 'closed' THEN 3
                    ELSE 4
                END
            ")
            ->orderByDesc('ac.starts_on')
            ->orderByDesc('ac.id')
            ->get();

        $activeCycle = $cycles->first(
            fn ($cycle): bool =>
                (bool) $cycle->is_active
                && $cycle->status === 'active'
        );

        return view('admin.cycles.index', [
            'cycles' => $cycles,
            'activeCycle' => $activeCycle,
        ]);
    }

    public function create(): View
    {
        return view('admin.cycles.create', [
            'cycleRow' => null,
        ]);
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $schoolId = $this->schoolId();

        $data = $this->validateCycle($request);

        $duplicate = DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(name) = ?', [
                mb_strtolower($data['name']),
            ])
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'Ya existe un ciclo con ese nombre.',
                ]);
        }

        $overlap = $this->hasDateOverlap(
            schoolId: $schoolId,
            startsOn: $data['starts_on'],
            endsOn: $data['ends_on']
        );

        if ($overlap) {
            return back()
                ->withInput()
                ->withErrors([
                    'starts_on' =>
                        'Las fechas se traslapan con otro ciclo escolar.',
                ]);
        }

        $cycleId = DB::table('academic_cycles')
            ->insertGetId([
                'school_id' => $schoolId,
                'name' => trim($data['name']),
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],

                /*
                 * Todo ciclo nuevo inicia como borrador.
                 * Activar es una acción independiente.
                 */
                'is_active' => false,
                'status' => 'draft',
                'closed_at' => null,

                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route(
                'admin.cycles.edit',
                $cycleId
            )
            ->with(
                'success',
                'Ciclo creado como borrador. Configura sus grupos y después actívalo.'
            );
    }

    public function edit(int $cycle): View
{
    $schoolId = $this->schoolId();

    $this->normalizeCycleStates($schoolId);

    $cycleRow = DB::table('academic_cycles')
        ->where('school_id', $schoolId)
        ->where('id', $cycle)
        ->firstOrFail();

    $groups = DB::table('school_groups as sg')
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
        ->where('sg.school_id', $schoolId)
        ->where(
            'sg.academic_cycle_id',
            $cycleRow->id
        )
        ->orderBy('al.sort_order')
        ->orderBy('sg.name')
        ->get([
            'sg.id',
            'sg.name',
            'sg.grade_label',
            'sg.status',
            'sg.campus_id',
            'sg.academic_level_id',
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
                    WHERE gas.school_id = sg.school_id
                    AND gas.group_id = sg.id
                    AND gas.status = 'active'
                ) as active_schedules_count"
            ),
        ]);

    $groupsCount = $groups->count();

    $activeGroupsCount = $groups
        ->where('status', 'active')
        ->count();

    $calendarDaysCount = DB::table(
        'school_calendar_days'
    )
        ->where('school_id', $schoolId)
        ->where(
            'academic_cycle_id',
            $cycleRow->id
        )
        ->count();

    $calendarClassDaysCount = DB::table(
        'school_calendar_days'
    )
        ->where('school_id', $schoolId)
        ->where(
            'academic_cycle_id',
            $cycleRow->id
        )
        ->where('status', 'active')
        ->whereIn('type', [
            'class',
            'school_day',
            'special_class',
            'makeup_class',
        ])
        ->count();

    $calendarNoClassDaysCount = DB::table(
        'school_calendar_days'
    )
        ->where('school_id', $schoolId)
        ->where(
            'academic_cycle_id',
            $cycleRow->id
        )
        ->where('status', 'active')
        ->whereIn('type', [
            'holiday',
            'vacation',
            'suspension',
            'technical_council',
            'no_class',
        ])
        ->count();

    $enrollmentBase = DB::table(
        'student_enrollments'
    )
        ->where('school_id', $schoolId)
        ->where(
            'academic_cycle_id',
            $cycleRow->id
        );

    $enrollmentSummary = [
        'total' => (clone $enrollmentBase)
            ->count(),

        'active' => (clone $enrollmentBase)
            ->where('status', 'active')
            ->count(),

        'completed' => (clone $enrollmentBase)
            ->where('status', 'completed')
            ->count(),

        'withdrawn' => (clone $enrollmentBase)
            ->where('status', 'withdrawn')
            ->count(),

        'graduated' => (clone $enrollmentBase)
            ->where('status', 'graduated')
            ->count(),

        'without_group' => (clone $enrollmentBase)
            ->whereNull('school_group_id')
            ->count(),

        'movements' => Schema::hasTable(
            'student_group_movements'
        )
            ? DB::table(
                'student_group_movements'
            )
                ->where('school_id', $schoolId)
                ->where(
                    'academic_cycle_id',
                    $cycleRow->id
                )
                ->count()
            : 0,

        'scheduled_groups' => DB::table(
            'group_access_schedules as gas'
        )
            ->join(
                'school_groups as sg',
                'sg.id',
                '=',
                'gas.group_id'
            )
            ->where(
                'sg.school_id',
                $schoolId
            )
            ->where(
                'sg.academic_cycle_id',
                $cycleRow->id
            )
            ->where(
                'gas.status',
                'active'
            )
            ->distinct()
            ->count('gas.group_id'),
    ];

    $previousCycle = DB::table(
        'academic_cycles'
    )
        ->where('school_id', $schoolId)
        ->where(
            'id',
            '!=',
            $cycleRow->id
        )
        ->whereDate(
            'starts_on',
            '<',
            $cycleRow->starts_on
        )
        ->orderByDesc('starts_on')
        ->orderByDesc('id')
        ->first();

    $pendingFromPrevious = 0;
    $previousEligibleCount = 0;

    if ($previousCycle) {
        $previousEligibleCount = DB::table(
            'student_enrollments'
        )
            ->where('school_id', $schoolId)
            ->where(
                'academic_cycle_id',
                $previousCycle->id
            )
            ->whereIn('status', [
                'active',
                'completed',
            ])
            ->count();

        $pendingFromPrevious = DB::table(
            'student_enrollments as source'
        )
            ->leftJoin(
                'student_enrollments as target',
                function ($join) use (
                    $cycleRow
                ): void {
                    $join->on(
                        'target.student_id',
                        '=',
                        'source.student_id'
                    )
                        ->where(
                            'target.academic_cycle_id',
                            '=',
                            $cycleRow->id
                        );
                }
            )
            ->where(
                'source.school_id',
                $schoolId
            )
            ->where(
                'source.academic_cycle_id',
                $previousCycle->id
            )
            ->whereIn(
                'source.status',
                [
                    'active',
                    'completed',
                ]
            )
            ->whereNull('target.id')
            ->count();
    }

    $preparationSummary = [
        'previous_cycle' =>
            $previousCycle,

        'previous_eligible' =>
            $previousEligibleCount,

        'pending_from_previous' =>
            $pendingFromPrevious,

        'groups_without_schedule' =>
            $groups
                ->where('status', 'active')
                ->filter(
                    fn ($group): bool =>
                        (int) $group
                            ->active_schedules_count < 1
                )
                ->count(),

        'empty_active_groups' =>
            $groups
                ->where('status', 'active')
                ->filter(
                    fn ($group): bool =>
                        (int) $group
                            ->students_count < 1
                )
                ->count(),
    ];

    return view('admin.cycles.edit', [
        'cycleRow' =>
            $cycleRow,

        'groups' =>
            $groups,

        'groupsCount' =>
            $groupsCount,

        'activeGroupsCount' =>
            $activeGroupsCount,

        'calendarDaysCount' =>
            $calendarDaysCount,

        'calendarClassDaysCount' =>
            $calendarClassDaysCount,

        'calendarNoClassDaysCount' =>
            $calendarNoClassDaysCount,

        'enrollmentSummary' =>
            $enrollmentSummary,

        'preparationSummary' =>
            $preparationSummary,
    ]);
}

    public function update(
        Request $request,
        int $cycle
    ): RedirectResponse {
        $schoolId = $this->schoolId();

        $cycleRow = DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('id', $cycle)
            ->firstOrFail();

        if ($cycleRow->status === 'closed') {
            return back()->withErrors([
                'cycle' =>
                    'Este ciclo está cerrado y forma parte del histórico. No puede modificarse.',
            ]);
        }

        $data = $this->validateCycle($request);

        $duplicate = DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('id', '!=', $cycleRow->id)
            ->whereRaw('LOWER(name) = ?', [
                mb_strtolower($data['name']),
            ])
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'Ya existe otro ciclo con ese nombre.',
                ]);
        }

        $overlap = $this->hasDateOverlap(
            schoolId: $schoolId,
            startsOn: $data['starts_on'],
            endsOn: $data['ends_on'],
            exceptCycleId: (int) $cycleRow->id
        );

        if ($overlap) {
            return back()
                ->withInput()
                ->withErrors([
                    'starts_on' =>
                        'Las fechas se traslapan con otro ciclo escolar.',
                ]);
        }

        DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('id', $cycleRow->id)
            ->update([
                'name' => trim($data['name']),
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'notes' => $data['notes'] ?? null,

                /*
                 * No se modifica status ni is_active.
                 *
                 * Actualizar datos nunca debe activar,
                 * cerrar ni regresar el ciclo a borrador.
                 */
                'updated_at' => now(),
            ]);

        return redirect()
            ->route(
                'admin.cycles.edit',
                $cycleRow->id
            )
            ->with(
                'success',
                'Datos del ciclo actualizados correctamente.'
            );
    }

    public function activate(
    int $cycle
): RedirectResponse {
    $schoolId = $this->schoolId();

    try {
        DB::transaction(function () use (
            $schoolId,
            $cycle
        ): void {
            $cycleRow = DB::table(
                'academic_cycles'
            )
                ->where(
                    'school_id',
                    $schoolId
                )
                ->where('id', $cycle)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $cycleRow->status === 'closed'
            ) {
                throw new \RuntimeException(
                    'No puedes activar un ciclo cerrado.'
                );
            }

            $otherActiveCycle = DB::table(
                'academic_cycles'
            )
                ->where(
                    'school_id',
                    $schoolId
                )
                ->where(
                    'id',
                    '!=',
                    $cycleRow->id
                )
                ->where(function ($query): void {
                    $query
                        ->where(
                            'is_active',
                            true
                        )
                        ->orWhere(
                            'status',
                            'active'
                        );
                })
                ->lockForUpdate()
                ->first();

            if ($otherActiveCycle) {
                throw new \RuntimeException(
                    'Ya existe un ciclo activo: '
                    .$otherActiveCycle->name
                    .'. Debes cerrarlo antes de activar el nuevo.'
                );
            }

            $activeGroupsCount = DB::table(
                'school_groups'
            )
                ->where(
                    'school_id',
                    $schoolId
                )
                ->where(
                    'academic_cycle_id',
                    $cycleRow->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->count();

            if ($activeGroupsCount < 1) {
                throw new \RuntimeException(
                    'El ciclo necesita al menos un grupo activo.'
                );
            }

            DB::table('academic_cycles')
                ->where(
                    'school_id',
                    $schoolId
                )
                ->where(
                    'id',
                    $cycleRow->id
                )
                ->update([
                    'is_active' => true,
                    'status' => 'active',
                    'closed_at' => null,
                    'updated_at' => now(),
                ]);
        }, 3);
    } catch (Throwable $exception) {
        return back()->withErrors([
            'cycle' =>
                $exception->getMessage(),
        ]);
    }

    return redirect()
        ->route(
            'admin.cycles.edit',
            $cycle
        )
        ->with(
            'success',
            'Ciclo activado correctamente.'
        );
}

    public function close(
        int $cycle
    ): RedirectResponse {
        $schoolId = $this->schoolId();

        try {
            DB::transaction(function () use (
                $schoolId,
                $cycle
            ): void {
                $cycleRow = DB::table(
                    'academic_cycles'
                )
                    ->where('school_id', $schoolId)
                    ->where('id', $cycle)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($cycleRow->status === 'closed') {
                    throw new \RuntimeException(
                        'Este ciclo ya está cerrado.'
                    );
                }

                if (
                    $cycleRow->status !== 'active'
                    || ! (bool) $cycleRow->is_active
                ) {
                    throw new \RuntimeException(
                        'Solo se puede cerrar el ciclo que está activo.'
                    );
                }

                DB::table('academic_cycles')
                    ->where('school_id', $schoolId)
                    ->where('id', $cycleRow->id)
                    ->update([
                        'is_active' => false,
                        'status' => 'closed',
                        'closed_at' => now(),
                        'updated_at' => now(),
                    ]);
            }, 3);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'cycle' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.cycles.index')
            ->with(
                'success',
                'Ciclo escolar cerrado y enviado al histórico.'
            );
    }

    private function validateCycle(
        Request $request
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'starts_on' => [
                'required',
                'date',
            ],

            'ends_on' => [
                'required',
                'date',
                'after_or_equal:starts_on',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);
    }

    private function hasDateOverlap(
        int $schoolId,
        string $startsOn,
        string $endsOn,
        ?int $exceptCycleId = null
    ): bool {
        return DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->when(
                $exceptCycleId,
                fn ($query, $id) =>
                    $query->where('id', '!=', $id)
            )
            ->where('status', '!=', 'closed')
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->exists();
    }

    private function normalizeCycleStates(
        int $schoolId
    ): void {
        DB::transaction(function () use (
            $schoolId
        ): void {
            $cycles = DB::table('academic_cycles')
                ->where('school_id', $schoolId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($cycles->isEmpty()) {
                return;
            }

            /*
             * Ciclos cerrados nunca pueden permanecer activos.
             */
            DB::table('academic_cycles')
                ->where('school_id', $schoolId)
                ->where('status', 'closed')
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            /*
             * Buscamos un candidato activo aunque la combinación
             * actual esté inconsistente.
             */
            $activeCandidate = $cycles->first(
                fn ($cycle): bool =>
                    (bool) $cycle->is_active
                    && $cycle->status !== 'closed'
            );

            if (! $activeCandidate) {
                $activeCandidate = $cycles->first(
                    fn ($cycle): bool =>
                        $cycle->status === 'active'
                        && $cycle->status !== 'closed'
                );
            }

            if (! $activeCandidate) {
                return;
            }

            /*
             * Solo un ciclo puede quedar activo.
             */
            DB::table('academic_cycles')
                ->where('school_id', $schoolId)
                ->where('id', '!=', $activeCandidate->id)
                ->where('status', '!=', 'closed')
                ->where(function ($query): void {
                    $query
                        ->where('is_active', true)
                        ->orWhere('status', 'active');
                })
                ->update([
                    'is_active' => false,
                    'status' => 'draft',
                    'closed_at' => null,
                    'updated_at' => now(),
                ]);

            /*
             * Reparamos el candidato:
             *
             * is_active = 1
             * status = active
             */
            DB::table('academic_cycles')
                ->where('school_id', $schoolId)
                ->where('id', $activeCandidate->id)
                ->update([
                    'is_active' => true,
                    'status' => 'active',
                    'closed_at' => null,
                    'updated_at' => now(),
                ]);
        }, 3);
    }

    private function schoolId(): int
    {
        $user = auth()->user();

        abort_unless(
            $user && $user->school_id,
            403
        );

        return (int) $user->school_id;
    }
}