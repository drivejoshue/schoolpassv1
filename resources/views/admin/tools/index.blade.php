@extends('layouts.app')

@section('title', 'Configuración y herramientas | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Configuración y herramientas')

@section('content')
    @include('admin.partials.tools-nav')

    <div class="alert alert-info">
        <div class="d-flex">
            <div><i class="ti ti-building-community me-2"></i></div>
            <div>
                <div class="fw-bold">{{ $school->name }}</div>
                <div>Las acciones de esta sección se aplican únicamente a esta institución.</div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto"><span class="bg-blue text-white avatar"><i class="ti ti-school"></i></span></div>
                        <div class="col">
                            <div class="font-weight-medium">{{ $stats['students'] }}</div>
                            <div class="text-secondary">Alumnos activos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto"><span class="bg-green text-white avatar"><i class="ti ti-users"></i></span></div>
                        <div class="col">
                            <div class="font-weight-medium">{{ $stats['guardians'] }}</div>
                            <div class="text-secondary">Tutores activos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto"><span class="bg-purple text-white avatar"><i class="ti ti-users-group"></i></span></div>
                        <div class="col">
                            <div class="font-weight-medium">{{ $stats['groups'] }}</div>
                            <div class="text-secondary">Grupos activos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto"><span class="bg-orange text-white avatar"><i class="ti ti-building"></i></span></div>
                        <div class="col">
                            <div class="font-weight-medium">{{ $stats['campuses'] }}</div>
                            <div class="text-secondary">Planteles activos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <span class="avatar bg-blue-lt mb-3"><i class="ti ti-file-import"></i></span>
                    <h3 class="card-title">Importación masiva</h3>
                    <p class="text-secondary">Carga alumnos y tutores desde CSV, revisa errores y confirma antes de guardar.</p>
                    <a href="{{ route('admin.imports.students.index') }}" class="btn btn-primary">Abrir importador</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <span class="avatar bg-purple-lt mb-3"><i class="ti ti-calendar-stats"></i></span>
                    <h3 class="card-title">Ciclo escolar</h3>
                    <p class="text-secondary">Administra ciclos, fechas de operación y el calendario institucional.</p>
                    <a href="{{ route('admin.cycles.index') }}" class="btn btn-outline-primary">Administrar ciclos</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <span class="avatar bg-green-lt mb-3"><i class="ti ti-users-group"></i></span>
                    <h3 class="card-title">Grupos y horarios</h3>
                    <p class="text-secondary">Consulta grupos y configura horarios de entrada, tolerancia y salida.</p>
                    <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-primary">Ver grupos</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <span class="avatar bg-orange-lt mb-3"><i class="ti ti-device-tablet"></i></span>
                    <h3 class="card-title">Dispositivos</h3>
                    <p class="text-secondary">Administra tablets, kioscos y asignaciones de personal de acceso.</p>
                    <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-primary">Ver dispositivos</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <span class="avatar bg-red-lt mb-3"><i class="ti ti-map-pin-cog"></i></span>
                    <h3 class="card-title">Áreas y permisos</h3>
                    <p class="text-secondary">Configura accesos generales, áreas restringidas y reglas por alumno o grupo.</p>
                    <a href="{{ route('admin.areas.index') }}" class="btn btn-outline-primary">Administrar áreas</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <span class="avatar bg-cyan-lt mb-3"><i class="ti ti-id-badge-2"></i></span>
                    <h3 class="card-title">Credenciales</h3>
                    <p class="text-secondary">Genera credenciales faltantes y prepara lotes para impresión.</p>
                    <a href="{{ route('admin.credentials.index') }}" class="btn btn-outline-primary">Administrar credenciales</a>
                </div>
            </div>
        </div>
    </div>
@endsection
