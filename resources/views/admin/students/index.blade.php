@extends('layouts.app')

@section('title', 'Alumnos | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Alumnos')

@section('topbar-actions')
    <a
        href="{{ route('admin.imports.students.index') }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-file-import me-1"></i>
        Importar alumnos
    </a>

    <a
        href="{{ route('admin.students.create') }}"
        class="btn btn-primary btn-sm"
    >
        <i class="ti ti-user-plus me-1"></i>
        Nuevo alumno
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

    @if($activeCycle)
        <div class="alert alert-info">
            <i class="ti ti-calendar-check me-2"></i>

            Ciclo operativo:

            <strong>
                {{ $activeCycle->name }}
            </strong>

            ·

            {{ \Illuminate\Support\Carbon::parse(
                $activeCycle->starts_on
            )->format('d/m/Y') }}

            al

            {{ \Illuminate\Support\Carbon::parse(
                $activeCycle->ends_on
            )->format('d/m/Y') }}

            <div class="small mt-1">
                El nivel y grupo mostrados corresponden a la inscripción
                del ciclo activo. Cuando el alumno no tiene inscripción
                vigente, se muestra su última asignación conocida.
            </div>
        </div>
    @else
        <div class="alert alert-warning">
            <i class="ti ti-calendar-off me-2"></i>

            <strong>No hay ciclo escolar activo.</strong>

            Puedes consultar alumnos y credenciales, pero no realizar
            inscripciones ni cambios de grupo hasta activar un ciclo.
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    Alumnos registrados
                </h3>

                <p class="card-subtitle">
                    Administración de alumnos, inscripciones,
                    grupos y credenciales.
                </p>
            </div>
        </div>

        <div class="card-body">
            <form
                method="GET"
                action="{{ route('admin.students.index') }}"
                class="row g-2"
            >
                <div class="col-md-9">
                    <div class="input-icon">
                        <span class="input-icon-addon">
                            <i class="ti ti-search"></i>
                        </span>

                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Buscar por nombre, matrícula o grupo"
                        >
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-fill">
                            <i class="ti ti-search me-1"></i>
                            Buscar
                        </button>

                        @if($search !== '')
                            <a
                                href="{{ route('admin.students.index') }}"
                                class="btn btn-outline-secondary"
                                title="Limpiar búsqueda"
                            >
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Matrícula</th>
                        <th>Nivel</th>
                        <th>Grupo</th>
                        <th>Inscripción</th>
                        <th>Estado</th>
                        <th>Credenciales</th>
                        <th class="w-1">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($student->photo_url)
                                        <span
                                            class="avatar avatar-sm me-2"
                                            style="background-image: url('{{
                                                asset($student->photo_url)
                                            }}')"
                                        ></span>
                                    @else
                                        <span class="avatar avatar-sm bg-blue-lt me-2">
                                            {{ strtoupper(
                                                mb_substr(
                                                    $student->first_name,
                                                    0,
                                                    1
                                                )
                                            ) }}
                                        </span>
                                    @endif

                                    <div>
                                        <div class="fw-bold">
                                            {{ $student->first_name }}
                                            {{ $student->last_name }}
                                        </div>

                                        <div class="text-secondary small">
                                            ID #{{ $student->id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="fw-semibold">
                                    {{ $student->student_code }}
                                </span>
                            </td>

                            <td>
                                {{ $student->level_name ?? 'Sin nivel' }}
                            </td>

                            <td>
                                @if($student->group_name)
                                    <div class="fw-semibold">
                                        {{ $student->group_name }}
                                    </div>
                                @else
                                    <span class="text-secondary">
                                        Sin grupo
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if($activeCycle)
                                    @if($student->active_enrollment_id)
                                        @switch($student->enrollment_status)
                                            @case('active')
                                                <span class="badge bg-success-lt">
                                                    Vigente
                                                </span>
                                                @break

                                            @case('withdrawn')
                                                <span class="badge bg-danger-lt">
                                                    Baja
                                                </span>
                                                @break

                                            @case('graduated')
                                                <span class="badge bg-blue-lt">
                                                    Egresado
                                                </span>
                                                @break

                                            @case('completed')
                                                <span class="badge bg-secondary-lt">
                                                    Completada
                                                </span>
                                                @break

                                            @default
                                                <span class="badge bg-warning-lt">
                                                    {{
                                                        ucfirst(
                                                            $student->enrollment_status
                                                            ?? 'Sin estado'
                                                        )
                                                    }}
                                                </span>
                                        @endswitch

                                        @if($student->enrollment_type)
                                            <div class="text-secondary small mt-1">
                                                @switch($student->enrollment_type)
                                                    @case('new')
                                                        Nuevo ingreso
                                                        @break

                                                    @case('reenrollment')
                                                        Reinscripción
                                                        @break

                                                    @case('promotion')
                                                        Promoción
                                                        @break

                                                    @case('repeat')
                                                        Repetidor
                                                        @break

                                                    @case('transfer')
                                                        Transferencia
                                                        @break

                                                    @default
                                                        {{
                                                            ucfirst(
                                                                $student->enrollment_type
                                                            )
                                                        }}
                                                @endswitch
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge bg-warning-lt">
                                            Sin inscripción
                                        </span>

                                        <div class="text-secondary small mt-1">
                                            No pertenece al ciclo activo
                                        </div>
                                    @endif
                                @else
                                    <span class="badge bg-secondary-lt">
                                        Sin ciclo activo
                                    </span>
                                @endif
                            </td>

                            <td>
                                @switch($student->status)
                                    @case('active')
                                        <span class="badge bg-success-lt">
                                            Activo
                                        </span>
                                        @break

                                    @case('suspended')
                                        <span class="badge bg-warning-lt">
                                            Suspendido
                                        </span>
                                        @break

                                    @case('withdrawn')
                                        <span class="badge bg-danger-lt">
                                            Baja
                                        </span>
                                        @break

                                    @case('graduated')
                                        <span class="badge bg-blue-lt">
                                            Egresado
                                        </span>
                                        @break

                                    @case('temporary')
                                        <span class="badge bg-orange-lt">
                                            Temporal
                                        </span>
                                        @break

                                    @default
                                        <span class="badge bg-secondary-lt">
                                            {{ ucfirst($student->status) }}
                                        </span>
                                @endswitch
                            </td>

                            <td>
                                <span class="badge bg-blue-lt">
                                    {{ number_format(
                                        $student->active_credentials_count
                                    ) }}
                                    {{
                                        (int) $student->active_credentials_count === 1
                                            ? 'activa'
                                            : 'activas'
                                    }}
                                </span>
                            </td>

                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a
                                        href="{{ route(
                                            'admin.students.show',
                                            $student->id
                                        ) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        <i class="ti ti-eye me-1"></i>
                                        Ver
                                    </a>

                                    <a
                                        href="{{ route(
                                            'admin.students.manage',
                                            $student->id
                                        ) }}"
                                        class="btn btn-sm btn-primary"
                                    >
                                        <i class="ti ti-user-cog me-1"></i>
                                        Gestionar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="8"
                                class="text-center text-secondary py-5"
                            >
                                @if($search !== '')
                                    No se encontraron alumnos que coincidan
                                    con la búsqueda.
                                @else
                                    No hay alumnos registrados.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($students->hasPages())
            <div class="card-footer">
                {{ $students->links() }}
            </div>
        @endif
    </div>
@endsection