@extends('layouts.app')

@section('title', 'Auditoría de exportaciones | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Auditoría de exportaciones')

@section('topbar-actions')
    <a
        href="{{ route('admin.reports.exports.index') }}"
        class="btn btn-outline-success btn-sm"
    >
        <i class="ti ti-file-export me-1"></i>
        Exportaciones
    </a>

    <a
        href="{{ route('admin.reports.analytics.index') }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-chart-bar me-1"></i>
        Analítica
    </a>
@endsection

@section('content')
    @php
        $statusLabels = [
            'success' => 'Correcta',
            'failed' => 'Fallida',
            'error' => 'Error',
        ];

        $statusBadges = [
            'success' => 'success',
            'failed' => 'warning',
            'error' => 'danger',
        ];

        $formatLabels = [
            'pdf' => 'PDF',
            'xlsx' => 'Excel',
            'file' => 'Archivo',
        ];
    @endphp

    <div class="card mb-3">
        <form
            method="GET"
            action="{{ route(
                'admin.reports.export-audit.index'
            ) }}"
        >
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Filtros de auditoría
                    </h3>

                    <p class="card-subtitle">
                        Consulta quién descargó cada reporte,
                        cuándo y con qué filtros.
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
                            Usuario
                        </label>

                        <select
                            name="user_id"
                            class="form-select"
                        >
                            <option value="">
                                Todos los usuarios
                            </option>

                            @foreach($users as $user)
                                <option
                                    value="{{ $user->id }}"
                                    @selected(
                                        (string) $filters['user_id']
                                        === (string) $user->id
                                    )
                                >
                                    {{ $user->name }}
                                    · {{ $user->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">
                            Reporte
                        </label>

                        <select
                            name="report_key"
                            class="form-select"
                        >
                            <option value="">
                                Todos los reportes
                            </option>

                            @foreach(
                                $reportOptions as $option
                            )
                                <option
                                    value="{{
                                        $option->report_key
                                    }}"
                                    @selected(
                                        $filters['report_key']
                                        === $option->report_key
                                    )
                                >
                                    {{ $option->report_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-1">
                        <label class="form-label">
                            Formato
                        </label>

                        <select
                            name="format"
                            class="form-select"
                        >
                            <option value="">
                                Todos
                            </option>

                            <option
                                value="pdf"
                                @selected(
                                    $filters['format']
                                    === 'pdf'
                                )
                            >
                                PDF
                            </option>

                            <option
                                value="xlsx"
                                @selected(
                                    $filters['format']
                                    === 'xlsx'
                                )
                            >
                                Excel
                            </option>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <label class="form-label">
                            Estado
                        </label>

                        <select
                            name="status"
                            class="form-select"
                        >
                            <option value="">
                                Todos
                            </option>

                            @foreach(
                                $statusLabels
                                as $value => $label
                            )
                                <option
                                    value="{{ $value }}"
                                    @selected(
                                        $filters['status']
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
                        'admin.reports.export-audit.index'
                    ) }}"
                    class="btn btn-outline-secondary"
                >
                    Limpiar
                </a>

                <button class="btn btn-primary">
                    <i class="ti ti-filter me-1"></i>
                    Aplicar filtros
                </button>
            </div>
        </form>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Exportaciones
                    </div>

                    <div class="h2 mb-0">
                        {{ number_format(
                            $summary['total']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Correctas
                    </div>

                    <div class="h2 mb-0 text-success">
                        {{ number_format(
                            $summary['success']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Errores
                    </div>

                    <div class="h2 mb-0 text-danger">
                        {{ number_format(
                            $summary['failed']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        PDF
                    </div>

                    <div class="h2 mb-0">
                        {{ number_format(
                            $summary['pdf']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Excel
                    </div>

                    <div class="h2 mb-0">
                        {{ number_format(
                            $summary['xlsx']
                        ) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        Tiempo promedio
                    </div>

                    <div class="h2 mb-0">
                        {{
                            number_format(
                                $summary[
                                    'average_duration_ms'
                                ]
                            )
                        }} ms
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($topReports->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        Reportes más exportados
                    </h3>

                    <p class="card-subtitle">
                        Actividad dentro del periodo seleccionado.
                    </p>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    @foreach($topReports as $report)
                        <div class="col-md-6 col-xl-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-secondary small">
                                    Reporte
                                </div>

                                <div class="fw-bold">
                                    {{ $report->report_name }}
                                </div>

                                <div class="h2 mt-2 mb-0">
                                    {{
                                        number_format(
                                            $report->exports_count
                                        )
                                    }}
                                </div>

                                <div class="text-secondary small">
                                    exportaciones
                                </div>
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
                    Historial de exportaciones
                </h3>

                <p class="card-subtitle">
                    Los filtros quedan registrados para
                    fines administrativos y de seguridad.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Fecha/hora</th>
                        <th>Usuario</th>
                        <th>Reporte</th>
                        <th>Formato</th>
                        <th>Filtros</th>
                        <th>Estado</th>
                        <th>Duración</th>
                        <th>IP</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($logs as $log)
                        @php
                            $logFilters = json_decode(
                                $log->filters_json
                                    ?? '{}',
                                true
                            );

                            $logFilters = is_array(
                                $logFilters
                            )
                                ? $logFilters
                                : [];
                        @endphp

                        <tr>
                            <td>
                                <div class="fw-bold">
                                    {{
                                        \Illuminate\Support\Carbon::parse(
                                            $log->exported_at
                                        )->format('d/m/Y')
                                    }}
                                </div>

                                <div class="text-secondary small">
                                    {{
                                        \Illuminate\Support\Carbon::parse(
                                            $log->exported_at
                                        )->format('H:i:s')
                                    }}
                                </div>
                            </td>

                            <td>
                                <div class="fw-bold">
                                    {{
                                        $log->user_name
                                        ?? 'Usuario eliminado'
                                    }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $log->user_email ?? '' }}
                                </div>
                            </td>

                            <td>
                                <div class="fw-bold">
                                    {{ $log->report_name }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $log->route_name }}
                                </div>

                                @if($log->download_filename)
                                    <div class="text-secondary small">
                                        {{
                                            $log->download_filename
                                        }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-blue-lt">
                                    {{
                                        $formatLabels[
                                            $log->format
                                        ]
                                        ?? strtoupper(
                                            $log->format
                                        )
                                    }}
                                </span>
                            </td>

                            <td style="min-width: 220px;">
                                @forelse(
                                    $logFilters
                                    as $key => $value
                                )
                                    <div class="small">
                                        <strong>
                                            {{ $key }}:
                                        </strong>

                                        {{
                                            is_array($value)
                                                ? implode(
                                                    ', ',
                                                    $value
                                                )
                                                : $value
                                        }}
                                    </div>
                                @empty
                                    <span class="text-secondary">
                                        Sin filtros
                                    </span>
                                @endforelse
                            </td>

                            <td>
                                <span class="badge bg-{{
                                    $statusBadges[
                                        $log->status
                                    ] ?? 'secondary'
                                }}-lt">
                                    {{
                                        $statusLabels[
                                            $log->status
                                        ] ?? $log->status
                                    }}
                                </span>

                                @if($log->error_message)
                                    <div class="text-danger small mt-1">
                                        {{
                                            \Illuminate\Support\Str::limit(
                                                $log->error_message,
                                                100
                                            )
                                        }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                {{
                                    number_format(
                                        $log->duration_ms ?? 0
                                    )
                                }} ms
                            </td>

                            <td>
                                {{ $log->ip_address ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="8"
                                class="text-center text-secondary py-5"
                            >
                                Todavía no hay exportaciones registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="card-footer">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection