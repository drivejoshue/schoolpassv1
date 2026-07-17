@extends('layouts.app')

@section('title', 'Promoción y reinscripción | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Promoción y reinscripción')

@section('topbar-actions')
    <a
        href="{{ route('admin.cycles.index') }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-calendar me-1"></i>
        Ciclos escolares
    </a>

    <a
        href="{{ route('admin.groups.index') }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-users-group me-1"></i>
        Grupos
    </a>
@endsection

@section('content')
    @php
        $decisionLabels = [
            'promotion' => 'Promover',
            'reenrollment' => 'Reinscribir',
            'repeat' => 'Repetir grado',
            'change_group' => 'Cambiar de grupo',
            'not_reenrolled' => 'No reinscrito',
            'graduated' => 'Egresado',
            'withdrawn' => 'Baja definitiva',
        ];

        $needsGroup = [
            'promotion',
            'reenrollment',
            'repeat',
            'change_group',
        ];
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
        <form
            method="GET"
            action="{{ route(
                'admin.promotions.index'
            ) }}"
        >
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Seleccionar ciclos
                    </h3>

                    <p class="card-subtitle">
                        El ciclo origen contiene las inscripciones actuales.
                        El destino recibirá las nuevas inscripciones.
                    </p>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">
                            Ciclo origen
                        </label>

                        <select
                            name="source_cycle_id"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Selecciona el ciclo origen
                            </option>

                            @foreach($cycles as $cycle)
                                <option
                                    value="{{ $cycle->id }}"
                                    @selected(
                                        (string) $sourceCycleId
                                        === (string) $cycle->id
                                    )
                                >
                                    {{ $cycle->name }}
                                    · {{ ucfirst($cycle->status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">
                            Ciclo destino
                        </label>

                        <select
                            name="destination_cycle_id"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Selecciona el ciclo destino
                            </option>

                            @foreach($cycles as $cycle)
                                <option
                                    value="{{ $cycle->id }}"
                                    @selected(
                                        (string) $destinationCycleId
                                        === (string) $cycle->id
                                    )
                                >
                                    {{ $cycle->name }}
                                    · {{ ucfirst($cycle->status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100">
                            <i class="ti ti-search me-1"></i>
                            Consultar
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @if(
        ! $sourceCycle
        || ! $destinationCycle
    )
        <div class="alert alert-warning">
            <i class="ti ti-alert-triangle me-2"></i>

            Debes tener al menos dos ciclos y seleccionar
            un ciclo origen y uno destino.
        </div>
    @elseif(
        $sourceCycle->id
        === $destinationCycle->id
    )
        <div class="alert alert-danger">
            El ciclo origen y destino no pueden ser el mismo.
        </div>
    @else
        <div class="row row-cards mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary">
                            Ciclo origen
                        </div>

                        <div class="h2 mb-1">
                            {{ $sourceCycle->name }}
                        </div>

                        <span class="badge bg-{{
                            $sourceCycle->status === 'closed'
                                ? 'secondary'
                                : (
                                    $sourceCycle->status === 'active'
                                        ? 'success'
                                        : 'warning'
                                )
                        }}-lt">
                            {{ ucfirst(
                                $sourceCycle->status
                            ) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary">
                            Ciclo destino
                        </div>

                        <div class="h2 mb-1">
                            {{ $destinationCycle->name }}
                        </div>

                        <span class="badge bg-{{
                            $destinationCycle->status === 'active'
                                ? 'success'
                                : (
                                    $destinationCycle->status === 'draft'
                                        ? 'warning'
                                        : 'secondary'
                                )
                        }}-lt">
                            {{ ucfirst(
                                $destinationCycle->status
                            ) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Copiar grupos y horarios
                    </h3>

                    <p class="card-subtitle">
                        Crea en el ciclo destino una copia de los grupos
                        del ciclo origen.
                    </p>
                </div>
            </div>

            <div class="card-body">
                @if(
                    $destinationCycle->status
                    !== 'draft'
                )
                    <div class="alert alert-warning mb-0">
                        Solo puede copiarse estructura hacia
                        un ciclo en borrador.
                    </div>
                @else
                    <form
                        method="POST"
                        action="{{ route(
                            'admin.promotions.copy-structure'
                        ) }}"
                        onsubmit="return confirm(
                            '¿Copiar grupos y horarios hacia el ciclo destino?'
                        );"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="source_cycle_id"
                            value="{{ $sourceCycle->id }}"
                        >

                        <input
                            type="hidden"
                            name="destination_cycle_id"
                            value="{{ $destinationCycle->id }}"
                        >

                        <label class="form-check mb-3">
                            <input
                                type="hidden"
                                name="copy_schedules"
                                value="0"
                            >

                            <input
                                type="checkbox"
                                name="copy_schedules"
                                value="1"
                                class="form-check-input"
                                checked
                            >

                            <span class="form-check-label">
                                Copiar también horarios de entrada,
                                tolerancia y salida
                            </span>
                        </label>

                        <button class="btn btn-outline-primary">
                            <i class="ti ti-copy me-1"></i>
                            Copiar estructura del ciclo anterior
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-xl-2">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary">
                            Alumnos
                        </div>

                        <div class="h2 mb-0">
                            {{ $summary['students'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-2">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary">
                            Preparados
                        </div>

                        <div class="h2 mb-0 text-primary">
                            {{ $summary['prepared'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-2">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary">
                            Pendientes
                        </div>

                        <div class="h2 mb-0 text-warning">
                            {{ $summary['pending'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-2">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary">
                            Promociones
                        </div>

                        <div class="h2 mb-0 text-success">
                            {{ $summary['promotion'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-2">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary">
                            Repetidores
                        </div>

                        <div class="h2 mb-0 text-orange">
                            {{ $summary['repeat'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-2">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary">
                            Aplicados
                        </div>

                        <div class="h2 mb-0">
                            {{ $summary['applied'] }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route(
                'admin.promotions.save'
            ) }}"
        >
            @csrf

            <input
                type="hidden"
                name="source_cycle_id"
                value="{{ $sourceCycle->id }}"
            >

            <input
                type="hidden"
                name="destination_cycle_id"
                value="{{ $destinationCycle->id }}"
            >

            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Decisiones por alumno
                        </h3>

                        <p class="card-subtitle">
                            Puedes asignar una decisión distinta
                            y un grupo específico a cada alumno.
                        </p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Grupo actual</th>
                                <th style="min-width: 190px;">
                                    Decisión
                                </th>
                                <th style="min-width: 250px;">
                                    Grupo destino
                                </th>
                                <th style="min-width: 220px;">
                                    Notas
                                </th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <td>
                                        <div class="fw-bold">
                                            {{ $row->last_name }}
                                            {{ $row->first_name }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ $row->student_code }}
                                        </div>
                                    </td>

                                    <td>
                                        <div>
                                            {{ $row->source_group_name
                                                ?? 'Sin grupo'
                                            }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ $row->level_name ?? '—' }}
                                            ·
                                            {{ $row->grade_label ?? '—' }}
                                        </div>
                                    </td>

                                    <td>
                                        <select
                                            name="decisions[{{
                                                $row->student_id
                                            }}][decision]"
                                            class="form-select decision-select"
                                            data-student="{{
                                                $row->student_id
                                            }}"
                                            @disabled(
                                                $row->transition_status
                                                === 'applied'
                                            )
                                        >
                                            <option value="">
                                                Sin decidir
                                            </option>

                                            @foreach(
                                                $decisionLabels
                                                as $value => $label
                                            )
                                                <option
                                                    value="{{ $value }}"
                                                    @selected(
                                                        $row->decision
                                                        === $value
                                                    )
                                                >
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <select
                                            name="decisions[{{
                                                $row->student_id
                                            }}][target_group_id]"
                                            class="form-select target-group-select"
                                            id="target-group-{{
                                                $row->student_id
                                            }}"
                                            @disabled(
                                                $row->transition_status
                                                === 'applied'
                                            )
                                        >
                                            <option value="">
                                                Selecciona un grupo
                                            </option>

                                            @foreach(
                                                $destinationGroups
                                                as $group
                                            )
                                                <option
                                                    value="{{ $group->id }}"
                                                    @selected(
                                                        (string) $row
                                                            ->target_group_id
                                                        === (string) $group
                                                            ->id
                                                    )
                                                >
                                                    {{ $group->level_name }}
                                                    · {{ $group->name }}
                                                    · {{ $group->campus_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <input
                                            type="text"
                                            name="decisions[{{
                                                $row->student_id
                                            }}][notes]"
                                            value="{{ $row->transition_notes }}"
                                            class="form-control"
                                            maxlength="1000"
                                            placeholder="Observación opcional"
                                            @disabled(
                                                $row->transition_status
                                                === 'applied'
                                            )
                                        >
                                    </td>

                                    <td>
                                        @if(
                                            $row->transition_status
                                            === 'applied'
                                        )
                                            <span class="badge bg-success-lt">
                                                Aplicado
                                            </span>
                                        @elseif($row->decision)
                                            <span class="badge bg-blue-lt">
                                                Preparado
                                            </span>
                                        @else
                                            <span class="badge bg-warning-lt">
                                                Pendiente
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="6"
                                        class="text-center text-secondary py-5"
                                    >
                                        No se encontraron inscripciones
                                        en el ciclo origen.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($rows->isNotEmpty())
                    <div class="card-footer d-flex justify-content-end">
                        <button class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i>
                            Guardar decisiones
                        </button>
                    </div>
                @endif
            </div>
        </form>

        <div class="card border-danger">
            <div class="card-header">
                <div>
                    <h3 class="card-title text-danger">
                        Aplicar promoción y reinscripción
                    </h3>

                    <p class="card-subtitle">
                        Crea las inscripciones definitivas
                        y actualiza el grupo actual.
                    </p>
                </div>
            </div>

            <div class="card-body">
                @if(
                    $sourceCycle->status !== 'closed'
                )
                    <div class="alert alert-warning">
                        Debes cerrar primero el ciclo origen:

                        <strong>
                            {{ $sourceCycle->name }}
                        </strong>
                    </div>
                @endif

                @if(
                    $destinationCycle->status !== 'active'
                    || ! $destinationCycle->is_active
                )
                    <div class="alert alert-warning">
                        Debes activar primero el ciclo destino:

                        <strong>
                            {{ $destinationCycle->name }}
                        </strong>
                    </div>
                @endif

                <p>
                    Esta acción procesará únicamente las decisiones
                    preparadas que todavía no hayan sido aplicadas.
                </p>

                <form
                    method="POST"
                    action="{{ route(
                        'admin.promotions.apply'
                    ) }}"
                    onsubmit="return confirm(
                        '¿Confirmas la aplicación definitiva de las promociones y reinscripciones?'
                    );"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="source_cycle_id"
                        value="{{ $sourceCycle->id }}"
                    >

                    <input
                        type="hidden"
                        name="destination_cycle_id"
                        value="{{ $destinationCycle->id }}"
                    >

                    <button
                        class="btn btn-danger"
                        @disabled(
                            $sourceCycle->status !== 'closed'
                            || $destinationCycle->status !== 'active'
                            || ! $destinationCycle->is_active
                        )
                    >
                        <i class="ti ti-checks me-1"></i>
                        Aplicar proceso definitivo
                    </button>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function () {
                const decisionsThatNeedGroup = @json(
                    $needsGroup
                );

                const updateGroupState = function (
                    select
                ) {
                    const studentId =
                        select.dataset.student;

                    const groupSelect =
                        document.getElementById(
                            'target-group-' + studentId
                        );

                    if (! groupSelect) {
                        return;
                    }

                    const needsGroup =
                        decisionsThatNeedGroup.includes(
                            select.value
                        );

                    groupSelect.disabled = ! needsGroup;

                    if (! needsGroup) {
                        groupSelect.value = '';
                    }
                };

                document
                    .querySelectorAll(
                        '.decision-select'
                    )
                    .forEach(function (select) {
                        updateGroupState(select);

                        select.addEventListener(
                            'change',
                            function () {
                                updateGroupState(
                                    select
                                );
                            }
                        );
                    });
            }
        );
    </script>
@endpush