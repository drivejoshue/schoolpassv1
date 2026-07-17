@extends('layouts.app')

@section('title', 'Nuevo alumno | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Registrar alumno')

@section('topbar-actions')
    <a
        href="{{ route('admin.students.index') }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Volver a alumnos
    </a>
@endsection

@section('content')
    @php
        $hasActiveCycle = ! empty($activeCycle);

        $cycleName = $hasActiveCycle
            ? data_get($activeCycle, 'name', 'Ciclo activo')
            : null;

        $cycleStartsOn = $hasActiveCycle
            ? data_get($activeCycle, 'starts_on')
            : null;

        $cycleEndsOn = $hasActiveCycle
            ? data_get($activeCycle, 'ends_on')
            : null;
    @endphp

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="d-flex">
                <div>
                    <i class="ti ti-alert-circle icon alert-icon"></i>
                </div>

                <div>
                    <h4 class="alert-title">
                        No fue posible registrar al alumno
                    </h4>

                    <div class="text-secondary">
                        Revisa los datos marcados antes de continuar.
                    </div>

                    <ul class="mb-0 mt-2 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if(! $hasActiveCycle)
        <div class="alert alert-warning">
            <div class="d-flex">
                <div>
                    <i class="ti ti-calendar-exclamation icon alert-icon"></i>
                </div>

                <div>
                    <h4 class="alert-title">
                        No existe un ciclo escolar activo
                    </h4>

                    <div class="text-secondary">
                        Debes activar un ciclo y registrar sus grupos antes
                        de dar de alta alumnos.
                    </div>

                    <div class="mt-3">
                        <a
                            href="{{ route('admin.cycles.index') }}"
                            class="btn btn-warning btn-sm"
                        >
                            <i class="ti ti-calendar me-1"></i>
                            Administrar ciclos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row row-cards">
        <div class="col-xl-8">

            <form
                method="POST"
                action="{{ route('admin.students.store') }}"
                class="card"
                autocomplete="off"
            >
                @csrf

                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-1">
                            Datos generales
                        </h3>

                        <div class="text-secondary small">
                            Información básica e inscripción inicial del alumno.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-4">

                        <div class="col-12">
                            <div class="subheader mb-2">
                                Identificación
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label
                                for="student_code"
                                class="form-label required"
                            >
                                Matrícula
                            </label>

                            <div class="input-icon">
                                <span class="input-icon-addon">
                                    <i class="ti ti-id"></i>
                                </span>

                                <input
                                    id="student_code"
                                    type="text"
                                    name="student_code"
                                    value="{{ old('student_code') }}"
                                    class="form-control
                                        @error('student_code') is-invalid @enderror"
                                    required
                                    autofocus
                                    maxlength="50"
                                    placeholder="Ej. A0006"
                                >
                            </div>

                            @error('student_code')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                            @enderror

                            <div class="form-hint">
                                Debe ser única dentro de la institución.
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label
                                for="first_name"
                                class="form-label required"
                            >
                                Nombre
                            </label>

                            <div class="input-icon">
                                <span class="input-icon-addon">
                                    <i class="ti ti-user"></i>
                                </span>

                                <input
                                    id="first_name"
                                    type="text"
                                    name="first_name"
                                    value="{{ old('first_name') }}"
                                    class="form-control
                                        @error('first_name') is-invalid @enderror"
                                    required
                                    maxlength="100"
                                    placeholder="Nombre o nombres"
                                >
                            </div>

                            @error('first_name')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label
                                for="last_name"
                                class="form-label required"
                            >
                                Apellidos
                            </label>

                            <div class="input-icon">
                                <span class="input-icon-addon">
                                    <i class="ti ti-user-circle"></i>
                                </span>

                                <input
                                    id="last_name"
                                    type="text"
                                    name="last_name"
                                    value="{{ old('last_name') }}"
                                    class="form-control
                                        @error('last_name') is-invalid @enderror"
                                    required
                                    maxlength="150"
                                    placeholder="Apellidos"
                                >
                            </div>

                            @error('last_name')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                        </div>

                        <div class="col-12">
                            <div class="subheader mb-2">
                                Inscripción inicial
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label
                                for="current_group_id"
                                class="form-label required"
                            >
                                Grupo del ciclo activo
                            </label>

                            <select
                                id="current_group_id"
                                name="current_group_id"
                                class="form-select
                                    @error('current_group_id') is-invalid @enderror"
                                required
                                @disabled(! $hasActiveCycle)
                            >
                                <option value="">
                                    Selecciona nivel, grado y grupo
                                </option>

                                @foreach($groups as $group)
                                    <option
                                        value="{{ $group->id }}"
                                        @selected(
                                            (string) old('current_group_id')
                                            === (string) $group->id
                                        )
                                    >
                                        {{ $group->level_name ?? 'Sin nivel' }}

                                        @if(! empty($group->grade_label))
                                            · {{ $group->grade_label }}
                                        @endif

                                        · {{ $group->name }}

                                        @if(! empty($group->campus_name))
                                            · {{ $group->campus_name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>

                            @error('current_group_id')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                            @enderror

                            @if($hasActiveCycle && $groups->isEmpty())
                                <div class="form-hint text-warning">
                                    <i class="ti ti-alert-triangle me-1"></i>
                                    El ciclo activo todavía no tiene grupos disponibles.
                                </div>
                            @else
                                <div class="form-hint">
                                    Al guardar, se creará también la inscripción
                                    del alumno en el ciclo actual.
                                </div>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <label
                                for="status"
                                class="form-label required"
                            >
                                Estado inicial
                            </label>

                            <select
                                id="status"
                                name="status"
                                class="form-select
                                    @error('status') is-invalid @enderror"
                                required
                            >
                                <option
                                    value="active"
                                    @selected(
                                        old('status', 'active') === 'active'
                                    )
                                >
                                    Activo
                                </option>

                                <option
                                    value="temporary"
                                    @selected(
                                        old('status') === 'temporary'
                                    )
                                >
                                    Temporal
                                </option>

                                <option
                                    value="suspended"
                                    @selected(
                                        old('status') === 'suspended'
                                    )
                                >
                                    Suspendido
                                </option>
                            </select>

                            @error('status')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                            @enderror

                            <div class="form-hint">
                                Normalmente debe registrarse como activo.
                            </div>
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                        </div>

                        <div class="col-12">
                            <div class="subheader mb-2">
                                Información interna
                            </div>
                        </div>

                        <div class="col-12">
                            <label
                                for="notes"
                                class="form-label"
                            >
                                Observaciones
                            </label>

                            <textarea
                                id="notes"
                                name="notes"
                                class="form-control
                                    @error('notes') is-invalid @enderror"
                                rows="4"
                                maxlength="2000"
                                placeholder="Alergias, indicaciones administrativas, condición temporal u otra observación relevante."
                            >{{ old('notes') }}</textarea>

                            @error('notes')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                            @enderror

                            <div class="form-hint">
                                Uso interno. No incluyas información innecesaria
                                o sensible.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <div
                        class="d-flex flex-column flex-sm-row
                               justify-content-between gap-2"
                    >
                        <a
                            href="{{ route('admin.students.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            <i class="ti ti-x me-1"></i>
                            Cancelar
                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary"
                            @disabled(
                                ! $hasActiveCycle
                                || $groups->isEmpty()
                            )
                        >
                            <i class="ti ti-user-plus me-1"></i>
                            Registrar alumno
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        Ciclo escolar
                    </h3>
                </div>

                <div class="card-body">
                    @if($hasActiveCycle)
                        <div class="d-flex align-items-center mb-3">
                            <span
                                class="avatar bg-green-lt text-green me-3"
                            >
                                <i class="ti ti-calendar-check"></i>
                            </span>

                            <div>
                                <div class="fw-semibold">
                                    {{ $cycleName }}
                                </div>

                                <div class="text-secondary small">
                                    Ciclo activo
                                </div>
                            </div>
                        </div>

                        <dl class="row mb-0">
                            <dt class="col-5 text-secondary">
                                Inicio
                            </dt>

                            <dd class="col-7">
                                {{ $cycleStartsOn
                                    ? \Illuminate\Support\Carbon::parse(
                                        $cycleStartsOn
                                    )->format('d/m/Y')
                                    : 'No definido' }}
                            </dd>

                            <dt class="col-5 text-secondary">
                                Fin
                            </dt>

                            <dd class="col-7">
                                {{ $cycleEndsOn
                                    ? \Illuminate\Support\Carbon::parse(
                                        $cycleEndsOn
                                    )->format('d/m/Y')
                                    : 'No definido' }}
                            </dd>

                            <dt class="col-5 text-secondary">
                                Grupos
                            </dt>

                            <dd class="col-7">
                                {{ number_format($groups->count()) }}
                            </dd>
                        </dl>
                    @else
                        <div class="empty py-3">
                            <div class="empty-img">
                                <i
                                    class="ti ti-calendar-off"
                                    style="font-size: 3rem;"
                                ></i>
                            </div>

                            <p class="empty-title">
                                Sin ciclo activo
                            </p>

                            <p class="empty-subtitle text-secondary">
                                No se pueden registrar alumnos hasta
                                activar un ciclo escolar.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        ¿Qué ocurrirá al guardar?
                    </h3>
                </div>

                <div class="card-body">
                    <div class="d-flex mb-3">
                        <span
                            class="avatar avatar-sm bg-blue-lt
                                   text-blue me-3"
                        >
                            1
                        </span>

                        <div>
                            <div class="fw-semibold">
                                Se crea el expediente
                            </div>

                            <div class="text-secondary small">
                                Quedará registrado con su matrícula,
                                nombre y estado inicial.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex mb-3">
                        <span
                            class="avatar avatar-sm bg-blue-lt
                                   text-blue me-3"
                        >
                            2
                        </span>

                        <div>
                            <div class="fw-semibold">
                                Se asigna el grupo
                            </div>

                            <div class="text-secondary small">
                                El alumno será inscrito en el grupo
                                seleccionado del ciclo activo.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex">
                        <span
                            class="avatar avatar-sm bg-blue-lt
                                   text-blue me-3"
                        >
                            3
                        </span>

                        <div>
                            <div class="fw-semibold">
                                Se habilita su expediente
                            </div>

                            <div class="text-secondary small">
                                Después podrás agregar foto, tutores
                                y credenciales QR.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection