@extends('layouts.app')

@section('title', 'Áreas | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Áreas')

@section('topbar-actions')
    <a href="{{ route('admin.areas.create') }}" class="btn btn-primary btn-sm">
        <i class="ti ti-plus me-1"></i>
        Nueva área
    </a>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">
            <i class="ti ti-circle-check me-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @php
        $typeLabels = [
            'entrance' => 'Entrada',
            'restricted' => 'Restringida',
            'lab' => 'Laboratorio / taller',
            'storage' => 'Almacén',
            'library' => 'Biblioteca',
            'classroom' => 'Aula',
            'other' => 'Otra',
        ];
    @endphp

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Áreas de acceso</h3>
                <p class="card-subtitle">
                    Entrada principal, laboratorios, almacenes, biblioteca y zonas restringidas.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Área</th>
                        <th>Campus</th>
                        <th>Tipo</th>
                        <th>Asistencia</th>
                        <th>Dispositivos</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($areas as $area)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $area->name }}</div>
                              <div class="text-secondary small">{{ $area->code ?? 'sin-codigo' }}</div>
                            </td>

                            <td>{{ $area->campus_name ?? 'Sin campus' }}</td>

                            <td>{{ $typeLabels[$area->type] ?? $area->type }}</td>

                            <td>
                                @if($area->affects_attendance)
                                    <span class="badge bg-success-lt">Sí afecta</span>
                                @else
                                    <span class="badge bg-secondary-lt">No afecta</span>
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-blue-lt">
                                    {{ $area->active_devices_count }} activos
                                </span>
                            </td>

                            <td>
                                @if($area->status === 'active')
                                    <span class="badge bg-success-lt">Activa</span>
                                @else
                                    <span class="badge bg-secondary-lt">Inactiva</span>
                                @endif
                            </td>

                            <td>
                                <a href="{{ route('admin.areas.edit', $area->id) }}" class="btn btn-sm btn-outline-primary">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-5">
                                No hay áreas registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection