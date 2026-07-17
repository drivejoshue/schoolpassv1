@extends('layouts.app')

@section('title', 'Reporte individual | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Reporte individual del alumno')

@section('topbar-actions')
    <a
        href="{{ route(
            'admin.reports.monthly-attendance.index'
        ) }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-calendar-month me-1"></i>
        Asistencia mensual
    </a>

    @if($report)
        <a
            href="{{ route(
                'admin.reports.student-individual.pdf',
                $filters
            ) }}"
            class="btn btn-danger btn-sm"
        >
            <i class="ti ti-file-type-pdf me-1"></i>
            Descargar PDF
        </a>
    @endif
@endsection

@section('content')
    @php
        $statusLabels = [
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Extemporáneo',
            'absent' => 'Ausente',
            'no_class' => 'Sin clase',
        ];

        $statusBadges = [
            'on_time' => 'success',
            'late' => 'warning',
            'very_late' => 'orange',
            'absent' => 'danger',
            'no_class' => 'secondary',
        ];

        $eventLabels = [
            'entry' => 'Entrada',
            'exit' => 'Salida',
            'access' => 'Acceso',
        ];

        $accessStatusLabels = [
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Extemporáneo',
            'normal_exit' => 'Salida normal',
            'early_exit' => 'Salida anticipada',
            'allowed' => 'Autorizado',
            'denied' => 'Denegado',
            'duplicate' => 'Duplicado',
        ];

        $formatTime = function ($value) {
            return $value
                ? \Illuminate\Support\Carbon::parse(
                    $value
                )->format('H:i')
                : '—';
        };
    @endphp

    <div class="card mb-3">
        <form
            method="GET"
            action="{{ route(
                'admin.reports.student-individual.index'
            ) }}"
        >
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Alumno y periodo
                    </h3>

                    <p class="card-subtitle">
                        Consulta asistencia, puntualidad y accesos.
                    </p>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">
                            Alumno
                        </label>

                        <select
                            name="student_id"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Selecciona un alumno
                            </option>

                            @foreach($students as $student)
                                <option
                                    value="{{ $student->id }}"
                                    @selected(
                                        (string) $filters['student_id']
                                        === (string) $student->id
                                    )
                                >
                                    {{ $student->last_name }}
                                    {{ $student->first_name }}
                                    · {{ $student->student_code }}
                                    · {{ $student->group_name ?? 'Sin grupo' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

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

    @if(! $report)
        <div class="empty">
            <div class="empty-img">
                <i
                    class="ti ti-user-search"
                    style="font-size: 72px;"
                ></i>
            </div>

            <p class="empty-title">
                Selecciona un alumno
            </p>

            <p class="empty-subtitle text-secondary">
                El reporte mostrará asistencia, gráfica y actividad.
            </p>
        </div>
    @else
        @if(! $report['has_active_cycle'])
            <div class="alert alert-warning">
                <i class="ti ti-calendar-off me-2"></i>

                <strong>No hay un ciclo escolar activo.</strong>

                El sistema no está infiriendo ausencias,
                retardos ni puntualidad para este alumno.

                Los accesos reales registrados en el periodo
                sí se muestran.
            </div>
        @elseif(! $report['has_effective_range'])
            <div class="alert alert-warning">
                <i class="ti ti-calendar-x me-2"></i>

                <strong>
                    El rango seleccionado está fuera del ciclo activo.
                </strong>

                No se calcularon ausencias ni puntualidad.
                Los accesos reales sí se conservan.
            </div>
        @else
            <div class="alert alert-info">
                <i class="ti ti-calendar-check me-2"></i>

                Ciclo activo:

                <strong>
                    {{ $report['active_cycle']->name
                        ?? 'Ciclo escolar'
                    }}
                </strong>

                · Periodo efectivo del reporte:

                <strong>
                    {{ $report['effective_from']->format('d/m/Y') }}
                    al
                    {{ $report['effective_to']->format('d/m/Y') }}
                </strong>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    @if($report['student']->photo_url)
                        <span
                            class="avatar avatar-xl me-3"
                            style="background-image: url('{{
                                asset(
                                    $report['student']->photo_url
                                )
                            }}')"
                        ></span>
                    @else
                        <span class="avatar avatar-xl bg-blue-lt me-3">
                            {{ strtoupper(
                                mb_substr(
                                    $report['student']->first_name,
                                    0,
                                    1
                                )
                            ) }}
                        </span>
                    @endif

                    <div class="flex-fill">
                        <h2 class="mb-1">
                            {{ $report['student']->first_name }}
                            {{ $report['student']->last_name }}
                        </h2>

                        <div class="text-secondary">
                            Matrícula:

                            <strong>
                                {{ $report['student']->student_code }}
                            </strong>
                        </div>

                        <div class="text-secondary">
                            {{ $report['student']->campus_name
                                ?? 'Sin plantel'
                            }}

                            ·

                            {{ $report['student']->level_name
                                ?? 'Sin nivel'
                            }}

                            ·

                            {{ $report['student']->group_name
                                ?? 'Sin grupo'
                            }}
                        </div>
                    </div>

                    <a
                        href="{{ route(
                            'admin.reports.student-individual.pdf',
                            $filters
                        ) }}"
                        class="btn btn-danger"
                    >
                        <i class="ti ti-file-type-pdf me-1"></i>
                        PDF individual
                    </a>
                </div>
            </div>
        </div>

        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-xl-2">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary">
                            Puntuales
                        </div>

                        <div class="h2 mb-0 text-success">
                            {{ $report['summary']['on_time'] }}
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
                            {{ $report['summary']['late'] }}
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
                            {{ $report['summary']['very_late'] }}
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
                            {{ $report['summary']['absent'] }}
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
                                $report['summary']['punctuality_rate'],
                                1
                            ) }}%
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-2">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary">
                            Incidencias
                        </div>

                        <div class="h2 mb-0">
                            {{ $report['summary']['incidents'] }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards mb-3">
            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">
                                Evolución semanal
                            </h3>

                            <p class="card-subtitle">
                                Puntualidad, retardos y ausencias
                                dentro del ciclo activo.
                            </p>
                        </div>
                    </div>

                    <div class="card-body">
                        @if(
                            $report['has_effective_range']
                            && count($report['daily_chart']) > 0
                        )
                            <div style="height: 340px;">
                                <canvas
                                    id="studentAttendanceChart"
                                ></canvas>
                            </div>
                        @else
                            <div class="empty py-5">
                                <p class="empty-title">
                                    Sin datos de asistencia
                                </p>

                                <p class="empty-subtitle text-secondary">
                                    No hay ciclo activo o el periodo
                                    consultado está fuera de su vigencia.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title">
                            Tutores vinculados
                        </h3>
                    </div>

                    <div class="list-group list-group-flush">
                        @forelse(
                            $report['guardians']
                            as $guardian
                        )
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fw-bold">
                                            {{ $guardian->first_name }}
                                            {{ $guardian->last_name }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ ucfirst(
                                                $guardian->relationship
                                            ) }}

                                            @if($guardian->is_primary)
                                                · Principal
                                            @endif
                                        </div>
                                    </div>

                                    @if($guardian->is_primary)
                                        <span class="badge bg-blue-lt">
                                            Principal
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-2 small">
                                    <div>
                                        <i class="ti ti-phone me-1"></i>

                                        {{ $guardian->phone
                                            ?: 'Sin teléfono'
                                        }}
                                    </div>

                                    <div>
                                        <i class="ti ti-mail me-1"></i>

                                        {{ $guardian->email
                                            ?: 'Sin correo'
                                        }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="list-group-item text-secondary">
                                Sin tutores vinculados.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Historial de asistencia
                    </h3>

                    <p class="card-subtitle">
                        Solo incluye fechas válidas dentro
                        del ciclo activo.
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Día</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Retardo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse(
                            $report['attendance']
                                ->sortByDesc('date')
                            as $row
                        )
                            <tr>
                                <td>
                                    {{ \Illuminate\Support\Carbon::parse(
                                        $row->date
                                    )->format('d/m/Y') }}
                                </td>

                                <td>
                                    {{ $row->weekday }}
                                </td>

                                <td>
                                    {{ $formatTime(
                                        $row->entry_time
                                    ) }}
                                </td>

                                <td>
                                    {{ $formatTime(
                                        $row->exit_time
                                    ) }}
                                </td>

                                <td>
                                    {{ $row->minutes_late > 0
                                        ? $row->minutes_late.' min'
                                        : '—'
                                    }}
                                </td>

                                <td>
                                    <span class="badge bg-{{
                                        $statusBadges[
                                            $row->final_status
                                        ] ?? 'secondary'
                                    }}-lt">
                                        {{ $statusLabels[
                                            $row->final_status
                                        ] ?? $row->final_status
                                        }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="6"
                                    class="text-center text-secondary py-5"
                                >
                                    No hay fechas de asistencia válidas
                                    dentro del ciclo activo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Actividad de acceso
                    </h3>

                    <p class="card-subtitle">
                        Entradas, salidas y accesos reales registrados
                        en el rango solicitado.
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Fecha/hora</th>
                            <th>Evento</th>
                            <th>Estado</th>
                            <th>Área</th>
                            <th>Dispositivo</th>
                            <th>Razón</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse(
                            $report['access_logs']
                            as $log
                        )
                            <tr>
                                <td>
                                    {{ \Illuminate\Support\Carbon::parse(
                                        $log->scanned_at
                                    )->format('d/m/Y H:i:s') }}
                                </td>

                                <td>
                                    {{ $eventLabels[
                                        $log->event_type
                                    ] ?? $log->event_type
                                    }}
                                </td>

                                <td>
                                    {{ $accessStatusLabels[
                                        $log->event_status
                                    ] ?? $log->event_status
                                    }}
                                </td>

                                <td>
                                    {{ $log->area_name ?? '—' }}
                                </td>

                                <td>
                                    {{ $log->device_name ?? '—' }}
                                </td>

                                <td>
                                    {{ $log->reason ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="6"
                                    class="text-center text-secondary py-5"
                                >
                                    Sin eventos de acceso en el periodo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@if(
    $report
    && $report['has_effective_range']
    && count($report['daily_chart']) > 0
)
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js"></script>

        <script>
            document.addEventListener(
                'DOMContentLoaded',
                function () {
                    const rows = @json(
                        $report['daily_chart']
                    );

                    new Chart(
                        document.getElementById(
                            'studentAttendanceChart'
                        ),
                        {
                            type: 'bar',
                            data: {
                                labels: rows.map(
                                    row => row.label
                                ),
                                datasets: [
                                    {
                                        label: 'Puntuales',
                                        data: rows.map(
                                            row => row.on_time
                                        ),
                                        backgroundColor: '#16a34a'
                                    },
                                    {
                                        label: 'Retardos',
                                        data: rows.map(
                                            row => row.late
                                        ),
                                        backgroundColor: '#f59e0b'
                                    },
                                    {
                                        label: 'Extemporáneos',
                                        data: rows.map(
                                            row => row.very_late
                                        ),
                                        backgroundColor: '#ea580c'
                                    },
                                    {
                                        label: 'Ausencias',
                                        data: rows.map(
                                            row => row.absent
                                        ),
                                        backgroundColor: '#dc2626'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: {
                                    mode: 'index',
                                    intersect: false
                                },
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            precision: 0
                                        }
                                    }
                                }
                            }
                        }
                    );
                }
            );
        </script>
    @endpush
@endif