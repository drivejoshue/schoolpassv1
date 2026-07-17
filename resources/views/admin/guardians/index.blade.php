@extends('layouts.app')

@section('title', 'Tutores | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Tutores')

@section('topbar-actions')
    <a href="{{ route('admin.guardians.create') }}" class="btn btn-primary btn-sm">
        <i class="ti ti-user-plus me-1"></i>
        Nuevo tutor
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

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Tutores registrados</h3>
                <p class="card-subtitle">
                    Padres, tutores y personas autorizadas vinculadas a alumnos.
                </p>
            </div>
        </div>

        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.guardians.index') }}" class="row g-2">
                <div class="col-lg-5">
                    <div class="input-icon">
                        <span class="input-icon-addon">
                            <i class="ti ti-search"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] }}"
                            class="form-control"
                            placeholder="Nombre, correo, teléfono o usuario"
                        >
                    </div>
                </div>

                <div class="col-sm-4 col-lg-2">
                    <select name="status" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="active" @selected($filters['status'] === 'active')>Activos</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
                        <option value="blocked" @selected($filters['status'] === 'blocked')>Bloqueados</option>
                    </select>
                </div>

                <div class="col-sm-4 col-lg-2">
                    <select name="account" class="form-select">
                        <option value="">Cualquier cuenta</option>
                        <option value="with" @selected($filters['account'] === 'with')>Con acceso app</option>
                        <option value="without" @selected($filters['account'] === 'without')>Sin acceso app</option>
                    </select>
                </div>

                <div class="col-sm-4 col-lg-2">
                    <select name="qr" class="form-select">
                        <option value="">Cualquier QR</option>
                        <option value="active" @selected($filters['qr'] === 'active')>Con QR activo</option>
                        <option value="without" @selected($filters['qr'] === 'without')>Sin QR activo</option>
                    </select>
                </div>

                <div class="col-lg-1 d-grid">
                    <button class="btn btn-primary" title="Aplicar filtros">
                        <i class="ti ti-filter"></i>
                    </button>
                </div>

                @if(collect($filters)->filter()->isNotEmpty())
                    <div class="col-12">
                        <a href="{{ route('admin.guardians.index') }}" class="btn btn-link px-0">
                            <i class="ti ti-x me-1"></i>
                            Limpiar filtros
                        </a>
                    </div>
                @endif
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Tutor</th>
                        <th>Contacto</th>
                        <th>Cuenta Family</th>
                        <th>Alumnos</th>
                        <th>QR</th>
                        <th>Último movimiento</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($guardians as $guardian)
                        @php
                            $fullName = trim($guardian->first_name.' '.$guardian->last_name);
                        @endphp

                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($guardian->photo_url)
                                        <span
                                            class="avatar avatar-sm me-2"
                                            style="background-image: url('{{ $guardian->photo_url }}')"
                                        ></span>
                                    @else
                                        <span class="avatar avatar-sm bg-blue-lt me-2">
                                            {{ mb_strtoupper(mb_substr($guardian->first_name, 0, 1)) }}
                                        </span>
                                    @endif

                                    <div>
                                        <a
                                            href="{{ route('admin.guardians.show', $guardian->id) }}"
                                            class="fw-bold text-reset"
                                        >
                                            {{ $fullName }}
                                        </a>

                                        @if((int) $guardian->primary_students_count > 0)
                                            <div class="text-secondary small">
                                                Principal de {{ $guardian->primary_students_count }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div>{{ $guardian->phone ?? 'Sin teléfono' }}</div>
                                <div class="text-secondary small">
                                    {{ $guardian->email ?? 'Sin correo' }}
                                </div>
                            </td>

                            <td>
                                @if($guardian->user_id)
                                    <span class="badge {{ $guardian->user_status === 'active' ? 'bg-success-lt' : 'bg-danger-lt' }}">
                                        {{ $guardian->user_status === 'active' ? 'Activa' : 'Bloqueada' }}
                                    </span>
                                    <div class="text-secondary small text-break mt-1">
                                        {{ $guardian->access_username }}
                                    </div>
                                @else
                                    <span class="badge bg-secondary-lt">Sin acceso</span>
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-blue-lt">
                                    {{ $guardian->students_count }} vinculados
                                </span>
                            </td>

                            <td>
                                @if((int) $guardian->active_credentials_count > 0)
                                    <span class="badge bg-green-lt">
                                        <i class="ti ti-qrcode me-1"></i>
                                        Activo
                                    </span>
                                @elseif(!$guardian->photo_url)
                                    <span class="badge bg-yellow-lt">Requiere foto</span>
                                @else
                                    <span class="badge bg-secondary-lt">Sin QR</span>
                                @endif
                            </td>

                            <td>
                                {{ $guardian->last_access_at
                                    ? \Illuminate\Support\Carbon::parse($guardian->last_access_at)->format('d/m/Y H:i')
                                    : 'Sin movimientos' }}
                            </td>

                            <td>
                                @if($guardian->status === 'active')
                                    <span class="badge bg-success-lt">Activo</span>
                                @elseif($guardian->status === 'blocked')
                                    <span class="badge bg-danger-lt">Bloqueado</span>
                                @else
                                    <span class="badge bg-secondary-lt">Inactivo</span>
                                @endif
                            </td>

                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a
                                        href="{{ route('admin.guardians.show', $guardian->id) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Ver
                                    </a>

                                    <a
                                        href="{{ route('admin.guardians.edit', $guardian->id) }}"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Editar tutor"
                                    >
                                        <i class="ti ti-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-5">
                                No hay tutores que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($guardians->hasPages())
            <div class="card-footer">
                {{ $guardians->links() }}
            </div>
        @endif
    </div>
@endsection
