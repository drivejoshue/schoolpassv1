@extends('layouts.app')

@section('title', 'Alumno | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Ficha integral del alumno')

@section('topbar-actions')
    <a
        href="{{ route(
            'admin.students.manage',
            $studentRow->id
        ) }}"
        class="btn btn-primary btn-sm"
    >
        <i class="ti ti-user-cog me-1"></i>
        Gestionar alumno
    </a>

    <a
        href="{{ route(
            'admin.reports.student-individual.index',
            [
                'student_id' => $studentRow->id,
            ]
        ) }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-chart-bar me-1"></i>
        Reporte individual
    </a>

    <a
        href="{{ route('admin.students.index') }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Alumnos
    </a>
@endsection

@section('content')
    @php
        $studentStatusLabels = [
            'active' => 'Activo',
            'suspended' => 'Suspendido',
            'withdrawn' => 'Baja',
            'graduated' => 'Egresado',
            'temporary' => 'Temporal',
            'inactive' => 'Inactivo',
        ];

        $studentStatusColors = [
            'active' => 'success',
            'suspended' => 'warning',
            'withdrawn' => 'danger',
            'graduated' => 'blue',
            'temporary' => 'orange',
            'inactive' => 'secondary',
        ];

        $enrollmentStatusLabels = [
            'active' => 'Vigente',
            'completed' => 'Completada',
            'withdrawn' => 'Baja',
            'transferred' => 'Transferencia',
            'not_reenrolled' => 'No reinscrito',
            'graduated' => 'Egresado',
        ];

        $enrollmentTypeLabels = [
            'new' => 'Nuevo ingreso',
            'reenrollment' => 'Reinscripción',
            'promotion' => 'Promoción',
            'repeat' => 'Repetidor',
            'transfer' => 'Transferencia',
        ];

        $movementLabels = [
            'initial_assignment' => 'Asignación inicial',
            'group_change' => 'Cambio de grupo',
            'campus_change' => 'Cambio de plantel',
            'administrative_correction' => 'Corrección administrativa',
            'late_enrollment' => 'Inscripción tardía',
            'reactivation' => 'Reactivación',
        ];

        $attendanceLabels = [
            'present' => 'Presente',
            'on_time' => 'Puntual',
            'present_on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Extemporáneo',
            'extemporaneous' => 'Extemporáneo',
            'extemporaneo' => 'Extemporáneo',
            'absent' => 'Ausente',
            'no_class' => 'Sin clase',
            'unknown' => 'Sin clasificar',
        ];

        $attendanceColors = [
            'present' => 'success',
            'on_time' => 'success',
            'present_on_time' => 'success',
            'late' => 'warning',
            'very_late' => 'orange',
            'extemporaneous' => 'orange',
            'extemporaneo' => 'orange',
            'absent' => 'danger',
            'no_class' => 'secondary',
            'unknown' => 'secondary',
        ];

        $eventLabels = [
            'entry' => 'Entrada',
            'exit' => 'Salida',
            'access' => 'Acceso',
        ];

        $formatDate = function ($value) {
            return $value
                ? \Illuminate\Support\Carbon::parse(
                    $value
                )->format('d/m/Y')
                : '—';
        };

        $formatDateTime = function ($value) {
            return $value
                ? \Illuminate\Support\Carbon::parse(
                    $value
                )->format('d/m/Y H:i:s')
                : '—';
        };

        $formatTime = function ($value) {
            return $value
                ? \Illuminate\Support\Carbon::parse(
                    $value
                )->format('H:i')
                : '—';
        };
    @endphp

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

    @if(! $activeCycle)
        <div class="alert alert-warning">
            <i class="ti ti-calendar-off me-2"></i>

            <strong>No existe un ciclo activo.</strong>

            La ficha conserva datos personales, tutores, credenciales y
            accesos, pero no existe una inscripción académica operativa.
        </div>
    @elseif(! $activeEnrollment)
        <div class="alert alert-warning">
            <i class="ti ti-user-exclamation me-2"></i>

            <strong>
                El alumno no tiene inscripción en el ciclo activo
                {{ $activeCycle->name }}.
            </strong>

            Usa “Gestionar alumno” para asignarlo a un grupo.
        </div>
    @else
        <div class="alert alert-info">
            <i class="ti ti-calendar-check me-2"></i>

            Ciclo operativo:

            <strong>{{ $activeCycle->name }}</strong>

            · Inscripción:

            <strong>
                {{ $enrollmentStatusLabels[
                    $activeEnrollment->status
                ] ?? ucfirst($activeEnrollment->status) }}
            </strong>

            · Grupo:

            <strong>
                {{ $activeEnrollment->level_name ?? 'Sin nivel' }}
                ·
                {{ $activeEnrollment->group_name ?? 'Sin grupo' }}
            </strong>
        </div>
    @endif

    <div class="row row-cards mb-3">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    @if($studentRow->photo_url)
                        <span
                            class="avatar avatar-2xl mb-3"
                            style="background-image: url('{{
                                asset($studentRow->photo_url)
                            }}')"
                        ></span>
                    @else
                        <span class="avatar avatar-2xl bg-blue-lt mb-3">
                            {{ strtoupper(
                                mb_substr(
                                    $studentRow->first_name,
                                    0,
                                    1
                                )
                            ) }}
                        </span>
                    @endif

                    <h2 class="mb-1">
                        {{ $studentRow->first_name }}
                        {{ $studentRow->last_name }}
                    </h2>

                    <div class="text-secondary">
                        Matrícula:
                        <strong>
                            {{ $studentRow->student_code }}
                        </strong>
                    </div>

                    <div class="mt-3">
                        <span class="badge bg-{{
                            $studentStatusColors[
                                $studentRow->status
                            ] ?? 'secondary'
                        }}-lt">
                            {{ $studentStatusLabels[
                                $studentRow->status
                            ] ?? ucfirst($studentRow->status)
                            }}
                        </span>

                        @if($activeEnrollment)
                            <span class="badge bg-blue-lt">
                                Inscripción vigente
                            </span>
                        @endif
                    </div>
                </div>

                <div class="card-body border-top">
                    <div class="mb-3">
                        <div class="text-secondary small">
                            Plantel
                        </div>

                        <div class="fw-bold">
                            {{ $studentRow->campus_name
                                ?? 'Sin plantel'
                            }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-secondary small">
                            Nivel
                        </div>

                        <div class="fw-bold">
                            {{ $studentRow->level_name
                                ?? 'Sin nivel'
                            }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-secondary small">
                            Grupo
                        </div>

                        <div class="fw-bold">
                            {{ $studentRow->group_name
                                ?? 'Sin grupo'
                            }}
                        </div>
                    </div>

                    <div>
                        <div class="text-secondary small">
                            Grado
                        </div>

                        <div class="fw-bold">
                            {{ $studentRow->grade_label
                                ?? 'Sin especificar'
                            }}
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <div class="d-grid gap-2">
                        <a
                            href="{{ route(
                                'admin.students.manage',
                                $studentRow->id
                            ) }}"
                            class="btn btn-primary"
                        >
                            <i class="ti ti-user-cog me-1"></i>
                            Gestionar grupo y estado
                        </a>

                        <a
                            href="{{ route(
                                'admin.reports.student-individual.index',
                                [
                                    'student_id' =>
                                        $studentRow->id,
                                ]
                            ) }}"
                            class="btn btn-outline-primary"
                        >
                            <i class="ti ti-chart-bar me-1"></i>
                            Ver reporte completo
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="row row-cards">
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">
                                Credenciales activas
                            </div>

                            <div class="h2 mb-0">
                                {{ $activeCredentialsCount }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">
                                Entradas recientes
                            </div>

                            <div class="h2 mb-0 text-success">
                                {{ $accessSummary['entries'] }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">
                                Salidas recientes
                            </div>

                            <div class="h2 mb-0 text-blue">
                                {{ $accessSummary['exits'] }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">
                                Accesos denegados
                            </div>

                            <div class="h2 mb-0 text-danger">
                                {{ $accessSummary['denied'] }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">
                                Puntuales
                            </div>

                            <div class="h2 mb-0 text-success">
                                {{ $attendanceSummary['present'] }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">
                                Retardos
                            </div>

                            <div class="h2 mb-0 text-warning">
                                {{ $attendanceSummary['late'] }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">
                                Extemporáneos
                            </div>

                            <div class="h2 mb-0 text-orange">
                                {{ $attendanceSummary['very_late'] }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">
                                Ausencias
                            </div>

                            <div class="h2 mb-0 text-danger">
                                {{ $attendanceSummary['absent'] }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($studentRow->notes)
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">
                            Notas administrativas
                        </h3>
                    </div>

                    <div class="card-body">
                        {{ $studentRow->notes }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Tutores vinculados
                        </h3>

                        <p class="card-subtitle">
                            Contactos, relación y permisos.
                        </p>
                    </div>
                </div>

                <div class="list-group list-group-flush">
                    @forelse($guardians as $guardian)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold">
                                        {{ $guardian->first_name }}
                                        {{ $guardian->last_name }}
                                    </div>

                                    <div class="text-secondary small">
                                        {{ ucfirst(
                                            $guardian->relationship
                                            ?? 'Tutor'
                                        ) }}

                                        @if($guardian->is_primary)
                                            · Tutor principal
                                        @endif
                                    </div>
                                </div>

                                @if($guardian->is_primary)
                                    <span class="badge bg-blue-lt">
                                        Principal
                                    </span>
                                @endif
                            </div>

                            <div class="mt-3 small">
                                <div class="mb-1">
                                    <i class="ti ti-phone me-1"></i>

                                    {{ $guardian->phone
                                        ?: 'Sin teléfono'
                                    }}
                                </div>

                                <div>
                                    <i class="ti ti-mail me-1"></i>

                                    {{ $guardian->email
                                        ?: 'Sin correo'
                                    }}
                                </div>
                            </div>

                            <div class="mt-3 d-flex flex-wrap gap-1">
                                @if($guardian->can_view_attendance)
                                    <span class="badge bg-success-lt">
                                        Ve asistencia
                                    </span>
                                @endif

                                @if($guardian->can_receive_notifications)
                                    <span class="badge bg-blue-lt">
                                        Recibe avisos
                                    </span>
                                @endif

                                @if($guardian->can_authorize_exit)
                                    <span class="badge bg-orange-lt">
                                        Autoriza salida
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-secondary py-4">
                            El alumno no tiene tutores vinculados.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Inscripción vigente
                        </h3>

                        <p class="card-subtitle">
                            Situación del alumno en el ciclo operativo.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    @if($activeCycle && $activeEnrollment)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-secondary small">
                                    Ciclo
                                </div>

                                <div class="fw-bold">
                                    {{ $activeCycle->name }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-secondary small">
                                    Estado
                                </div>

                                <div>
                                    <span class="badge bg-success-lt">
                                        {{ $enrollmentStatusLabels[
                                            $activeEnrollment->status
                                        ] ?? ucfirst(
                                            $activeEnrollment->status
                                        ) }}
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-secondary small">
                                    Tipo
                                </div>

                                <div class="fw-bold">
                                    {{ $enrollmentTypeLabels[
                                        $activeEnrollment->enrollment_type
                                    ] ?? ucfirst(
                                        $activeEnrollment->enrollment_type
                                    ) }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-secondary small">
                                    Fecha de inscripción
                                </div>

                                <div class="fw-bold">
                                    {{ $formatDate(
                                        $activeEnrollment->enrolled_on
                                    ) }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-secondary small">
                                    Plantel
                                </div>

                                <div class="fw-bold">
                                    {{ $activeEnrollment->campus_name
                                        ?? 'Sin plantel'
                                    }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-secondary small">
                                    Grupo
                                </div>

                                <div class="fw-bold">
                                    {{ $activeEnrollment->level_name
                                        ?? 'Sin nivel'
                                    }}

                                    ·

                                    {{ $activeEnrollment->group_name
                                        ?? 'Sin grupo'
                                    }}
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="empty py-4">
                            <p class="empty-title">
                                Sin inscripción operativa
                            </p>

                            <p class="empty-subtitle text-secondary">
                                Asigna al alumno desde la gestión individual.
                            </p>

                            <div class="empty-action">
                                <a
                                    href="{{ route(
                                        'admin.students.manage',
                                        $studentRow->id
                                    ) }}"
                                    class="btn btn-primary"
                                >
                                    Gestionar inscripción
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    Actividad de acceso
                </h3>

                <p class="card-subtitle">
                    Últimas entradas, salidas y accesos registrados.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Evento</th>
                        <th>Resultado</th>
                        <th>Área</th>
                        <th>Dispositivo</th>
                        <th>Origen</th>
                        <th>Razón</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($accessLogs as $log)
                        @php
                            $result = $log->event_status
                                ?? $log->decision
                                ?? $log->action
                                ?? 'registrado';

                            $resultColor = in_array(
                                $result,
                                [
                                    'denied',
                                    'rejected',
                                ],
                                true
                            )
                                ? 'danger'
                                : 'success';
                        @endphp

                        <tr>
                            <td>
                                {{ $formatDateTime(
                                    $log->scanned_at
                                ) }}
                            </td>

                            <td>
                                {{ $eventLabels[
                                    $log->event_type
                                ] ?? ucfirst(
                                    $log->event_type
                                    ?? 'Acceso'
                                ) }}
                            </td>

                            <td>
                                <span class="badge bg-{{
                                    $resultColor
                                }}-lt">
                                    {{ ucfirst($result) }}
                                </span>
                            </td>

                            <td>
                                {{ $log->area_name ?? '—' }}
                            </td>

                            <td>
                                {{ $log->device_name ?? '—' }}
                            </td>

                            <td>
                                {{ $log->source ?? '—' }}
                            </td>

                            <td>
                                {{ $log->reason ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="text-center text-secondary py-5"
                            >
                                No existen eventos de acceso para este alumno.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    Asistencia reciente
                </h3>

                <p class="card-subtitle">
                    Últimos registros consolidados de asistencia.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Retardo</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($attendance as $row)
                        <tr>
                            <td>
                                {{ $formatDate($row->date) }}
                            </td>

                            <td>
                                <span class="badge bg-{{
                                    $attendanceColors[
                                        $row->attendance_status
                                    ] ?? 'secondary'
                                }}-lt">
                                    {{ $attendanceLabels[
                                        $row->attendance_status
                                    ] ?? ucfirst(
                                        $row->attendance_status
                                    ) }}
                                </span>
                            </td>

                            <td>
                                {{ $formatTime($row->entry_at) }}
                            </td>

                            <td>
                                {{ $formatTime($row->exit_at) }}
                            </td>

                            <td>
                                {{ (int) $row->minutes_late > 0
                                    ? $row->minutes_late.' min'
                                    : '—'
                                }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="5"
                                class="text-center text-secondary py-5"
                            >
                                Sin registros recientes de asistencia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Historial de ciclos
                        </h3>

                        <p class="card-subtitle">
                            Inscripciones académicas conservadas.
                        </p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Ciclo</th>
                                <th>Grupo</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Fechas</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($enrollments as $enrollment)
                                <tr>
                                    <td>
                                        <div class="fw-bold">
                                            {{ $enrollment->cycle_name }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ $formatDate(
                                                $enrollment->cycle_starts_on
                                            ) }}

                                            al

                                            {{ $formatDate(
                                                $enrollment->cycle_ends_on
                                            ) }}
                                        </div>
                                    </td>

                                    <td>
                                        <div>
                                            {{ $enrollment->group_name
                                                ?? 'Sin grupo'
                                            }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ $enrollment->level_name
                                                ?? 'Sin nivel'
                                            }}
                                        </div>
                                    </td>

                                    <td>
                                        {{ $enrollmentTypeLabels[
                                            $enrollment->enrollment_type
                                        ] ?? ucfirst(
                                            $enrollment->enrollment_type
                                        ) }}
                                    </td>

                                    <td>
                                        <span class="badge bg-{{
                                            $enrollment->status === 'active'
                                                ? 'success'
                                                : 'secondary'
                                        }}-lt">
                                            {{ $enrollmentStatusLabels[
                                                $enrollment->status
                                            ] ?? ucfirst(
                                                $enrollment->status
                                            ) }}
                                        </span>
                                    </td>

                                    <td>
                                        Inscripción:
                                        {{ $formatDate(
                                            $enrollment->enrolled_on
                                        ) }}

                                        @if($enrollment->completed_on)
                                            <br>

                                            Conclusión:
                                            {{ $formatDate(
                                                $enrollment->completed_on
                                            ) }}
                                        @endif

                                        @if($enrollment->withdrawn_on)
                                            <br>

                                            Baja:
                                            {{ $formatDate(
                                                $enrollment->withdrawn_on
                                            ) }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="5"
                                        class="text-center text-secondary py-5"
                                    >
                                        No existe historial de inscripciones.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Movimientos de grupo
                        </h3>

                        <p class="card-subtitle">
                            Cambios y asignaciones registrados.
                        </p>
                    </div>
                </div>

                <div class="list-group list-group-flush">
                    @forelse($movements as $movement)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div class="fw-bold">
                                    {{ $movementLabels[
                                        $movement->movement_type
                                    ] ?? ucfirst(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $movement->movement_type
                                        )
                                    ) }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $formatDate(
                                        $movement->effective_on
                                    ) }}
                                </div>
                            </div>

                            <div class="mt-2">
                                {{ $movement->from_group_name
                                    ?? 'Sin grupo anterior'
                                }}

                                <i class="ti ti-arrow-right mx-1"></i>

                                {{ $movement->to_group_name
                                    ?? 'Sin grupo destino'
                                }}
                            </div>

                            <div class="text-secondary small mt-1">
                                Ciclo:
                                {{ $movement->cycle_name }}
                            </div>

                            @if($movement->reason)
                                <div class="small mt-2">
                                    {{ $movement->reason }}
                                </div>
                            @endif

                            @if($movement->created_by_name)
                                <div class="text-secondary small mt-1">
                                    Registró:
                                    {{ $movement->created_by_name }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-secondary py-4">
                            No existen movimientos registrados.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    Credenciales
                </h3>

                <p class="card-subtitle">
                    Códigos vigentes, revocados e históricos.
                </p>
            </div>

            <div class="card-actions">
                <form
                    method="POST"
                    action="{{ route(
                        'admin.students.credentials.store',
                        $studentRow->id
                    ) }}"
                >
                    @csrf

                    <button class="btn btn-primary btn-sm">
                        <i class="ti ti-qrcode me-1"></i>
                        Generar credencial
                    </button>
                </form>
            </div>
        </div>

        <div class="card-body">
            <div class="row row-cards">
                @forelse($credentials as $credential)
                    <div class="col-md-6 col-xl-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                @if($credential->public_code)
                                    <div
                                        class="qr-canvas mx-auto mb-3"
                                        data-token="{{
                                            $credential->public_code
                                        }}"
                                        style="width: 180px; height: 180px;"
                                    ></div>
                                @else
                                    <div class="empty py-4">
                                        <i
                                            class="ti ti-qrcode-off"
                                            style="font-size: 54px;"
                                        ></i>
                                    </div>
                                @endif

                                <div class="fw-bold">
                                    {{ $credential->public_code
                                        ?? 'Sin código público'
                                    }}
                                </div>

                                <div class="mt-2">
                                    @if($credential->status === 'active')
                                        <span class="badge bg-success-lt">
                                            Activa
                                        </span>
                                    @elseif($credential->status === 'revoked')
                                        <span class="badge bg-danger-lt">
                                            Revocada
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-lt">
                                            {{ ucfirst(
                                                $credential->status
                                            ) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="text-secondary small mt-2">
                                    Emitida:
                                    {{ $formatDateTime(
                                        $credential->issued_at
                                    ) }}
                                </div>

                                @if($credential->expires_at)
                                    <div class="text-secondary small">
                                        Vence:
                                        {{ $formatDateTime(
                                            $credential->expires_at
                                        ) }}
                                    </div>
                                @endif
                            </div>

                            @if($credential->status === 'active')
                                <div class="card-footer">
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'admin.credentials.revoke',
                                            $credential->id
                                        ) }}"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <button
                                            class="btn btn-outline-danger w-100"
                                            onclick="return confirm(
                                                '¿Revocar esta credencial?'
                                            );"
                                        >
                                            <i class="ti ti-ban me-1"></i>
                                            Revocar credencial
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="empty py-5">
                            <div class="empty-img">
                                <i
                                    class="ti ti-qrcode"
                                    style="font-size: 72px;"
                                ></i>
                            </div>

                            <p class="empty-title">
                                Sin credenciales
                            </p>

                            <p class="empty-subtitle text-secondary">
                                Genera una credencial para registrar accesos.
                            </p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">
                Fotografía del alumno
            </h3>
        </div>

        <form
            method="POST"
            action="{{ route(
                'admin.students.photo.upload',
                $studentRow->id
            ) }}"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label">
                            Seleccionar fotografía
                        </label>

                        <input
                            type="file"
                            name="photo"
                            class="form-control"
                            accept="image/jpeg,image/png,image/webp"
                            required
                        >

                        <div class="form-hint">
                            JPG, PNG o WEBP. Máximo 2 MB.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <button class="btn btn-primary w-100">
                            <i class="ti ti-photo-up me-1"></i>
                            Guardar foto
                        </button>
                    </div>
                </div>
            </div>
        </form>

        @if($studentRow->photo_url)
            <div class="card-footer">
                <form
                    method="POST"
                    action="{{ route(
                        'admin.students.photo.remove',
                        $studentRow->id
                    ) }}"
                    onsubmit="return confirm(
                        '¿Eliminar la fotografía del alumno?'
                    );"
                >
                    @csrf
                    @method('DELETE')

                    <button class="btn btn-outline-danger">
                        <i class="ti ti-trash me-1"></i>
                        Quitar fotografía
                    </button>
                </form>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener(
            'DOMContentLoaded',
            async function () {
                document
                    .querySelectorAll('.qr-canvas')
                    .forEach(
                        async function (element) {
                            const token =
                                element.dataset.token;

                            if (! token) {
                                element.innerHTML =
                                    '<div class="text-danger small">Token vacío</div>';

                                return;
                            }

                            if (
                                ! window.QRCode
                                || typeof window.QRCode.toDataURL
                                    !== 'function'
                            ) {
                                element.innerHTML =
                                    '<div class="text-danger small">QR no disponible</div>';

                                return;
                            }

                            try {
                                const url =
                                    await window.QRCode
                                        .toDataURL(
                                            token,
                                            {
                                                width: 180,
                                                margin: 1,
                                                errorCorrectionLevel: 'M'
                                            }
                                        );

                                element.innerHTML = `
                                    <img
                                        src="${url}"
                                        alt="Código QR"
                                        class="img-fluid border rounded bg-white p-2"
                                    >
                                `;
                            } catch (error) {
                                element.innerHTML =
                                    '<div class="text-danger small">Error generando QR</div>';

                                console.error(error);
                            }
                        }
                    );
            }
        );
    </script>
@endpush