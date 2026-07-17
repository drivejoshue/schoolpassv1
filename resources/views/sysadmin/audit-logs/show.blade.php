@extends('layouts.sysadmin')

@section('title', 'Detalle de auditoría')
@section('page_title', 'Detalle de auditoría')

@section('content')
<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <a
                href="{{ route(
                    'sysadmin.audit-logs.index'
                ) }}"
                class="text-secondary"
            >
                <i class="ti ti-arrow-left me-1"></i>
                Regresar a auditoría
            </a>

            <h2 class="page-title mt-2">
                Evento #{{ $auditLog->id }}
            </h2>
        </div>
    </div>
</div>

<div class="row row-cards">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Información
                </h3>
            </div>

            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Fecha</dt>
                    <dd class="col-7">
                        {{ $auditLog->created_at?->format(
                            'd/m/Y H:i:s'
                        ) }}
                    </dd>

                    <dt class="col-5">Acción</dt>
                    <dd class="col-7">
                        {{ $auditLog->action }}
                    </dd>

                    <dt class="col-5">Escuela</dt>
                    <dd class="col-7">
                        {{ $auditLog->school?->name
                            ?: 'Global'
                        }}
                    </dd>

                    <dt class="col-5">Actor</dt>
                    <dd class="col-7">
                        {{ $auditLog->actor?->name
                            ?: 'Sistema'
                        }}
                    </dd>

                    <dt class="col-5">Tipo</dt>
                    <dd class="col-7">
                        {{ $auditLog->actor_type }}
                    </dd>

                    <dt class="col-5">Entidad</dt>
                    <dd class="col-7">
                        {{ $auditLog->entity_type ?: '—' }}

                        @if ($auditLog->entity_id)
                            #{{ $auditLog->entity_id }}
                        @endif
                    </dd>

                    <dt class="col-5">Dirección IP</dt>
                    <dd class="col-7">
                        {{ $auditLog->ip_address ?: '—' }}
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    Valores anteriores
                </h3>
            </div>

            <div class="card-body">
                <pre class="mb-0"><code>{{ json_encode(
                    $auditLog->old_values ?? [],
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ) }}</code></pre>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Valores nuevos
                </h3>
            </div>

            <div class="card-body">
                <pre class="mb-0"><code>{{ json_encode(
                    $auditLog->new_values ?? [],
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ) }}</code></pre>
            </div>
        </div>
    </div>
</div>
@endsection