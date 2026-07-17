@extends('layouts.app')

@section('title', 'Incidencias por alumno | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Incidencias por alumno')

@section('topbar-actions')
    <a
        href="{{ route(
            'admin.reports.student-individual.index'
        ) }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-user-stats me-1"></i>
        Reporte individual
    </a>

    <a
        href="{{ route(
            'admin.reports.student-incidents.excel',
            $filters
        ) }}"
        class="btn btn-success btn-sm"
    >
        <i class="ti ti-file-spreadsheet me-1"></i>
        Excel
    </a>

    <a
        href="{{ route(
            'admin.reports.student-incidents.pdf',
            $filters
        ) }}"
        class="btn btn-danger btn-sm"
    >
        <i class="ti ti-file-type-pdf me-1"></i>
        PDF
    </a>
@endsection

@section('content')
    @php
        $incidentTypes = [
            'repeated_late' =>
                'Retardos recurrentes',

            'repeated_very_late' =>
                'Extemporáneos recurrentes',

            'consecutive_absence' =>
                'Ausencias consecutivas',

            'early_exit' =>
                'Salidas anticipadas',

            'denied_access' =>
                'Accesos denegados',

            'low_punctuality' =>
                'Baja puntualidad',
        ];

        $riskLabels = [
            'high' => 'Alto',
            'medium' => 'Medio',
            'low' => 'Bajo',
        ];

        $riskBadges = [
            'high' => 'danger',
            'medium' => 'warning',
            'low' => 'blue',
        ];
    @endphp

    @if(! $hasActiveCycle)
        <div class="alert alert-warning">
            <i class="ti ti-calendar-off me-2"></i>

            <strong>No hay un ciclo escolar activo.</strong>

            No se generarán incidencias automáticas por
            ausencias, retardos o baja puntualidad.

            Los accesos denegados y las salidas anticipadas
            reales sí pueden aparecer.
        </div>
    @elseif(! $hasEffectiveRange)
        <div class="alert alert-warning">
            <i class="ti ti-calendar-x me-2"></i>

            <strong>
                El rango seleccionado está fuera del ciclo activo.
            </strong>

            No se calcularon faltas, retardos ni puntualidad.
            Los eventos reales de acceso sí se conservaron.
        </div>
    @else
        <div class="alert alert-info">
            <i class="ti ti-calendar-check me-2"></i>

            Ciclo activo:

            <strong>
                {{ $activeCycle->name
                    ?? 'Ciclo escolar'
                }}
            </strong>

            · Periodo efectivo de asistencia:

            <strong>
                {{ $effectiveFrom->format('d/m/Y') }}
                al
                {{ $effectiveTo->format('d/m/Y') }}
            </strong>
        </div>
    @endif

    <div class="card mb-3">
        <form
            method="GET"
            action="{{ route(
                'admin.reports.student-incidents.index'
            ) }}"
        >
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Filtros de incidencias
                    </h3>

                    <p class="card-subtitle">
                        Detecta alumnos que requieren seguimiento.
                    </p>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">
                            Desde
                        </label>

                        <input
                            type="date"
                            name="from"
                            value="{{ $filters['from'] }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">
                            Hasta
                        </label>

                        <input
                            type="date"
                            name="to"
                            value="{{ $filters['to'] }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">
                            Grupo
                        </label>

                        <select
                            name="group_id"
                            class="form-select"
                        >
                            <option value="">
                                Todos los grupos
                            </option>

                            @foreach($groups as $group)
                                <option
                                    value="{{ $group->id }}"
                                    @selected(
                                        (string) $filters['group_id']
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

                    <div class="col-md-3">
                        <label class="form-label">
                            Tipo de incidencia
                        </label>

                        <select
                            name="type"
                            class="form-select"
                        >
                            <option value="">
                                Todas
                            </option>

                            @foreach(
                                $incidentTypes
                                as $value => $label
                            )
                                <option
                                    value="{{ $value }}"
                                    @selected(
                                        $filters['type']
                                        === $value
                                    )
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">
                            Riesgo
                        </label>

                        <select
                            name="risk"
                            class="form-select"
                        >
                            <option value="">
                                Todos
                            </option>

                            @foreach(
                                $riskLabels
                                as $value => $label
                            )
                                <option
                                    value="{{ $value }}"
                                    @selected(
                                        $filters['risk']
                                        === $value
                                    )
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a
                    href="{{ route(
                        'admin.reports.student-incidents.index'
                    ) }}"
                    class="btn btn-outline-secondary"
                >
                    Limpiar
                </a>

                <div class="btn-list">
                    <a
                        href="{{ route(
                            'admin.reports.student-incidents.excel',
                            $filters
                        ) }}"
                        class="btn btn-success"
                    >
                        <i class="ti ti-file-spreadsheet me-1"></i>
                        Excel
                    </a>

                    <a
                        href="{{ route(
                            'admin.reports.student-incidents.pdf',
                            $filters
                        ) }}"
                        class="btn btn-danger"
                    >
                        <i class="ti ti-file-type-pdf me-1"></i>
                        PDF
                    </a>

                    <button class="btn btn-primary">
                        <i class="ti ti-filter me-1"></i>
                        Analizar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Incidencias detectadas
                    </div>

                    <div class="h1 mb-0">
                        {{ number_format(
                            $summary['total']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Alumnos involucrados
                    </div>

                    <div class="h1 mb-0">
                        {{ number_format(
                            $summary['students']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Riesgo alto
                    </div>

                    <div class="h1 mb-0 text-danger">
                        {{ number_format(
                            $summary['high']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Riesgo medio
                    </div>

                    <div class="h1 mb-0 text-warning">
                        {{ number_format(
                            $summary['medium']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Riesgo bajo
                    </div>

                    <div class="h1 mb-0 text-blue">
                        {{ number_format(
                            $summary['low']
                        ) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($topStudents->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Alumnos con mayor seguimiento requerido
                    </h3>

                    <p class="card-subtitle">
                        Ordenados por riesgo e incidencias detectadas.
                    </p>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    @foreach($topStudents as $row)
                        <div class="col-md-6 col-xl-4">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fw-bold">
                                            {{ $row['full_name'] }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ $row['student_code'] }}
                                            ·
                                            {{ $row['group_name'] }}
                                        </div>
                                    </div>

                                    <span class="badge bg-danger-lt">
                                        {{ $row['incidents_count'] }}
                                    </span>
                                </div>

                                <div class="mt-2 small">
                                    Incidencias de riesgo alto:

                                    <strong>
                                        {{ $row['high_count'] }}
                                    </strong>
                                </div>

                                <a
                                    href="{{ route(
                                        'admin.reports.student-individual.index',
                                        [
                                            'student_id' =>
                                                $row['student_id'],

                                            'from' =>
                                                $filters['from'],

                                            'to' =>
                                                $filters['to'],
                                        ]
                                    ) }}"
                                    class="btn btn-sm btn-outline-primary mt-3"
                                >
                                    Ver reporte individual
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    Resultados detectados
                </h3>

                <p class="card-subtitle">
                    Las incidencias de asistencia se generan
                    únicamente dentro del ciclo activo.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Grupo</th>
                        <th>Incidencia</th>
                        <th>Cantidad</th>
                        <th>Última fecha</th>
                        <th>Riesgo</th>
                        <th>Puntualidad</th>
                        <th>Acción sugerida</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($incidents as $incident)
                        <tr>
                            <td>
                                <div class="fw-bold">
                                    {{ $incident['full_name'] }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $incident['student_code'] }}
                                </div>
                            </td>

                            <td>
                                <div>
                                    {{ $incident['group_name'] }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $incident['level_name'] }}
                                </div>
                            </td>

                            <td>
                                {{ $incident['type_label'] }}
                            </td>

                            <td class="fw-bold">
                                {{ $incident['quantity'] }}
                            </td>

                            <td>
                                @if($incident['last_date'])
                                    {{ \Illuminate\Support\Carbon::parse(
                                        $incident['last_date']
                                    )->format('d/m/Y') }}
                                @else
                                    —
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-{{
                                    $riskBadges[
                                        $incident['risk']
                                    ] ?? 'secondary'
                                }}-lt">
                                    {{ $incident['risk_label'] }}
                                </span>
                            </td>

                            <td>
                                {{ number_format(
                                    $incident['punctuality_rate'],
                                    1
                                ) }}%
                            </td>

                            <td style="min-width: 280px;">
                                {{ $incident['suggested_action'] }}
                            </td>

                            <td>
                                <a
                                    href="{{ route(
                                        'admin.reports.student-individual.index',
                                        [
                                            'student_id' =>
                                                $incident['student_id'],

                                            'from' =>
                                                $filters['from'],

                                            'to' =>
                                                $filters['to'],
                                        ]
                                    ) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Detalle
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="9"
                                class="text-center text-secondary py-5"
                            >
                                No se detectaron incidencias
                                con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection