@extends('layouts.app')

@section('title', 'Dispositivos | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Dispositivos')

@section('topbar-actions')
    <a href="{{ route('admin.devices.create') }}" class="btn btn-primary btn-sm">
        <i class="ti ti-plus me-1"></i>
        Nuevo dispositivo
    </a>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">
            <i class="ti ti-circle-check me-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @php
        $typeLabels = [
            'prefect_app' => 'Prefectura',
            'kiosk' => 'Kiosco',
            'scanner' => 'Scanner',
            'door_controller' => 'Controlador puerta',
            'mobile' => 'Móvil',
            'other' => 'Otro',
        ];

        $modeLabels = [
            'attendance' => 'Asistencia',
            'restricted_access' => 'Acceso restringido',
            'log_only' => 'Solo registro',
        ];

        $eventLabels = [
            'entry' => 'Entrada',
            'exit' => 'Salida',
            'access' => 'Acceso',
        ];
    @endphp

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Dispositivos de acceso</h3>
                <p class="card-subtitle">
                    Prefectura, kioscos, lectores QR, controladores de puerta y futuros dispositivos físicos.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Dispositivo</th>
                        <th>Área</th>
                        <th>Tipo</th>
                        <th>Modo</th>
                        <th>Evento</th>
                        <th>Asignado</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($devices as $device)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $device->name }}</div>
                                <div class="text-secondary small">{{ $device->device_uuid }}</div>
                            </td>

                            <td>
                                <div>{{ $device->area_name ?? 'Sin área' }}</div>
                                <div class="text-secondary small">{{ $device->campus_name ?? '' }}</div>
                            </td>

                            <td>
                                {{ $typeLabels[$device->device_type] ?? $device->device_type }}
                            </td>

                            <td>
                                {{ $modeLabels[$device->mode] ?? $device->mode }}
                            </td>

                            <td>
                                {{ $eventLabels[$device->default_event_type] ?? $device->default_event_type }}
                            </td>

                            <td>
                                @if($device->assigned_user_name)
                                    <div class="fw-bold">{{ $device->assigned_user_name }}</div>
                                    <div class="text-secondary small">{{ $device->assigned_user_email ?? '' }}</div>
                                @else
                                    <span class="badge bg-warning-lt">Sin usuario</span>
                                @endif
                            </td>

                            <td>
                                @if($device->status === 'active')
                                    <span class="badge bg-success-lt">Activo</span>
                                @elseif($device->status === 'blocked')
                                    <span class="badge bg-danger-lt">Bloqueado</span>
                                @else
                                    <span class="badge bg-secondary-lt">Inactivo</span>
                                @endif
                            </td>

                            <td>
                                <div class="btn-list flex-nowrap">
                                    @if(in_array($device->device_type, ['kiosk', 'scanner', 'door_controller'], true))
                                        <a
                                            href="{{ route('kiosk.access', ['device_uuid' => $device->device_uuid]) }}"
                                            class="btn btn-sm btn-primary"
                                            target="_blank"
                                        >
                                            Abrir
                                        </a>
                                    @endif

                                    <a href="{{ route('admin.devices.edit', $device->id) }}" class="btn btn-sm btn-outline-primary">
                                        Editar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-5">
                                No hay dispositivos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection