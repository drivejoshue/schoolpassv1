@extends('layouts.app')

@section('title', 'Matrícula del ciclo | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Matrícula del ciclo')

@section('topbar-actions')
    <a
        href="{{ route(
            'admin.cycles.edit',
            $targetCycle->id
        ) }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-calendar me-1"></i>
        Ver ciclo
    </a>

    <a
        href="{{ route(
            'admin.students.index'
        ) }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-users me-1"></i>
        Alumnos
    </a>

    <a
        href="{{ route(
            'admin.cycles.index'
        ) }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Ciclos
    </a>
@endsection

@section('content')
    @php
        $targetIsActive =
            $targetCycle->status === 'active'
            && (bool) $targetCycle->is_active;

        $targetIsClosed =
            $targetCycle->status === 'closed';

        $cycleStatusLabels = [
            'draft' => 'Borrador',
            'active' => 'Activo',
            'closed' => 'Cerrado',
        ];

        $cycleStatusColors = [
            'draft' => 'warning',
            'active' => 'success',
            'closed' => 'secondary',
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
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="avatar avatar-lg bg-blue-lt">
                    <i class="ti ti-school fs-1"></i>
                </span>

                <div class="flex-fill">
                    <div class="text-secondary">
                        Ciclo destino
                    </div>

                    <h2 class="mb-1">
                        {{ $targetCycle->name }}
                    </h2>

                    <div>
                        <span class="badge bg-{{
                            $cycleStatusColors[
                                $targetCycle->status
                            ] ?? 'secondary'
                        }}-lt">
                            {{ $cycleStatusLabels[
                                $targetCycle->status
                            ] ?? ucfirst(
                                $targetCycle->status
                            ) }}
                        </span>

                        <span class="text-secondary ms-2">
                            {{
                                \Illuminate\Support\Carbon::parse(
                                    $targetCycle->starts_on
                                )->format('d/m/Y')
                            }}

                            al

                            {{
                                \Illuminate\Support\Carbon::parse(
                                    $targetCycle->ends_on
                                )->format('d/m/Y')
                            }}
                        </span>
                    </div>
                </div>

                @if($targetIsActive)
                    <form
                        method="POST"
                        action="{{ route(
                            'admin.cycle-enrollments.sync',
                            $targetCycle->id
                        ) }}"
                        onsubmit="return confirm(
                            '¿Sincronizar current_group_id con la matrícula activa?'
                        );"
                    >
                        @csrf

                        <button class="btn btn-outline-primary">
                            <i class="ti ti-refresh me-1"></i>
                            Sincronizar alumnos
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @if($targetIsClosed)
        <div class="alert alert-warning">
            <i class="ti ti-lock me-2"></i>

            Este ciclo está cerrado. La matrícula solo puede consultarse.
        </div>
    @elseif($targetCycle->status === 'draft')
        <div class="alert alert-info">
            <i class="ti ti-info-circle me-2"></i>

            Puedes preparar toda la matrícula mientras el ciclo está
            en borrador. El grupo actual del alumno no cambiará hasta
            que el ciclo sea activado o se ejecute la sincronización
            correspondiente.
        </div>
    @else
        <div class="alert alert-success">
            <i class="ti ti-circle-check me-2"></i>

            Este ciclo está activo. Las asignaciones realizadas aquí
            actualizan inmediatamente el grupo operativo del alumno.
        </div>
    @endif

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Inscritos
                    </div>

                    <div class="h2 mb-0">
                        {{ number_format(
                            $summary['enrolled']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Inscripciones activas
                    </div>

                    <div class="h2 mb-0 text-success">
                        {{ number_format(
                            $summary['active_enrolled']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Pendientes del origen
                    </div>

                    <div class="h2 mb-0 text-warning">
                        {{ number_format(
                            $summary['pending_from_source']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Sin grupo
                    </div>

                    <div class="h2 mb-0 text-danger">
                        {{ number_format(
                            $summary['without_group']
                        ) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <form
            method="GET"
            action="{{ route(
                'admin.cycle-enrollments.index',
                $targetCycle->id
            ) }}"
        >
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Seleccionar alumnos de origen
                    </h3>

                    <p class="card-subtitle">
                        Filtra un ciclo y grupo anterior para
                        incorporarlos al ciclo destino.
                    </p>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">
                            Ciclo origen
                        </label>

                        <select
                            name="source_cycle_id"
                            class="form-select"
                            onchange="this.form.submit()"
                        >
                            <option value="">
                                Selecciona ciclo
                            </option>

                            @foreach($cycles as $cycle)
                                @if(
                                    (int) $cycle->id
                                    !== (int) $targetCycle->id
                                )
                                    <option
                                        value="{{ $cycle->id }}"
                                        @selected(
                                            (string) $sourceCycleId
                                            === (string) $cycle->id
                                        )
                                    >
                                        {{ $cycle->name }}
                                        ·
                                        {{ ucfirst($cycle->status) }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">
                            Grupo origen
                        </label>

                        <select
                            name="source_group_id"
                            class="form-select"
                        >
                            <option value="">
                                Todos los grupos
                            </option>

                            @foreach($sourceGroups as $group)
                                <option
                                    value="{{ $group->id }}"
                                    @selected(
                                        (string) $sourceGroupId
                                        === (string) $group->id
                                    )
                                >
                                    {{ $group->level_name }}
                                    ·
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">
                            Vista
                        </label>

                        <select
                            name="view"
                            class="form-select"
                        >
                            <option
                                value="pending"
                                @selected(
                                    $viewMode === 'pending'
                                )
                            >
                                Pendientes
                            </option>

                            <option
                                value="enrolled"
                                @selected(
                                    $viewMode === 'enrolled'
                                )
                            >
                                Ya inscritos
                            </option>

                            <option
                                value="all"
                                @selected(
                                    $viewMode === 'all'
                                )
                            >
                                Todos
                            </option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">
                            Buscar
                        </label>

                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Nombre o matrícula"
                        >
                    </div>

                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-primary w-100">
                            <i class="ti ti-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @if(
        ! $targetIsClosed
        && $sourceCycle
        && $sourceGroupId
    )
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Copiar grupo completo
                    </h3>

                    <p class="card-subtitle">
                        Inscribe en una sola operación a todos los
                        alumnos elegibles del grupo origen.
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route(
                    'admin.cycle-enrollments.copy-group',
                    $targetCycle->id
                ) }}"
                onsubmit="return confirm(
                    '¿Copiar todos los alumnos del grupo seleccionado?'
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
                    name="source_group_id"
                    value="{{ $sourceGroupId }}"
                >

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">
                                Grupo destino
                            </label>

                            <select
                                name="target_group_id"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    Selecciona grupo destino
                                </option>

                                @foreach($targetGroups as $group)
                                    <option value="{{ $group->id }}">
                                        {{ $group->level_name }}
                                        ·
                                        {{ $group->name }}
                                        ·
                                        {{ $group->campus_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">
                                Tipo
                            </label>

                            <select
                                name="enrollment_type"
                                class="form-select"
                                required
                            >
                                <option value="reenrollment">
                                    Reinscripción
                                </option>

                                <option value="promotion">
                                    Promoción
                                </option>

                                <option value="repeat">
                                    Repetición
                                </option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">
                                Fecha efectiva
                            </label>

                            <input
                                type="date"
                                name="effective_on"
                                value="{{ $targetCycle->starts_on }}"
                                class="form-control"
                                required
                            >
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-outline-primary w-100">
                                <i class="ti ti-copy me-1"></i>
                                Copiar grupo
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route(
            'admin.cycle-enrollments.assign',
            $targetCycle->id
        ) }}"
        id="batch-enrollment-form"
    >
        @csrf

        <input
            type="hidden"
            name="source_cycle_id"
            value="{{ $sourceCycleId }}"
        >

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Alumnos
                    </h3>

                    <p class="card-subtitle">
                        Selecciona alumnos individualmente o marca todos.
                    </p>
                </div>

                @if(! $targetIsClosed)
                    <div class="card-actions">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            id="select-all"
                        >
                            Seleccionar todos
                        </button>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            id="clear-all"
                        >
                            Limpiar
                        </button>
                    </div>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th class="w-1">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="master-checkbox"
                                    @disabled($targetIsClosed)
                                >
                            </th>

                            <th>Alumno</th>
                            <th>Matrícula</th>
                            <th>Grupo origen</th>
                            <th>Situación destino</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        class="form-check-input student-checkbox"
                                        name="student_ids[]"
                                        value="{{ $row->student_id }}"
                                        @disabled($targetIsClosed)
                                    >
                                </td>

                                <td>
                                    <div class="d-flex align-items-center">
                                        @if($row->photo_url)
                                            <span
                                                class="avatar avatar-sm me-2"
                                                style="background-image: url('{{
                                                    asset($row->photo_url)
                                                }}')"
                                            ></span>
                                        @else
                                            <span class="avatar avatar-sm bg-blue-lt me-2">
                                                {{ strtoupper(
                                                    mb_substr(
                                                        $row->first_name,
                                                        0,
                                                        1
                                                    )
                                                ) }}
                                            </span>
                                        @endif

                                        <div>
                                            <div class="fw-bold">
                                                {{ $row->first_name }}
                                                {{ $row->last_name }}
                                            </div>

                                            <a
                                                href="{{ route(
                                                    'admin.students.show',
                                                    $row->student_id
                                                ) }}"
                                                class="small"
                                            >
                                                Ver ficha
                                            </a>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    {{ $row->student_code }}
                                </td>

                                <td>
                                    <div>
                                        {{ $row->source_group_name
                                            ?? 'Sin grupo'
                                        }}
                                    </div>

                                    <div class="text-secondary small">
                                        {{ $row->source_level_name
                                            ?? 'Sin nivel'
                                        }}
                                    </div>
                                </td>

                                <td>
                                    @if($row->target_enrollment_id)
                                        <span class="badge bg-success-lt">
                                            Inscrito
                                        </span>

                                        <div class="small mt-1">
                                            {{ $row->target_level_name
                                                ?? 'Sin nivel'
                                            }}

                                            ·

                                            {{ $row->target_group_name
                                                ?? 'Sin grupo'
                                            }}
                                        </div>
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
                                    colspan="5"
                                    class="text-center text-secondary py-5"
                                >
                                    No existen alumnos para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(
                ! $targetIsClosed
                && $rows->isNotEmpty()
            )
                <div class="card-footer">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">
                                Grupo destino
                            </label>

                            <select
                                name="target_group_id"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    Selecciona grupo
                                </option>

                                @foreach($targetGroups as $group)
                                    <option
                                        value="{{ $group->id }}"
                                        @selected(
                                            (string) $targetGroupId
                                            === (string) $group->id
                                        )
                                    >
                                        {{ $group->level_name }}
                                        ·
                                        {{ $group->name }}
                                        ·
                                        {{ $group->campus_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">
                                Tipo
                            </label>

                            <select
                                name="enrollment_type"
                                class="form-select"
                                required
                            >
                                <option value="reenrollment">
                                    Reinscripción
                                </option>

                                <option value="promotion">
                                    Promoción
                                </option>

                                <option value="repeat">
                                    Repetición
                                </option>

                                <option value="transfer">
                                    Transferencia
                                </option>

                                <option value="new">
                                    Nuevo ingreso
                                </option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">
                                Fecha efectiva
                            </label>

                            <input
                                type="date"
                                name="effective_on"
                                value="{{ $targetCycle->starts_on }}"
                                class="form-control"
                                required
                            >
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">
                                Motivo
                            </label>

                            <input
                                type="text"
                                name="reason"
                                class="form-control"
                                maxlength="255"
                                placeholder="Opcional"
                            >
                        </div>

                        <div class="col-md-2">
                            <button class="btn btn-primary w-100">
                                <i class="ti ti-users-plus me-1"></i>
                                Aplicar selección
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    Distribución actual del ciclo
                </h3>

                <p class="card-subtitle">
                    Cantidad de inscripciones activas por grupo.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Nivel</th>
                        <th>Plantel</th>
                        <th>Estado</th>
                        <th>Alumnos</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($groupSummary as $group)
                        <tr>
                            <td class="fw-bold">
                                {{ $group->name }}
                            </td>

                            <td>
                                {{ $group->level_name ?? '—' }}
                            </td>

                            <td>
                                {{ $group->campus_name ?? '—' }}
                            </td>

                            <td>
                                <span class="badge bg-{{
                                    $group->status === 'active'
                                        ? 'success'
                                        : 'secondary'
                                }}-lt">
                                    {{ ucfirst($group->status) }}
                                </span>
                            </td>

                            <td>
                                <span class="badge bg-blue-lt">
                                    {{ number_format(
                                        $group->students_count
                                    ) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="5"
                                class="text-center text-secondary py-5"
                            >
                                El ciclo todavía no tiene grupos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function () {
                const master =
                    document.getElementById(
                        'master-checkbox'
                    );

                const selectAll =
                    document.getElementById(
                        'select-all'
                    );

                const clearAll =
                    document.getElementById(
                        'clear-all'
                    );

                const checkboxes =
                    Array.from(
                        document.querySelectorAll(
                            '.student-checkbox'
                        )
                    );

                const setAll = function (
                    checked
                ) {
                    checkboxes.forEach(
                        function (checkbox) {
                            if (! checkbox.disabled) {
                                checkbox.checked =
                                    checked;
                            }
                        }
                    );

                    if (master) {
                        master.checked = checked;
                    }
                };

                if (master) {
                    master.addEventListener(
                        'change',
                        function () {
                            setAll(master.checked);
                        }
                    );
                }

                if (selectAll) {
                    selectAll.addEventListener(
                        'click',
                        function () {
                            setAll(true);
                        }
                    );
                }

                if (clearAll) {
                    clearAll.addEventListener(
                        'click',
                        function () {
                            setAll(false);
                        }
                    );
                }

                const form =
                    document.getElementById(
                        'batch-enrollment-form'
                    );

                if (form) {
                    form.addEventListener(
                        'submit',
                        function (event) {
                            const selected =
                                checkboxes.filter(
                                    function (checkbox) {
                                        return checkbox.checked;
                                    }
                                );

                            if (selected.length < 1) {
                                event.preventDefault();

                                alert(
                                    'Selecciona al menos un alumno.'
                                );

                                return;
                            }

                            if (! confirm(
                                '¿Aplicar la asignación a '
                                + selected.length
                                + ' alumno(s)?'
                            )) {
                                event.preventDefault();
                            }
                        }
                    );
                }
            }
        );
    </script>
@endpush