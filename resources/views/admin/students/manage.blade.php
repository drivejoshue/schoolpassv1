@extends('layouts.app')

@section('title', 'Gestionar alumno | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Gestión de alumno y grupo')

@section('topbar-actions')
    <a
        href="{{ route(
            'admin.students.show',
            $studentRow->id
        ) }}"
        class="btn btn-outline-primary btn-sm"
    >
        <i class="ti ti-user me-1"></i>
        Ficha del alumno
    </a>

    <a
        href="{{ route(
            'admin.students.index'
        ) }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Alumnos
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

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center">
                @if($studentRow->photo_url)
                    <span
                        class="avatar avatar-xl me-3"
                        style="background-image: url('{{
                            asset($studentRow->photo_url)
                        }}')"
                    ></span>
                @else
                    <span class="avatar avatar-xl bg-blue-lt me-3">
                        {{ strtoupper(
                            mb_substr(
                                $studentRow->first_name,
                                0,
                                1
                            )
                        ) }}
                    </span>
                @endif

                <div class="flex-fill">
                    <h2 class="mb-1">
                        {{ $studentRow->first_name }}
                        {{ $studentRow->last_name }}
                    </h2>

                    <div class="text-secondary">
                        Matrícula:
                        <strong>
                            {{ $studentRow->student_code }}
                        </strong>
                    </div>

                    <div class="mt-2">
                        <span class="badge bg-{{
                            $studentRow->status === 'active'
                                ? 'success'
                                : 'secondary'
                        }}-lt">
                            {{ ucfirst(
                                $studentRow->status
                            ) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(! $activeCycle)
        <div class="alert alert-warning">
            <i class="ti ti-calendar-off me-2"></i>

            <strong>No existe un ciclo activo.</strong>

            Puedes consultar al alumno, pero no asignarlo
            ni cambiarlo de grupo.
        </div>
    @else
        <div class="alert alert-info">
            <i class="ti ti-calendar-check me-2"></i>

            Ciclo operativo:

            <strong>
                {{ $activeCycle->name }}
            </strong>

            @if($activeEnrollment)
                · Grupo vigente:

                <strong>
                    {{ $activeEnrollment->level_name }}
                    ·
                    {{ $activeEnrollment->group_name }}
                </strong>
            @else
                ·

                <strong>
                    El alumno todavía no está inscrito
                    en este ciclo.
                </strong>
            @endif
        </div>

        <div class="row row-cards">
            <div class="col-xl-8">
                <form
                    method="POST"
                    action="{{ route(
                        'admin.students.enrollment.update',
                        $studentRow->id
                    ) }}"
                    class="card"
                >
                    @csrf
                    @method('PATCH')

                    <div class="card-header">
                        <div>
                            <h3 class="card-title">
                                Asignación y movimiento
                            </h3>

                            <p class="card-subtitle">
                                Inscribe al alumno o cámbialo de grupo
                                dentro del ciclo activo.
                            </p>
                        </div>
                    </div>

                    <div class="card-body">
                        <input
                            type="hidden"
                            name="action"
                            value="assign_group"
                        >

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">
                                    Grupo destino
                                </label>

                                <select
                                    name="group_id"
                                    class="form-select"
                                    required
                                >
                                    <option value="">
                                        Selecciona un grupo
                                    </option>

                                    @foreach($groups as $group)
                                        <option
                                            value="{{ $group->id }}"
                                            @selected(
                                                (string) old(
                                                    'group_id'
                                                )
                                                === (string) $group->id
                                            )
                                        >
                                            {{ $group->level_name }}
                                            ·
                                            {{ $group->name }}
                                            ·
                                            {{ $group->campus_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">
                                    Fecha efectiva
                                </label>

                                <input
                                    type="date"
                                    name="effective_on"
                                    value="{{ old(
                                        'effective_on',
                                        now()->toDateString()
                                    ) }}"
                                    class="form-control"
                                    required
                                >
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">
                                    Motivo
                                </label>

                                <input
                                    type="text"
                                    name="reason"
                                    value="{{ old('reason') }}"
                                    class="form-control"
                                    maxlength="255"
                                    placeholder="Ej. Cambio administrativo de grupo"
                                >
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">
                                    Notas
                                </label>

                                <textarea
                                    name="notes"
                                    rows="3"
                                    class="form-control"
                                    maxlength="2000"
                                >{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-end">
                        <button class="btn btn-primary">
                            <i class="ti ti-arrows-exchange me-1"></i>

                            @if($activeEnrollment)
                                Cambiar de grupo
                            @else
                                Inscribir en el ciclo
                            @endif
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Estado del alumno
                        </h3>
                    </div>

                    <div class="card-body">
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.students.enrollment.update',
                                $studentRow->id
                            ) }}"
                        >
                            @csrf
                            @method('PATCH')

                            <input
                                type="hidden"
                                name="effective_on"
                                value="{{ now()->toDateString() }}"
                            >

                            <div class="mb-3">
                                <label class="form-label">
                                    Acción
                                </label>

                                <select
                                    name="action"
                                    class="form-select"
                                    required
                                >
                                    <option value="">
                                        Selecciona una acción
                                    </option>

                                    <option value="suspend">
                                        Suspender temporalmente
                                    </option>

                                    <option value="reactivate">
                                        Reactivar alumno
                                    </option>

                                    <option value="withdraw">
                                        Dar de baja
                                    </option>

                                    <option value="graduate">
                                        Marcar como egresado
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Motivo
                                </label>

                                <input
                                    type="text"
                                    name="reason"
                                    class="form-control"
                                    maxlength="255"
                                >
                            </div>

                            <button class="btn btn-outline-primary w-100">
                                Aplicar estado
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection