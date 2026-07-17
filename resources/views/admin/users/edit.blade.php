@extends('layouts.app')

@section('title', 'Editar usuario | SchoolPass')
@section('section-label', 'Administración')
@section('page-title', 'Editar usuario institucional')

@section('topbar-actions')
    <a
        href="{{ route('admin.users.index') }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Usuarios del sistema
    </a>
@endsection

@section('content')
    @include('admin.users.partials.credentials')

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

    <div class="row row-cards">
        <div class="col-xl-8">
            <form
                method="POST"
                action="{{ route('admin.users.update', $userRow->id) }}"
                autocomplete="off"
            >
                @csrf
                @method('PUT')

                @include('admin.users.partials.form')
            </form>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Resumen de la cuenta</h3>
                </div>

                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="avatar avatar-lg bg-blue-lt text-blue">
                            {{ mb_strtoupper(mb_substr($userRow->name ?: 'U', 0, 1)) }}
                        </span>

                        <div>
                            <div class="fw-bold fs-3">{{ $userRow->name }}</div>
                            <div class="text-secondary">{{ $userRow->email }}</div>
                        </div>
                    </div>

                    <dl class="row mb-0">
                        <dt class="col-5 text-secondary">Perfil</dt>
                        <dd class="col-7">
                            {{ $roleLabels[$userRow->role] ?? $userRow->role }}
                        </dd>

                        <dt class="col-5 text-secondary">Estado</dt>
                        <dd class="col-7">
                            {{ $statusLabels[$userRow->status] ?? $userRow->status }}
                        </dd>

                        <dt class="col-5 text-secondary">Contraseña</dt>
                        <dd class="col-7">
                            {{ $userRow->must_change_password
                                ? 'Cambio pendiente'
                                : 'Actualizada' }}
                        </dd>

                        <dt class="col-5 text-secondary">Último acceso</dt>
                        <dd class="col-7">
                            {{ $userRow->last_login_at
                                ? \Carbon\Carbon::parse($userRow->last_login_at)->format('d/m/Y H:i')
                                : 'Sin acceso registrado' }}
                        </dd>

                        <dt class="col-5 text-secondary">Dispositivo</dt>
                        <dd class="col-7">
                            {{ $assignedDevice->name ?? 'Sin dispositivo' }}
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Restablecer contraseña</h3>
                        <div class="text-secondary small mt-1">
                            Revoca los tokens anteriores y obliga a cambiarla.
                        </div>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.users.password.reset', $userRow->id) }}"
                    autocomplete="off"
                >
                    @csrf
                    @method('PATCH')

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Nueva contraseña temporal</label>

                            <input
                                type="password"
                                name="password"
                                autocomplete="new-password"
                                class="form-control"
                                placeholder="Vacía para generar automáticamente"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirmar contraseña</label>

                            <input
                                type="password"
                                name="password_confirmation"
                                autocomplete="new-password"
                                class="form-control"
                            >
                        </div>

                        <label class="form-check form-switch">
                            <input
                                type="checkbox"
                                name="send_credentials"
                                value="1"
                                class="form-check-input"
                                checked
                            >

                            <span class="form-check-label">
                                Enviar por correo
                            </span>
                        </label>
                    </div>

                    <div class="card-footer">
                        <button
                            type="submit"
                            class="btn btn-warning w-100"
                            onclick="return confirm('¿Restablecer la contraseña y cerrar las sesiones móviles de esta cuenta?')"
                        >
                            <i class="ti ti-key me-1"></i>
                            Generar contraseña temporal
                        </button>
                    </div>
                </form>
            </div>

            @if((int) auth()->id() !== (int) $userRow->id)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Acceso de la cuenta</h3>
                    </div>

                    <div class="card-body">
                        <p class="text-secondary">
                            Al suspenderla, no podrá iniciar sesión y sus tokens móviles quedarán revocados.
                        </p>

                        <form
                            method="POST"
                            action="{{ route('admin.users.status', $userRow->id) }}"
                            onsubmit="return confirm('{{ $userRow->status === 'active'
                                ? '¿Suspender esta cuenta?'
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
                                class="btn w-100 {{ $userRow->status === 'active'
                                    ? 'btn-outline-danger'
                                    : 'btn-outline-success' }}"
                            >
                                <i class="ti {{ $userRow->status === 'active'
                                    ? 'ti-user-off'
                                    : 'ti-user-check' }} me-1"></i>

                                {{ $userRow->status === 'active'
                                    ? 'Suspender cuenta'
                                    : 'Reactivar cuenta' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection