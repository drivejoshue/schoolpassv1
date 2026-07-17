@extends('layouts.app')

@section('title', 'Licencia | SchoolPass')
@section('section-label', 'Administración')
@section('page-title', 'Licencia SchoolPass')

@section('content')
@php
    $statusData = match ($state['status']) {
        'active' => [
            'Activa',
            'bg-green-lt text-green',
            'ti-circle-check',
        ],

        'trial' => [
            'Periodo de prueba',
            'bg-blue-lt text-blue',
            'ti-clock',
        ],

        'grace' => [
            'Periodo de gracia',
            'bg-yellow-lt text-yellow',
            'ti-alert-triangle',
        ],

        'expired' => [
            'Vencida',
            'bg-red-lt text-red',
            'ti-calendar-x',
        ],

        'suspended' => [
            'Suspendida',
            'bg-orange-lt text-orange',
            'ti-player-pause',
        ],

        'cancelled' => [
            'Cancelada',
            'bg-red-lt text-red',
            'ti-ban',
        ],

        default => [
            'Sin licencia',
            'bg-secondary-lt text-secondary',
            'ti-license-off',
        ],
    };
@endphp

<div class="row row-cards">

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div
                    class="d-flex flex-column flex-md-row
                           align-items-md-center gap-3"
                >
                    <span
                        class="avatar avatar-lg
                               {{ $statusData[1] }}"
                    >
                        <i class="ti {{ $statusData[2] }} fs-1"></i>
                    </span>

                    <div class="flex-fill">
                        <div class="subheader">
                            Estado actual
                        </div>

                        <h2 class="mb-1">
                            {{ $statusData[0] }}
                        </h2>

                        <div class="text-secondary">
                            {{ $state['message'] }}
                        </div>
                    </div>

                    <span class="badge fs-4 {{ $statusData[1] }}">
                        {{ $statusData[0] }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">
                    Información contractual
                </h3>
            </div>

            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-secondary">
                        Plan
                    </dt>
                    <dd class="col-sm-7">
                        {{ $state['plan_name'] ?: '—' }}
                    </dd>

                    <dt class="col-sm-5 text-secondary">
                        Ciclo
                    </dt>
                    <dd class="col-sm-7">
                        {{ $state['billing_cycle']
                            ? ucfirst($state['billing_cycle'])
                            : '—'
                        }}
                    </dd>

                    <dt class="col-sm-5 text-secondary">
                        Fecha de inicio
                    </dt>
                    <dd class="col-sm-7">
                        {{ $state['starts_at']
                            ? \Illuminate\Support\Carbon::parse(
                                $state['starts_at']
                            )->format('d/m/Y')
                            : '—'
                        }}
                    </dd>

                    <dt class="col-sm-5 text-secondary">
                        Fecha de vencimiento
                    </dt>
                    <dd class="col-sm-7">
                        {{ $state['expires_at']
                            ? \Illuminate\Support\Carbon::parse(
                                $state['expires_at']
                            )->format('d/m/Y')
                            : 'Sin vencimiento'
                        }}
                    </dd>

                    <dt class="col-sm-5 text-secondary">
                        Fin del periodo de gracia
                    </dt>
                    <dd class="col-sm-7">
                        {{ $state['grace_ends_at']
                            ? \Illuminate\Support\Carbon::parse(
                                $state['grace_ends_at']
                            )->format('d/m/Y')
                            : '—'
                        }}
                    </dd>

                    <dt class="col-sm-5 text-secondary">
                        Días restantes
                    </dt>
                    <dd class="col-sm-7">
                        {{ $state['days_remaining'] !== null
                            ? max(0, $state['days_remaining'])
                            : '—'
                        }}
                    </dd>

                    <dt class="col-sm-5 text-secondary">
                        Renovación automática
                    </dt>
                    <dd class="col-sm-7">
                        {{ $state['auto_renew'] ? 'Sí' : 'No' }}
                    </dd>

                    <dt class="col-sm-5 text-secondary">
                        Precio contratado
                    </dt>
                    <dd class="col-sm-7">
                        @if ($state['contract_price'] !== null)
                            ${{ number_format(
                                $state['contract_price'],
                                2
                            ) }}
                            {{ $state['currency'] }}
                        @else
                            —
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">
                    Límites contratados
                </h3>
            </div>

            <div class="card-body">
                <div class="list-group list-group-flush">
                    @foreach ([
                        'students' => [
                            'Alumnos',
                            'ti-users',
                        ],
                        'devices' => [
                            'Dispositivos',
                            'ti-device-tablet',
                        ],
                        'staff' => [
                            'Usuarios staff',
                            'ti-user-shield',
                        ],
                        'campuses' => [
                            'Planteles',
                            'ti-building-community',
                        ],
                    ] as $key => [$label, $icon])
                        <div class="list-group-item px-0">
                            <div class="d-flex align-items-center">
                                <i
                                    class="ti {{ $icon }}
                                           text-secondary me-3"
                                ></i>

                                <span class="flex-fill">
                                    {{ $label }}
                                </span>

                                <strong>
                                    {{ $state['limits'][$key] === null
                                        ? 'Sin límite'
                                        : number_format(
                                            $state['limits'][$key]
                                        )
                                    }}
                                </strong>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">
                    Renovación y soporte
                </h3>
            </div>

            <div class="card-body">
                <p class="text-secondary">
                    Los cambios de plan, precio y vigencia son administrados
                    por SchoolPass.
                </p>

                <dl class="mb-0">
                    <dt>Correo</dt>
                    <dd>
                        {{ $state['renewal_contact']['email']
                            ?: 'No configurado'
                        }}
                    </dd>

                    <dt>Teléfono</dt>
                    <dd>
                        {{ $state['renewal_contact']['phone']
                            ?: 'No configurado'
                        }}
                    </dd>

                    <dt>WhatsApp</dt>
                    <dd>
                        {{ $state['renewal_contact']['whatsapp']
                            ?: 'No configurado'
                        }}
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Historial de la licencia
                </h3>
            </div>

            <div class="list-group list-group-flush">
                @forelse ($events as $event)
                    <div class="list-group-item">
                        <div class="d-flex gap-3">
                            <span
                                class="avatar avatar-sm
                                       bg-azure-lt text-azure"
                            >
                                <i class="ti ti-history"></i>
                            </span>

                            <div class="flex-fill">
                                <div class="fw-semibold">
                                    {{ ucfirst(str_replace(
                                        '_',
                                        ' ',
                                        $event->event_type
                                    )) }}
                                </div>

                                <div class="small text-secondary">
                                    {{ $event->previous_status ?: '—' }}
                                    →
                                    {{ $event->new_status ?: '—' }}
                                </div>

                                <div class="small text-secondary mt-1">
                                    {{ \Illuminate\Support\Carbon::parse(
                                        $event->created_at
                                    )->format('d/m/Y H:i') }}

                                    ·

                                    {{ $event->performed_by_name
                                        ?: 'Sistema'
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div
                        class="list-group-item
                               text-center text-secondary py-4"
                    >
                        No hay eventos registrados.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection