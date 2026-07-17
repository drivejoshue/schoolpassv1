@extends('layouts.app')

@section('title', 'Nuevo ciclo | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Nuevo ciclo escolar')

@section('topbar-actions')
    <a
        href="{{ route('admin.cycles.index') }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Ciclos escolares
    </a>
@endsection

@section('content')
    <div class="row row-cards">
        <div class="col-xl-8">
            <form
                method="POST"
                action="{{ route('admin.cycles.store') }}"
                class="card"
            >
                @csrf

                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Datos del ciclo escolar
                        </h3>

                        <p class="card-subtitle">
                            Define el periodo académico que después
                            utilizará matrícula, grupos y asistencia.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="ti ti-info-circle me-2"></i>

                        <strong>
                            El ciclo se creará como borrador.
                        </strong>

                        Crear el ciclo no lo convierte todavía en
                        el periodo operativo de la escuela.
                    </div>

                    @include(
                        'admin.cycles.partials.form',
                        [
                            'cycleRow' => null,
                        ]
                    )
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a
                        href="{{ route('admin.cycles.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>
                        Crear como borrador
                    </button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        Proceso recomendado
                    </h3>
                </div>

                <div class="card-body">
                    <div class="steps steps-vertical">
                        <div class="step-item active">
                            <div class="h4 m-0">
                                1. Crear ciclo
                            </div>

                            <div class="text-secondary">
                                Define nombre y fechas.
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="h4 m-0">
                                2. Preparar grupos
                            </div>

                            <div class="text-secondary">
                                Copia o configura grupos y horarios.
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="h4 m-0">
                                3. Preparar matrícula
                            </div>

                            <div class="text-secondary">
                                Inscribe alumnos individualmente
                                o por grupo.
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="h4 m-0">
                                4. Activar ciclo
                            </div>

                            <div class="text-secondary">
                                Se convierte en el periodo oficial
                                de acceso y asistencia.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="ti ti-alert-triangle me-2"></i>

                Solo puede existir un ciclo activo por escuela.
                Para activar el nuevo, el ciclo anterior debe
                estar cerrado.
            </div>

            <div class="alert alert-light">
                <i class="ti ti-shield-check me-2"></i>

                Un ciclo en borrador puede recibir grupos,
                horarios e inscripciones sin afectar la operación
                del ciclo actualmente activo.
            </div>
        </div>
    </div>
@endsection