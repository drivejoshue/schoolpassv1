@extends('layouts.app')

@section('title', 'Asistencia diaria | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Asistencia diaria')

@section('topbar-actions')
    <div class="btn-list">
        <a
            href="{{ route('admin.reports.access') }}"
            class="btn btn-outline-primary btn-sm"
        >
            <i class="ti ti-list-details me-1"></i>
            Bitácora
        </a>

        <a
            href="{{ route('admin.reports.monthly-attendance.index') }}"
            class="btn btn-outline-primary btn-sm"
        >
            <i class="ti ti-calendar-stats me-1"></i>
            Asistencia mensual
        </a>
    </div>
@endsection

@section('content')
    @php
        $statusLabels = [
            'present' => 'Presentes',
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Muy tarde',
            'absent' => 'Ausente',
            'pending' => 'Pendiente',
            'no_class' => 'Sin clase',
            'outside_cycle' => 'Fuera del ciclo',
            'exited' => 'Con salida',
            'early_exit' => 'Salida anticipada',
        ];

        $statusBadges = [
            'on_time' => 'success',
            'late' => 'warning',
            'very_late' => 'orange',
            'absent' => 'danger',
            'pending' => 'azure',
            'no_class' => 'secondary',
            'outside_cycle' => 'secondary',
        ];

        $sourceLabels = [
            'qr' => 'QR de alumno',
            'guardian_qr' => 'QR de tutor',
            'manual' => 'Registro manual',
            'kiosk' => 'Kiosco',
            'nfc' => 'NFC',
            'app' => 'Aplicación',
        ];

        $readerLabels = [
            'camera_qr' => 'Cámara QR',
            'manual' => 'Manual',
            'nfc' => 'Lector NFC',
        ];

        $formatTime = function ($value) {
            if (! $value) {
                return '—';
            }

            return \Illuminate\Support\Carbon::parse($value)
                ->format('H:i');
        };

        $formatDateTime = function ($value) {
            if (! $value) {
                return '—';
            }

            return \Illuminate\Support\Carbon::parse($value)
                ->format('d/m/Y H:i');
        };
    @endphp

    <style>
        .attendance-table {
            min-width: 1880px;
        }

        .attendance-table th {
            white-space: nowrap;
        }

        .attendance-meta {
            min-width: 155px;
        }

        .attendance-person {
            min-width: 190px;
        }

        @media print {
            .attendance-no-print,
            .navbar,
            .sidebar {
                display: none !important;
            }

            .attendance-table {
                min-width: 100%;
                font-size: 9px;
            }

            .card {
                box-shadow: none !important;
            }
        }
    </style>

    @if(!$hasActiveCycle)
        <div class="alert alert-danger">
            <i class="ti ti-alert-triangle me-2"></i>
            No existe un ciclo escolar activo. El reporte no puede calcularse.
        </div>
    @elseif(!$dateInsideCycle)
        <div class="alert alert-warning">
            <i class="ti ti-calendar-exclamation me-2"></i>
            La fecha seleccionada está fuera de la vigencia del ciclo
            <strong>{{ $activeCycle->name }}</strong>.
        </div>
    @elseif($dateIsFuture)
        <div class="alert alert-info">
            <i class="ti ti-calendar-time me-2"></i>
            La fecha seleccionada es futura. No se contabilizan ausencias.
        </div>
    @elseif($isNoClassDay)
        <div class="alert alert-warning">
            <i class="ti ti-calendar-off me-2"></i>
            Esta fecha está marcada como
            <strong>{{ $calendarDay->title ?? 'día sin clase' }}</strong>.
            No se contabilizan ausencias.
        </div>
    @endif

    @if($activeCycle)
        <div class="alert alert-success">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <i class="ti ti-calendar-check me-2"></i>
                    Ciclo activo:
                    <strong>{{ $activeCycle->name }}</strong>
                </div>

                <div class="text-secondary">
                    {{ \Illuminate\Support\Carbon::parse($activeCycle->starts_on)->format('d/m/Y') }}
                    al
                    {{ \Illuminate\Support\Carbon::parse($activeCycle->ends_on)->format('d/m/Y') }}
                </div>

                <div class="ms-auto">
                    Fecha consultada:
                    <strong>
                        {{ \Illuminate\Support\Carbon::parse($filters['date'])->format('d/m/Y') }}
                    </strong>
                </div>
            </div>
        </div>
    @endif

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Alumnos considerados</div>
                    <div class="h1 mb-0">{{ $summary['total'] }}</div>
                    <div class="text-secondary small">
                        Según ciclo y filtros aplicados
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Presentes</div>
                    <div class="h1 mb-0 text-success">
                        {{ $summary['present'] }}
                    </div>
                    <div class="text-secondary small">
                        {{ $summary['on_time'] }} puntuales
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Retardos</div>
                    <div class="h1 mb-0 text-warning">
                        {{ $summary['late'] + $summary['very_late'] }}
                    </div>
                    <div class="text-secondary small">
                        {{ $summary['late'] }} retardo ·
                        {{ $summary['very_late'] }} muy tarde
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">
                        {{ $isNoClassDay ? 'Sin clase' : 'Ausentes' }}
                    </div>

                    <div class="h1 mb-0 {{ $isNoClassDay ? '' : 'text-danger' }}">
                        {{ $isNoClassDay
                            ? $summary['no_class']
                            : $summary['absent'] }}
                    </div>

                    @if($summary['pending'] > 0)
                        <div class="text-secondary small">
                            {{ $summary['pending'] }} pendientes
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Con salida</div>
                    <div class="h1 mb-0">{{ $summary['exited'] }}</div>
                    <div class="text-secondary small">
                        {{ $summary['early_exit'] }} anticipadas
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Puntualidad</div>
                    <div class="h1 mb-0">
                        {{ number_format($summary['attendance_rate'], 1) }}%
                    </div>

                    <div class="progress progress-sm mt-2">
                        <div
                            class="progress-bar"
                            style="width: {{ min(100, $summary['attendance_rate']) }}%"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Sin clase</div>
                    <div class="h1 mb-0">{{ $summary['no_class'] }}</div>
                    <div class="text-secondary small">
                        Por calendario u horario
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Mostrados</div>
                    <div class="h1 mb-0">{{ $displayedTotal }}</div>
                    <div class="text-secondary small">
                        Después del filtro de estado
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3 attendance-no-print">
        <form method="GET" action="{{ route('admin.reports.attendance') }}">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Filtros del reporte</h3>
                    <div class="text-secondary">
                        Consulta por fecha, plantel, nivel, grupo, estado o alumno.
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4 col-xl-2">
                        <label class="form-label">Fecha</label>
                        <input
                            type="date"
                            name="date"
                            value="{{ $filters['date'] }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-4 col-xl-2">
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

                    <div class="col-md-4 col-xl-2">
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

                    <div class="col-md-6 col-xl-3">
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

                    <div class="col-md-6 col-xl-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="">Todos los estados</option>

                            @foreach($statusLabels as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected($filters['status'] === $value)
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Alumno</label>
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <i class="ti ti-search"></i>
                            </span>

                            <input
                                type="text"
                                name="student"
                                value="{{ $filters['student'] }}"
                                class="form-control"
                                placeholder="Nombre, apellidos o matrícula"
                            >
                        </div>
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <div class="btn-list w-100">
                            <button class="btn btn-primary flex-fill">
                                <i class="ti ti-filter me-1"></i>
                                Consultar
                            </button>

                            <a
                                href="{{ route('admin.reports.attendance') }}"
                                class="btn btn-outline-secondary"
                                title="Limpiar filtros"
                            >
                                <i class="ti ti-x"></i>
                            </a>

                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                onclick="window.print()"
                                title="Imprimir"
                            >
                                <i class="ti ti-printer"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Asistencia del día</h3>
                <p class="card-subtitle">
                    Entrada, salida, tutores, origen, dispositivo y operador.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table attendance-table">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Plantel / grupo</th>
                        <th>Estado</th>
                        <th>Entrada</th>
                        <th>Tutor que entregó</th>
                        <th>Origen de entrada</th>
                        <th>Salida</th>
                        <th>Tutor que recogió</th>
                        <th>Origen de salida</th>
                        <th>Operador / dispositivo</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $row)
                        @php
                            $badge = $statusBadges[$row->final_status]
                                ?? 'secondary';

                            $entryObservation = $row->entry_notes
                                ?: $row->entry_reason;

                            $exitObservation = $row->exit_notes
                                ?: $row->exit_reason;
                        @endphp

                        <tr>
                            <td class="attendance-person">
                                <div class="d-flex align-items-center">
                                    @if($row->photo_url)
                                        <span
                                            class="avatar avatar-sm me-2"
                                            style="background-image: url('{{ $row->photo_url }}')"
                                        ></span>
                                    @else
                                        <span class="avatar avatar-sm bg-blue-lt me-2">
                                            {{ mb_strtoupper(
                                                mb_substr(
                                                    $row->first_name,
                                                    0,
                                                    1
                                                )
                                            ) }}
                                        </span>
                                    @endif

                                    <div>
                                        <div class="fw-bold">
                                            {{ $row->first_name }}
                                            {{ $row->last_name }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ $row->student_code }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="attendance-meta">
                                <div class="fw-semibold">
                                    {{ $row->group_name }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $row->level_name ?? 'Sin nivel' }}
                                    · {{ $row->campus_name }}
                                </div>
                            </td>

                            <td>
                                <span class="badge bg-{{ $badge }}-lt">
                                    {{ $statusLabels[$row->final_status]
                                        ?? ucfirst(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $row->final_status
                                            )
                                        ) }}
                                </span>

                                @if($row->is_early_exit)
                                    <div class="mt-1">
                                        <span class="badge bg-red-lt">
                                            Salida anticipada
                                        </span>
                                    </div>
                                @endif

                                @if($row->minutes_late > 0)
                                    <div class="text-secondary small mt-1">
                                        {{ $row->minutes_late }} min tarde
                                    </div>
                                @endif
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    {{ $formatTime($row->entry_at) }}
                                </div>

                                @if($row->scheduled_entry_time)
                                    <div class="text-secondary small">
                                        Horario:
                                        {{ \Illuminate\Support\Carbon::parse(
                                            $row->scheduled_entry_time
                                        )->format('H:i') }}
                                    </div>
                                @endif
                            </td>

                            <td class="attendance-meta">
                                @if($row->entry_guardian_name)
                                    <div class="fw-semibold">
                                        {{ $row->entry_guardian_name }}
                                    </div>

                                    <div class="text-secondary small">
                                        Entrega autorizada
                                    </div>
                                @elseif($row->entry_at)
                                    <span class="text-secondary">
                                        Sin tutor asociado
                                    </span>
                                @else
                                    —
                                @endif
                            </td>

                            <td class="attendance-meta">
                                @if($row->entry_at)
                                    <div>
                                        {{ $sourceLabels[$row->entry_source]
                                            ?? ucfirst(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    (string) $row->entry_source
                                                )
                                            ) }}
                                    </div>

                                    <div class="text-secondary small">
                                        {{ $readerLabels[$row->entry_reader_type]
                                            ?? $row->entry_reader_type
                                            ?? '—' }}
                                    </div>
                                @else
                                    —
                                @endif
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    {{ $formatTime($row->exit_at) }}
                                </div>

                                @if($row->scheduled_exit_time)
                                    <div class="text-secondary small">
                                        Horario:
                                        {{ \Illuminate\Support\Carbon::parse(
                                            $row->scheduled_exit_time
                                        )->format('H:i') }}
                                    </div>
                                @endif
                            </td>

                            <td class="attendance-meta">
                                @if($row->exit_guardian_name)
                                    <div class="fw-semibold">
                                        {{ $row->exit_guardian_name }}
                                    </div>

                                    <div class="text-secondary small">
                                        Recogida autorizada
                                    </div>
                                @elseif($row->exit_at)
                                    <span class="text-secondary">
                                        Sin tutor asociado
                                    </span>
                                @else
                                    —
                                @endif
                            </td>

                            <td class="attendance-meta">
                                @if($row->exit_at)
                                    <div>
                                        {{ $sourceLabels[$row->exit_source]
                                            ?? ucfirst(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    (string) $row->exit_source
                                                )
                                            ) }}
                                    </div>

                                    <div class="text-secondary small">
                                        {{ $readerLabels[$row->exit_reader_type]
                                            ?? $row->exit_reader_type
                                            ?? '—' }}
                                    </div>
                                @else
                                    —
                                @endif
                            </td>

                            <td class="attendance-meta">
                                @if($row->entry_at)
                                    <div class="mb-2">
                                        <span class="badge bg-blue-lt">
                                            Entrada
                                        </span>

                                        <div class="fw-semibold mt-1">
                                            {{ $row->entry_user_name
                                                ?? 'Sin operador' }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ $row->entry_device_name
                                                ?? 'Sin dispositivo' }}
                                        </div>
                                    </div>
                                @endif

                                @if($row->exit_at)
                                    <div>
                                        <span class="badge bg-purple-lt">
                                            Salida
                                        </span>

                                        <div class="fw-semibold mt-1">
                                            {{ $row->exit_user_name
                                                ?? 'Sin operador' }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ $row->exit_device_name
                                                ?? 'Sin dispositivo' }}
                                        </div>
                                    </div>
                                @endif

                                @if(!$row->entry_at && !$row->exit_at)
                                    —
                                @endif
                            </td>

                            <td class="attendance-meta">
                                @if($entryObservation)
                                    <div class="mb-2">
                                        <span class="badge bg-blue-lt">
                                            Entrada
                                        </span>

                                        <div class="text-secondary small mt-1">
                                            {{ $entryObservation }}
                                        </div>
                                    </div>
                                @endif

                                @if($exitObservation)
                                    <div>
                                        <span class="badge bg-purple-lt">
                                            Salida
                                        </span>

                                        <div class="text-secondary small mt-1">
                                            {{ $exitObservation }}
                                        </div>
                                    </div>
                                @endif

                                @if(!$entryObservation && !$exitObservation)
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="11"
                                class="text-center text-secondary py-5"
                            >
                                No hay alumnos con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rows->hasPages())
            <div class="card-footer attendance-no-print">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
@endsection
