@extends('layouts.app')

@section('title', 'Reporte de accesos | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Reporte de accesos')

@section('content')
    @php
        $eventLabels = [
            'entry' => 'Entrada',
            'exit' => 'Salida',
            'access' => 'Acceso',
        ];

        $statusLabels = [
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Extemporánea',
            'duplicate' => 'Duplicado',
            'normal_exit' => 'Salida normal',
            'early_exit' => 'Salida anticipada',
            'allowed' => 'Autorizado',
            'denied' => 'Denegado',
        ];

        $statusBadges = [
            'on_time' => 'success',
            'late' => 'warning',
            'very_late' => 'orange',
            'duplicate' => 'secondary',
            'normal_exit' => 'blue',
            'early_exit' => 'warning',
            'allowed' => 'success',
            'denied' => 'danger',
        ];
    @endphp

    @section('topbar-actions')
    <a href="{{ route('admin.reports.attendance') }}" class="btn btn-outline-primary btn-sm">
        <i class="ti ti-calendar-stats me-1"></i>
        Asistencia diaria
    </a>
@endsection

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Eventos</div>
                    <div class="h1 mb-0">{{ $summary['total'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Puntuales</div>
                    <div class="h1 mb-0">{{ $summary['on_time'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Retardos / extemporáneas</div>
                    <div class="h1 mb-0">{{ $summary['late'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Denegados</div>
                    <div class="h1 mb-0">{{ $summary['denied'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <form method="GET" action="{{ route('admin.reports.access') }}">
            <div class="card-header">
                <h3 class="card-title">Filtros</h3>
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

                    <div class="col-md-3">
                        <label class="form-label">Grupo</label>
                        <select name="group_id" class="form-select">
                            <option value="">Todos</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" @selected((string) $filters['group_id'] === (string) $group->id)>
                                    {{ $group->level_name }} · {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Alumno</label>
                        <select name="student_id" class="form-select">
                            <option value="">Todos</option>
                            @foreach($students as $student)
                                <option value="{{ $student->id }}" @selected((string) $filters['student_id'] === (string) $student->id)>
                                    {{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_code }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Área</label>
                        <select name="area_id" class="form-select">
                            <option value="">Todas</option>
                            @foreach($areas as $area)
                                <option value="{{ $area->id }}" @selected((string) $filters['area_id'] === (string) $area->id)>
                                    {{ $area->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Dispositivo</label>
                        <select name="device_id" class="form-select">
                            <option value="">Todos</option>
                            @foreach($devices as $device)
                                <option value="{{ $device->id }}" @selected((string) $filters['device_id'] === (string) $device->id)>
                                    {{ $device->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Evento</label>
                        <select name="event_type" class="form-select">
                            <option value="">Todos</option>
                            @foreach($eventLabels as $value => $label)
                                <option value="{{ $value }}" @selected($filters['event_type'] === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="event_status" class="form-select">
                            <option value="">Todos</option>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected($filters['event_status'] === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('admin.reports.access') }}" class="btn btn-outline-secondary">
                    Limpiar
                </a>

                <button class="btn btn-primary">
                    <i class="ti ti-filter me-1"></i>
                    Aplicar filtros
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Eventos registrados</h3>
                <p class="card-subtitle">
                    Bitácora de entradas, salidas, duplicados y accesos restringidos.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Fecha/hora</th>
                        <th>Alumno</th>
                        <th>Grupo</th>
                        <th>Área</th>
                        <th>Dispositivo</th>
                        <th>Evento</th>
                        <th>Estado</th>
                        <th>Razón</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>
                                <div class="fw-bold">
                                    {{ \Illuminate\Support\Carbon::parse($log->scanned_at)->format('d/m/Y') }}
                                </div>
                                <div class="text-secondary small">
                                    {{ \Illuminate\Support\Carbon::parse($log->scanned_at)->format('H:i:s') }}
                                </div>
                            </td>

                            <td>
                                <div class="fw-bold">
                                    {{ trim(($log->first_name ?? '') . ' ' . ($log->last_name ?? '')) ?: 'Sin alumno' }}
                                </div>
                                <div class="text-secondary small">
                                    {{ $log->student_code ?? '' }}
                                </div>
                            </td>

                            <td>
                                {{ $log->group_name ?? 'Sin grupo' }}
                            </td>

                            <td>
                                <div>{{ $log->area_name ?? 'Sin área' }}</div>
                                <div class="text-secondary small">{{ $log->area_type ?? '' }}</div>
                            </td>

                            <td>
                                <div>{{ $log->device_name ?? 'Sin dispositivo' }}</div>
                                <div class="text-secondary small">{{ $log->device_type ?? '' }}</div>
                            </td>

                            <td>
                                {{ $eventLabels[$log->event_type] ?? $log->event_type }}
                            </td>

                            <td>
                                @php
                                    $badge = $statusBadges[$log->event_status] ?? 'secondary';
                                @endphp

                                <span class="badge bg-{{ $badge }}-lt">
                                    {{ $statusLabels[$log->event_status] ?? $log->event_status }}
                                </span>
                            </td>

                            <td>
                                {{ $log->reason ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-5">
                                No hay eventos con los filtros seleccionados.
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