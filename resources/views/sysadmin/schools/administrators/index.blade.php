@extends('layouts.sysadmin')

@section('title', 'Administradores · '.$school->name)
@section('page_title', 'Administradores')

@section('content')
<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">
                <a
                    href="{{ route('sysadmin.schools.show', $school) }}"
                    class="text-secondary text-decoration-none"
                >
                    <i class="ti ti-arrow-left me-1"></i>
                    {{ $school->name }}
                </a>
            </div>

            <h2 class="page-title">Administradores de la escuela</h2>

            <div class="text-secondary mt-1">
                Alta, edición, desactivación y restablecimiento de contraseña.
            </div>
        </div>

        <div class="col-auto ms-auto">
            <a
                href="{{ route(
                    'sysadmin.schools.app-config.edit',
                    $school
                ) }}"
                class="btn btn-outline-primary"
            >
                <i class="ti ti-device-mobile-cog me-2"></i>
                Configurar apps
            </a>
        </div>
    </div>
</div>

<div class="row row-cards">
    <div class="col-lg-5">
        <div class="card">
            <form
                method="POST"
                action="{{ route(
                    'sysadmin.schools.administrators.store',
                    $school
                ) }}"
            >
                @csrf

                <div class="card-header">
                    <h3 class="card-title">Nuevo administrador</h3>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">Nombre</label>
                        <input
                            type="text"
                            name="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}"
                            maxlength="160"
                            required
                        >
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Correo</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control @error('email') is-invalid @enderror"
                            value="{{ old('email') }}"
                            maxlength="180"
                            required
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                value="{{ old('phone') }}"
                                maxlength="30"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Rol</label>
                            <select
                                name="role"
                                class="form-select"
                                required
                            >
                                <option value="director">
                                    Director
                                </option>
                                <option value="school_admin">
                                    Administrador escolar
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">
                                Contraseña
                            </label>
                            <input
                                type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                minlength="8"
                                required
                            >
                            @error('password')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">
                                Confirmar
                            </label>
                            <input
                                type="password"
                                name="password_confirmation"
                                class="form-control"
                                minlength="8"
                                required
                            >
                        </div>
                    </div>
                </div>

                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-user-plus me-2"></i>
                        Crear administrador
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Usuarios administrativos
                </h3>

                <div class="card-actions">
                    <span class="badge bg-blue-lt text-blue">
                        {{ $administrators->count() }}
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse ($administrators as $administrator)
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    {{ $administrator->name }}
                                </div>

                                <div class="small text-secondary">
                                    {{ $administrator->email }}
                                </div>

                                @if ($administrator->phone)
                                    <div class="small text-secondary">
                                        {{ $administrator->phone }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-azure-lt text-azure">
                                    {{ $administrator->role === 'director'
                                        ? 'Director'
                                        : 'Administrador'
                                    }}
                                </span>
                            </td>

                            <td>
                                <span class="badge {{
                                    $administrator->status === 'active'
                                        ? 'bg-green-lt text-green'
                                        : 'bg-red-lt text-red'
                                }}">
                                    {{ $administrator->status === 'active'
                                        ? 'Activo'
                                        : 'Inactivo'
                                    }}
                                </span>
                            </td>

                            <td>
                                <div class="dropdown">
                                    <button
                                        type="button"
                                        class="btn btn-icon btn-ghost-primary"
                                        data-bs-toggle="dropdown"
                                    >
                                        <i class="ti ti-dots"></i>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-end">
                                        <button
                                            type="button"
                                            class="dropdown-item"
                                            data-bs-toggle="modal"
                                            data-bs-target="#edit-admin-{{ $administrator->id }}"
                                        >
                                            <i class="ti ti-edit me-2"></i>
                                            Editar
                                        </button>

                                        <button
                                            type="button"
                                            class="dropdown-item"
                                            data-bs-toggle="modal"
                                            data-bs-target="#reset-admin-{{ $administrator->id }}"
                                        >
                                            <i class="ti ti-key me-2"></i>
                                            Restablecer contraseña
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="4"
                                class="text-center text-secondary py-5"
                            >
                                Sin administradores.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@foreach ($administrators as $administrator)
    <div
        class="modal modal-blur fade"
        id="edit-admin-{{ $administrator->id }}"
        tabindex="-1"
    >
        <div class="modal-dialog modal-dialog-centered">
            <form
                method="POST"
                action="{{ route(
                    'sysadmin.schools.administrators.update',
                    [$school, $administrator->id]
                ) }}"
                class="modal-content"
            >
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title">
                        Editar administrador
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Nombre</label>
                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            value="{{ $administrator->name }}"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Correo</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            value="{{ $administrator->email }}"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="{{ $administrator->phone }}"
                        >
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Rol</label>
                            <select
                                name="role"
                                class="form-select"
                                required
                            >
                                <option
                                    value="director"
                                    @selected($administrator->role === 'director')
                                >
                                    Director
                                </option>
                                <option
                                    value="school_admin"
                                    @selected($administrator->role === 'school_admin')
                                >
                                    Administrador escolar
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">
                                Estado
                            </label>
                            <select
                                name="status"
                                class="form-select"
                                required
                            >
                                <option
                                    value="active"
                                    @selected($administrator->status === 'active')
                                >
                                    Activo
                                </option>
                                <option
                                    value="inactive"
                                    @selected($administrator->status !== 'active')
                                >
                                    Inactivo
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn me-auto"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        class="modal modal-blur fade"
        id="reset-admin-{{ $administrator->id }}"
        tabindex="-1"
    >
        <div class="modal-dialog modal-dialog-centered">
            <form
                method="POST"
                action="{{ route(
                    'sysadmin.schools.administrators.reset-password',
                    [$school, $administrator->id]
                ) }}"
                class="modal-content"
            >
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">
                        Restablecer contraseña
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning">
                        Se cerrarán sus sesiones API actuales.
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">
                            Nueva contraseña
                        </label>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="8"
                            required
                        >
                    </div>

                    <div>
                        <label class="form-label required">
                            Confirmar contraseña
                        </label>
                        <input
                            type="password"
                            name="password_confirmation"
                            class="form-control"
                            minlength="8"
                            required
                        >
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn me-auto"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button type="submit" class="btn btn-warning">
                        Restablecer
                    </button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
