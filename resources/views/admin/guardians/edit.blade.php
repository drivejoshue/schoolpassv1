@extends('layouts.app')

@section('title', 'Editar tutor | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Editar tutor')

@section('topbar-actions')
    <a
        href="{{ route('admin.guardians.show', $guardianRow->id) }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Expediente
    </a>
@endsection

@section('content')
    <div class="row row-cards">
        <div class="col-xl-8">
            <form
                method="POST"
                action="{{ route('admin.guardians.update', $guardianRow->id) }}"
                class="card"
            >
                @csrf
                @method('PUT')

                <div class="card-header">
                    <div>
                        <h3 class="card-title">Datos generales</h3>
                        <div class="text-secondary">
                            Actualiza la información administrativa del tutor.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <i class="ti ti-alert-circle me-2"></i>
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Nombre</label>
                            <input
                                type="text"
                                name="first_name"
                                value="{{ old('first_name', $guardianRow->first_name) }}"
                                class="form-control"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Apellidos</label>
                            <input
                                type="text"
                                name="last_name"
                                value="{{ old('last_name', $guardianRow->last_name) }}"
                                class="form-control"
                                maxlength="150"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input
                                type="tel"
                                name="phone"
                                value="{{ old('phone', $guardianRow->phone) }}"
                                class="form-control"
                                maxlength="30"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Correo electrónico</label>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email', $guardianRow->email) }}"
                                class="form-control"
                                maxlength="150"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Estado administrativo</label>
                            <select name="status" class="form-select" required>
                                <option
                                    value="active"
                                    @selected(old('status', $guardianRow->status) === 'active')
                                >
                                    Activo
                                </option>
                                <option
                                    value="inactive"
                                    @selected(old('status', $guardianRow->status) === 'inactive')
                                >
                                    Inactivo
                                </option>
                                <option
                                    value="blocked"
                                    @selected(old('status', $guardianRow->status) === 'blocked')
                                >
                                    Bloqueado
                                </option>
                            </select>

                            <div class="form-hint">
                                Al dejarlo inactivo o bloqueado se bloquea su cuenta y se revocan sus QR activos.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a
                        href="{{ route('admin.guardians.show', $guardianRow->id) }}"
                        class="btn btn-outline-secondary"
                    >
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        @if($guardianRow->photo_url)
                            <span
                                class="avatar avatar-xl me-3"
                                style="background-image: url('{{ $guardianRow->photo_url }}')"
                            ></span>
                        @else
                            <span class="avatar avatar-xl bg-blue-lt me-3">
                                {{ mb_strtoupper(mb_substr($guardianRow->first_name, 0, 1)) }}
                            </span>
                        @endif

                        <div>
                            <div class="h3 mb-1">
                                {{ trim($guardianRow->first_name.' '.$guardianRow->last_name) }}
                            </div>
                            <div class="text-secondary">
                                {{ $guardianRow->access_username ?? 'Sin cuenta Family' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning mt-3">
                <i class="ti ti-shield-lock me-2"></i>
                Bloquear el tutor no elimina su expediente ni sus vínculos históricos.
            </div>
        </div>
    </div>
@endsection
