@extends('layouts.app')

@section('title', 'Analítica | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Analítica de asistencia y accesos')

@section('topbar-actions')
    <a
        href="{{ route('admin.reports.exports.index') }}"
        class="btn btn-outline-success btn-sm"
    >
        <i class="ti ti-file-spreadsheet me-1"></i>
        Exportaciones Excel
    </a>

    <a
        href="{{ route('admin.reports.analytics.pdf', request()->query()) }}"
        class="btn btn-danger btn-sm"
    >
        <i class="ti ti-file-type-pdf me-1"></i>
        Exportar PDF
    </a>

    <a
    href="{{
        route(
            'admin.reports.monthly-attendance.index'
        )
    }}"
    class="btn btn-outline-primary btn-sm"
>
    <i class="ti ti-calendar-month me-1"></i>
    Asistencia mensual
</a>
@endsection

@section('content')
    <div class="card mb-3">
        <form method="GET"
              action="{{ route('admin.reports.analytics.index') }}">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Periodo de análisis</h3>
                    <p class="card-subtitle">
                        Compara puntualidad, retardos y actividad por grupo.
                    </p>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Desde</label>

                        <input
                            type="date"
                            name="from"
                            value="{{ $filters['from'] }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>

                        <input
                            type="date"
                            name="to"
                            value="{{ $filters['to'] }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Grupo</label>

                        <select name="group_id" class="form-select">
                            <option value="">Todos los grupos</option>

                            @foreach($groups as $group)
                                <option
                                    value="{{ $group->id }}"
                                    @selected(
                                        (string) $filters['group_id']
                                        === (string) $group->id
                                    )
                                >
                                    {{ $group->level_name }}
                                    · {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100">
                            <i class="ti ti-filter me-1"></i>
                            Analizar
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a
                    href="{{ route('admin.reports.analytics.index') }}"
                    class="btn btn-outline-secondary"
                >
                    Limpiar
                </a>

                <a
                    href="{{ route(
                        'admin.reports.analytics.pdf',
                        $filters
                    ) }}"
                    class="btn btn-danger"
                >
                    <i class="ti ti-file-type-pdf me-1"></i>
                    Descargar PDF del periodo
                </a>
            </div>
        </form>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Alumnos activos</div>
                    <div class="h1 mb-0">
                        {{ number_format($summary['active_students']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Entradas registradas</div>
                    <div class="h1 mb-0">
                        {{ number_format($summary['entries']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Puntualidad</div>
                    <div class="h1 mb-0">
                        {{ number_format($summary['punctuality_rate'], 1) }}%
                    </div>

                    <div class="text-secondary small">
                        {{ number_format($summary['on_time']) }}
                        entradas puntuales
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Retardos</div>
                    <div class="h1 mb-0">
                        {{ number_format(
                            $summary['late']
                            + $summary['very_late']
                        ) }}
                    </div>

                    <div class="text-secondary small">
                        {{ number_format($summary['late_rate'], 1) }}%
                        de las entradas clasificadas
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
                            Tendencia diaria
                        </h3>

                        <p class="card-subtitle">
                            Puntuales, retardos y entradas extemporáneas.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    <div style="height: 340px;">
                        <canvas id="dailyTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Distribución de estados
                        </h3>

                        <p class="card-subtitle">
                            Clasificación de los eventos del periodo.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    <div style="height: 340px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">Comparativo por grupo</h3>
                <p class="card-subtitle">
                    Número de entradas puntuales contra retardos.
                </p>
            </div>
        </div>

        <div class="card-body">
            <div style="height: 390px;">
                <canvas id="groupChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Resultados por grupo</h3>
                <p class="card-subtitle">
                    Resumen acumulado para el rango seleccionado.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Alumnos</th>
                        <th>Entradas</th>
                        <th>Puntuales</th>
                        <th>Retardos</th>
                        <th>Extemporáneos</th>
                        <th>Puntualidad</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($groupResults as $row)
                        <tr>
                            <td>
                                <div class="fw-bold">
                                    {{ $row['group_name'] }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $row['level_name'] }}
                                </div>
                            </td>

                            <td>{{ number_format($row['students']) }}</td>
                            <td>{{ number_format($row['entries']) }}</td>
                            <td>{{ number_format($row['on_time']) }}</td>
                            <td>{{ number_format($row['late']) }}</td>
                            <td>{{ number_format($row['very_late']) }}</td>

                            <td style="min-width: 170px;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-fill">
                                        <div
                                            class="progress-bar bg-success"
                                            style="width: {{
                                                min(
                                                    100,
                                                    $row['punctuality_rate']
                                                )
                                            }}%"
                                        ></div>
                                    </div>

                                    <strong>
                                        {{ number_format(
                                            $row['punctuality_rate'],
                                            1
                                        ) }}%
                                    </strong>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="text-center text-secondary py-5"
                            >
                                No hay resultados para este periodo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dailyTrend = @json($dailyTrend);
            const groupResults = @json($groupResults);
            const statusDistribution = @json($statusDistribution);

            const commonOptions = {
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
            };

            new Chart(
                document.getElementById('dailyTrendChart'),
                {
                    type: 'line',
                    data: {
                        labels: dailyTrend.map(row => row.label),
                        datasets: [
                            {
                                label: 'Puntuales',
                                data: dailyTrend.map(row => row.on_time),
                                borderColor: '#16a34a',
                                backgroundColor: 'rgba(22, 163, 74, .15)',
                                tension: .25,
                                fill: false
                            },
                            {
                                label: 'Retardos',
                                data: dailyTrend.map(row => row.late),
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245, 158, 11, .15)',
                                tension: .25,
                                fill: false
                            },
                            {
                                label: 'Extemporáneos',
                                data: dailyTrend.map(row => row.very_late),
                                borderColor: '#ea580c',
                                backgroundColor: 'rgba(234, 88, 12, .15)',
                                tension: .25,
                                fill: false
                            }
                        ]
                    },
                    options: commonOptions
                }
            );

            new Chart(
                document.getElementById('statusChart'),
                {
                    type: 'doughnut',
                    data: {
                        labels: statusDistribution.map(row => row.label),
                        datasets: [
                            {
                                data: statusDistribution.map(
                                    row => row.value
                                ),
                                backgroundColor: [
                                    '#16a34a',
                                    '#f59e0b',
                                    '#ea580c',
                                    '#dc2626',
                                    '#64748b'
                                ],
                                borderWidth: 0
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                }
            );

            new Chart(
                document.getElementById('groupChart'),
                {
                    type: 'bar',
                    data: {
                        labels: groupResults.map(
                            row => row.group_short
                        ),
                        datasets: [
                            {
                                label: 'Puntuales',
                                data: groupResults.map(
                                    row => row.on_time
                                ),
                                backgroundColor: '#16a34a'
                            },
                            {
                                label: 'Retardos',
                                data: groupResults.map(
                                    row => row.late_total
                                ),
                                backgroundColor: '#f59e0b'
                            }
                        ]
                    },
                    options: {
                        ...commonOptions,
                        scales: {
                            x: {
                                ticks: {
                                    autoSkip: false,
                                    maxRotation: 45,
                                    minRotation: 0
                                }
                            },
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
        });
    </script>
@endpush