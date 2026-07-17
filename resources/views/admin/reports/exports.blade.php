@extends('layouts.app')

@section('title', 'Exportaciones | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Centro de exportaciones')

@section('topbar-actions')
    <a href="{{ route('admin.reports.attendance') }}"
       class="btn btn-outline-primary btn-sm">
        <i class="ti ti-calendar-stats me-1"></i>
        Asistencia
    </a>

    <a href="{{ route('admin.reports.access') }}"
       class="btn btn-outline-primary btn-sm">
        <i class="ti ti-list-details me-1"></i>
        Accesos
    </a>

    <a
    href="{{ route('admin.reports.analytics.index') }}"
    class="btn btn-outline-primary btn-sm"
>
    <i class="ti ti-chart-bar me-1"></i>
    Analítica
</a>
@endsection

@section('content')
    <div class="alert alert-info">
        <i class="ti ti-file-spreadsheet me-2"></i>
        Los archivos se generan en Excel y contienen únicamente información
        de la escuela activa.
    </div>

    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card h-100">
                <form method="GET"
                      action="{{ route('admin.reports.exports.students') }}">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Alumnos</h3>
                            <p class="card-subtitle">
                                Directorio con matrícula, plantel, nivel y grupo.
                            </p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Plantel</label>
                                <select name="campus_id" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($campuses as $campus)
                                        <option value="{{ $campus->id }}">
                                            {{ $campus->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Grupo</label>
                                <select name="group_id" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}">
                                            {{ $group->level_name }}
                                            · {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="active">Activo</option>
                                    <option value="suspended">Suspendido</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer text-end">
                        <button class="btn btn-success">
                            <i class="ti ti-file-spreadsheet me-1"></i>
                            Exportar alumnos
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <form method="GET"
                      action="{{ route('admin.reports.exports.guardians') }}">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Tutores</h3>
                            <p class="card-subtitle">
                                Directorio, contacto y estado de cuenta.
                            </p>
                        </div>
                    </div>

                    <div class="card-body">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                            <option value="suspended">Suspendido</option>
                        </select>
                    </div>

                    <div class="card-footer text-end">
                        <button class="btn btn-success">
                            <i class="ti ti-file-spreadsheet me-1"></i>
                            Exportar tutores
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <form method="GET"
                      action="{{ route('admin.reports.exports.relationships') }}">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Alumno–tutor</h3>
                            <p class="card-subtitle">
                                Vínculos, parentesco y permisos familiares.
                            </p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Plantel</label>
                                <select name="campus_id" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($campuses as $campus)
                                        <option value="{{ $campus->id }}">
                                            {{ $campus->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Grupo</label>
                                <select name="group_id" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}">
                                            {{ $group->level_name }}
                                            · {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">
                                    Estado del vínculo
                                </label>

                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer text-end">
                        <button class="btn btn-success">
                            <i class="ti ti-file-spreadsheet me-1"></i>
                            Exportar relaciones
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <form method="GET"
                      action="{{ route('admin.reports.exports.attendance') }}">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Asistencia diaria</h3>
                            <p class="card-subtitle">
                                Puntualidad, retardos, ausencias y salidas.
                            </p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Fecha</label>
                                <input type="date"
                                       name="date"
                                       value="{{ now()->toDateString() }}"
                                       class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Grupo</label>
                                <select name="group_id" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}">
                                            {{ $group->level_name }}
                                            · {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="on_time">Puntual</option>
                                    <option value="late">Retardo</option>
                                    <option value="very_late">
                                        Extemporáneo
                                    </option>
                                    <option value="absent">Ausente</option>
                                    <option value="no_class">Sin clase</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer text-end">
                        <button class="btn btn-success">
                            <i class="ti ti-file-spreadsheet me-1"></i>
                            Exportar asistencia
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <form method="GET"
                      action="{{ route('admin.reports.exports.access') }}">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Bitácora de accesos</h3>
                            <p class="card-subtitle">
                                Entradas, salidas, dispositivos y decisiones.
                            </p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Desde</label>
                                <input type="date"
                                       name="from"
                                       value="{{ now()->toDateString() }}"
                                       class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Hasta</label>
                                <input type="date"
                                       name="to"
                                       value="{{ now()->toDateString() }}"
                                       class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Grupo</label>
                                <select name="group_id" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}">
                                            {{ $group->level_name }}
                                            · {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Área</label>
                                <select name="area_id" class="form-select">
                                    <option value="">Todas</option>
                                    @foreach($areas as $area)
                                        <option value="{{ $area->id }}">
                                            {{ $area->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Dispositivo</label>
                                <select name="device_id" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($devices as $device)
                                        <option value="{{ $device->id }}">
                                            {{ $device->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Evento</label>
                                <select name="event_type" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="entry">Entrada</option>
                                    <option value="exit">Salida</option>
                                    <option value="access">Acceso</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select name="event_status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="on_time">Puntual</option>
                                    <option value="late">Retardo</option>
                                    <option value="very_late">
                                        Extemporáneo
                                    </option>
                                    <option value="duplicate">Duplicado</option>
                                    <option value="allowed">Autorizado</option>
                                    <option value="denied">Denegado</option>
                                    <option value="normal_exit">
                                        Salida normal
                                    </option>
                                    <option value="early_exit">
                                        Salida anticipada
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer text-end">
                        <button class="btn btn-success">
                            <i class="ti ti-file-spreadsheet me-1"></i>
                            Exportar bitácora
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection