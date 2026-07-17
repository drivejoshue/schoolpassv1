@extends('layouts.sysadmin')

@section('title', 'Auditoría')
@section('page_title', 'Auditoría')

@section('content')
<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">
                Seguridad
            </div>

            <h2 class="page-title">
                Registro de auditoría
            </h2>

            <div class="text-secondary mt-1">
                Cambios administrativos, licencias y sesiones de soporte.
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET">
            <div class="row g-2">
                <div class="col-12 col-lg-4">
                    <input
                        type="search"
                        name="q"
                        class="form-control"
                        value="{{ $filters['q'] ?? '' }}"
                        placeholder="Acción, usuario o escuela"
                    >
                </div>

                <div class="col-12 col-md-4 col-lg-2">
                    <select
                        name="school_id"
                        class="form-select"
                    >
                        <option value="">
                            Todas las escuelas
                        </option>

                        @foreach ($schools as $school)
                            <option
                                value="{{ $school->id }}"
                                @selected(
                                    (string) ($filters['school_id'] ?? '')
                                    === (string) $school->id
                                )
                            >
                                {{ $school->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-4 col-lg-2">
                    <select
                        name="action"
                        class="form-select"
                    >
                        <option value="">
                            Todas las acciones
                        </option>

                        @foreach ($actions as $action)
                            <option
                                value="{{ $action }}"
                                @selected(
                                    ($filters['action'] ?? '')
                                    === $action
                                )
                            >
                                {{ str_replace('_', ' ', $action) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <input
                        type="date"
                        name="date_from"
                        class="form-control"
                        value="{{ $filters['date_from'] ?? '' }}"
                    >
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <input
                        type="date"
                        name="date_to"
                        class="form-control"
                        value="{{ $filters['date_to'] ?? '' }}"
                    >
                </div>

                <div class="col-auto">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="ti ti-filter me-2"></i>
                        Filtrar
                    </button>
                </div>

                <div class="col-auto">
                    <a
                        href="{{ route(
                            'sysadmin.audit-logs.index'
                        ) }}"
                        class="btn btn-outline-secondary"
                    >
                        Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Escuela</th>
                <th>Actor</th>
                <th>Acción</th>
                <th>Entidad</th>
                <th class="w-1"></th>
            </tr>
            </thead>

            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="text-nowrap">
                        {{ $log->created_at?->format(
                            'd/m/Y H:i:s'
                        ) }}
                    </td>

                    <td>
                        {{ $log->school?->name
                            ?: 'Global'
                        }}
                    </td>

                    <td>
                        <div class="fw-semibold">
                            {{ $log->actor?->name
                                ?: 'Sistema'
                            }}
                        </div>

                        <div class="small text-secondary">
                            {{ $log->actor_type }}
                        </div>
                    </td>

                    <td>
                        <span class="badge bg-blue-lt text-blue">
                            {{ str_replace(
                                '_',
                                ' ',
                                $log->action
                            ) }}
                        </span>
                    </td>

                    <td>
                        @if ($log->entity_type)
                            {{ class_basename(
                                $log->entity_type
                            ) }}

                            @if ($log->entity_id)
                                #{{ $log->entity_id }}
                            @endif
                        @else
                            —
                        @endif
                    </td>

                    <td>
                        <a
                            href="{{ route(
                                'sysadmin.audit-logs.show',
                                $log
                            ) }}"
                            class="btn btn-icon btn-ghost-primary"
                            title="Ver detalle"
                        >
                            <i class="ti ti-eye"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td
                        colspan="6"
                        class="text-center text-secondary py-5"
                    >
                        No hay registros con estos filtros.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection