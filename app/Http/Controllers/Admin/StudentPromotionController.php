<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class StudentPromotionController extends Controller
{
    public function index(Request $request): View
    {
        $schoolId = $this->schoolId($request);

        $filters = $request->validate([
            'source_cycle_id' => [
                'nullable',
                'integer',
            ],

            'destination_cycle_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $cycles = DB::table(
            'academic_cycles'
        )
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
                $cycles
            );

        $destinationCycleId = ! empty(
            $filters['destination_cycle_id']
        )
            ? (int) $filters[
                'destination_cycle_id'
            ]
            : $this->defaultDestinationCycleId(
                cycles: $cycles,
                sourceCycleId: $sourceCycleId
            );

        $sourceCycle = $cycles->firstWhere(
            'id',
            $sourceCycleId
        );

        $destinationCycle = $cycles->firstWhere(
            'id',
            $destinationCycleId
        );

        if (
            $sourceCycleId
            && ! $sourceCycle
        ) {
            abort(404);
        }

        if (
            $destinationCycleId
            && ! $destinationCycle
        ) {
            abort(404);
        }

        $destinationGroups = collect();
        $rows = collect();
        $summary = $this->emptySummary();

        if (
            $sourceCycle
            && $destinationCycle
            && $sourceCycle->id
                !== $destinationCycle->id
        ) {
            $destinationGroups = $this
                ->destinationGroups(
                    schoolId: $schoolId,
                    cycleId: (int) $destinationCycle->id
                );

            $rows = $this->promotionRows(
                schoolId: $schoolId,
                sourceCycleId:
                    (int) $sourceCycle->id,
                destinationCycleId:
                    (int) $destinationCycle->id
            );

            $summary = [
                'students' => $rows->count(),

                'prepared' => $rows
                    ->whereNotNull('decision')
                    ->count(),

                'pending' => $rows
                    ->whereNull('decision')
                    ->count(),

                'applied' => $rows
                    ->where(
                        'transition_status',
                        'applied'
                    )
                    ->count(),

                'promotion' => $rows
                    ->where(
                        'decision',
                        'promotion'
                    )
                    ->count(),

                'repeat' => $rows
                    ->where(
                        'decision',
                        'repeat'
                    )
                    ->count(),

                'not_reenrolled' => $rows
                    ->where(
                        'decision',
                        'not_reenrolled'
                    )
                    ->count(),

                'graduated' => $rows
                    ->where(
                        'decision',
                        'graduated'
                    )
                    ->count(),
            ];
        }

        return view(
            'admin.promotions.index',
            [
                'cycles' => $cycles,

                'sourceCycleId' =>
                    $sourceCycleId,

                'destinationCycleId' =>
                    $destinationCycleId,

                'sourceCycle' =>
                    $sourceCycle,

                'destinationCycle' =>
                    $destinationCycle,

                'destinationGroups' =>
                    $destinationGroups,

                'rows' => $rows,
                'summary' => $summary,
            ]
        );
    }

    public function copyStructure(
        Request $request
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $data = $request->validate([
            'source_cycle_id' => [
                'required',
                'integer',
            ],

            'destination_cycle_id' => [
                'required',
                'integer',
                'different:source_cycle_id',
            ],

            'copy_schedules' => [
                'nullable',
                'boolean',
            ],
        ]);

        $sourceCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId:
                (int) $data['source_cycle_id']
        );

        $destinationCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId:
                (int) $data[
                    'destination_cycle_id'
                ]
        );

        if (
            $destinationCycle->status
            !== 'draft'
        ) {
            return back()->withErrors([
                'destination_cycle_id' =>
                    'Solo puedes copiar grupos hacia un ciclo en borrador.',
            ]);
        }

        try {
            $result = DB::transaction(
                function () use (
                    $schoolId,
                    $sourceCycle,
                    $destinationCycle,
                    $data
                ): array {
                    $sourceGroups = DB::table(
                        'school_groups'
                    )
                        ->where(
                            'school_id',
                            $schoolId
                        )
                        ->where(
                            'academic_cycle_id',
                            $sourceCycle->id
                        )
                        ->orderBy('id')
                        ->get();

                    if ($sourceGroups->isEmpty()) {
                        throw new RuntimeException(
                            'El ciclo origen no tiene grupos para copiar.'
                        );
                    }

                    $groupsCreated = 0;
                    $groupsExisting = 0;
                    $schedulesCreated = 0;

                    foreach (
                        $sourceGroups as $sourceGroup
                    ) {
                        $destinationGroup = DB::table(
                            'school_groups'
                        )
                            ->where(
                                'school_id',
                                $schoolId
                            )
                            ->where(
                                'academic_cycle_id',
                                $destinationCycle->id
                            )
                            ->where(
                                'campus_id',
                                $sourceGroup->campus_id
                            )
                            ->where(
                                'academic_level_id',
                                $sourceGroup
                                    ->academic_level_id
                            )
                            ->where(
                                'name',
                                $sourceGroup->name
                            )
                            ->first();

                        if (! $destinationGroup) {
                            $destinationGroupId =
                                DB::table(
                                    'school_groups'
                                )->insertGetId([
                                    'school_id' =>
                                        $schoolId,

                                    'campus_id' =>
                                        $sourceGroup
                                            ->campus_id,

                                    'academic_level_id' =>
                                        $sourceGroup
                                            ->academic_level_id,

                                    'academic_cycle_id' =>
                                        $destinationCycle
                                            ->id,

                                    'name' =>
                                        $sourceGroup->name,

                                    'grade_label' =>
                                        $sourceGroup
                                            ->grade_label,

                                    'status' =>
                                        'active',

                                    'created_at' =>
                                        now(),

                                    'updated_at' =>
                                        now(),
                                ]);

                            $groupsCreated++;
                        } else {
                            $destinationGroupId =
                                (int) $destinationGroup->id;

                            $groupsExisting++;
                        }

                        if (
                            ! (
                                $data[
                                    'copy_schedules'
                                ] ?? false
                            )
                        ) {
                            continue;
                        }

                        $schedules = DB::table(
                            'group_access_schedules'
                        )
                            ->where(
                                'school_id',
                                $schoolId
                            )
                            ->where(
                                'group_id',
                                $sourceGroup->id
                            )
                            ->orderBy('weekday')
                            ->get();

                        foreach (
                            $schedules as $schedule
                        ) {
                            $exists = DB::table(
                                'group_access_schedules'
                            )
                                ->where(
                                    'group_id',
                                    $destinationGroupId
                                )
                                ->where(
                                    'weekday',
                                    $schedule->weekday
                                )
                                ->exists();

                            if ($exists) {
                                continue;
                            }

                            DB::table(
                                'group_access_schedules'
                            )->insert([
                                'school_id' =>
                                    $schoolId,

                                'group_id' =>
                                    $destinationGroupId,

                                'weekday' =>
                                    $schedule->weekday,

                                'entry_time' =>
                                    $schedule->entry_time,

                                'grace_until' =>
                                    $schedule->grace_until,

                                'late_until' =>
                                    $schedule->late_until,

                                'exit_time' =>
                                    $schedule->exit_time,

                                'status' =>
                                    $schedule->status,

                                'created_at' =>
                                    now(),

                                'updated_at' =>
                                    now(),
                            ]);

                            $schedulesCreated++;
                        }
                    }

                    return [
                        'groups_created' =>
                            $groupsCreated,

                        'groups_existing' =>
                            $groupsExisting,

                        'schedules_created' =>
                            $schedulesCreated,
                    ];
                },
                3
            );
        } catch (Throwable $exception) {
            return back()->withErrors([
                'copy' =>
                    $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route(
                'admin.promotions.index',
                [
                    'source_cycle_id' =>
                        $sourceCycle->id,

                    'destination_cycle_id' =>
                        $destinationCycle->id,
                ]
            )
            ->with(
                'success',
                sprintf(
                    'Estructura copiada: %d grupos nuevos, %d existentes y %d horarios nuevos.',
                    $result['groups_created'],
                    $result['groups_existing'],
                    $result['schedules_created']
                )
            );
    }

    public function save(
        Request $request
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $data = $request->validate([
            'source_cycle_id' => [
                'required',
                'integer',
            ],

            'destination_cycle_id' => [
                'required',
                'integer',
                'different:source_cycle_id',
            ],

            'decisions' => [
                'required',
                'array',
            ],

            'decisions.*.decision' => [
                'nullable',
                'in:promotion,reenrollment,repeat,change_group,not_reenrolled,graduated,withdrawn',
            ],

            'decisions.*.target_group_id' => [
                'nullable',
                'integer',
            ],

            'decisions.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $sourceCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId:
                (int) $data['source_cycle_id']
        );

        $destinationCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId:
                (int) $data[
                    'destination_cycle_id'
                ]
        );

        if (
            $destinationCycle->status
            === 'closed'
        ) {
            return back()->withErrors([
                'destination_cycle_id' =>
                    'No puedes preparar promociones hacia un ciclo cerrado.',
            ]);
        }

        $destinationGroupIds = DB::table(
            'school_groups'
        )
            ->where('school_id', $schoolId)
            ->where(
                'academic_cycle_id',
                $destinationCycle->id
            )
            ->pluck('id')
            ->map(
                fn ($id): int => (int) $id
            )
            ->all();

        $sourceEnrollments = DB::table(
            'student_enrollments'
        )
            ->where('school_id', $schoolId)
            ->where(
                'academic_cycle_id',
                $sourceCycle->id
            )
            ->get()
            ->keyBy('student_id');

        $saved = 0;
        $deleted = 0;

        try {
            DB::transaction(
                function () use (
                    $request,
                    $data,
                    $schoolId,
                    $sourceCycle,
                    $destinationCycle,
                    $destinationGroupIds,
                    $sourceEnrollments,
                    &$saved,
                    &$deleted
                ): void {
                    foreach (
                        $data['decisions']
                        as $studentId => $decisionData
                    ) {
                        $studentId = (int) $studentId;

                        $sourceEnrollment =
                            $sourceEnrollments->get(
                                $studentId
                            );

                        if (! $sourceEnrollment) {
                            continue;
                        }

                        $decision =
                            $decisionData[
                                'decision'
                            ] ?? null;

                        if (! $decision) {
                            $deleted += DB::table(
                                'student_cycle_transitions'
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
                                    'source_cycle_id',
                                    $sourceCycle->id
                                )
                                ->where(
                                    'destination_cycle_id',
                                    $destinationCycle
                                        ->id
                                )
                                ->where(
                                    'status',
                                    'draft'
                                )
                                ->delete();

                            continue;
                        }

                        $targetGroupId = ! empty(
                            $decisionData[
                                'target_group_id'
                            ]
                        )
                            ? (int) $decisionData[
                                'target_group_id'
                            ]
                            : null;

                        if (
                            $this->decisionNeedsGroup(
                                $decision
                            )
                        ) {
                            if (
                                ! $targetGroupId
                                || ! in_array(
                                    $targetGroupId,
                                    $destinationGroupIds,
                                    true
                                )
                            ) {
                                throw new RuntimeException(
                                    'Todos los alumnos que continúan deben tener un grupo válido del ciclo destino.'
                                );
                            }
                        } else {
                            $targetGroupId = null;
                        }

                        DB::table(
                            'student_cycle_transitions'
                        )->updateOrInsert(
                            [
                                'student_id' =>
                                    $studentId,

                                'source_cycle_id' =>
                                    $sourceCycle->id,

                                'destination_cycle_id' =>
                                    $destinationCycle
                                        ->id,
                            ],
                            [
                                'school_id' =>
                                    $schoolId,

                                'source_enrollment_id' =>
                                    $sourceEnrollment->id,

                                'target_group_id' =>
                                    $targetGroupId,

                                'decision' =>
                                    $decision,

                                'status' =>
                                    'draft',

                                'notes' =>
                                    $decisionData[
                                        'notes'
                                    ] ?? null,

                                'created_by_user_id' =>
                                    $request->user()->id,

                                'applied_by_user_id' =>
                                    null,

                                'applied_at' =>
                                    null,

                                'updated_at' =>
                                    now(),

                                'created_at' =>
                                    DB::raw(
                                        'COALESCE(created_at, CURRENT_TIMESTAMP)'
                                    ),
                            ]
                        );

                        $saved++;
                    }
                },
                3
            );
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'decisions' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'admin.promotions.index',
                [
                    'source_cycle_id' =>
                        $sourceCycle->id,

                    'destination_cycle_id' =>
                        $destinationCycle->id,
                ]
            )
            ->with(
                'success',
                sprintf(
                    'Decisiones guardadas: %d. Decisiones eliminadas: %d.',
                    $saved,
                    $deleted
                )
            );
    }

    public function apply(
        Request $request
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $data = $request->validate([
            'source_cycle_id' => [
                'required',
                'integer',
            ],

            'destination_cycle_id' => [
                'required',
                'integer',
                'different:source_cycle_id',
            ],
        ]);

        $sourceCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId:
                (int) $data['source_cycle_id']
        );

        $destinationCycle = $this->cycle(
            schoolId: $schoolId,
            cycleId:
                (int) $data[
                    'destination_cycle_id'
                ]
        );

        if (
            $sourceCycle->status
            !== 'closed'
        ) {
            return back()->withErrors([
                'apply' =>
                    'Primero debes cerrar el ciclo origen.',
            ]);
        }

        if (
            $destinationCycle->status
                !== 'active'
            || ! (bool) $destinationCycle
                ->is_active
        ) {
            return back()->withErrors([
                'apply' =>
                    'El ciclo destino debe estar activo antes de aplicar la promoción.',
            ]);
        }

        try {
            $result = DB::transaction(
                function () use (
                    $request,
                    $schoolId,
                    $sourceCycle,
                    $destinationCycle
                ): array {
                    $transitions = DB::table(
                        'student_cycle_transitions'
                    )
                        ->where(
                            'school_id',
                            $schoolId
                        )
                        ->where(
                            'source_cycle_id',
                            $sourceCycle->id
                        )
                        ->where(
                            'destination_cycle_id',
                            $destinationCycle
                                ->id
                        )
                        ->where(
                            'status',
                            'draft'
                        )
                        ->lockForUpdate()
                        ->get();

                    if ($transitions->isEmpty()) {
                        throw new RuntimeException(
                            'No hay decisiones pendientes para aplicar.'
                        );
                    }

                    $applied = 0;
                    $continued = 0;
                    $departed = 0;

                    foreach (
                        $transitions as $transition
                    ) {
                        $student = DB::table(
                            'students'
                        )
                            ->where(
                                'school_id',
                                $schoolId
                            )
                            ->where(
                                'id',
                                $transition->student_id
                            )
                            ->lockForUpdate()
                            ->first();

                        if (! $student) {
                            continue;
                        }

                        $sourceEnrollment =
                            DB::table(
                                'student_enrollments'
                            )
                                ->where(
                                    'school_id',
                                    $schoolId
                                )
                                ->where(
                                    'id',
                                    $transition
                                        ->source_enrollment_id
                                )
                                ->lockForUpdate()
                                ->first();

                        if (! $sourceEnrollment) {
                            throw new RuntimeException(
                                'No se encontró una inscripción origen para uno de los alumnos.'
                            );
                        }

                        if (
                            $this->decisionNeedsGroup(
                                $transition->decision
                            )
                        ) {
                            $targetGroup =
                                DB::table(
                                    'school_groups'
                                )
                                    ->where(
                                        'school_id',
                                        $schoolId
                                    )
                                    ->where(
                                        'academic_cycle_id',
                                        $destinationCycle
                                            ->id
                                    )
                                    ->where(
                                        'id',
                                        $transition
                                            ->target_group_id
                                    )
                                    ->where(
                                        'status',
                                        'active'
                                    )
                                    ->first();

                            if (! $targetGroup) {
                                throw new RuntimeException(
                                    'Uno de los grupos destino ya no existe o no está activo.'
                                );
                            }

                            DB::table(
                                'student_enrollments'
                            )->updateOrInsert(
                                [
                                    'student_id' =>
                                        $student->id,

                                    'academic_cycle_id' =>
                                        $destinationCycle
                                            ->id,
                                ],
                                [
                                    'school_id' =>
                                        $schoolId,

                                    'school_group_id' =>
                                        $targetGroup->id,

                                    'campus_id' =>
                                        $targetGroup
                                            ->campus_id,

                                    'previous_enrollment_id' =>
                                        $sourceEnrollment
                                            ->id,

                                    'status' =>
                                        'active',

                                    'enrollment_type' =>
                                        $this
                                            ->enrollmentType(
                                                $transition
                                                    ->decision
                                            ),

                                    'enrolled_on' =>
                                        $destinationCycle
                                            ->starts_on,

                                    'completed_on' =>
                                        null,

                                    'withdrawn_on' =>
                                        null,

                                    'withdrawal_reason' =>
                                        null,

                                    'notes' =>
                                        $transition->notes,

                                    'created_by_user_id' =>
                                        $request->user()->id,

                                    'updated_at' =>
                                        now(),

                                    'created_at' =>
                                        DB::raw(
                                            'COALESCE(created_at, CURRENT_TIMESTAMP)'
                                        ),
                                ]
                            );

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
                                        $targetGroup->id,

                                    'campus_id' =>
                                        $targetGroup
                                            ->campus_id,

                                    'status' =>
                                        'active',

                                    'updated_at' =>
                                        now(),
                                ]);

                            DB::table(
                                'student_enrollments'
                            )
                                ->where(
                                    'id',
                                    $sourceEnrollment
                                        ->id
                                )
                                ->update([
                                    'status' =>
                                        'completed',

                                    'completed_on' =>
                                        $sourceCycle
                                            ->ends_on,

                                    'updated_at' =>
                                        now(),
                                ]);

                            $continued++;
                        } else {
                            $this->applyDepartureDecision(
                                studentId:
                                    (int) $student->id,
                                sourceEnrollmentId:
                                    (int) $sourceEnrollment
                                        ->id,
                                decision:
                                    $transition->decision,
                                schoolId:
                                    $schoolId,
                                sourceCycle:
                                    $sourceCycle
                            );

                            $departed++;
                        }

                        DB::table(
                            'student_cycle_transitions'
                        )
                            ->where(
                                'id',
                                $transition->id
                            )
                            ->update([
                                'status' =>
                                    'applied',

                                'applied_by_user_id' =>
                                    $request->user()->id,

                                'applied_at' =>
                                    now(),

                                'updated_at' =>
                                    now(),
                            ]);

                        $applied++;
                    }

                    return [
                        'applied' => $applied,
                        'continued' => $continued,
                        'departed' => $departed,
                    ];
                },
                3
            );
        } catch (Throwable $exception) {
            return back()->withErrors([
                'apply' =>
                    $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route(
                'admin.promotions.index',
                [
                    'source_cycle_id' =>
                        $sourceCycle->id,

                    'destination_cycle_id' =>
                        $destinationCycle->id,
                ]
            )
            ->with(
                'success',
                sprintf(
                    'Proceso aplicado: %d alumnos. Continúan: %d. Bajas, egresos o no reinscritos: %d.',
                    $result['applied'],
                    $result['continued'],
                    $result['departed']
                )
            );
    }

    private function promotionRows(
        int $schoolId,
        int $sourceCycleId,
        int $destinationCycleId
    ): Collection {
        return DB::table(
            'student_enrollments as se'
        )
            ->join(
                'students as s',
                's.id',
                '=',
                'se.student_id'
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
                'student_cycle_transitions as sct',
                function ($join) use (
                    $sourceCycleId,
                    $destinationCycleId
                ): void {
                    $join->on(
                        'sct.student_id',
                        '=',
                        'se.student_id'
                    )
                        ->where(
                            'sct.source_cycle_id',
                            '=',
                            $sourceCycleId
                        )
                        ->where(
                            'sct.destination_cycle_id',
                            '=',
                            $destinationCycleId
                        );
                }
            )
            ->where(
                'se.school_id',
                $schoolId
            )
            ->where(
                'se.academic_cycle_id',
                $sourceCycleId
            )
            ->whereIn(
                'se.status',
                [
                    'active',
                    'completed',
                ]
            )
            ->orderBy('al.sort_order')
            ->orderBy('sg.name')
            ->orderBy('s.last_name')
            ->orderBy('s.first_name')
            ->get([
                'se.id as source_enrollment_id',
                'se.student_id',
                'se.status as enrollment_status',

                's.student_code',
                's.first_name',
                's.last_name',
                's.status as student_status',

                'sg.id as source_group_id',
                'sg.name as source_group_name',
                'sg.grade_label',
                'al.name as level_name',

                'sct.id as transition_id',
                'sct.decision',
                'sct.target_group_id',
                'sct.status as transition_status',
                'sct.notes as transition_notes',
            ]);
    }

    private function destinationGroups(
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
            ->orderBy('al.sort_order')
            ->orderBy('sg.name')
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

    private function applyDepartureDecision(
        int $studentId,
        int $sourceEnrollmentId,
        string $decision,
        int $schoolId,
        object $sourceCycle
    ): void {
        $enrollmentStatus = match (
            $decision
        ) {
            'graduated' =>
                'graduated',

            'withdrawn' =>
                'withdrawn',

            default =>
                'not_reenrolled',
        };

        $studentStatus = match (
            $decision
        ) {
            'graduated' =>
                'graduated',

            default =>
                'inactive',
        };

        DB::table('student_enrollments')
            ->where(
                'id',
                $sourceEnrollmentId
            )
            ->update([
                'status' =>
                    $enrollmentStatus,

                'completed_on' =>
                    $decision === 'withdrawn'
                        ? null
                        : $sourceCycle->ends_on,

                'withdrawn_on' =>
                    $decision === 'withdrawn'
                        ? now()->toDateString()
                        : null,

                'updated_at' =>
                    now(),
            ]);

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
                'status' =>
                    $studentStatus,

                /*
                 * current_group_id no puede quedar null
                 * en la estructura actual.
                 *
                 * Conservamos el último grupo histórico,
                 * pero el alumno deja de estar activo.
                 */
                'updated_at' =>
                    now(),
            ]);
    }

    private function decisionNeedsGroup(
        string $decision
    ): bool {
        return in_array(
            $decision,
            [
                'promotion',
                'reenrollment',
                'repeat',
                'change_group',
            ],
            true
        );
    }

    private function enrollmentType(
        string $decision
    ): string {
        return match ($decision) {
            'promotion' =>
                'promotion',

            'repeat' =>
                'repeat',

            'change_group' =>
                'reenrollment',

            default =>
                'reenrollment',
        };
    }

    private function cycle(
        int $schoolId,
        int $cycleId
    ): object {
        return DB::table('academic_cycles')
            ->where(
                'school_id',
                $schoolId
            )
            ->where('id', $cycleId)
            ->firstOrFail();
    }

    private function defaultSourceCycleId(
        Collection $cycles
    ): ?int {
        $active = $cycles->first(
            fn ($cycle): bool =>
                $cycle->status === 'active'
                && (bool) $cycle->is_active
        );

        if ($active) {
            return (int) $active->id;
        }

        $closed = $cycles->first(
            fn ($cycle): bool =>
                $cycle->status === 'closed'
        );

        return $closed
            ? (int) $closed->id
            : null;
    }

    private function defaultDestinationCycleId(
        Collection $cycles,
        ?int $sourceCycleId
    ): ?int {
        $destination = $cycles->first(
            fn ($cycle): bool =>
                (int) $cycle->id
                    !== $sourceCycleId
                && $cycle->status === 'draft'
        );

        return $destination
            ? (int) $destination->id
            : null;
    }

    private function emptySummary(): array
    {
        return [
            'students' => 0,
            'prepared' => 0,
            'pending' => 0,
            'applied' => 0,
            'promotion' => 0,
            'repeat' => 0,
            'not_reenrolled' => 0,
            'graduated' => 0,
        ];
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