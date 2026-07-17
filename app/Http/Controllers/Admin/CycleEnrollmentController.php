<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CycleEnrollmentController extends Controller
{
    public function index(
        Request $request,
        int $cycle
    ): View {
        $schoolId = $this->schoolId($request);

        $targetCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId: $cycle
        );

        $filters = $request->validate([
            'source_cycle_id' => [
                'nullable',
                'integer',
            ],

            'source_group_id' => [
                'nullable',
                'integer',
            ],

            'target_group_id' => [
                'nullable',
                'integer',
            ],

            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'view' => [
                'nullable',
                Rule::in([
                    'pending',
                    'enrolled',
                    'all',
                ]),
            ],
        ]);

        $cycles = DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get([
                'id',
                'name',
                'starts_on',
                'ends_on',
                'status',
                'is_active',
            ]);

        $sourceCycleId = ! empty(
            $filters['source_cycle_id']
        )
            ? (int) $filters['source_cycle_id']
            : $this->defaultSourceCycleId(
                cycles: $cycles,
                targetCycle: $targetCycle
            );

        $sourceCycle = $sourceCycleId
            ? $cycles->firstWhere(
                'id',
                $sourceCycleId
            )
            : null;

        $sourceGroups = $sourceCycle
            ? $this->groupsForCycle(
                schoolId: $schoolId,
                cycleId: (int) $sourceCycle->id
            )
            : collect();

        $targetGroups = $this->groupsForCycle(
            schoolId: $schoolId,
            cycleId: (int) $targetCycle->id
        );

        $sourceGroupId = ! empty(
            $filters['source_group_id']
        )
            ? (int) $filters['source_group_id']
            : null;

        $targetGroupId = ! empty(
            $filters['target_group_id']
        )
            ? (int) $filters['target_group_id']
            : null;

        $search = trim(
            (string) (
                $filters['search']
                ?? ''
            )
        );

        $viewMode = $filters['view']
            ?? 'pending';

        $rows = collect();

        if ($sourceCycle) {
            $rows = $this->candidateRows(
                schoolId: $schoolId,
                targetCycleId: (int) $targetCycle->id,
                sourceCycleId: (int) $sourceCycle->id,
                sourceGroupId: $sourceGroupId,
                search: $search,
                viewMode: $viewMode
            );
        }

        $groupSummary = $this->targetGroupSummary(
            schoolId: $schoolId,
            targetCycleId: (int) $targetCycle->id
        );

        $summary = [
            'total_students' => DB::table(
                'students'
            )
                ->where(
                    'school_id',
                    $schoolId
                )
                ->count(),

            'enrolled' => DB::table(
                'student_enrollments'
            )
                ->where(
                    'school_id',
                    $schoolId
                )
                ->where(
                    'academic_cycle_id',
                    $targetCycle->id
                )
                ->count(),

            'active_enrolled' => DB::table(
                'student_enrollments'
            )
                ->where(
                    'school_id',
                    $schoolId
                )
                ->where(
                    'academic_cycle_id',
                    $targetCycle->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->count(),

            'pending_from_source' => $sourceCycle
                ? DB::table(
                    'student_enrollments as source'
                )
                    ->leftJoin(
                        'student_enrollments as target',
                        function ($join) use (
                            $targetCycle
                        ): void {
                            $join->on(
                                'target.student_id',
                                '=',
                                'source.student_id'
                            )
                                ->where(
                                    'target.academic_cycle_id',
                                    '=',
                                    $targetCycle->id
                                );
                        }
                    )
                    ->where(
                        'source.school_id',
                        $schoolId
                    )
                    ->where(
                        'source.academic_cycle_id',
                        $sourceCycle->id
                    )
                    ->whereIn(
                        'source.status',
                        [
                            'active',
                            'completed',
                        ]
                    )
                    ->whereNull('target.id')
                    ->count()
                : 0,

            'without_group' => DB::table(
                'student_enrollments'
            )
                ->where(
                    'school_id',
                    $schoolId
                )
                ->where(
                    'academic_cycle_id',
                    $targetCycle->id
                )
                ->whereNull(
                    'school_group_id'
                )
                ->count(),
        ];

        return view(
            'admin.cycle-enrollments.index',
            [
                'targetCycle' =>
                    $targetCycle,

                'cycles' =>
                    $cycles,

                'sourceCycle' =>
                    $sourceCycle,

                'sourceCycleId' =>
                    $sourceCycleId,

                'sourceGroups' =>
                    $sourceGroups,

                'targetGroups' =>
                    $targetGroups,

                'sourceGroupId' =>
                    $sourceGroupId,

                'targetGroupId' =>
                    $targetGroupId,

                'search' =>
                    $search,

                'viewMode' =>
                    $viewMode,

                'rows' =>
                    $rows,

                'groupSummary' =>
                    $groupSummary,

                'summary' =>
                    $summary,
            ]
        );
    }

    public function assign(
        Request $request,
        int $cycle
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $targetCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId: $cycle
        );

        if ($targetCycle->status === 'closed') {
            return back()->withErrors([
                'cycle' =>
                    'No puedes modificar la matrícula de un ciclo cerrado.',
            ]);
        }

        $data = $request->validate([
            'student_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'student_ids.*' => [
                'required',
                'integer',
            ],

            'target_group_id' => [
                'required',
                'integer',
            ],

            'source_cycle_id' => [
                'nullable',
                'integer',
            ],

            'effective_on' => [
                'required',
                'date',
            ],

            'enrollment_type' => [
                'required',

                Rule::in([
                    'new',
                    'reenrollment',
                    'promotion',
                    'repeat',
                    'transfer',
                ]),
            ],

            'reason' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $targetGroup = $this->group(
            schoolId: $schoolId,
            cycleId: (int) $targetCycle->id,
            groupId: (int) $data[
                'target_group_id'
            ]
        );

        $studentIds = collect(
            $data['student_ids']
        )
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->unique()
            ->values();

        $students = DB::table('students')
            ->where(
                'school_id',
                $schoolId
            )
            ->whereIn(
                'id',
                $studentIds
            )
            ->get()
            ->keyBy('id');

        if (
            $students->count()
            !== $studentIds->count()
        ) {
            return back()->withErrors([
                'students' =>
                    'Uno o más alumnos no pertenecen a esta escuela.',
            ]);
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;

        try {
            DB::transaction(
                function () use (
                    $request,
                    $schoolId,
                    $targetCycle,
                    $targetGroup,
                    $studentIds,
                    $students,
                    $data,
                    &$created,
                    &$updated,
                    &$unchanged
                ): void {
                    foreach (
                        $studentIds as $studentId
                    ) {
                        $student = $students->get(
                            $studentId
                        );

                        $enrollment = DB::table(
                            'student_enrollments'
                        )
                            ->where(
                                'school_id',
                                $schoolId
                            )
                            ->where(
                                'student_id',
                                $studentId
                            )
                            ->where(
                                'academic_cycle_id',
                                $targetCycle->id
                            )
                            ->lockForUpdate()
                            ->first();

                        $fromGroupId = $enrollment
                            ? $enrollment->school_group_id
                            : null;

                        if (
                            $enrollment
                            && (int) $enrollment
                                ->school_group_id
                                === (int) $targetGroup->id
                            && $enrollment->status
                                === 'active'
                        ) {
                            $unchanged++;

                            continue;
                        }

                        if (! $enrollment) {
                            $previousEnrollmentId =
                                $this
                                    ->previousEnrollmentId(
                                        schoolId:
                                            $schoolId,

                                        studentId:
                                            $studentId,

                                        targetCycleId:
                                            (int) $targetCycle
                                                ->id
                                    );

                            $enrollmentId =
                                DB::table(
                                    'student_enrollments'
                                )->insertGetId([
                                    'school_id' =>
                                        $schoolId,

                                    'student_id' =>
                                        $studentId,

                                    'academic_cycle_id' =>
                                        $targetCycle->id,

                                    'school_group_id' =>
                                        $targetGroup->id,

                                    'campus_id' =>
                                        $targetGroup
                                            ->campus_id,

                                    'previous_enrollment_id' =>
                                        $previousEnrollmentId,

                                    'status' =>
                                        'active',

                                    'enrollment_type' =>
                                        $data[
                                            'enrollment_type'
                                        ],

                                    'enrolled_on' =>
                                        $data[
                                            'effective_on'
                                        ],

                                    'completed_on' =>
                                        null,

                                    'withdrawn_on' =>
                                        null,

                                    'withdrawal_reason' =>
                                        null,

                                    'notes' =>
                                        $data['reason']
                                        ?? 'Asignación masiva desde matrícula del ciclo.',

                                    'created_by_user_id' =>
                                        $request->user()->id,

                                    'created_at' =>
                                        now(),

                                    'updated_at' =>
                                        now(),
                                ]);

                            $movementType =
                                'initial_assignment';

                            $created++;
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
                                        $targetGroup->id,

                                    'campus_id' =>
                                        $targetGroup
                                            ->campus_id,

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
                                (int) $enrollment->id;

                            $movementType =
                                'group_change';

                            $updated++;
                        }

                        if (
                            $targetCycle->status
                                === 'active'
                            && (bool) $targetCycle
                                ->is_active
                        ) {
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
                                    'current_group_id' =>
                                        $targetGroup->id,

                                    'campus_id' =>
                                        $targetGroup
                                            ->campus_id,

                                    'status' =>
                                        'active',

                                    'updated_at' =>
                                        now(),
                                ]);
                        }

                        $this->recordMovement(
                            schoolId: $schoolId,
                            studentId: $studentId,
                            cycleId:
                                (int) $targetCycle->id,
                            enrollmentId:
                                (int) $enrollmentId,
                            fromGroupId:
                                $fromGroupId
                                    ? (int) $fromGroupId
                                    : null,
                            toGroupId:
                                (int) $targetGroup->id,
                            movementType:
                                $movementType,
                            effectiveOn:
                                $data['effective_on'],
                            reason:
                                $data['reason']
                                ?? 'Asignación masiva de matrícula',
                            userId:
                                (int) $request->user()->id
                        );
                    }
                },
                3
            );
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'assignment' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'admin.cycle-enrollments.index',
                [
                    'cycle' =>
                        $targetCycle->id,

                    'source_cycle_id' =>
                        $data[
                            'source_cycle_id'
                        ] ?? null,

                    'target_group_id' =>
                        $targetGroup->id,
                ]
            )
            ->with(
                'success',
                sprintf(
                    'Proceso completado. Nuevas inscripciones: %d. Movidos o actualizados: %d. Sin cambios: %d.',
                    $created,
                    $updated,
                    $unchanged
                )
            );
    }

    public function copyGroup(
        Request $request,
        int $cycle
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $targetCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId: $cycle
        );

        if ($targetCycle->status === 'closed') {
            return back()->withErrors([
                'cycle' =>
                    'No puedes modificar un ciclo cerrado.',
            ]);
        }

        $data = $request->validate([
            'source_cycle_id' => [
                'required',
                'integer',
                'different:target_cycle_id',
            ],

            'source_group_id' => [
                'required',
                'integer',
            ],

            'target_group_id' => [
                'required',
                'integer',
            ],

            'enrollment_type' => [
                'required',

                Rule::in([
                    'reenrollment',
                    'promotion',
                    'repeat',
                ]),
            ],

            'effective_on' => [
                'required',
                'date',
            ],
        ]);

        $sourceCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId: (int) $data[
                'source_cycle_id'
            ]
        );

        $sourceGroup = $this->group(
            schoolId: $schoolId,
            cycleId: (int) $sourceCycle->id,
            groupId: (int) $data[
                'source_group_id'
            ]
        );

        $targetGroup = $this->group(
            schoolId: $schoolId,
            cycleId: (int) $targetCycle->id,
            groupId: (int) $data[
                'target_group_id'
            ]
        );

        $studentIds = DB::table(
            'student_enrollments'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'academic_cycle_id',
                $sourceCycle->id
            )
            ->where(
                'school_group_id',
                $sourceGroup->id
            )
            ->whereIn(
                'status',
                [
                    'active',
                    'completed',
                ]
            )
            ->pluck('student_id')
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->all();

        if (empty($studentIds)) {
            return back()->withErrors([
                'group' =>
                    'El grupo origen no tiene alumnos elegibles.',
            ]);
        }

        $request->merge([
            'student_ids' =>
                $studentIds,

            'target_group_id' =>
                $targetGroup->id,

            'source_cycle_id' =>
                $sourceCycle->id,

            'effective_on' =>
                $data['effective_on'],

            'enrollment_type' =>
                $data['enrollment_type'],

            'reason' =>
                'Copia completa del grupo '
                .$sourceGroup->name
                .' hacia '
                .$targetGroup->name,
        ]);

        return $this->assign(
            request: $request,
            cycle: (int) $targetCycle->id
        );
    }

    public function syncActiveCycle(
        Request $request,
        int $cycle
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $targetCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId: $cycle
        );

        if (
            $targetCycle->status !== 'active'
            || ! (bool) $targetCycle->is_active
        ) {
            return back()->withErrors([
                'cycle' =>
                    'Solo puede sincronizarse el ciclo que está activo.',
            ]);
        }

        $updated = DB::table(
            'student_enrollments as se'
        )
            ->join(
                'school_groups as sg',
                'sg.id',
                '=',
                'se.school_group_id'
            )
            ->where(
                'se.school_id',
                $schoolId
            )
            ->where(
                'se.academic_cycle_id',
                $targetCycle->id
            )
            ->where(
                'se.status',
                'active'
            )
            ->select([
                'se.student_id',
                'sg.id as group_id',
                'sg.campus_id',
            ])
            ->get()
            ->each(
                function ($row) use (
                    $schoolId
                ): void {
                    DB::table('students')
                        ->where(
                            'school_id',
                            $schoolId
                        )
                        ->where(
                            'id',
                            $row->student_id
                        )
                        ->update([
                            'current_group_id' =>
                                $row->group_id,

                            'campus_id' =>
                                $row->campus_id,

                            'status' =>
                                'active',

                            'updated_at' =>
                                now(),
                        ]);
                }
            )
            ->count();

        return back()->with(
            'success',
            "Se sincronizaron {$updated} alumnos con la matrícula del ciclo activo."
        );
    }

    private function candidateRows(
        int $schoolId,
        int $targetCycleId,
        int $sourceCycleId,
        ?int $sourceGroupId,
        string $search,
        string $viewMode
    ): Collection {
        return DB::table(
            'student_enrollments as source'
        )
            ->join(
                'students as s',
                's.id',
                '=',
                'source.student_id'
            )
            ->leftJoin(
                'school_groups as source_group',
                'source_group.id',
                '=',
                'source.school_group_id'
            )
            ->leftJoin(
                'academic_levels as source_level',
                'source_level.id',
                '=',
                'source_group.academic_level_id'
            )
            ->leftJoin(
                'student_enrollments as target',
                function ($join) use (
                    $targetCycleId
                ): void {
                    $join->on(
                        'target.student_id',
                        '=',
                        'source.student_id'
                    )
                        ->where(
                            'target.academic_cycle_id',
                            '=',
                            $targetCycleId
                        );
                }
            )
            ->leftJoin(
                'school_groups as target_group',
                'target_group.id',
                '=',
                'target.school_group_id'
            )
            ->leftJoin(
                'academic_levels as target_level',
                'target_level.id',
                '=',
                'target_group.academic_level_id'
            )
            ->where(
                'source.school_id',
                $schoolId
            )
            ->where(
                'source.academic_cycle_id',
                $sourceCycleId
            )
            ->whereIn(
                'source.status',
                [
                    'active',
                    'completed',
                ]
            )
            ->when(
                $sourceGroupId,
                fn ($query) =>
                    $query->where(
                        'source.school_group_id',
                        $sourceGroupId
                    )
            )
            ->when(
                $viewMode === 'pending',
                fn ($query) =>
                    $query->whereNull(
                        'target.id'
                    )
            )
            ->when(
                $viewMode === 'enrolled',
                fn ($query) =>
                    $query->whereNotNull(
                        'target.id'
                    )
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
                                );
                        }
                    );
                }
            )
            ->orderBy(
                'source_level.sort_order'
            )
            ->orderBy(
                'source_group.name'
            )
            ->orderBy(
                's.last_name'
            )
            ->orderBy(
                's.first_name'
            )
            ->get([
                's.id as student_id',
                's.student_code',
                's.first_name',
                's.last_name',
                's.photo_url',
                's.status as student_status',

                'source.id as source_enrollment_id',
                'source.status as source_enrollment_status',
                'source_group.id as source_group_id',
                'source_group.name as source_group_name',
                'source_level.name as source_level_name',

                'target.id as target_enrollment_id',
                'target.status as target_enrollment_status',
                'target_group.id as target_group_id',
                'target_group.name as target_group_name',
                'target_level.name as target_level_name',
            ]);
    }

    private function targetGroupSummary(
        int $schoolId,
        int $targetCycleId
    ): Collection {
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
                $targetCycleId
            )
            ->orderBy(
                'al.sort_order'
            )
            ->orderBy(
                'sg.name'
            )
            ->get([
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
            ]);
    }

    private function groupsForCycle(
        int $schoolId,
        int $cycleId
    ): Collection {
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
            ->orderBy(
                'al.sort_order'
            )
            ->orderBy(
                'sg.name'
            )
            ->get([
                'sg.id',
                'sg.name',
                'sg.grade_label',
                'sg.campus_id',
                'sg.academic_level_id',
                'al.name as level_name',
                'c.name as campus_name',
            ]);
    }

    private function defaultSourceCycleId(
        Collection $cycles,
        object $targetCycle
    ): ?int {
        $source = $cycles
            ->filter(
                fn ($cycle): bool =>
                    (int) $cycle->id
                        !== (int) $targetCycle->id
                    && $cycle->starts_on
                        < $targetCycle->starts_on
            )
            ->sortByDesc('starts_on')
            ->first();

        return $source
            ? (int) $source->id
            : null;
    }

    private function previousEnrollmentId(
        int $schoolId,
        int $studentId,
        int $targetCycleId
    ): ?int {
        $id = DB::table(
            'student_enrollments as se'
        )
            ->join(
                'academic_cycles as ac',
                'ac.id',
                '=',
                'se.academic_cycle_id'
            )
            ->where(
                'se.school_id',
                $schoolId
            )
            ->where(
                'se.student_id',
                $studentId
            )
            ->where(
                'se.academic_cycle_id',
                '!=',
                $targetCycleId
            )
            ->orderByDesc(
                'ac.starts_on'
            )
            ->orderByDesc(
                'se.id'
            )
            ->value('se.id');

        return $id
            ? (int) $id
            : null;
    }

    private function recordMovement(
        int $schoolId,
        int $studentId,
        int $cycleId,
        int $enrollmentId,
        ?int $fromGroupId,
        int $toGroupId,
        string $movementType,
        string $effectiveOn,
        string $reason,
        int $userId
    ): void {
        if (
            ! Schema::hasTable(
                'student_group_movements'
            )
        ) {
            return;
        }

        DB::table(
            'student_group_movements'
        )->insert([
            'school_id' =>
                $schoolId,

            'student_id' =>
                $studentId,

            'academic_cycle_id' =>
                $cycleId,

            'enrollment_id' =>
                $enrollmentId,

            'from_group_id' =>
                $fromGroupId,

            'to_group_id' =>
                $toGroupId,

            'movement_type' =>
                $movementType,

            'effective_on' =>
                $effectiveOn,

            'reason' =>
                $reason,

            'notes' =>
                null,

            'created_by_user_id' =>
                $userId,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    private function cycle(
        int $schoolId,
        int $cycleId
    ): object {
        return DB::table(
            'academic_cycles'
        )
            ->where(
                'school_id',
                $schoolId
            )
            ->where(
                'id',
                $cycleId
            )
            ->firstOrFail();
    }

    private function group(
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