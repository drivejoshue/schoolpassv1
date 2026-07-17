@extends('layouts.app')

@section('title', 'Asistencia mensual | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Asistencia mensual')

@section('topbar-actions')
    <a
        href="{{ route('admin.reports.analytics.index') }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-chart-bar me-1"></i>
        Analítica
    </a>

    <a
        href="{{ route(
            'admin.reports.monthly-attendance.excel',
            $filters
        ) }}"
        class="btn btn-success btn-sm"
    >
        <i class="ti ti-file-spreadsheet me-1"></i>
        Excel
    </a>

    <a
        href="{{ route(
            'admin.reports.monthly-attendance.pdf',
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
    $monthNames = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    $statusClasses = [
        'P' => 'status-p',
        'R' => 'status-r',
        'E' => 'status-e',
        'A' => 'status-a',
        'SC' => 'status-sc',
        '—' => 'status-outside',
    ];

    /*
     * Compatibilidad con controladores anteriores.
     *
     * No asumimos que cycleHasStarted ni periodIsInsideCycle
     * hayan sido enviados explícitamente por el controlador.
     */
    $activeCycle = $activeCycle ?? null;

    $hasActiveCycle = isset($hasActiveCycle)
        ? (bool) $hasActiveCycle
        : $activeCycle !== null;

    $selectedMonth = (int) (
        $filters['month']
        ?? now()->month
    );

    $selectedYear = (int) (
        $filters['year']
        ?? now()->year
    );

    $selectedPeriodStart = \Illuminate\Support\Carbon::create(
        $selectedYear,
        $selectedMonth,
        1
    )->startOfDay();

    $selectedPeriodEnd = $selectedPeriodStart
        ->copy()
        ->endOfMonth()
        ->endOfDay();

    $cycleStart = $activeCycle
        ? \Illuminate\Support\Carbon::parse(
            $activeCycle->starts_on
        )->startOfDay()
        : null;

    $cycleEnd = $activeCycle
        ? \Illuminate\Support\Carbon::parse(
            $activeCycle->ends_on
        )->endOfDay()
        : null;

    /*
     * Indica si el ciclo ya comenzó respecto a la fecha actual.
     */
    $cycleHasStarted = isset($cycleHasStarted)
        ? (bool) $cycleHasStarted
        : (
            $hasActiveCycle
            && $cycleStart
            && now()->endOfDay()->greaterThanOrEqualTo(
                $cycleStart
            )
        );

    /*
     * Un periodo pertenece al ciclo cuando existe intersección
     * entre el mes consultado y las fechas del ciclo.
     *
     * Ejemplo:
     * ciclo 01/09/2027–30/06/2028
     * consulta julio 2026
     * resultado: fuera del ciclo.
     */
    $periodIsInsideCycle = isset(
        $periodIsInsideCycle
    )
        ? (bool) $periodIsInsideCycle
        : (
            $hasActiveCycle
            && $cycleStart
            && $cycleEnd
            && $selectedPeriodStart->lessThanOrEqualTo(
                $cycleEnd
            )
            && $selectedPeriodEnd->greaterThanOrEqualTo(
                $cycleStart
            )
        );

    $cycleHasEnded = isset($cycleHasEnded)
        ? (bool) $cycleHasEnded
        : (
            $hasActiveCycle
            && $cycleEnd
            && now()->startOfDay()->greaterThan(
                $cycleEnd
            )
        );
@endphp

    <style>
        .monthly-table-wrapper {
            max-height: 680px;
            overflow: auto;
        }

        .monthly-attendance-table {
            white-space: nowrap;
            border-collapse: separate;
            border-spacing: 0;
        }

        .monthly-attendance-table th,
        .monthly-attendance-table td {
            min-width: 42px;
            text-align: center;
            vertical-align: middle;
            border-right: 1px solid var(--tblr-border-color);
            border-bottom: 1px solid var(--tblr-border-color);
        }

        .monthly-attendance-table .student-column {
            min-width: 240px;
            text-align: left;
        }

        .monthly-attendance-table .code-column {
            min-width: 110px;
            text-align: left;
        }

        .monthly-attendance-table .group-column {
            min-width: 140px;
            text-align: left;
        }

        .monthly-attendance-table thead th {
            position: sticky;
            top: 0;
            z-index: 4;
            background: var(--tblr-bg-surface);
        }

        .monthly-attendance-table .sticky-code {
            position: sticky;
            left: 0;
            z-index: 3;
            background: var(--tblr-bg-surface);
        }

        .monthly-attendance-table .sticky-student {
            position: sticky;
            left: 110px;
            z-index: 3;
            background: var(--tblr-bg-surface);
        }

        .monthly-attendance-table thead .sticky-code,
        .monthly-attendance-table thead .sticky-student {
            z-index: 6;
        }

        .status-cell {
            font-weight: 700;
        }

        .status-p {
            color: #166534;
            background: #dcfce7 !important;
        }

        .status-r {
            color: #92400e;
            background: #fef3c7 !important;
        }

        .status-e {
            color: #9a3412;
            background: #ffedd5 !important;
        }

        .status-a {
            color: #991b1b;
            background: #fee2e2 !important;
        }

        .status-sc {
            color: #475569;
            background: #e2e8f0 !important;
        }

        .status-outside {
            color: #94a3b8;
            background: #f8fafc !important;
        }

        .non-class-header {
            background: #e2e8f0 !important;
        }

        .outside-cycle-header {
            color: #94a3b8 !important;
            background: #f8fafc !important;
        }

        .future-header {
            color: #94a3b8 !important;
            background: #f8fafc !important;
        }
    </style>

  @if(! $hasActiveCycle)
    <div class="alert alert-warning">
        <i class="ti ti-calendar-off me-2"></i>

        <strong>
            No hay un ciclo escolar activo configurado.
        </strong>

        No se inferirán ausencias, retardos ni puntualidad.
        Los accesos reales registrados permanecen disponibles.
    </div>
@elseif(! $periodIsInsideCycle)
    <div class="alert alert-warning">
        <i class="ti ti-calendar-exclamation me-2"></i>

        <strong>
            Existe un ciclo activo:
            {{ $activeCycle->name }}.
        </strong>

        El periodo consultado,

        <strong>
            {{ $monthNames[$selectedMonth] ?? $selectedMonth }}
            {{ $selectedYear }}
        </strong>,

        está fuera de su vigencia:

        <strong>
            {{ $cycleStart->format('d/m/Y') }}
            al
            {{ $cycleEnd->format('d/m/Y') }}
        </strong>.

        No se calcularán ausencias ni puntualidad para este mes.
    </div>
@elseif(! $cycleHasStarted)
    <div class="alert alert-info">
        <i class="ti ti-calendar-time me-2"></i>

        <strong>
            Ciclo activo programado:
            {{ $activeCycle->name }}.
        </strong>

        Su vigencia comienza el

        <strong>
            {{ $cycleStart->format('d/m/Y') }}
        </strong>.

        Todavía no se generan ausencias oficiales.
    </div>
@elseif($cycleHasEnded)
    <div class="alert alert-secondary">
        <i class="ti ti-calendar-check me-2"></i>

        <strong>
            El periodo consultado pertenece al ciclo
            {{ $activeCycle->name }}.
        </strong>

        El ciclo ya concluyó el

        <strong>
            {{ $cycleEnd->format('d/m/Y') }}
        </strong>.

        Se muestran sus datos históricos.
    </div>
@else
    <div class="alert alert-success">
        <i class="ti ti-calendar-check me-2"></i>

        Ciclo activo:

        <strong>
            {{ $activeCycle->name }}
        </strong>

        · Vigencia:

        <strong>
            {{ $cycleStart->format('d/m/Y') }}
            al
            {{ $cycleEnd->format('d/m/Y') }}
        </strong>.
    </div>
@endif

    <div class="card mb-3">
        <form
            method="GET"
            action="{{ route(
                'admin.reports.monthly-attendance.index'
            ) }}"
        >
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Periodo y grupo
                    </h3>

                    <p class="card-subtitle">
                        Consulta la asistencia consolidada de cada alumno.
                    </p>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">
                            Mes
                        </label>

                        <select
                            name="month"
                            class="form-select"
                        >
                            @foreach(
                                $monthNames as $number => $name
                            )
                                <option
                                    value="{{ $number }}"
                                    @selected(
                                        (int) $filters['month']
                                        === $number
                                    )
                                >
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">
                            Año
                        </label>

                        <select
                            name="year"
                            class="form-select"
                        >
                            @for(
                                $year = now()->year + 1;
                                $year >= now()->year - 5;
                                $year--
                            )
                                <option
                                    value="{{ $year }}"
                                    @selected(
                                        (int) $filters['year']
                                        === $year
                                    )
                                >
                                    {{ $year }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">
                            Plantel
                        </label>

                        <select
                            name="campus_id"
                            class="form-select"
                        >
                            <option value="">
                                Todos los planteles
                            </option>

                            @foreach($campuses as $campus)
                                <option
                                    value="{{ $campus->id }}"
                                    @selected(
                                        (string) $filters['campus_id']
                                        === (string) $campus->id
                                    )
                                >
                                    {{ $campus->name }}
                                </option>
                            @endforeach
                        </select>
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

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100">
                            <i class="ti ti-filter me-1"></i>
                            Consultar
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a
                    href="{{ route(
                        'admin.reports.monthly-attendance.index'
                    ) }}"
                    class="btn btn-outline-secondary"
                >
                    Limpiar
                </a>

                <div class="btn-list">
                    <a
                        href="{{ route(
                            'admin.reports.monthly-attendance.excel',
                            $filters
                        ) }}"
                        class="btn btn-success"
                    >
                        <i class="ti ti-file-spreadsheet me-1"></i>
                        Descargar Excel
                    </a>

                    <a
                        href="{{ route(
                            'admin.reports.monthly-attendance.pdf',
                            $filters
                        ) }}"
                        class="btn btn-danger"
                    >
                        <i class="ti ti-file-type-pdf me-1"></i>
                        Descargar PDF
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Alumnos
                    </div>

                    <div class="h2 mb-0">
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
                        Puntuales
                    </div>

                    <div class="h2 mb-0 text-success">
                        {{ number_format(
                            $summary['P']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Retardos
                    </div>

                    <div class="h2 mb-0 text-warning">
                        {{ number_format(
                            $summary['R']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Extemporáneos
                    </div>

                    <div class="h2 mb-0 text-orange">
                        {{ number_format(
                            $summary['E']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Ausencias
                    </div>

                    <div class="h2 mb-0 text-danger">
                        {{ number_format(
                            $summary['A']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Puntualidad
                    </div>

                    <div class="h2 mb-0">
                        {{ number_format(
                            $summary['punctuality_rate'],
                            1
                        ) }}%
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <strong>P</strong> = puntual ·
        <strong>R</strong> = retardo ·
        <strong>E</strong> = extemporáneo ·
        <strong>A</strong> = ausente ·
        <strong>SC</strong> = sin clase ·
        <strong>—</strong> = fuera del ciclo activo o fecha futura
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    {{ $month_name }}
                    {{ $filters['year'] }}
                </h3>

                <p class="card-subtitle">
                    Lista mensual por alumno y día.
                </p>
            </div>
        </div>

        <div class="monthly-table-wrapper">
            <table
                class="table table-sm table-vcenter monthly-attendance-table mb-0"
            >
                <thead>
                    <tr>
                        <th class="code-column sticky-code">
                            Matrícula
                        </th>

                        <th class="student-column sticky-student">
                            Alumno
                        </th>

                        <th class="group-column">
                            Grupo
                        </th>

                        @foreach($days as $day)
                            @php
                                $headerClass = '';

                                if (! $day['is_inside_cycle']) {
                                    $headerClass =
                                        'outside-cycle-header';
                                } elseif ($day['is_future']) {
                                    $headerClass =
                                        'future-header';
                                } elseif (! $day['is_class_day']) {
                                    $headerClass =
                                        'non-class-header';
                                }

                                $headerTitle = match (true) {
                                    ! $day['is_inside_cycle'] =>
                                        'Fuera del ciclo activo',

                                    $day['is_future'] =>
                                        'Fecha futura',

                                    ! $day['is_class_day'] =>
                                        $day['calendar_title']
                                        ?? 'Día sin clase',

                                    default =>
                                        $day['calendar_title']
                                        ?? $day['date'],
                                };
                            @endphp

                            <th
                                title="{{ $headerTitle }}"
                                class="{{ $headerClass }}"
                            >
                                <div>
                                    {{ str_pad(
                                        (string) $day['day'],
                                        2,
                                        '0',
                                        STR_PAD_LEFT
                                    ) }}
                                </div>

                                <div class="small text-secondary">
                                    {{ strtoupper(
                                        $day['weekday']
                                    ) }}
                                </div>
                            </th>
                        @endforeach

                        <th class="bg-success-lt">
                            P
                        </th>

                        <th class="bg-warning-lt">
                            R
                        </th>

                        <th class="bg-orange-lt">
                            E
                        </th>

                        <th class="bg-danger-lt">
                            A
                        </th>

                        <th class="bg-secondary-lt">
                            SC
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td class="code-column sticky-code">
                                {{ $student['student_code'] }}
                            </td>

                            <td class="student-column sticky-student">
                                <div class="fw-bold">
                                    {{ $student['full_name'] }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $student['campus_name'] }}
                                </div>
                            </td>

                            <td class="group-column">
                                <div>
                                    {{ $student['group_name'] }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $student['level_name'] }}
                                </div>
                            </td>

                            @foreach($days as $day)
                                @php
                                    $status = $student['days'][
                                        $day['date']
                                    ] ?? '—';

                                    $cellTitle = match ($status) {
                                        'P' => 'Puntual',
                                        'R' => 'Retardo',
                                        'E' => 'Extemporáneo',
                                        'A' => 'Ausente',
                                        'SC' => (
                                            $day['calendar_title']
                                            ?? 'Sin clase'
                                        ),
                                        default => (
                                            ! $day['is_inside_cycle']
                                                ? 'Fuera del ciclo activo'
                                                : (
                                                    $day['is_future']
                                                        ? 'Fecha futura'
                                                        : 'Sin clasificación'
                                                )
                                        ),
                                    };
                                @endphp

                                <td
                                    class="status-cell {{
                                        $statusClasses[$status]
                                        ?? ''
                                    }}"
                                    title="{{
                                        $day['date']
                                        .' · '
                                        .$cellTitle
                                    }}"
                                >
                                    {{ $status }}
                                </td>
                            @endforeach

                            <td class="fw-bold text-success">
                                {{ $student['totals']['P'] }}
                            </td>

                            <td class="fw-bold text-warning">
                                {{ $student['totals']['R'] }}
                            </td>

                            <td class="fw-bold text-orange">
                                {{ $student['totals']['E'] }}
                            </td>

                            <td class="fw-bold text-danger">
                                {{ $student['totals']['A'] }}
                            </td>

                            <td class="fw-bold text-secondary">
                                {{ $student['totals']['SC'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="{{ count($days) + 8 }}"
                                class="text-center text-secondary py-5"
                            >
                                No hay alumnos con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection