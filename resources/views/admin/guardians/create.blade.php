@extends('layouts.app')

@section('title', 'Nuevo tutor | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Nuevo tutor')

@section('topbar-actions')
    <a
        href="{{ route('admin.guardians.index') }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Tutores
    </a>
@endsection

@section('content')
    <div class="row row-cards">
        <div class="col-xl-8">
            <form
                method="POST"
                action="{{ route('admin.guardians.store') }}"
                class="card"
            >
                @csrf

                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Datos del tutor
                        </h3>

                        <div class="text-secondary">
                            Registra al tutor y, si corresponde, genera su
                            acceso a la app de padres.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <div class="d-flex">
                                <div>
                                    <i class="ti ti-alert-circle icon alert-icon"></i>
                                </div>

                                <div>
                                    <h4 class="alert-title">
                                        Revisa la información
                                    </h4>

                                    <div class="text-secondary">
                                        {{ $errors->first() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">
                                Nombre
                            </label>

                            <input
                                type="text"
                                name="first_name"
                                value="{{ old('first_name') }}"
                                class="form-control"
                                required
                                maxlength="100"
                                autocomplete="given-name"
                                placeholder="Nombre"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">
                                Apellidos
                            </label>

                            <input
                                type="text"
                                name="last_name"
                                value="{{ old('last_name') }}"
                                class="form-control"
                                required
                                maxlength="150"
                                autocomplete="family-name"
                                placeholder="Apellidos"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Teléfono
                            </label>

                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-phone"></i>
                                </span>

                                <input
                                    type="tel"
                                    name="phone"
                                    value="{{ old('phone') }}"
                                    class="form-control"
                                    maxlength="30"
                                    autocomplete="tel"
                                    inputmode="tel"
                                    placeholder="2290000000"
                                >
                            </div>

                            <div class="form-hint">
                                Se usa para enviar el acceso por WhatsApp.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Correo electrónico
                            </label>

                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="ti ti-mail"></i>
                                </span>

                                <input
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    class="form-control"
                                    maxlength="150"
                                    autocomplete="email"
                                    placeholder="correo@ejemplo.com"
                                >
                            </div>

                            <div class="form-hint">
                                Es opcional. Puede usarse para enviar el acceso
                                y para recuperación futura.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">
                                Estado del tutor
                            </label>

                            <select
                                name="status"
                                class="form-select"
                                required
                            >
                                <option
                                    value="active"
                                    @selected(
                                        old('status', 'active') === 'active'
                                    )
                                >
                                    Activo
                                </option>

                                <option
                                    value="inactive"
                                    @selected(
                                        old('status') === 'inactive'
                                    )
                                >
                                    Inactivo
                                </option>

                                <option
                                    value="blocked"
                                    @selected(
                                        old('status') === 'blocked'
                                    )
                                >
                                    Bloqueado
                                </option>
                            </select>

                            <div class="form-hint">
                                Un tutor inactivo o bloqueado no tendrá acceso
                                habilitado aunque se genere una cuenta.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Acceso a la app
                            </label>

                            <label
                                class="form-check form-switch
                                       border rounded p-3 ps-5"
                            >
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="create_user"
                                    value="1"
                                    id="create-user"
                                    @checked(old('create_user'))
                                >

                                <span class="form-check-label">
                                    <span class="fw-semibold d-block">
                                        Generar cuenta para la app de padres
                                    </span>

                                    <span class="text-secondary small">
                                        SchoolPass creará automáticamente un
                                        usuario único y una contraseña temporal.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="col-12">
                            <div
                                class="alert alert-info mb-0"
                                id="account-help"
                            >
                                <div class="d-flex">
                                    <div>
                                        <i class="ti ti-key icon alert-icon"></i>
                                    </div>

                                    <div>
                                        <h4 class="alert-title">
                                            Entrega de credenciales
                                        </h4>

                                        <div class="text-secondary">
                                            Al guardar, el sistema mostrará una
                                            sola vez el usuario y la contraseña
                                            temporal. Podrás copiarlos o enviarlos
                                            por WhatsApp o correo desde la ficha
                                            del tutor.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a
                        href="{{ route('admin.guardians.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>
                        Guardar tutor
                    </button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Flujo recomendado
                    </h3>
                </div>

                <div class="card-body">
                    <div class="steps steps-vertical">
                        <div class="step-item">
                            <div class="h4 m-0">
                                1. Registrar tutor
                            </div>

                            <div class="text-secondary">
                                Captura nombre, teléfono y correo cuando estén
                                disponibles.
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="h4 m-0">
                                2. Generar acceso
                            </div>

                            <div class="text-secondary">
                                Activa la opción para crear usuario y contraseña
                                automáticamente.
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="h4 m-0">
                                3. Vincular alumnos
                            </div>

                            <div class="text-secondary">
                                Desde la ficha del tutor asigna alumnos y
                                permisos.
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="h4 m-0">
                                4. Entregar credenciales
                            </div>

                            <div class="text-secondary">
                                Envía el acceso por WhatsApp, correo o copia
                                manual.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <div class="d-flex">
                        <span class="avatar bg-green-lt me-3">
                            <i class="ti ti-shield-check"></i>
                        </span>

                        <div>
                            <div class="fw-semibold">
                                Contraseña segura
                            </div>

                            <div class="text-secondary">
                                No se reutiliza una contraseña general.
                                Cada tutor recibe una contraseña temporal única.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection