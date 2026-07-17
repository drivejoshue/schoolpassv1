@extends('layouts.app')

@section('title', 'Usuarios del sistema | SchoolPass')
@section('section-label', 'Administración')
@section('page-title', 'Usuarios del sistema')

@section('topbar-actions')
    <a
        href="{{ route('admin.users.create') }}"
        class="btn btn-primary btn-sm"
    >
        <i class="ti ti-user-plus me-1"></i>
        Nuevo usuario
    </a>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">
            <i class="ti ti-circle-check me-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning">
            <i class="ti ti-alert-triangle me-2"></i>
            {{ session('warning') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <i class="ti ti-alert-circle me-2"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-xl">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar">
                                <i class="ti ti-users"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                {{ number_format($summary['total']) }} usuarios
                            </div>
                            <div class="text-secondary">Total institucional</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-success text-white avatar">
                                <i class="ti ti-user-check"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                {{ number_format($summary['active']) }} activos
                            </div>
                            <div class="text-secondary">Con acceso habilitado</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-azure text-white avatar">
                                <i class="ti ti-shield-check"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                {{ number_format($summary['prefect']) }} prefectos
                            </div>
                            <div class="text-secondary">Personal operativo</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-indigo text-white avatar">
                                <i class="ti ti-device-imac"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                {{ number_format($summary['kiosk']) }} kioscos
                            </div>
                            <div class="text-secondary">Puntos fijos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-orange text-white avatar">
                                <i class="ti ti-user-off"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                {{ number_format($summary['blocked']) }} suspendidos
                            </div>
                            <div class="text-secondary">Sin acceso</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.users.index') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Buscar</label>

                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <i class="ti ti-search"></i>
                            </span>

                            <input
                                type="search"
                                name="q"
                                value="{{ $filters['q'] }}"
                                class="form-control"
                                placeholder="Nombre, correo o teléfono"
                            >
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Perfil</label>

                        <select name="role" class="form-select">
                            <option value="">Todos</option>

                            @foreach($roles as $roleOption)
                                <option
                                    value="{{ $roleOption }}"
                                    @selected($filters['role'] === $roleOption)
                                >
                                    {{ $roleLabels[$roleOption] ?? $roleOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Estado</label>

                        <select name="status" class="form-select">
                            <option value="">Todos</option>

                            @foreach($statusLabels as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected($filters['status'] === $value)
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <div class="btn-list">
                            <button type="submit" class="btn btn-primary">
                                Filtrar
                            </button>

                            <a
                                href="{{ route('admin.users.index') }}"
                                class="btn btn-outline-secondary"
                            >
                                Limpiar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Cuentas institucionales</h3>
                <div class="text-secondary small mt-1">
                    Administradores, directores, prefectos y kioscos de esta escuela.
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Perfil</th>
                    <th>Estado</th>
                    <th>Dispositivo</th>
                    <th>Último acceso</th>
                    <th class="w-1"></th>
                </tr>
                </thead>

                <tbody>
                @forelse($users as $userRow)
                    @php
                        $initial = mb_strtoupper(
                            mb_substr($userRow->name ?: 'U', 0, 1)
                        );
                    @endphp

                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar bg-blue-lt text-blue">
                                    {{ $initial }}
                                </span>

                                <div>
                                    <div class="fw-semibold">
                                        {{ $userRow->name }}

                                        @if((int) auth()->id() === (int) $userRow->id)
                                            <span class="badge bg-blue-lt text-blue ms-1">
                                                Tu cuenta
                                            </span>
                                        @endif
                                    </div>

                                    <div class="text-secondary small">
                                        {{ $userRow->email }}
                                    </div>

                                    @if($userRow->phone)
                                        <div class="text-secondary small">
                                            {{ $userRow->phone }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="badge bg-azure-lt text-azure">
                                {{ $roleLabels[$userRow->role] ?? $userRow->role }}
                            </span>
                        </td>

                        <td>
                            @if($userRow->status === 'active')
                                <span class="badge bg-success-lt text-success">
                                    Activo
                                </span>
                            @else
                                <span class="badge bg-danger-lt text-danger">
                                    Suspendido
                                </span>
                            @endif

                            @if($userRow->must_change_password)
                                <div class="mt-1">
                                    <span class="badge bg-warning-lt text-warning">
                                        Cambio de contraseña pendiente
                                    </span>
                                </div>
                            @endif
                        </td>

                        <td>
                            @if($userRow->device_name)
                                <div class="fw-medium">
                                    <i class="ti ti-device-desktop me-1"></i>
                                    {{ $userRow->device_name }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $userRow->device_status }}
                                </div>
                            @elseif(in_array($userRow->role, ['prefect', 'kiosk'], true))
                                <span class="text-orange">
                                    <i class="ti ti-alert-triangle me-1"></i>
                                    Sin dispositivo
                                </span>
                            @else
                                <span class="text-secondary">No aplica</span>
                            @endif
                        </td>

                        <td>
                            @if($userRow->last_login_at)
                                <div>
                                    {{ \Carbon\Carbon::parse($userRow->last_login_at)->format('d/m/Y') }}
                                </div>
                                <div class="text-secondary small">
                                    {{ \Carbon\Carbon::parse($userRow->last_login_at)->format('H:i') }}
                                </div>
                            @else
                                <span class="text-secondary">Sin acceso registrado</span>
                            @endif
                        </td>

                        <td>
                            <div class="btn-list flex-nowrap">
                                <a
                                    href="{{ route('admin.users.edit', $userRow->id) }}"
                                    class="btn btn-outline-primary btn-sm"
                                >
                                    <i class="ti ti-edit me-1"></i>
                                    Editar
                                </a>

                                @if((int) auth()->id() !== (int) $userRow->id)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.users.status', $userRow->id) }}"
                                        onsubmit="return confirm('{{ $userRow->status === 'active'
                                            ? '¿Suspender esta cuenta y revocar sus sesiones?'
                                            : '¿Reactivar esta cuenta?' }}')"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="{{ $userRow->status === 'active'
                                                ? 'blocked'
                                                : 'active' }}"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm {{ $userRow->status === 'active'
                                                ? 'btn-outline-danger'
                                                : 'btn-outline-success' }}"
                                        >
                                            <i class="ti {{ $userRow->status === 'active'
                                                ? 'ti-user-off'
                                                : 'ti-user-check' }} me-1"></i>

                                            {{ $userRow->status === 'active'
                                                ? 'Suspender'
                                                : 'Reactivar' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="empty">
                                <div class="empty-img">
                                    <i class="ti ti-users-minus fs-1"></i>
                                </div>

                                <p class="empty-title">No hay usuarios que mostrar</p>
                                <p class="empty-subtitle text-secondary">
                                    Ajusta los filtros o registra una nueva cuenta institucional.
                                </p>

                                <div class="empty-action">
                                    <a
                                        href="{{ route('admin.users.create') }}"
                                        class="btn btn-primary"
                                    >
                                        <i class="ti ti-user-plus me-1"></i>
                                        Nuevo usuario
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="card-footer">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection