@extends('layouts.app')

@section('title', 'Credenciales | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Credenciales')

@section('topbar-actions')
    <a
        href="{{ route('admin.credentials.print', ['group_id' => $filters['group_id']]) }}"
        class="btn btn-outline-primary btn-sm"
        target="_blank"
    >
        <i class="ti ti-printer me-1"></i>
        Imprimir
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
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card mb-3">
        <form method="GET" action="{{ route('admin.credentials.index') }}">
            <div class="card-header">
                <h3 class="card-title">Filtros</h3>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Grupo</label>
                        <select name="group_id" class="form-select">
                            <option value="">Todos los grupos</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" @selected((string) $filters['group_id'] === (string) $group->id)>
                                    {{ $group->level_name }} · {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Estado de credencial</label>
                        <select name="credential_status" class="form-select">
                            <option value="">Todos</option>
                            <option value="with" @selected($filters['credential_status'] === 'with')>
                                Con credencial activa
                            </option>
                            <option value="without" @selected($filters['credential_status'] === 'without')>
                                Sin credencial activa
                            </option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] }}"
                            class="form-control"
                            placeholder="Nombre, matrícula o QR"
                        >
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('admin.credentials.index') }}" class="btn btn-outline-secondary">
                    Limpiar
                </a>

                <div class="btn-list">
                    <button class="btn btn-primary">
                        <i class="ti ti-filter me-1"></i>
                        Filtrar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold">Generar credenciales faltantes</div>
                <div class="text-secondary">
                    Crea QR solo para alumnos activos que no tienen credencial activa.
                </div>
            </div>

            <form method="POST" action="{{ route('admin.credentials.generate-missing') }}">
                @csrf
                <input type="hidden" name="group_id" value="{{ $filters['group_id'] }}">

                <button class="btn btn-outline-primary">
                    <i class="ti ti-qrcode me-1"></i>
                    Generar faltantes
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Credenciales de alumnos</h3>
                <p class="card-subtitle">
                    Control de QR activos por alumno y grupo.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Grupo</th>
                        <th>Credencial</th>
                        <th>Emitida</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($student->photo_url)
                                        <span
                                            class="avatar avatar-sm me-2"
                                            style="background-image: url('{{ asset($student->photo_url) }}')"
                                        ></span>
                                    @else
                                        <span class="avatar avatar-sm bg-blue-lt me-2">
                                            {{ strtoupper(substr($student->first_name, 0, 1)) }}
                                        </span>
                                    @endif

                                    <div>
                                        <div class="fw-bold">
                                            {{ $student->first_name }} {{ $student->last_name }}
                                        </div>
                                        <div class="text-secondary small">
                                            {{ $student->student_code }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div>{{ $student->group_name ?? 'Sin grupo' }}</div>
                                <div class="text-secondary small">{{ $student->level_name ?? '' }}</div>
                            </td>

                            <td>
                                @if($student->credential_id)
                                    <span class="badge bg-success-lt">Activa</span>
                                    <div class="text-secondary small mt-1">
                                        {{ $student->public_code }}
                                    </div>
                                @else
                                    <span class="badge bg-warning-lt">Sin credencial</span>
                                @endif
                            </td>

                            <td>
                                @if($student->issued_at)
                                    {{ \Illuminate\Support\Carbon::parse($student->issued_at)->format('d/m/Y H:i') }}
                                @else
                                    —
                                @endif
                            </td>

                            <td>
    <div class="btn-list flex-nowrap">
        @if($student->credential_id)
            <a
                href="{{ route('admin.credentials.print', ['student_id' => $student->id]) }}"
                class="btn btn-sm btn-primary"
                target="_blank"
            >
                Imprimir
            </a>
        @endif

        <a href="{{ route('admin.students.show', $student->id) }}" class="btn btn-sm btn-outline-primary">
            Alumno
        </a>
    </div>
</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-5">
                                No hay alumnos con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection