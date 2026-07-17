@extends('layouts.sysadmin')

@section('title', 'Planes')
@section('page_title', 'Planes y licencias')

@section('content')
<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Catálogo comercial</div>
            <h2 class="page-title">Planes y licencias</h2>
            <div class="text-secondary mt-1">
                Precios, límites, funciones incluidas y licencias actuales.
            </div>
        </div>
    </div>
</div>

<div class="row row-cards">
    @foreach ($plans as $plan)
        <div class="col-12 col-xl-6">
            <div class="card h-100">

                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-1">{{ $plan->name }}</h3>
                        <div class="small text-secondary">
                            <code>{{ $plan->code }}</code>
                        </div>
                    </div>

                    <div class="card-actions">
                        <span class="badge {{
                            $plan->status === 'active'
                                ? 'bg-green-lt text-green'
                                : 'bg-red-lt text-red'
                        }}">
                            {{ $plan->status === 'active' ? 'Activo' : $plan->status }}
                        </span>
                    </div>
                </div>

                <div class="card-body">
                    <p class="text-secondary">
                        {{ $plan->description }}
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <div class="card card-sm bg-primary-lt">
                                <div class="card-body">
                                    <div class="subheader">Mensual</div>
                                    <div class="h2 mb-0 mt-2 text-primary">
                                        {{ $plan->monthly_price === null
                                            ? 'Cotización'
                                            : '$'.number_format((float) $plan->monthly_price, 2)
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="card card-sm bg-green-lt">
                                <div class="card-body">
                                    <div class="subheader">Anual</div>
                                    <div class="h2 mb-0 mt-2 text-green">
                                        {{ $plan->annual_price === null
                                            ? 'Cotización'
                                            : '$'.number_format((float) $plan->annual_price, 2)
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <dl class="sp-definition-list mb-4">
                        <dt>Alumnos</dt>
                        <dd>
                            {{ $plan->student_limit === null
                                ? 'Personalizado'
                                : number_format($plan->student_limit)
                            }}
                        </dd>

                        <dt>Dispositivos</dt>
                        <dd>
                            {{ $plan->device_limit === null
                                ? 'Sin límite configurado'
                                : number_format($plan->device_limit)
                            }}
                        </dd>

                        <dt>Usuarios staff</dt>
                        <dd>
                            {{ $plan->staff_limit === null
                                ? 'Sin límite configurado'
                                : number_format($plan->staff_limit)
                            }}
                        </dd>

                        <dt>Planteles</dt>
                        <dd>
                            {{ $plan->campus_limit === null
                                ? 'Personalizado'
                                : number_format($plan->campus_limit)
                            }}
                        </dd>

                        <dt>Soporte</dt>
                        <dd>{{ ucfirst($plan->support_level) }}</dd>

                        <dt>Licencias actuales</dt>
                        <dd>
                            <span class="badge bg-blue-lt text-blue">
                                {{ number_format($plan->current_licenses_count) }}
                            </span>
                        </dd>
                    </dl>

                    <h4 class="mb-3">Funciones</h4>

                    <div class="row g-2">
                        @foreach ($plan->features as $feature)
                            <div class="col-12 col-sm-6">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-{{ $feature->is_enabled ? 'green' : 'red' }}">
                                        <i class="ti {{
                                            $feature->is_enabled
                                                ? 'ti-circle-check-filled'
                                                : 'ti-circle-x-filled'
                                        }}"></i>
                                    </span>

                                    <code class="text-reset">
                                        {{ $feature->feature_key }}
                                    </code>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>
    @endforeach
</div>
@endsection
