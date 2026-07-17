@extends('layouts.app')

@section('title', 'Horarios | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Horarios de grupo')

@section('topbar-actions')
    <a
        href="{{ route('admin.groups.index') }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Grupos
    </a>

    <a
        href="{{ route(
            'admin.students.index',
            [
                'search' => $groupRow->name,
            ]
        ) }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-users me-1"></i>
        Ver alumnos
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

    <div class="alert alert-info">
        <i class="ti ti-calendar-check me-2"></i>

        Ciclo operativo:

        <strong>
            {{ $activeCycle->name }}
        </strong>

        · Grupo:

        <strong>
            {{ $groupRow->level_name ?? 'Sin nivel' }}
            ·
            {{ $groupRow->name }}
        </strong>

        · Vigencia:

        <strong>
            {{ \Illuminate\Support\Carbon::parse(
                $activeCycle->starts_on
            )->format('d/m/Y') }}

            al

            {{ \Illuminate\Support\Carbon::parse(
                $activeCycle->ends_on
            )->format('d/m/Y') }}
        </strong>
    </div>

    @php
        $activeFromOld = old('active_weekdays');

        $timeValue = function (
            string $field,
            int $weekday,
            string $default
        ) use ($schedules): string {
            $oldValue = old(
                $field.'.'.$weekday
            );

            if ($oldValue !== null) {
                return (string) $oldValue;
            }

            $schedule = $schedules->get(
                $weekday
            );

            if (
                $schedule
                && ! empty($schedule->{$field})
            ) {
                return substr(
                    (string) $schedule->{$field},
                    0,
                    5
                );
            }

            return $default;
        };

        $isActive = function (
            int $weekday
        ) use (
            $schedules,
            $activeFromOld
        ): bool {
            if (is_array($activeFromOld)) {
                return in_array(
                    (string) $weekday,
                    $activeFromOld,
                    true
                ) || in_array(
                    $weekday,
                    $activeFromOld,
                    true
                );
            }

            $schedule = $schedules->get(
                $weekday
            );

            return $schedule
                && $schedule->status === 'active';
        };

        $activeDaysCount = collect($weekdays)
            ->keys()
            ->filter(
                fn ($weekday): bool =>
                    $isActive((int) $weekday)
            )
            ->count();
    @endphp

    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center">
                    <span class="avatar avatar-xl bg-blue-lt mb-3">
                        <i class="ti ti-users-group fs-1"></i>
                    </span>

                    <h2 class="mb-1">
                        {{ $groupRow->name }}
                    </h2>

                    <div class="text-secondary">
                        {{ $groupRow->level_name ?? 'Sin nivel' }}
                    </div>

                    @if(! empty($groupRow->grade_label))
                        <div class="text-secondary small mt-1">
                            Grado:
                            {{ $groupRow->grade_label }}
                        </div>
                    @endif

                    <div class="mt-3">
                        @if($groupRow->status === 'active')
                            <span class="badge bg-success-lt">
                                Activo
                            </span>
                        @else
                            <span class="badge bg-secondary-lt">
                                {{ ucfirst($groupRow->status) }}
                            </span>
                        @endif

                        <span class="badge bg-blue-lt">
                            {{ $activeDaysCount }}
                            {{ $activeDaysCount === 1
                                ? 'día activo'
                                : 'días activos'
                            }}
                        </span>
                    </div>
                </div>

                <div class="card-footer">
                    <a
                        href="{{ route(
                            'admin.students.index',
                            [
                                'search' => $groupRow->name,
                            ]
                        ) }}"
                        class="btn btn-outline-primary w-100"
                    >
                        <i class="ti ti-users me-1"></i>
                        Ver alumnos del grupo
                    </a>
                </div>
            </div>

            <div class="alert alert-info mt-3">
                <i class="ti ti-info-circle me-2"></i>

                Estos horarios se utilizan durante el escaneo para
                determinar puntualidad, retardo, entrada extemporánea
                y salida anticipada.
            </div>

            <div class="alert alert-warning">
                <i class="ti ti-alert-triangle me-2"></i>

                Los días desactivados no generan asistencia ni ausencia
                automática para este grupo.
            </div>
        </div>

        <div class="col-lg-8">
            <form
                method="POST"
                action="{{ route(
                    'admin.groups.schedules.update',
                    $groupRow->id
                ) }}"
                class="card"
            >
                @csrf
                @method('PUT')

                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Horario semanal
                        </h3>

                        <p class="card-subtitle">
                            Activa los días aplicables y define los
                            cortes de asistencia.
                        </p>
                    </div>
                </div>

                <div class="card-body border-bottom">
    <div class="row align-items-end">
        <div class="col-md-6">
            <label
                for="auto_transition_minutes"
                class="form-label"
            >
                Anticipación del modo automático
            </label>

            <div class="input-group">
                <input
                    type="number"
                    id="auto_transition_minutes"
                    name="auto_transition_minutes"
                    class="form-control"
                    min="0"
                    max="120"
                    step="1"
                    value="{{ old(
                        'auto_transition_minutes',
                        $groupRow->auto_transition_minutes ?? 30
                    ) }}"
                    required
                >

                <span class="input-group-text">
                    minutos
                </span>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-hint mt-2 mt-md-0">
                En dispositivos automáticos, el sistema cambia a salida
                esta cantidad de minutos antes de la hora de salida
                configurada para cada día.
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-3 mb-0">
        <i class="ti ti-arrows-exchange me-2"></i>

        Ejemplo: si la salida es a las
        <strong>13:00</strong>
        y la anticipación es de
        <strong>30 minutos</strong>,
        el modo automático comienza a registrar salidas
        desde las <strong>12:30</strong>.
    </div>
</div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Día</th>
                                <th>Activo</th>
                                <th>Entrada</th>
                                <th>Tolerancia</th>
                                <th>Límite retardo</th>
                                <th>Salida</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach(
                                $weekdays
                                as $weekday => $label
                            )
                                @php
                                    $dayIsActive = $isActive(
                                        (int) $weekday
                                    );
                                @endphp

                                <tr
                                    class="{{
                                        $dayIsActive
                                            ? ''
                                            : 'text-secondary'
                                    }}"
                                >
                                    <td class="fw-bold">
                                        {{ $label }}
                                    </td>

                                    <td>
                                        <label class="form-check form-switch m-0">
                                            <input
                                                class="form-check-input day-toggle"
                                                type="checkbox"
                                                name="active_weekdays[]"
                                                value="{{ $weekday }}"
                                                data-weekday="{{ $weekday }}"
                                                @checked($dayIsActive)
                                            >

                                            <span class="form-check-label">
                                                {{ $dayIsActive
                                                    ? 'Sí'
                                                    : 'No'
                                                }}
                                            </span>
                                        </label>
                                    </td>

                                    <td>
                                        <input
                                            type="time"
                                            name="entry_time[{{ $weekday }}]"
                                            value="{{ $timeValue(
                                                'entry_time',
                                                (int) $weekday,
                                                '07:00'
                                            ) }}"
                                            class="form-control schedule-time"
                                            data-weekday="{{ $weekday }}"
                                            required
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="time"
                                            name="grace_until[{{ $weekday }}]"
                                            value="{{ $timeValue(
                                                'grace_until',
                                                (int) $weekday,
                                                '07:10'
                                            ) }}"
                                            class="form-control schedule-time"
                                            data-weekday="{{ $weekday }}"
                                            required
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="time"
                                            name="late_until[{{ $weekday }}]"
                                            value="{{ $timeValue(
                                                'late_until',
                                                (int) $weekday,
                                                '07:30'
                                            ) }}"
                                            class="form-control schedule-time"
                                            data-weekday="{{ $weekday }}"
                                            required
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="time"
                                            name="exit_time[{{ $weekday }}]"
                                            value="{{ $timeValue(
                                                'exit_time',
                                                (int) $weekday,
                                                '13:00'
                                            ) }}"
                                            class="form-control schedule-time"
                                            data-weekday="{{ $weekday }}"
                                            required
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-body border-top">
                    <div class="alert alert-light mb-0">
                        <strong>Orden obligatorio:</strong>

                        entrada ≤ tolerancia ≤ límite de retardo ≤ salida.
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a
                        href="{{ route('admin.groups.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>
                        Guardar horarios
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function () {
                const toggles = document.querySelectorAll(
                    '.day-toggle'
                );

                const updateRowState = function (
                    toggle
                ) {
                    const row = toggle.closest('tr');
                    const label = toggle
                        .closest('.form-check')
                        .querySelector(
                            '.form-check-label'
                        );

                    if (! row || ! label) {
                        return;
                    }

                    if (toggle.checked) {
                        row.classList.remove(
                            'text-secondary'
                        );

                        label.textContent = 'Sí';
                    } else {
                        row.classList.add(
                            'text-secondary'
                        );

                        label.textContent = 'No';
                    }
                };

                toggles.forEach(
                    function (toggle) {
                        updateRowState(toggle);

                        toggle.addEventListener(
                            'change',
                            function () {
                                updateRowState(toggle);
                            }
                        );
                    }
                );
            }
        );
    </script>
@endpush