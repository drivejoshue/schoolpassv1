@extends('layouts.app')

@section('title', 'Editar dispositivo | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Editar dispositivo')

@section('topbar-actions')
    @if(in_array($deviceRow->device_type, ['kiosk', 'scanner', 'door_controller'], true))
        <a
            href="{{ route('kiosk.access', ['device_uuid' => $deviceRow->device_uuid]) }}"
            class="btn btn-primary btn-sm"
            target="_blank"
        >
            <i class="ti ti-external-link me-1"></i>
            Abrir
        </a>
    @endif

    <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i>
        Dispositivos
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
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row row-cards">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('admin.devices.update', $deviceRow->id) }}" class="card">
                @csrf
                @method('PUT')

                <div class="card-header">
                    <h3 class="card-title">Datos del dispositivo</h3>
                </div>

                <div class="card-body">
                    @include('admin.devices.partials.form', ['deviceRow' => $deviceRow])
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>
                        Actualizar dispositivo
                    </button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Cuenta operativa</h3>
                </div>

                <div class="card-body">
                    @if($assignedUser ?? false)
                        <div class="alert alert-success">
                            <i class="ti ti-circle-check me-2"></i>
                            Usuario operativo asignado.
                        </div>

                        <div class="mb-3">
                            <div class="text-secondary small">Nombre</div>
                            <div class="fw-bold">{{ $assignedUser->name }}</div>
                        </div>

                        <div class="mb-3">
                            <div class="text-secondary small">Correo operativo</div>
                            <div class="fw-bold text-break">{{ $assignedUser->email }}</div>
                        </div>

                        <div class="mb-3">
                            <div class="text-secondary small">Rol</div>
                            <span class="badge bg-blue-lt">{{ $assignedUser->role }}</span>
                        </div>

                        <form method="POST" action="{{ route('admin.devices.password.reset', $deviceRow->id) }}">
                            @csrf
                            @method('PATCH')

                            <div class="mb-3">
                                <label class="form-label">Nueva contraseña</label>
                                <input
                                    type="text"
                                    name="password"
                                    class="form-control"
                                    placeholder="Mínimo 8 caracteres"
                                    required
                                >
                            </div>

                            <button class="btn btn-outline-primary w-100">
                                <i class="ti ti-key me-1"></i>
                                Actualizar contraseña
                            </button>
                        </form>
                    @else
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-triangle me-2"></i>
                            Este dispositivo aún no tiene usuario operativo.
                        </div>

                        <form method="POST" action="{{ route('admin.devices.account.create', $deviceRow->id) }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Correo operativo</label>
                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    value="{{ old('email', 'scanner.' . \Illuminate\Support\Str::slug($deviceRow->name, '.') . '@demo.test') }}"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Contraseña inicial</label>
                                <input
                                    type="text"
                                    name="password"
                                    class="form-control"
                                    value="{{ old('password', '12345678') }}"
                                    required
                                >
                            </div>

                            <button class="btn btn-primary w-100">
                                <i class="ti ti-user-check me-1"></i>
                                Crear y asignar cuenta
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Operación</h3>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-secondary small">URL de operación</div>
                        <input
                            type="text"
                            class="form-control"
                            readonly
                            value="{{ route('kiosk.access', ['device_uuid' => $deviceRow->device_uuid]) }}"
                        >
                    </div>

                    @if(in_array($deviceRow->device_type, ['kiosk', 'scanner', 'door_controller'], true))
                        <a
                            href="{{ route('kiosk.access', ['device_uuid' => $deviceRow->device_uuid]) }}"
                            class="btn btn-primary w-100"
                            target="_blank"
                        >
                            <i class="ti ti-external-link me-1"></i>
                            Abrir dispositivo
                        </a>
                    @else
                        <div class="text-secondary">
                            Este tipo de dispositivo no abre pantalla de kiosco.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection