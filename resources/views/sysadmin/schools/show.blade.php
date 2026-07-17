@extends('layouts.sysadmin')

@section('title', $schoolData->name)
@section('page_title', 'Detalle de escuela')

@section('content')
@php
    $schoolStatus = match ($schoolData->status) {
        'active' => ['Activa', 'bg-green-lt text-green'],
        'suspended' => ['Suspendida', 'bg-yellow-lt text-yellow'],
        'cancelled' => ['Cancelada', 'bg-red-lt text-red'],
        default => [
            $schoolData->status,
            'bg-secondary-lt text-secondary',
        ],
    };

    $licenseStatus = match ($license?->status) {
        'active' => ['Activa', 'bg-green-lt text-green'],
        'trial' => ['Prueba', 'bg-blue-lt text-blue'],
        'grace' => ['Gracia', 'bg-yellow-lt text-yellow'],
        'expired' => ['Vencida', 'bg-red-lt text-red'],
        'suspended' => [
            'Suspendida',
            'bg-orange-lt text-orange',
        ],
        'cancelled' => ['Cancelada', 'bg-red-lt text-red'],
        default => [
            'Sin licencia',
            'bg-secondary-lt text-secondary',
        ],
    };
@endphp

<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">
                <a
                    href="{{ route('sysadmin.schools.index') }}"
                    class="text-secondary text-decoration-none"
                >
                    <i class="ti ti-arrow-left me-1"></i>
                    Escuelas
                </a>
            </div>

            <div class="d-flex align-items-center gap-2 mt-1">
                <h2 class="page-title mb-0">
                    {{ $schoolData->name }}
                </h2>

                <span class="badge {{ $schoolStatus[1] }}">
                    {{ $schoolStatus[0] }}
                </span>
            </div>

            <div class="text-secondary mt-1">
                {{ $schoolData->legal_name ?: $schoolData->slug }}
            </div>
        </div>

        <div class="col-auto ms-auto">
            <div class="btn-list">
               <a
    href="{{ route(
        'sysadmin.schools.administrators.index',
        $schoolData
    ) }}"
    class="btn btn-outline-primary"
>
    <i class="ti ti-users me-2"></i>
    Administradores
</a>

<a
    href="{{ route(
        'sysadmin.schools.app-config.edit',
        $schoolData
    ) }}"
    class="btn btn-outline-primary"
>
    <i class="ti ti-device-mobile-cog me-2"></i>
    Configurar apps
</a>
                <button
                    type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#modal-license"
                >
                    <i class="ti ti-license me-2"></i>
                    {{ $license ? 'Cambiar plan' : 'Asignar plan' }}
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row row-deck row-cards">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Datos generales</h3>
            </div>

            <div class="card-body">
                <dl class="sp-definition-list">
                    <dt>Nombre legal</dt>
                    <dd>{{ $schoolData->legal_name ?: '—' }}</dd>

                    <dt>Slug</dt>
                    <dd><code>{{ $schoolData->slug }}</code></dd>

                    <dt>Zona horaria</dt>
                    <dd>{{ $schoolData->timezone }}</dd>

                    <dt>Contacto</dt>
                    <dd>{{ $schoolData->contact_name ?: '—' }}</dd>

                    <dt>Correo</dt>
                    <dd>{{ $schoolData->contact_email ?: '—' }}</dd>

                    <dt>Teléfono</dt>
                    <dd>{{ $schoolData->contact_phone ?: '—' }}</dd>

                    <dt>RFC / Tax ID</dt>
                    <dd>{{ $schoolData->tax_id ?: '—' }}</dd>

                    <dt>Dirección</dt>
                    <dd>{{ $schoolData->address ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Licencia actual</h3>

                <div class="card-actions">
                    <span class="badge {{ $licenseStatus[1] }}">
                        {{ $licenseStatus[0] }}
                    </span>
                </div>
            </div>

            <div class="card-body">
                @if ($license)
                    <dl class="sp-definition-list">
                        <dt>Plan</dt>
                        <dd>{{ $license->plan_name ?: '—' }}</dd>

                        <dt>Ciclo</dt>
                        <dd>{{ ucfirst($license->billing_cycle) }}</dd>

                        <dt>Inicio</dt>
                        <dd>
                            {{ \Illuminate\Support\Carbon::parse(
                                $license->starts_at
                            )->format('d/m/Y') }}
                        </dd>

                        <dt>Vencimiento</dt>
                        <dd>
                            {{ $license->expires_at
                                ? \Illuminate\Support\Carbon::parse(
                                    $license->expires_at
                                )->format('d/m/Y')
                                : 'Sin vencimiento'
                            }}
                        </dd>

                        <dt>Precio contratado</dt>
                        <dd>
                            ${{ number_format(
                                (float) $license->contract_price,
                                2
                            ) }}
                            {{ $license->currency }}
                        </dd>

                        <dt>Renovación automática</dt>
                        <dd>{{ $license->auto_renew ? 'Sí' : 'No' }}</dd>
                    </dl>

                    <div class="btn-list mt-4">
                        <button
                            type="button"
                            class="btn btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#modal-renew"
                        >
                            Renovar
                        </button>

                        @if ($license->status === 'trial')
                            <button
                                type="button"
                                class="btn btn-outline-warning"
                                data-bs-toggle="modal"
                                data-bs-target="#modal-trial"
                            >
                                Extender prueba
                            </button>
                        @endif

                        @if ($license->status === 'suspended')
                            <form
                                method="POST"
                                action="{{ route(
                                    'sysadmin.schools.license.reactivate',
                                    $schoolData
                                ) }}"
                            >
                                @csrf
                                <button
                                    type="submit"
                                    class="btn btn-success"
                                >
                                    Reactivar licencia
                                </button>
                            </form>
                        @else
                            <form
                                method="POST"
                                action="{{ route(
                                    'sysadmin.schools.license.suspend',
                                    $schoolData
                                ) }}"
                            >
                                @csrf
                                <button
                                    type="submit"
                                    class="btn btn-outline-warning"
                                >
                                    Suspender licencia
                                </button>
                            </form>
                        @endif
                    </div>
                @else
                    <div class="empty py-4">
                        <div class="empty-icon">
                            <i class="ti ti-license-off"></i>
                        </div>

                        <p class="empty-title">Sin licencia actual</p>

                        <p class="empty-subtitle text-secondary">
                            Asigna plan, fechas, costo y límites.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @php
        $resourceLabels = [
            'students' => ['Alumnos', 'ti-users', 'blue'],
            'devices' => [
                'Dispositivos',
                'ti-device-tablet',
                'purple',
            ],
            'staff' => [
                'Usuarios staff',
                'ti-user-shield',
                'azure',
            ],
            'campuses' => [
                'Planteles',
                'ti-building-community',
                'green',
            ],
        ];
    @endphp

    @foreach ($usage as $resource => $item)
        @php
            [$label, $icon, $color] = $resourceLabels[$resource];
        @endphp

        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <div>
                            <div class="subheader">{{ $label }}</div>

                            <div class="h2 mb-0 mt-2">
                                {{ number_format($item['used']) }}
                                /
                                {{ $item['limit'] === null
                                    ? '∞'
                                    : number_format($item['limit'])
                                }}
                            </div>
                        </div>

                        <span
                            class="sp-stat-icon bg-{{ $color }}-lt text-{{ $color }} ms-auto"
                        >
                            <i class="ti {{ $icon }}"></i>
                        </span>
                    </div>

                    @if ($item['percent'] !== null)
                        <div class="mt-3">
                            <div class="d-flex mb-1">
                                <span class="text-secondary">Uso</span>
                                <span class="ms-auto fw-semibold">
                                    {{ $item['percent'] }}%
                                </span>
                            </div>

                            <div class="progress progress-sm">
                                <div
                                    class="progress-bar bg-{{ $color }}"
                                    style="width: {{ min(
                                        100,
                                        $item['percent']
                                    ) }}%"
                                ></div>
                            </div>
                        </div>
                    @else
                        <div class="small text-secondary mt-3">
                            Sin límite configurado
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    <div class="col-12">
        <div class="card">
            <form
                method="POST"
                action="{{ route(
                    'sysadmin.schools.license.limits',
                    $schoolData
                ) }}"
            >
                @csrf
                @method('PUT')

                <div class="card-header">
                    <h3 class="card-title">Límites contratados</h3>
                </div>

                <div class="card-body">
                    @if ($license)
                        <div class="row g-3">
                            @foreach ([
                                'student_limit' => 'Alumnos',
                                'device_limit' => 'Dispositivos',
                                'staff_limit' => 'Usuarios staff',
                                'campus_limit' => 'Planteles',
                            ] as $field => $label)
                                <div class="col-sm-6 col-lg-3">
                                    <label class="form-label">
                                        {{ $label }}
                                    </label>

                                    <input
                                        type="number"
                                        name="{{ $field }}"
                                        class="form-control"
                                        value="{{ $license->{$field} }}"
                                        min="0"
                                        placeholder="Sin límite"
                                    >
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-warning mb-0">
                            Primero asigna una licencia.
                        </div>
                    @endif
                </div>

                @if ($license)
                    <div class="card-footer text-end">
                        <button class="btn btn-primary" type="submit">
                            Guardar límites
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <form
                method="POST"
                action="{{ route(
                    'sysadmin.schools.features.update',
                    $schoolData
                ) }}"
            >
                @csrf
                @method('PUT')

                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Funciones habilitadas
                        </h3>

                        <div class="small text-secondary">
                            Heredar conserva lo incluido en el contrato.
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th>Función</th>
                            <th>Incluida</th>
                            <th>Excepción</th>
                            <th>Resultado</th>
                        </tr>
                        </thead>

                        <tbody>
                        @forelse ($featureMatrix as $feature)
                            <tr>
                                <td>
                                    <code>{{ $feature['key'] }}</code>
                                </td>

                                <td>
                                    {{ $feature['inherited_enabled']
                                        ? 'Sí'
                                        : 'No'
                                    }}
                                </td>

                                <td>
                                    <select
                                        name="features[{{ $feature['key'] }}]"
                                        class="form-select form-select-sm"
                                    >
                                        <option
                                            value="inherit"
                                            @selected(
                                                $feature['override']
                                                === 'inherit'
                                            )
                                        >
                                            Heredar
                                        </option>

                                        <option
                                            value="enabled"
                                            @selected(
                                                $feature['override']
                                                === 'enabled'
                                            )
                                        >
                                            Habilitar
                                        </option>

                                        <option
                                            value="disabled"
                                            @selected(
                                                $feature['override']
                                                === 'disabled'
                                            )
                                        >
                                            Deshabilitar
                                        </option>
                                    </select>
                                </td>

                                <td>
                                    <span class="badge {{
                                        $feature['effective_enabled']
                                            ? 'bg-green-lt text-green'
                                            : 'bg-red-lt text-red'
                                    }}">
                                        {{ $feature['effective_enabled']
                                            ? 'Habilitada'
                                            : 'Deshabilitada'
                                        }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="4"
                                    class="text-center text-secondary py-4"
                                >
                                    No hay funciones configuradas.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer text-end">
                    <button class="btn btn-primary" type="submit">
                        Guardar funciones
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Administradores</h3>
            </div>

            <div class="list-group list-group-flush">
                @forelse ($administrators as $administrator)
                    <div class="list-group-item">
                        <div class="fw-semibold">
                            {{ $administrator->name }}
                        </div>

                        <div class="small text-secondary">
                            {{ $administrator->email }}
                            ·
                            {{ $administrator->role }}
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-secondary">
                        Sin administradores registrados.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Configuración para la app
                </h3>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Clave</th>
                        <th>Pública</th>
                        <th>Valor</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse ($settings as $setting)
                        <tr>
                            <td><code>{{ $setting->key }}</code></td>
                            <td>{{ $setting->is_public ? 'Sí' : 'No' }}</td>
                            <td>
                                <pre class="sp-json">{{ $setting->value_json }}</pre>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="3"
                                class="text-center text-secondary py-4"
                            >
                                Sin configuraciones.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Historial comercial
                </h3>
            </div>

            <div class="list-group list-group-flush">
                @forelse ($events as $event)
                    <div class="list-group-item">
                        <div class="fw-semibold">
                            {{ str_replace(
                                '_',
                                ' ',
                                $event->event_type
                            ) }}
                        </div>

                        <div class="small text-secondary">
                            {{ $event->previous_status ?: '—' }}
                            →
                            {{ $event->new_status ?: '—' }}
                        </div>

                        <div class="small text-secondary">
                            {{ \Illuminate\Support\Carbon::parse(
                                $event->created_at
                            )->format('d/m/Y H:i') }}
                            ·
                            {{ $event->performed_by_name ?: 'Sistema' }}
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-secondary">
                        Sin eventos.
                    </div>
                @endforelse
            </div>

                 @include('sysadmin.schools.partials.support-access')
        </div>

   


    </div>
</div>

{{-- Asignación o cambio de plan --}}
<div class="modal modal-blur fade" id="modal-license" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form
            method="POST"
            action="{{ route(
                'sysadmin.schools.license.assign',
                $schoolData
            ) }}"
            class="modal-content"
        >
            @csrf

            <div class="modal-header">
                <h5 class="modal-title">
                    {{ $license
                        ? 'Cambiar plan y contrato'
                        : 'Asignar plan y contrato'
                    }}
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info">
                    Define plan, tipo de contrato, fecha de inicio,
                    vencimiento y costo real acordado con la escuela.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">Plan</label>

                        <select
                            name="subscription_plan_id"
                            class="form-select"
                            required
                        >
                            @foreach ($plans as $plan)
                                <option
                                    value="{{ $plan->id }}"
                                    @selected(
                                        (int) ($license
                                            ->subscription_plan_id ?? 0)
                                        === $plan->id
                                    )
                                >
                                    {{ $plan->name }}
                                    —
                                    {{ $plan->student_limit
                                        ? number_format(
                                            $plan->student_limit
                                        ).' alumnos'
                                        : 'Personalizado'
                                    }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label required">
                            Estado inicial
                        </label>

                        <select
                            name="status"
                            class="form-select"
                            data-license-status
                            required
                        >
                            <option value="active">Activa</option>
                            <option value="trial">Prueba</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label required">
                            Ciclo
                        </label>

                        <select
                            name="billing_cycle"
                            class="form-select"
                            data-billing-cycle
                            required
                        >
                            <option value="monthly">Mensual</option>
                            <option value="annual" selected>Anual</option>
                            <option value="custom">Personalizado</option>
                            <option value="trial">Prueba</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label required">
                            Fecha de inicio
                        </label>

                        <input
                            type="date"
                            name="starts_at"
                            class="form-control"
                            value="{{ now()->toDateString() }}"
                            required
                        >
                    </div>

                    <div class="col-md-4 d-none" data-trial-days>
                        <label class="form-label required">
                            Días de prueba
                        </label>

                        <input
                            type="number"
                            name="trial_days"
                            class="form-control"
                            value="30"
                            min="1"
                            max="365"
                        >
                    </div>

                    <div class="col-md-4 d-none" data-custom-expiration>
                        <label class="form-label required">
                            Vencimiento
                        </label>

                        <input
                            type="date"
                            name="expires_at"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Precio contratado
                        </label>

                        <div class="input-group">
                            <span class="input-group-text">$</span>

                            <input
                                type="number"
                                name="contract_price"
                                class="form-control"
                                min="0"
                                step="0.01"
                                placeholder="Usar precio del plan"
                            >
                        </div>

                        <div class="form-hint">
                            Para promociones escribe el monto real acordado.
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notas comerciales</label>

                        <textarea
                            name="notes"
                            class="form-control"
                            rows="3"
                            placeholder="Implementación, promoción, condiciones especiales..."
                        ></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-check form-switch">
                            <input
                                type="checkbox"
                                name="auto_renew"
                                value="1"
                                class="form-check-input"
                            >

                            <span class="form-check-label">
                                Renovación automática
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn me-auto"
                    data-bs-dismiss="modal"
                >
                    Cancelar
                </button>

                <button type="submit" class="btn btn-primary">
                    Guardar contrato
                </button>
            </div>
        </form>
    </div>
</div>

@if ($license)
    <div class="modal modal-blur fade" id="modal-renew" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form
                method="POST"
                action="{{ route(
                    'sysadmin.schools.license.renew',
                    $schoolData
                ) }}"
                class="modal-content"
            >
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">Renovar licencia</h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">
                            Nuevo ciclo
                        </label>

                        <select
                            name="billing_cycle"
                            class="form-select"
                            required
                        >
                            <option value="monthly">Mensual</option>
                            <option value="annual" selected>Anual</option>
                            <option value="custom">Personalizado</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Inicio de la renovación
                        </label>

                        <input
                            type="date"
                            name="starts_at"
                            class="form-control"
                            value="{{ $license->expires_at
                                ? \Illuminate\Support\Carbon::parse(
                                    $license->expires_at
                                )->addDay()->toDateString()
                                : now()->toDateString()
                            }}"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Vencimiento personalizado
                        </label>

                        <input
                            type="date"
                            name="expires_at"
                            class="form-control"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">
                            Precio de renovación
                        </label>

                        <div class="input-group">
                            <span class="input-group-text">$</span>

                            <input
                                type="number"
                                name="contract_price"
                                class="form-control"
                                value="{{ $license->contract_price }}"
                                min="0"
                                step="0.01"
                                required
                            >
                        </div>
                    </div>

                    <label class="form-check form-switch">
                        <input
                            type="checkbox"
                            name="auto_renew"
                            value="1"
                            class="form-check-input"
                            @checked($license->auto_renew)
                        >

                        <span class="form-check-label">
                            Renovación automática
                        </span>
                    </label>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn me-auto"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Renovar licencia
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($license?->status === 'trial')
    <div class="modal modal-blur fade" id="modal-trial" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form
                method="POST"
                action="{{ route(
                    'sysadmin.schools.license.extend-trial',
                    $schoolData
                ) }}"
                class="modal-content"
            >
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">Extender prueba</h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">
                            Días adicionales
                        </label>

                        <input
                            type="number"
                            name="days"
                            class="form-control"
                            value="7"
                            min="1"
                            max="365"
                            required
                        >
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Motivo</label>

                        <textarea
                            name="reason"
                            class="form-control"
                            rows="3"
                        ></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn me-auto"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Extender prueba
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    (function () {
        const status = document.querySelector('[data-license-status]');
        const cycle = document.querySelector('[data-billing-cycle]');
        const trialDays = document.querySelector('[data-trial-days]');
        const customExpiration = document.querySelector(
            '[data-custom-expiration]'
        );

        if (!status || !cycle) {
            return;
        }

        function syncFields() {
            const trial = status.value === 'trial';

            if (trial) {
                cycle.value = 'trial';
            } else if (cycle.value === 'trial') {
                cycle.value = 'annual';
            }

            trialDays?.classList.toggle('d-none', !trial);

            customExpiration?.classList.toggle(
                'd-none',
                cycle.value !== 'custom'
            );
        }

        status.addEventListener('change', syncFields);
        cycle.addEventListener('change', syncFields);
        syncFields();
    })();
</script>
@endpush
