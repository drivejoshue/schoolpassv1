@extends('layouts.app')

@section('title', 'Editar ciclo | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Gestión del ciclo escolar')

@section('topbar-actions')
    <a
        href="{{ route(
            'admin.cycle-enrollments.index',
            $cycleRow->id
        ) }}"
        class="btn btn-primary btn-sm"
    >
        <i class="ti ti-users-group me-1"></i>
        Matrícula
    </a>

    <a
        href="{{ route('admin.groups.index') }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-clock me-1"></i>
        Grupos y horarios
    </a>

    <a
        href="{{ route(
            'admin.calendar.index',
            [
                'cycle_id' => $cycleRow->id,
            ]
        ) }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-calendar me-1"></i>
        Calendario
    </a>

    <a
        href="{{ route('admin.cycles.index') }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Ciclos
    </a>
@endsection

@section('content')
    @php
        $isDraft =
            $cycleRow->status === 'draft';

        $isActive =
            $cycleRow->status === 'active'
            && (bool) $cycleRow->is_active;

        $isClosed =
            $cycleRow->status === 'closed';

        $statusLabels = [
            'draft' => 'Borrador',
            'active' => 'Activo',
            'closed' => 'Cerrado',
        ];

        $statusColors = [
            'draft' => 'warning',
            'active' => 'success',
            'closed' => 'secondary',
        ];

        $hasActivationWarnings =
            $activeGroupsCount < 1
            || $enrollmentSummary['active'] < 1
            || $preparationSummary[
                'groups_without_schedule'
            ] > 0
            || $preparationSummary[
                'pending_from_previous'
            ] > 0;

        $activationBlocked =
            $activeGroupsCount < 1;
    @endphp

    @if(session('success'))
        <div class="alert alert-success">
            <i class="ti ti-circle-check me-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <i class="ti ti-alert-circle me-2"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="avatar avatar-lg bg-blue-lt">
                    <i class="ti ti-calendar-stats fs-1"></i>
                </span>

                <div class="flex-fill">
                    <div class="text-secondary">
                        Ciclo escolar
                    </div>

                    <h2 class="mb-1">
                        {{ $cycleRow->name }}
                    </h2>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge bg-{{
                            $statusColors[
                                $cycleRow->status
                            ] ?? 'secondary'
                        }}-lt">
                            {{ $statusLabels[
                                $cycleRow->status
                            ] ?? ucfirst(
                                $cycleRow->status
                            ) }}
                        </span>

                        <span class="text-secondary">
                            {{
                                \Illuminate\Support\Carbon::parse(
                                    $cycleRow->starts_on
                                )->format('d/m/Y')
                            }}

                            al

                            {{
                                \Illuminate\Support\Carbon::parse(
                                    $cycleRow->ends_on
                                )->format('d/m/Y')
                            }}
                        </span>
                    </div>
                </div>

                @if($isActive)
                    <span class="badge bg-success-lt p-2">
                        <i class="ti ti-circle-check me-1"></i>
                        Ciclo operativo
                    </span>
                @endif
            </div>
        </div>
    </div>

    @if($isDraft)
        <div class="alert alert-warning">
            <i class="ti ti-pencil me-2"></i>

            <strong>
                Este ciclo está en preparación.
            </strong>

            Puedes editar datos, matrícula, grupos, horarios
            y calendario antes de activarlo.
        </div>
    @elseif($isActive)
        <div class="alert alert-success">
            <i class="ti ti-circle-check me-2"></i>

            <strong>
                Este es el ciclo operativo de la escuela.
            </strong>

            Los alumnos con inscripción activa y grupo válido
            pueden generar accesos y asistencia.
        </div>
    @elseif($isClosed)
        <div class="alert alert-secondary">
            <i class="ti ti-lock me-2"></i>

            <strong>
                Este ciclo está cerrado.
            </strong>

            Sus datos se conservan para consulta histórica y
            ya no pueden modificarse.
        </div>
    @endif

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Alumnos inscritos
                    </div>

                    <div class="h2 mb-0">
                        {{ number_format(
                            $enrollmentSummary['total']
                        ) }}
                    </div>

                    <div class="text-secondary small">
                        {{ number_format(
                            $enrollmentSummary['active']
                        ) }}
                        activos
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Pendientes del ciclo anterior
                    </div>

                    <div class="h2 mb-0 text-warning">
                        {{ number_format(
                            $preparationSummary[
                                'pending_from_previous'
                            ]
                        ) }}
                    </div>

                    <div class="text-secondary small">
                        @if(
                            $preparationSummary[
                                'previous_cycle'
                            ]
                        )
                            Desde
                            {{
                                $preparationSummary[
                                    'previous_cycle'
                                ]->name
                            }}
                        @else
                            Sin ciclo anterior
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Grupos
                    </div>

                    <div class="h2 mb-0">
                        {{ number_format(
                            $groupsCount
                        ) }}
                    </div>

                    <div class="text-secondary small">
                        {{ number_format(
                            $activeGroupsCount
                        ) }}
                        activos
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Grupos con horario
                    </div>

                    <div class="h2 mb-0">
                        {{ number_format(
                            $enrollmentSummary[
                                'scheduled_groups'
                            ]
                        ) }}
                    </div>

                    <div class="text-secondary small">
                        {{
                            number_format(
                                $preparationSummary[
                                    'groups_without_schedule'
                                ]
                            )
                        }}
                        sin horario
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Bajas
                    </div>

                    <div class="h2 mb-0 text-danger">
                        {{ number_format(
                            $enrollmentSummary[
                                'withdrawn'
                            ]
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Egresados
                    </div>

                    <div class="h2 mb-0 text-blue">
                        {{ number_format(
                            $enrollmentSummary[
                                'graduated'
                            ]
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Movimientos
                    </div>

                    <div class="h2 mb-0">
                        {{ number_format(
                            $enrollmentSummary[
                                'movements'
                            ]
                        ) }}
                    </div>

                    <div class="text-secondary small">
                        Altas y cambios de grupo
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Calendario
                    </div>

                    <div class="h2 mb-0">
                        {{ number_format(
                            $calendarDaysCount
                        ) }}
                    </div>

                    <div class="text-secondary small">
                        {{
                            number_format(
                                $calendarNoClassDaysCount
                            )
                        }}
                        días sin clase
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(
        ! $isClosed
        && (
            $enrollmentSummary['without_group'] > 0
            || $preparationSummary[
                'groups_without_schedule'
            ] > 0
            || $preparationSummary[
                'pending_from_previous'
            ] > 0
        )
    )
        <div class="card border-warning mb-3">
            <div class="card-header">
                <h3 class="card-title text-warning">
                    <i class="ti ti-alert-triangle me-1"></i>
                    Pendientes de preparación
                </h3>
            </div>

            <div class="list-group list-group-flush">
                @if(
                    $preparationSummary[
                        'pending_from_previous'
                    ] > 0
                )
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="fw-bold">
                                    Alumnos sin inscripción
                                </div>

                                <div class="text-secondary small">
                                    Hay alumnos del ciclo anterior que
                                    todavía no fueron incorporados.
                                </div>
                            </div>

                            <div>
                                <span class="badge bg-warning-lt">
                                    {{
                                        number_format(
                                            $preparationSummary[
                                                'pending_from_previous'
                                            ]
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endif

                @if(
                    $enrollmentSummary[
                        'without_group'
                    ] > 0
                )
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="fw-bold">
                                    Inscripciones sin grupo
                                </div>

                                <div class="text-secondary small">
                                    Estas inscripciones no pueden operar
                                    correctamente en asistencia.
                                </div>
                            </div>

                            <span class="badge bg-danger-lt">
                                {{
                                    number_format(
                                        $enrollmentSummary[
                                            'without_group'
                                        ]
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                @endif

                @if(
                    $preparationSummary[
                        'groups_without_schedule'
                    ] > 0
                )
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="fw-bold">
                                    Grupos sin horario
                                </div>

                                <div class="text-secondary small">
                                    No podrán clasificar correctamente
                                    puntualidad y retardos.
                                </div>
                            </div>

                            <span class="badge bg-warning-lt">
                                {{
                                    number_format(
                                        $preparationSummary[
                                            'groups_without_schedule'
                                        ]
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="card-footer">
                <a
                    href="{{ route(
                        'admin.cycle-enrollments.index',
                        $cycleRow->id
                    ) }}"
                    class="btn btn-warning"
                >
                    <i class="ti ti-users-group me-1"></i>
                    Resolver matrícula
                </a>

                <a
                    href="{{ route(
                        'admin.groups.index'
                    ) }}"
                    class="btn btn-outline-warning"
                >
                    <i class="ti ti-clock me-1"></i>
                    Revisar horarios
                </a>
            </div>
        </div>
    @endif

    <div class="row row-cards">
        <div class="col-xl-8">
            <form
                method="POST"
                action="{{ route(
                    'admin.cycles.update',
                    $cycleRow->id
                ) }}"
                class="card"
            >
                @csrf
                @method('PUT')

                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Datos del ciclo
                        </h3>

                        <p class="card-subtitle">
                            Guardar estos datos no cambia el estado
                            operativo del ciclo.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    @include(
                        'admin.cycles.partials.form',
                        [
                            'cycleRow' => $cycleRow,
                        ]
                    )
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a
                        href="{{ route(
                            'admin.cycles.index'
                        ) }}"
                        class="btn btn-outline-secondary"
                    >
                        Cancelar
                    </a>

                    @unless($isClosed)
                        <button class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i>
                            Guardar cambios
                        </button>
                    @endunless
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        Acciones del ciclo
                    </h3>
                </div>

                <div class="list-group list-group-flush">
                    <a
                        href="{{ route(
                            'admin.cycle-enrollments.index',
                            $cycleRow->id
                        ) }}"
                        class="list-group-item list-group-item-action"
                    >
                        <div class="d-flex align-items-center">
                            <span class="avatar bg-blue-lt me-3">
                                <i class="ti ti-users-group"></i>
                            </span>

                            <div>
                                <div class="fw-bold">
                                    Matrícula del ciclo
                                </div>

                                <div class="text-secondary small">
                                    Inscribir, mover y revisar pendientes.
                                </div>
                            </div>
                        </div>
                    </a>

                    <a
                        href="{{ route(
                            'admin.groups.index'
                        ) }}"
                        class="list-group-item list-group-item-action"
                    >
                        <div class="d-flex align-items-center">
                            <span class="avatar bg-orange-lt me-3">
                                <i class="ti ti-clock"></i>
                            </span>

                            <div>
                                <div class="fw-bold">
                                    Grupos y horarios
                                </div>

                                <div class="text-secondary small">
                                    Configurar horarios operativos.
                                </div>
                            </div>
                        </div>
                    </a>

                    <a
                        href="{{ route(
                            'admin.calendar.index',
                            [
                                'cycle_id' =>
                                    $cycleRow->id,
                            ]
                        ) }}"
                        class="list-group-item list-group-item-action"
                    >
                        <div class="d-flex align-items-center">
                            <span class="avatar bg-green-lt me-3">
                                <i class="ti ti-calendar"></i>
                            </span>

                            <div>
                                <div class="fw-bold">
                                    Calendario escolar
                                </div>

                                <div class="text-secondary small">
                                    Vacaciones, suspensiones y días especiales.
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            @if($isDraft)
                <div class="card border-success">
                    <div class="card-header">
                        <h3 class="card-title text-success">
                            Activar ciclo
                        </h3>
                    </div>

                    <div class="card-body">
                        <p>
                            La activación convierte este periodo
                            en el ciclo oficial de asistencia.
                        </p>

                        @if($activationBlocked)
                            <div class="alert alert-danger">
                                Debes crear al menos un grupo activo
                                antes de activar el ciclo.
                            </div>
                        @elseif($hasActivationWarnings)
                            <div class="alert alert-warning">
                                El ciclo todavía tiene elementos
                                pendientes. Puedes revisarlos antes
                                de activarlo.
                            </div>
                        @endif

                        <form
                            method="POST"
                            action="{{ route(
                                'admin.cycles.activate',
                                $cycleRow->id
                            ) }}"
                            onsubmit="return confirm(
                                '¿Confirmas que deseas activar este ciclo? Se convertirá en el periodo operativo de la escuela.'
                            );"
                        >
                            @csrf
                            @method('PATCH')

                            <button
                                class="btn btn-success w-100"
                                @disabled(
                                    $activationBlocked
                                )
                            >
                                <i class="ti ti-player-play me-1"></i>
                                Activar ciclo escolar
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            @if($isActive)
                <div class="card border-danger">
                    <div class="card-header">
                        <h3 class="card-title text-danger">
                            Cerrar ciclo
                        </h3>
                    </div>

                    <div class="card-body">
                        <p>
                            Cerrar el ciclo detiene su operación
                            y lo conserva como histórico.
                        </p>

                        <div class="alert alert-danger">
                            Revisa primero matrícula, bajas,
                            egresados, movimientos y promoción.
                        </div>

                        <form
                            method="POST"
                            action="{{ route(
                                'admin.cycles.close',
                                $cycleRow->id
                            ) }}"
                            onsubmit="return confirm(
                                '¿Confirmas que deseas cerrar este ciclo? Dejará de ser operativo y se enviará al histórico.'
                            );"
                        >
                            @csrf
                            @method('PATCH')

                            <button class="btn btn-danger w-100">
                                <i class="ti ti-lock me-1"></i>
                                Cerrar ciclo escolar
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            @if($isClosed)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Ciclo histórico
                        </h3>
                    </div>

                    <div class="card-body">
                        <p class="text-secondary mb-0">
                            El ciclo está bloqueado para cambios.
                            Sus inscripciones, grupos, asistencias
                            y movimientos permanecen disponibles
                            para consulta.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    Distribución por grupo
                </h3>

                <p class="card-subtitle">
                    Grupos, matrícula activa y cobertura de horarios
                    de {{ $cycleRow->name }}.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Nivel</th>
                        <th>Grado</th>
                        <th>Plantel</th>
                        <th>Alumnos</th>
                        <th>Días con horario</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($groups as $group)
                        <tr>
                            <td class="fw-bold">
                                {{ $group->name }}
                            </td>

                            <td>
                                {{ $group->level_name ?? '—' }}
                            </td>

                            <td>
                                {{ $group->grade_label ?? '—' }}
                            </td>

                            <td>
                                {{ $group->campus_name ?? '—' }}
                            </td>

                            <td>
                                <span class="badge bg-blue-lt">
                                    {{ number_format(
                                        $group->students_count
                                    ) }}
                                </span>
                            </td>

                            <td>
                                <span class="badge bg-{{
                                    (int) $group
                                        ->active_schedules_count > 0
                                        ? 'success'
                                        : 'warning'
                                }}-lt">
                                    {{ number_format(
                                        $group
                                            ->active_schedules_count
                                    ) }}
                                    de 7
                                </span>
                            </td>

                            <td>
                                @if($group->status === 'active')
                                    <span class="badge bg-success-lt">
                                        Activo
                                    </span>
                                @else
                                    <span class="badge bg-secondary-lt">
                                        {{ ucfirst(
                                            $group->status
                                        ) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="text-center text-secondary py-5"
                            >
                                Este ciclo todavía no tiene grupos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection