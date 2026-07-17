@extends('layouts.app')

@section('title', 'Ciclos escolares | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Ciclos escolares')

@section('topbar-actions')
    <a
        href="{{ route('admin.cycles.create') }}"
        class="btn btn-primary btn-sm"
    >
        <i class="ti ti-plus me-1"></i>
        Nuevo ciclo
    </a>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">
            <i class="ti ti-circle-check me-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <i class="ti ti-alert-circle me-2"></i>
            {{ $errors->first() }}
        </div>
    @endif

    @if($activeCycle)
        <div class="alert alert-success">
            <i class="ti ti-calendar-check me-2"></i>

            Ciclo activo:

            <strong>{{ $activeCycle->name }}</strong>

            ·

            {{ \Illuminate\Support\Carbon::parse(
                $activeCycle->starts_on
            )->format('d/m/Y') }}

            al

            {{ \Illuminate\Support\Carbon::parse(
                $activeCycle->ends_on
            )->format('d/m/Y') }}
        </div>
    @else
        <div class="alert alert-warning">
            <i class="ti ti-calendar-off me-2"></i>

            <strong>No hay ciclo escolar activo.</strong>

            Los escáneres pueden registrar accesos, pero no se generarán
            asistencias, retardos ni ausencias oficiales.
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    Ciclos registrados
                </h3>

                <p class="card-subtitle">
                    Crea el periodo, configura sus grupos y actívalo
                    cuando esté listo para operar.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Ciclo</th>
                        <th>Vigencia</th>
                        <th>Grupos</th>
                        <th>Calendario</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($cycles as $cycle)
                        <tr>
                            <td>
                                <div class="fw-bold">
                                    {{ $cycle->name }}
                                </div>

                                @if($cycle->notes)
                                    <div class="text-secondary small">
                                        {{ \Illuminate\Support\Str::limit(
                                            $cycle->notes,
                                            100
                                        ) }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <div>
                                    {{ \Illuminate\Support\Carbon::parse(
                                        $cycle->starts_on
                                    )->format('d/m/Y') }}
                                </div>

                                <div class="text-secondary small">
                                    al
                                    {{ \Illuminate\Support\Carbon::parse(
                                        $cycle->ends_on
                                    )->format('d/m/Y') }}
                                </div>
                            </td>

                            <td>
                                <div class="fw-bold">
                                    {{ number_format(
                                        $cycle->groups_count
                                    ) }}
                                </div>

                                <div class="text-secondary small">
                                    {{ number_format(
                                        $cycle->active_groups_count
                                    ) }}
                                    activos
                                </div>
                            </td>

                            <td>
                                {{ number_format(
                                    $cycle->calendar_days_count
                                ) }}
                                fechas especiales
                            </td>

                            <td>
                                @switch($cycle->status)
                                    @case('active')
                                        <span class="badge bg-success-lt">
                                            Activo
                                        </span>
                                        @break

                                    @case('closed')
                                        <span class="badge bg-secondary-lt">
                                            Cerrado
                                        </span>
                                        @break

                                    @default
                                        <span class="badge bg-warning-lt">
                                            Borrador
                                        </span>
                                @endswitch
                            </td>

                            <td>
                                <a
                                    href="{{ route(
                                        'admin.cycles.edit',
                                        $cycle->id
                                    ) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    @if($cycle->status === 'closed')
                                        Ver
                                    @else
                                        Administrar
                                    @endif
                                </a>
                                <a
    href="{{ route(
        'admin.cycle-enrollments.index',
        $cycle->id
    ) }}"
    class="btn btn-sm btn-outline-primary"
>
    <i class="ti ti-users-group me-1"></i>
    Matrícula
</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="6"
                                class="text-center text-secondary py-5"
                            >
                                No hay ciclos escolares registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection