@extends('layouts.app')

@section('title', 'Grupos y horarios | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Grupos y horarios')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">
            <i class="ti ti-circle-check me-2"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Grupos escolares</h3>
                <p class="card-subtitle">
                    Configuración de horarios de entrada, tolerancia, retardo y salida.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Nivel</th>
                        <th>Alumnos activos</th>
                        <th>Días con horario</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($groups as $group)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $group->name }}</div>
                            </td>

                            <td>
                                {{ $group->level_name ?? 'Sin nivel' }}
                            </td>

                            <td>
                                <span class="badge bg-blue-lt">
                                    {{ $group->students_count }} alumnos
                                </span>
                            </td>

                            <td>
                                @if((int) $group->active_schedules_count > 0)
                                    <span class="badge bg-success-lt">
                                        {{ $group->active_schedules_count }} día(s)
                                    </span>
                                @else
                                    <span class="badge bg-warning-lt">
                                        Sin horario activo
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if($group->status === 'active')
                                    <span class="badge bg-success-lt">Activo</span>
                                @else
                                    <span class="badge bg-secondary-lt">{{ $group->status }}</span>
                                @endif
                            </td>

                            <td>
                                <a href="{{ route('admin.groups.schedules.edit', $group->id) }}" class="btn btn-sm btn-outline-primary">
                                    Horarios
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-5">
                                No hay grupos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection