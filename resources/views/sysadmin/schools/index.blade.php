@extends('layouts.sysadmin')

@section('title', 'Escuelas')
@section('page_title', 'Escuelas')

@section('content')
<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Licenciamiento</div>
            <h2 class="page-title">Escuelas</h2>
            <div class="text-secondary mt-1">
                Escuelas, administradores, configuración de apps y licencias.
            </div>
        </div>

        <div class="col-auto ms-auto">
            <a
                href="{{ route('sysadmin.schools.create') }}"
                class="btn btn-primary"
            >
                <i class="ti ti-plus me-2"></i>
                Nueva escuela
            </a>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET">
            <div class="row g-2">
                <div class="col-12 col-lg">
                    <div class="input-icon">
                        <span class="input-icon-addon">
                            <i class="ti ti-search"></i>
                        </span>

                        <input
                            type="search"
                            name="q"
                            value="{{ $filters['q'] ?? '' }}"
                            class="form-control"
                            placeholder="Buscar escuela, slug o correo..."
                        >
                    </div>
                </div>

                <div class="col-12 col-md-4 col-lg-3">
                    <select name="status" class="form-select">
                        <option value="">Todos los estados</option>

                        @foreach ([
                            'active' => 'Activa',
                            'suspended' => 'Suspendida',
                            'cancelled' => 'Cancelada',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(($filters['status'] ?? '') === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-4 col-lg-3">
                    <select name="license_status" class="form-select">
                        <option value="">Todas las licencias</option>

                        @foreach ([
                            'trial' => 'Prueba',
                            'active' => 'Activa',
                            'grace' => 'Gracia',
                            'expired' => 'Vencida',
                            'suspended' => 'Suspendida',
                            'cancelled' => 'Cancelada',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(($filters['license_status'] ?? '') === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-auto">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="ti ti-filter me-2"></i>
                        Filtrar
                    </button>
                </div>

                @if (request()->hasAny([
                    'q',
                    'status',
                    'license_status',
                ]))
                    <div class="col-12 col-md-auto">
                        <a
                            href="{{ route('sysadmin.schools.index') }}"
                            class="btn btn-outline-secondary w-100"
                        >
                            Limpiar
                        </a>
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
            <tr>
                <th>Escuela</th>
                <th>Estado</th>
                <th>Plan</th>
                <th>Licencia</th>
                <th>Alumnos</th>
                <th>Vencimiento</th>
                <th class="w-1"></th>
            </tr>
            </thead>

            <tbody>
            @forelse ($schools as $school)
                @php
                    $schoolStatus = match ($school->status) {
                        'active' => ['Activa', 'bg-green-lt text-green'],
                        'suspended' => [
                            'Suspendida',
                            'bg-yellow-lt text-yellow',
                        ],
                        'cancelled' => [
                            'Cancelada',
                            'bg-red-lt text-red',
                        ],
                        default => [
                            $school->status,
                            'bg-secondary-lt text-secondary',
                        ],
                    };

                    $licenseStatus = match ($school->license_status) {
                        'active' => ['Activa', 'bg-green-lt text-green'],
                        'trial' => ['Prueba', 'bg-blue-lt text-blue'],
                        'grace' => ['Gracia', 'bg-yellow-lt text-yellow'],
                        'expired' => ['Vencida', 'bg-red-lt text-red'],
                        'suspended' => [
                            'Suspendida',
                            'bg-orange-lt text-orange',
                        ],
                        'cancelled' => [
                            'Cancelada',
                            'bg-red-lt text-red',
                        ],
                        default => [
                            'Sin licencia',
                            'bg-secondary-lt text-secondary',
                        ],
                    };
                @endphp

                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar bg-blue-lt text-blue">
                                <i class="ti ti-school"></i>
                            </span>

                            <div>
                                <a
                                    href="{{ route(
                                        'sysadmin.schools.show',
                                        $school->id
                                    ) }}"
                                    class="fw-semibold text-reset"
                                >
                                    {{ $school->name }}
                                </a>

                                <div class="small text-secondary">
                                    {{ $school->slug }}
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="badge {{ $schoolStatus[1] }}">
                            {{ $schoolStatus[0] }}
                        </span>
                    </td>

                    <td>{{ $school->plan_name ?: 'Sin plan' }}</td>

                    <td>
                        <span class="badge {{ $licenseStatus[1] }}">
                            {{ $licenseStatus[0] }}
                        </span>
                    </td>

                    <td class="text-nowrap">
                        {{ number_format($school->students_used) }}

                        @if ($school->student_limit !== null)
                            / {{ number_format($school->student_limit) }}
                        @endif
                    </td>

                    <td class="text-nowrap">
                        {{ $school->expires_at
                            ? \Illuminate\Support\Carbon::parse(
                                $school->expires_at
                            )->format('d/m/Y')
                            : '—'
                        }}
                    </td>

                    <td>
                        <div class="dropdown">
                            <button
                                type="button"
                                class="btn btn-icon btn-ghost-primary"
                                data-bs-toggle="dropdown"
                                aria-label="Acciones"
                            >
                                <i class="ti ti-dots-vertical"></i>
                            </button>

                            <div class="dropdown-menu dropdown-menu-end">
                                <a
                                    href="{{ route(
                                        'sysadmin.schools.show',
                                        $school->id
                                    ) }}"
                                    class="dropdown-item"
                                >
                                    <i class="ti ti-eye me-2"></i>
                                    Detalle y licencia
                                </a>

                                <a
                                    href="{{ route(
                                        'sysadmin.schools.administrators.index',
                                        $school->id
                                    ) }}"
                                    class="dropdown-item"
                                >
                                    <i class="ti ti-users me-2"></i>
                                    Administradores
                                </a>

                                <a
                                    href="{{ route(
                                        'sysadmin.schools.app-config.edit',
                                        $school->id
                                    ) }}"
                                    class="dropdown-item"
                                >
                                    <i class="ti ti-device-mobile-cog me-2"></i>
                                    Configuración de apps
                                </a>

                                <a
                                    href="{{ route(
                                        'sysadmin.schools.edit',
                                        $school->id
                                    ) }}"
                                    class="dropdown-item"
                                >
                                    <i class="ti ti-edit me-2"></i>
                                    Editar escuela
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td
                        colspan="7"
                        class="text-secondary text-center py-5"
                    >
                        <i class="ti ti-school-off fs-1 d-block mb-2"></i>
                        No se encontraron escuelas.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($schools->hasPages())
        <div class="card-footer d-flex align-items-center">
            <div class="ms-auto">
                {{ $schools->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
