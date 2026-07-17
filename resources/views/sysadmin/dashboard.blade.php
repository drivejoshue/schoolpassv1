@extends('layouts.sysadmin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard global')

@section('content')
<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Administración global</div>
            <h2 class="page-title">Resumen de SchoolPass</h2>
            <div class="text-secondary mt-1">
                Escuelas, licencias, consumo e ingreso recurrente estimado.
            </div>
        </div>

        <div class="col-auto ms-auto d-print-none">
            <a href="{{ route('sysadmin.schools.index') }}" class="btn btn-primary">
                <i class="ti ti-school me-2"></i>
                Ver escuelas
            </a>
        </div>
    </div>
</div>

<div class="row row-deck row-cards">

    @php
        $cards = [
            [
                'label' => 'Escuelas activas',
                'value' => number_format($metrics['active_schools']),
                'icon' => 'ti-school',
                'class' => 'bg-blue-lt text-blue',
            ],
            [
                'label' => 'Licencias en prueba',
                'value' => number_format($metrics['trial_licenses']),
                'icon' => 'ti-flask',
                'class' => 'bg-yellow-lt text-yellow',
            ],
            [
                'label' => 'Vencen en 30 días',
                'value' => number_format($metrics['expiring_soon']),
                'icon' => 'ti-calendar-exclamation',
                'class' => 'bg-orange-lt text-orange',
            ],
            [
                'label' => 'Licencias vencidas',
                'value' => number_format($metrics['expired_licenses']),
                'icon' => 'ti-license-off',
                'class' => 'bg-red-lt text-red',
            ],
            [
                'label' => 'Alumnos activos',
                'value' => number_format($metrics['students']),
                'icon' => 'ti-users',
                'class' => 'bg-cyan-lt text-cyan',
            ],
            [
                'label' => 'Dispositivos activos',
                'value' => number_format($metrics['devices']),
                'icon' => 'ti-device-tablet',
                'class' => 'bg-purple-lt text-purple',
            ],
            [
                'label' => 'MRR estimado',
                'value' => '$'.number_format($metrics['mrr'], 2),
                'icon' => 'ti-cash',
                'class' => 'bg-green-lt text-green',
            ],
            [
                'label' => 'ARR estimado',
                'value' => '$'.number_format($metrics['arr'], 2),
                'icon' => 'ti-chart-line',
                'class' => 'bg-indigo-lt text-indigo',
            ],
        ];
    @endphp

    @foreach ($cards as $card)
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div>
                            <div class="subheader">{{ $card['label'] }}</div>
                            <div class="h1 mb-0 mt-2">{{ $card['value'] }}</div>
                        </div>

                        <span class="sp-stat-icon {{ $card['class'] }} ms-auto">
                            <i class="ti {{ $card['icon'] }}"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Distribución por plan</h3>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Plan</th>
                        <th class="text-end">Licencias actuales</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse ($planDistribution as $plan)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $plan->name }}</div>
                                <div class="small text-secondary">{{ $plan->code }}</div>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-blue-lt text-blue">
                                    {{ number_format($plan->total) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-secondary text-center py-4">
                                Sin planes registrados.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Escuelas cerca del límite</h3>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Escuela</th>
                        <th>Alumnos</th>
                        <th style="min-width: 11rem;">Uso</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse ($schoolsNearLimit as $school)
                        @php
                            $progressClass = $school->usage_percent >= 100
                                ? 'bg-red'
                                : 'bg-yellow';
                        @endphp

                        <tr>
                            <td>
                                <a
                                    href="{{ route('sysadmin.schools.show', $school->id) }}"
                                    class="fw-semibold text-reset"
                                >
                                    {{ $school->name }}
                                </a>
                                <div class="small text-secondary">
                                    {{ $school->plan_name ?: 'Sin plan' }}
                                </div>
                            </td>

                            <td>
                                {{ number_format($school->students_used) }}
                                /
                                {{ number_format($school->student_limit) }}
                            </td>

                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress progress-sm flex-fill">
                                        <div
                                            class="progress-bar {{ $progressClass }}"
                                            style="width: {{ min(100, $school->usage_percent) }}%"
                                            role="progressbar"
                                            aria-valuenow="{{ min(100, $school->usage_percent) }}"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                        ></div>
                                    </div>

                                    <span class="small fw-semibold">
                                        {{ $school->usage_percent }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-secondary text-center py-4">
                                Ninguna escuela ha llegado al 80%.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Actividad reciente de licencias</h3>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Escuela</th>
                        <th>Evento</th>
                        <th>Cambio</th>
                        <th>Realizado por</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse ($recentEvents as $event)
                        <tr>
                            <td class="text-secondary text-nowrap">
                                {{ \Illuminate\Support\Carbon::parse($event->created_at)->format('d/m/Y H:i') }}
                            </td>

                            <td>
                                <a
                                    href="{{ route('sysadmin.schools.show', $event->school_id) }}"
                                    class="fw-semibold text-reset"
                                >
                                    {{ $event->school_name }}
                                </a>
                            </td>

                            <td>
                                <span class="badge bg-azure-lt text-azure">
                                    {{ str_replace('_', ' ', $event->event_type) }}
                                </span>
                            </td>

                            <td class="text-nowrap">
                                {{ $event->previous_status ?: '—' }}
                                <i class="ti ti-arrow-right mx-1 text-secondary"></i>
                                {{ $event->new_status ?: '—' }}
                            </td>

                            <td>{{ $event->performed_by_name ?: 'Sistema' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-secondary text-center py-4">
                                Sin actividad registrada.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
