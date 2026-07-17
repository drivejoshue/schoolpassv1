@extends('layouts.app')

@section('title', 'Reglas de acceso | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Reglas de acceso')

@section('topbar-actions')
    <a href="{{ route('admin.area-rules.create') }}" class="btn btn-primary btn-sm">
        <i class="ti ti-plus me-1"></i>
        Nueva regla
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
            'group' => 'Grupo',
            'student' => 'Alumno',
        ];

        $weekdayLabels = [
            null => 'Todos',
            '' => 'Todos',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    @endphp

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Reglas para áreas restringidas</h3>
                <p class="card-subtitle">
                    Define qué grupos o alumnos pueden entrar a laboratorios, talleres, almacenes o zonas internas.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Área</th>
                        <th>Aplica a</th>
                        <th>Autorizado</th>
                        <th>Día</th>
                        <th>Horario</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rules as $rule)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $rule->area_name }}</div>
                                <div class="text-secondary small">{{ $rule->area_type }}</div>
                            </td>

                            <td>
                                {{ $typeLabels[$rule->applies_to_type] ?? $rule->applies_to_type }}
                            </td>

                            <td>
                                {{ $rule->target_name }}
                            </td>

                            <td>
                                {{ $weekdayLabels[$rule->weekday] ?? 'Todos' }}
                            </td>

                            <td>
                                @if($rule->starts_at && $rule->ends_at)
                                    {{ substr($rule->starts_at, 0, 5) }} - {{ substr($rule->ends_at, 0, 5) }}
                                @else
                                    Todo el día
                                @endif
                            </td>

                            <td>
                                @if($rule->status === 'active')
                                    <span class="badge bg-success-lt">Activa</span>
                                @else
                                    <span class="badge bg-secondary-lt">Inactiva</span>
                                @endif
                            </td>

                            <td>
                                <a href="{{ route('admin.area-rules.edit', $rule->id) }}" class="btn btn-sm btn-outline-primary">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-5">
                                No hay reglas registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection