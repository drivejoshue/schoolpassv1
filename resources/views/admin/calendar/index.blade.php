@extends('layouts.app')

@section('title', 'Calendario escolar | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Calendario escolar')

@section('topbar-actions')
    <a href="{{ route('admin.cycles.index') }}" class="btn btn-outline-primary btn-sm">
        <i class="ti ti-calendar-stats me-1"></i>
        Ciclos escolares
    </a>

    <a href="{{ route('admin.calendar.create') }}" class="btn btn-primary btn-sm">
        <i class="ti ti-plus me-1"></i>
        Agregar fecha especial
    </a>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">
            <i class="ti ti-circle-check me-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($activeCycle)
        <div class="alert alert-info">
            <i class="ti ti-info-circle me-2"></i>
            Ciclo activo:
            <strong>{{ $activeCycle->name }}</strong>
            @if($activeCycle->starts_on && $activeCycle->ends_on)
                · {{ \Illuminate\Support\Carbon::parse($activeCycle->starts_on)->format('d/m/Y') }}
                -
                {{ \Illuminate\Support\Carbon::parse($activeCycle->ends_on)->format('d/m/Y') }}
            @endif
        </div>
    @else
        <div class="alert alert-warning">
            <i class="ti ti-alert-triangle me-2"></i>
            No hay ciclo escolar activo. Crea o activa un ciclo antes de cargar calendario, grupos o futuras calificaciones.
        </div>
    @endif

    <div class="card mb-3">
        <form method="GET" action="{{ route('admin.calendar.index') }}">
            <div class="card-header">
                <h3 class="card-title">Filtros</h3>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Ciclo escolar</label>
                        <select name="academic_cycle_id" class="form-select">
                            <option value="">Todos los ciclos</option>
                            @foreach($cycles as $cycle)
                                <option value="{{ $cycle->id }}" @selected((string) $filters['academic_cycle_id'] === (string) $cycle->id)>
                                    {{ $cycle->name }}
                                    @if($cycle->is_active)
                                        · activo
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="type" class="form-select">
                            <option value="">Todos</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected($filters['type'] === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('admin.calendar.index') }}" class="btn btn-outline-secondary">
                    Limpiar
                </a>

                <button class="btn btn-primary">
                    <i class="ti ti-filter me-1"></i>
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <div class="alert alert-secondary">
        <strong>Cómo funciona:</strong>
        no captures todos los días de clase. El sistema asume días normales según horarios de grupo.
        Aquí solo registra excepciones: vacaciones, suspensión, festivos, consejo técnico, exámenes o eventos.
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Fechas especiales registradas</h3>
                <p class="card-subtitle">
                    Estas fechas modifican cómo se calculan asistencias, ausencias y reportes.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Efecto en asistencia</th>
                        <th>Título</th>
                        <th>Ciclo</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($days as $day)
                        <tr>
                            <td>
                                <div class="fw-bold">
                                    {{ \Illuminate\Support\Carbon::parse($day->date)->format('d/m/Y') }}
                                </div>
                                <div class="text-secondary small">
                                    {{ ucfirst(\Illuminate\Support\Carbon::parse($day->date)->locale('es')->translatedFormat('l')) }}
                                </div>
                            </td>

                            <td>
                                @if(in_array($day->type, $noClassTypes, true))
                                    <span class="badge bg-warning-lt">
                                        {{ $types[$day->type] ?? $day->type }}
                                    </span>
                                @elseif($day->type === 'class_day')
                                    <span class="badge bg-success-lt">
                                        {{ $types[$day->type] ?? $day->type }}
                                    </span>
                                @else
                                    <span class="badge bg-blue-lt">
                                        {{ $types[$day->type] ?? $day->type }}
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if(in_array($day->type, $noClassTypes, true))
                                    <span class="badge bg-secondary-lt">No cuenta ausencias</span>
                                @else
                                    <span class="badge bg-success-lt">Puede contar asistencia</span>
                                @endif
                            </td>

                            <td>
                                <div class="fw-bold">{{ $day->title }}</div>
                                @if($day->notes)
                                    <div class="text-secondary small">{{ $day->notes }}</div>
                                @endif
                            </td>

                            <td>{{ $day->cycle_name ?? 'Sin ciclo' }}</td>

                            <td>
                                @if($day->status === 'active')
                                    <span class="badge bg-success-lt">Activo</span>
                                @else
                                    <span class="badge bg-secondary-lt">Inactivo</span>
                                @endif
                            </td>

                            <td>
                                <a href="{{ route('admin.calendar.edit', $day->id) }}" class="btn btn-sm btn-outline-primary">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-5">
                                No hay fechas especiales en este rango.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection