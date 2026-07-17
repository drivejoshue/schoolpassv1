@extends('layouts.app')

@section('title', 'Dashboard | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Dashboard escolar')

@section('topbar-actions')
    <div class="btn-list">
        @if(Route::has('admin.reports.attendance'))
            <a
                href="{{ route('admin.reports.attendance', [
                    'date' => $filters['date'],
                    'campus_id' => $filters['campus_id'],
                    'level_id' => $filters['level_id'],
                    'group_id' => $filters['group_id'],
                ]) }}"
                class="btn btn-outline-primary btn-sm"
            >
                <i class="ti ti-calendar-check me-1"></i>
                Asistencia
            </a>
        @endif

        <a href="{{ route('prefect.access') }}" class="btn btn-outline-primary btn-sm">
            <i class="ti ti-qrcode me-1"></i>
            Prefectura
        </a>

        <a href="{{ route('kiosk.access') }}" class="btn btn-primary btn-sm">
            <i class="ti ti-device-imac me-1"></i>
            Kiosco
        </a>
    </div>
@endsection

@section('content')
    @php
        $eventLabels = [
            'entry' => 'Entrada',
            'exit' => 'Salida',
            'access' => 'Acceso interno',
        ];

        $statusLabels = [
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Muy tarde',
            'early_exit' => 'Salida anticipada',
            'normal_exit' => 'Salida normal',
            'duplicate' => 'Duplicado',
            'allowed' => 'Autorizado',
            'denied' => 'Denegado',
            'manual' => 'Manual',
            'guardian_required' => 'Tutor requerido',
            'invalid_credential' => 'Credencial inválida',
            'inactive_student' => 'Alumno inactivo',
            'student_not_enrolled' => 'Sin inscripción',
            'inactive_enrollment' => 'Inscripción inactiva',
            'cycle_not_started' => 'Ciclo no iniciado',
            'cycle_ended' => 'Ciclo finalizado',
            'no_active_cycle' => 'Sin ciclo activo',
            'invalid_enrollment_group' => 'Grupo inválido',
            'different_campus' => 'Otro plantel',
        ];

        $statusColors = [
            'on_time' => 'green',
            'late' => 'yellow',
            'very_late' => 'orange',
            'early_exit' => 'orange',
            'normal_exit' => 'blue',
            'duplicate' => 'secondary',
            'allowed' => 'green',
            'denied' => 'red',
            'manual' => 'azure',
            'guardian_required' => 'red',
            'invalid_credential' => 'red',
            'inactive_student' => 'red',
            'student_not_enrolled' => 'red',
            'inactive_enrollment' => 'red',
            'cycle_not_started' => 'yellow',
            'cycle_ended' => 'red',
            'no_active_cycle' => 'red',
            'invalid_enrollment_group' => 'red',
            'different_campus' => 'red',
        ];

        $sourceLabels = [
            'qr' => 'QR de alumno',
            'guardian_qr' => 'QR de tutor',
            'manual' => 'Manual',
            'kiosk' => 'Kiosco',
            'nfc' => 'NFC',
        ];

        $trendMaximum = collect($weeklyTrend)
            ->flatMap(fn ($row) => [
                $row['present'],
                $row['late'],
                $row['absent'],
                $row['early_exit'],
            ])
            ->max() ?: 1;

        $enrollmentPercent = $stats['students'] > 0
            ? round(($stats['enrolled_students'] / $stats['students']) * 100, 1)
            : 0;
    @endphp

    <style>
        .dashboard-trend {
            height: 265px;
        }

        .dashboard-trend-column {
            min-width: 48px;
        }

        .dashboard-trend-bar {
            min-width: 7px;
            width: 17%;
        }

        .dashboard-group-table {
            min-width: 850px;
        }
    </style>

    @if($dbError)
        <div class="alert alert-danger">
            <i class="ti ti-alert-circle me-2"></i>
            {{ $dbError }}
        </div>
    @endif

    @if(! $activeCycle)
        <div class="alert alert-warning">
            <i class="ti ti-calendar-off me-2"></i>
            No hay un ciclo escolar activo.
        </div>
    @elseif(! $cycleHasStarted)
        <div class="alert alert-info">
            <i class="ti ti-calendar-time me-2"></i>
            El ciclo <strong>{{ $activeCycle->name }}</strong> inicia el
            {{ \Illuminate\Support\Carbon::parse($activeCycle->starts_on)->format('d/m/Y') }}.
        </div>
    @elseif($cycleHasEnded)
        <div class="alert alert-danger">
            <i class="ti ti-calendar-x me-2"></i>
            El ciclo activo ya finalizó.
        </div>
    @elseif(!$dateInsideCycle)
        <div class="alert alert-warning">
            <i class="ti ti-calendar-exclamation me-2"></i>
            La fecha consultada está fuera de la vigencia del ciclo.
        </div>
    @elseif($dateIsFuture)
        <div class="alert alert-info">
            <i class="ti ti-calendar-time me-2"></i>
            La fecha consultada es futura. No se calculan ausencias.
        </div>
    @elseif($isNoClassDay)
        <div class="alert alert-warning">
            <i class="ti ti-calendar-off me-2"></i>
            La fecha está marcada como
            <strong>{{ $calendarDay->title ?? 'día sin clase' }}</strong>.
        </div>
    @endif

    <div class="card mb-3">
        <form method="GET" action="{{ url()->current() }}">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Consulta de dirección</h3>
                    <div class="text-secondary">
                        Los indicadores diarios respetan estos filtros.
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input
                            type="date"
                            name="date"
                            value="{{ $filters['date'] }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Plantel</label>
                        <select name="campus_id" class="form-select">
                            <option value="">Todos los planteles</option>

                            @foreach($campuses as $campus)
                                <option
                                    value="{{ $campus->id }}"
                                    @selected(
                                        (string) $filters['campus_id']
                                        === (string) $campus->id
                                    )
                                >
                                    {{ $campus->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Nivel</label>
                        <select name="level_id" class="form-select">
                            <option value="">Todos los niveles</option>

                            @foreach($levels as $level)
                                <option
                                    value="{{ $level->id }}"
                                    @selected(
                                        (string) $filters['level_id']
                                        === (string) $level->id
                                    )
                                >
                                    {{ $level->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Grupo</label>
                        <select name="group_id" class="form-select">
                            <option value="">Todos los grupos</option>

                            @foreach($groups as $group)
                                <option
                                    value="{{ $group->id }}"
                                    @selected(
                                        (string) $filters['group_id']
                                        === (string) $group->id
                                    )
                                >
                                    {{ $group->campus_name }}
                                    · {{ $group->level_name }}
                                    · {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a href="{{ url()->current() }}" class="btn btn-outline-secondary">
                    <i class="ti ti-x me-1"></i>
                    Limpiar
                </a>

                <button class="btn btn-primary">
                    <i class="ti ti-filter me-1"></i>
                    Actualizar dashboard
                </button>
            </div>
        </form>
    </div>

    <div class="row row-deck row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div
                        class="d-flex flex-column flex-lg-row
                               align-items-lg-center justify-content-between gap-3"
                    >
                        <div>
                            <div class="subheader">Ciclo escolar operativo</div>

                            <div class="d-flex align-items-center gap-2 mt-1">
                                <h2 class="mb-0">
                                    {{ $activeCycle?->name ?? 'Sin ciclo activo' }}
                                </h2>

                                @if($cycleIsInForce)
                                    <span class="badge bg-green-lt">En curso</span>
                                @elseif($activeCycle && ! $cycleHasStarted)
                                    <span class="badge bg-blue-lt">Próximo</span>
                                @elseif($activeCycle && $cycleHasEnded)
                                    <span class="badge bg-red-lt">Finalizado</span>
                                @else
                                    <span class="badge bg-secondary-lt">No configurado</span>
                                @endif
                            </div>

                            @if($activeCycle)
                                <div class="text-secondary mt-1">
                                    {{ \Illuminate\Support\Carbon::parse($activeCycle->starts_on)->format('d/m/Y') }}
                                    al
                                    {{ \Illuminate\Support\Carbon::parse($activeCycle->ends_on)->format('d/m/Y') }}
                                </div>
                            @endif
                        </div>

                        <div style="min-width: 290px;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-secondary">Alumnos inscritos</span>
                                <strong>
                                    {{ $stats['enrolled_students'] }}
                                    de
                                    {{ $stats['students'] }}
                                </strong>
                            </div>

                            <div class="progress progress-sm">
                                <div
                                    class="progress-bar"
                                    style="width: {{ min(100, $enrollmentPercent) }}%"
                                ></div>
                            </div>

                            <div class="small text-secondary mt-1">
                                {{ $enrollmentPercent }}% con inscripción vigente
                            </div>
                        </div>

                        <div class="text-lg-end">
                            <div class="subheader">Fecha consultada</div>
                            <div class="h2 mb-0">
                                {{ \Illuminate\Support\Carbon::parse($filters['date'])->format('d/m/Y') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-blue-lt me-3">
                            <i class="ti ti-school"></i>
                        </span>

                        <div>
                            <div class="subheader">Inscritos considerados</div>
                            <div class="h1 mb-0">{{ $stats['considered_students'] }}</div>
                        </div>
                    </div>

                    <div class="text-secondary mt-3">
                        Después de filtros y vigencia.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-green-lt me-3">
                            <i class="ti ti-user-check"></i>
                        </span>

                        <div>
                            <div class="subheader">Presentes</div>
                            <div class="h1 mb-0">{{ $stats['present'] }}</div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-between">
                        <span class="text-secondary">Puntuales</span>
                        <strong class="text-success">{{ $stats['on_time'] }}</strong>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Con salida</span>
                        <strong>{{ $stats['exited'] }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-yellow-lt me-3">
                            <i class="ti ti-clock-exclamation"></i>
                        </span>

                        <div>
                            <div class="subheader">Retardos</div>
                            <div class="h1 mb-0">
                                {{ $stats['late'] + $stats['very_late'] }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-between">
                        <span class="text-secondary">Retardo</span>
                        <strong class="text-warning">{{ $stats['late'] }}</strong>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Muy tarde</span>
                        <strong class="text-orange">{{ $stats['very_late'] }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-red-lt me-3">
                            <i class="ti ti-user-x"></i>
                        </span>

                        <div>
                            <div class="subheader">Ausentes</div>
                            <div class="h1 mb-0">{{ $stats['absent'] }}</div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-between">
                        <span class="text-secondary">Pendientes</span>
                        <strong>{{ $stats['pending'] }}</strong>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Salidas anticipadas</span>
                        <strong class="text-danger">{{ $stats['early_exit'] }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-azure-lt me-3">
                            <i class="ti ti-chart-donut"></i>
                        </span>

                        <div>
                            <div class="subheader">Asistencia</div>
                            <div class="h1 mb-0">
                                {{ number_format($stats['attendance_rate'], 1) }}%
                            </div>
                        </div>
                    </div>

                    <div class="progress progress-sm mt-3">
                        <div
                            class="progress-bar"
                            style="width: {{ min(100, $stats['attendance_rate']) }}%"
                        ></div>
                    </div>

                    <div class="text-secondary mt-2">
                        Presentes sobre alumnos computables.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-green-lt me-3">
                            <i class="ti ti-progress-check"></i>
                        </span>

                        <div>
                            <div class="subheader">Puntualidad</div>
                            <div class="h1 mb-0">
                                {{ number_format($stats['punctuality_rate'], 1) }}%
                            </div>
                        </div>
                    </div>

                    <div class="progress progress-sm mt-3">
                        <div
                            class="progress-bar bg-success"
                            style="width: {{ min(100, $stats['punctuality_rate']) }}%"
                        ></div>
                    </div>

                    <div class="text-secondary mt-2">
                        Puntuales sobre alumnos presentes.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-orange-lt me-3">
                            <i class="ti ti-shield-x"></i>
                        </span>

                        <div>
                            <div class="subheader">Denegados</div>
                            <div class="h1 mb-0">{{ $stats['denied_selected'] }}</div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-between">
                        <span class="text-secondary">Eventos</span>
                        <strong>{{ $stats['logs_selected'] }}</strong>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Duplicados</span>
                        <strong>{{ $stats['duplicates_selected'] }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-purple-lt me-3">
                            <i class="ti ti-calendar-off"></i>
                        </span>

                        <div>
                            <div class="subheader">Sin clase</div>
                            <div class="h1 mb-0">{{ $stats['no_class'] }}</div>
                        </div>
                    </div>

                    <div class="text-secondary mt-3">
                        Por calendario u horario del grupo.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-purple-lt me-3">
                            <i class="ti ti-user-heart"></i>
                        </span>

                        <div>
                            <div class="text-secondary">Tutores activos</div>
                            <div class="h2 mb-0">{{ $stats['guardians'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-blue-lt me-3">
                            <i class="ti ti-school"></i>
                        </span>

                        <div>
                            <div class="text-secondary">Grupos del ciclo</div>
                            <div class="h2 mb-0">{{ $stats['groups'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-green-lt me-3">
                            <i class="ti ti-device-tablet"></i>
                        </span>

                        <div>
                            <div class="text-secondary">Dispositivos activos</div>
                            <div class="h2 mb-0">{{ $stats['devices'] }}</div>
                        </div>

                        <div class="ms-auto text-success">
                            {{ $stats['online_devices'] }} en línea
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-blue-lt me-3">
                            <i class="ti ti-users"></i>
                        </span>

                        <div>
                            <div class="text-secondary">Alumnos activos</div>
                            <div class="h2 mb-0">{{ $stats['students'] }}</div>
                        </div>

                        <div class="ms-auto text-warning">
                            {{ $stats['unenrolled_students'] }} sin inscripción
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Asistencia de los últimos 7 días</h3>
                        <div class="text-secondary">
                            Presentes, retardos, ausentes y salidas anticipadas.
                        </div>
                    </div>

                    <div class="card-actions">
                        <span class="badge bg-blue-lt me-1">Presentes</span>
                        <span class="badge bg-yellow-lt me-1">Retardos</span>
                        <span class="badge bg-red-lt me-1">Ausentes</span>
                        <span class="badge bg-purple-lt">Anticipadas</span>
                    </div>
                </div>

                <div class="card-body">
                    <div class="dashboard-trend d-flex align-items-end gap-3">
                        @foreach($weeklyTrend as $row)
                            <div
                                class="dashboard-trend-column flex-fill
                                       d-flex flex-column align-items-center h-100"
                            >
                                <div
                                    class="flex-fill w-100 d-flex
                                           align-items-end justify-content-center gap-1"
                                >
                                    <div
                                        class="dashboard-trend-bar bg-primary rounded-top"
                                        title="Presentes: {{ $row['present'] }}"
                                        style="height: {{ max(
                                            2,
                                            ($row['present'] / $trendMaximum) * 190
                                        ) }}px;"
                                    ></div>

                                    <div
                                        class="dashboard-trend-bar bg-warning rounded-top"
                                        title="Retardos: {{ $row['late'] }}"
                                        style="height: {{ max(
                                            2,
                                            ($row['late'] / $trendMaximum) * 190
                                        ) }}px;"
                                    ></div>

                                    <div
                                        class="dashboard-trend-bar bg-danger rounded-top"
                                        title="Ausentes: {{ $row['absent'] }}"
                                        style="height: {{ max(
                                            2,
                                            ($row['absent'] / $trendMaximum) * 190
                                        ) }}px;"
                                    ></div>

                                    <div
                                        class="dashboard-trend-bar bg-purple rounded-top"
                                        title="Salidas anticipadas: {{ $row['early_exit'] }}"
                                        style="height: {{ max(
                                            2,
                                            ($row['early_exit'] / $trendMaximum) * 190
                                        ) }}px;"
                                    ></div>
                                </div>

                                <div class="text-center mt-2">
                                    <div class="fw-semibold">{{ $row['label'] }}</div>
                                    <div class="small text-secondary">{{ $row['day'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Resumen por grupo</h3>
                        <div class="text-secondary">
                            Asistencia de la fecha consultada.
                        </div>
                    </div>
                </div>

                <div class="list-group list-group-flush">
                    @forelse($groupActivity as $group)
                        <div class="list-group-item">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $group->group_name }}</div>
                                    <div class="small text-secondary">
                                        {{ $group->level_name }} · {{ $group->campus_name }}
                                    </div>
                                </div>

                                <span
                                    class="badge
                                        {{ $group->attendance_rate >= 80
                                            ? 'bg-green-lt'
                                            : ($group->attendance_rate >= 60
                                                ? 'bg-yellow-lt'
                                                : 'bg-red-lt') }}"
                                >
                                    {{ number_format($group->attendance_rate, 1) }}%
                                </span>
                            </div>

                            <div class="small text-secondary mt-2">
                                {{ $group->present }}/{{ $group->total }} presentes
                                · {{ $group->late + $group->very_late }} retardos
                                · {{ $group->absent }} ausentes
                                · {{ $group->exited }} salidas
                            </div>

                            <div class="progress progress-sm mt-2">
                                <div
                                    class="progress-bar
                                        {{ $group->attendance_rate >= 80
                                            ? 'bg-success'
                                            : ($group->attendance_rate >= 60
                                                ? 'bg-warning'
                                                : 'bg-danger') }}"
                                    style="width: {{ min(100, $group->attendance_rate) }}%"
                                ></div>
                            </div>
                        </div>
                    @empty
                        <div class="empty py-4">
                            <div class="empty-icon">
                                <i class="ti ti-chart-bar"></i>
                            </div>
                            <p class="empty-title">Sin grupos para mostrar</p>
                            <p class="empty-subtitle text-secondary">
                                Revisa el ciclo o los filtros seleccionados.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Desglose de asistencia por grupo</h3>
                        <div class="text-secondary">
                            Total, puntualidad, retardos, ausencias y salidas.
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table dashboard-group-table">
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>Total</th>
                                <th>Presentes</th>
                                <th>Puntuales</th>
                                <th>Retardos</th>
                                <th>Muy tarde</th>
                                <th>Ausentes</th>
                                <th>Salidas</th>
                                <th>Anticipadas</th>
                                <th>Asistencia</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($groupActivity as $group)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $group->group_name }}</div>
                                        <div class="small text-secondary">
                                            {{ $group->level_name }} · {{ $group->campus_name }}
                                        </div>
                                    </td>
                                    <td>{{ $group->total }}</td>
                                    <td class="text-success">{{ $group->present }}</td>
                                    <td>{{ $group->on_time }}</td>
                                    <td class="text-warning">{{ $group->late }}</td>
                                    <td class="text-orange">{{ $group->very_late }}</td>
                                    <td class="text-danger">{{ $group->absent }}</td>
                                    <td>{{ $group->exited }}</td>
                                    <td>{{ $group->early_exit }}</td>
                                    <td>
                                        <span
                                            class="badge
                                                {{ $group->attendance_rate >= 80
                                                    ? 'bg-green-lt'
                                                    : ($group->attendance_rate >= 60
                                                        ? 'bg-yellow-lt'
                                                        : 'bg-red-lt') }}"
                                        >
                                            {{ number_format($group->attendance_rate, 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-secondary py-5">
                                        No hay grupos con los filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-9">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Actividad reciente</h3>
                        <div class="text-secondary">
                            Últimos movimientos de la fecha consultada.
                        </div>
                    </div>

                    @if(Route::has('admin.reports.access'))
                        <div class="card-actions">
                            <a
                                href="{{ route('admin.reports.access', [
                                    'date' => $filters['date'],
                                ]) }}"
                                class="btn btn-outline-primary btn-sm"
                            >
                                Ver bitácora
                            </a>
                        </div>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Grupo</th>
                                <th>Origen</th>
                                <th>Evento</th>
                                <th>Resultado</th>
                                <th>Hora</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($recentLogs as $log)
                                @php
                                    $studentName = trim($log->student_name ?? '');
                                    $guardianName = trim($log->guardian_name ?? '');
                                    $initial = $studentName !== ''
                                        ? mb_strtoupper(mb_substr($studentName, 0, 1))
                                        : 'S';
                                    $color = $statusColors[$log->event_status]
                                        ?? ($log->decision === 'denied' ? 'red' : 'secondary');
                                @endphp

                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($log->photo_url)
                                                <span
                                                    class="avatar avatar-sm me-2"
                                                    style="background-image: url('{{ $log->photo_url }}')"
                                                ></span>
                                            @else
                                                <span class="avatar avatar-sm bg-blue-lt me-2">
                                                    {{ $initial }}
                                                </span>
                                            @endif

                                            <div>
                                                <div class="fw-semibold">
                                                    {{ $studentName !== '' ? $studentName : 'Sin alumno' }}
                                                </div>
                                                <div class="small text-secondary">
                                                    {{ $log->student_code ?? 'Sin matrícula' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>{{ $log->group_name ?? 'Sin grupo' }}</td>

                                    <td>
                                        <div>
                                            {{ $sourceLabels[$log->source]
                                                ?? ucfirst(str_replace('_', ' ', $log->source)) }}
                                        </div>
                                        <div class="small text-secondary">
                                            {{ $log->device_name ?? 'Sin dispositivo' }}
                                        </div>
                                        @if($guardianName !== '')
                                            <div class="small text-secondary">
                                                Tutor: {{ $guardianName }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="badge bg-blue-lt">
                                            {{ $eventLabels[$log->event_type] ?? $log->event_type }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge bg-{{ $color }}-lt">
                                            {{ $statusLabels[$log->event_status]
                                                ?? $log->event_status }}
                                        </span>

                                        @if($log->reason)
                                            <div
                                                class="small text-secondary mt-1 text-truncate"
                                                style="max-width: 220px;"
                                                title="{{ $log->reason }}"
                                            >
                                                {{ $log->reason }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="text-nowrap">
                                        <div>
                                            {{ \Illuminate\Support\Carbon::parse($log->scanned_at)->format('H:i') }}
                                        </div>
                                        <div class="small text-secondary">
                                            {{ \Illuminate\Support\Carbon::parse($log->scanned_at)->format('d/m/Y') }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-5">
                                        No hay actividad para la fecha consultada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Accesos rápidos</h3>
                </div>

                <div class="list-group list-group-flush">
                    <a
                        href="{{ route('prefect.access') }}"
                        class="list-group-item list-group-item-action"
                    >
                        <i class="ti ti-qrcode me-2 text-primary"></i>
                        Abrir prefectura
                    </a>

                    <a
                        href="{{ route('kiosk.access') }}"
                        class="list-group-item list-group-item-action"
                    >
                        <i class="ti ti-device-imac me-2 text-primary"></i>
                        Abrir kiosco
                    </a>

                    @if(Route::has('admin.reports.attendance'))
                        <a
                            href="{{ route('admin.reports.attendance', [
                                'date' => $filters['date'],
                                'campus_id' => $filters['campus_id'],
                                'level_id' => $filters['level_id'],
                                'group_id' => $filters['group_id'],
                            ]) }}"
                            class="list-group-item list-group-item-action"
                        >
                            <i class="ti ti-calendar-check me-2 text-primary"></i>
                            Asistencia diaria
                        </a>
                    @endif

                    @if(Route::has('admin.students.index'))
                        <a
                            href="{{ route('admin.students.index') }}"
                            class="list-group-item list-group-item-action"
                        >
                            <i class="ti ti-users me-2 text-primary"></i>
                            Administrar alumnos
                        </a>
                    @endif

                    @if(Route::has('admin.guardians.index'))
                        <a
                            href="{{ route('admin.guardians.index') }}"
                            class="list-group-item list-group-item-action"
                        >
                            <i class="ti ti-user-heart me-2 text-primary"></i>
                            Administrar tutores
                        </a>
                    @endif

                    @if(Route::has('admin.reports.analytics.index'))
                        <a
                            href="{{ route('admin.reports.analytics.index') }}"
                            class="list-group-item list-group-item-action"
                        >
                            <i class="ti ti-chart-bar me-2 text-primary"></i>
                            Analítica
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
