@extends('layouts.app')

@section('title', 'Tutor | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Expediente del tutor')

@section('topbar-actions')
    <div class="btn-list">
        <a
            href="{{ route('admin.guardians.index') }}"
            class="btn btn-outline-secondary btn-sm"
        >
            <i class="ti ti-arrow-left me-1"></i>
            Tutores
        </a>

        <a
            href="{{ route('admin.guardians.edit', $guardianRow->id) }}"
            class="btn btn-primary btn-sm"
        >
            <i class="ti ti-edit me-1"></i>
            Editar
        </a>
    </div>
@endsection

@section('content')
    @php
        $generatedAccess = session('generated_credentials');
        $guardianName = trim($guardianRow->first_name.' '.$guardianRow->last_name);

        $phoneDigits = preg_replace('/\D+/', '', (string) ($guardianRow->phone ?? ''));

        if ($phoneDigits !== '' && strlen($phoneDigits) === 10) {
            $phoneDigits = '52'.$phoneDigits;
        }

        $accessText = $generatedAccess
            ? "SchoolPass\n"
                ."Tutor: {$guardianName}\n"
                ."Usuario: {$generatedAccess['username']}\n"
                ."Contraseña temporal: {$generatedAccess['password']}\n\n"
                ."Por seguridad, cambia la contraseña después de iniciar sesión."
            : '';

        $whatsappUrl = $generatedAccess && $phoneDigits !== ''
            ? 'https://wa.me/'.$phoneDigits.'?text='.rawurlencode($accessText)
            : null;

        $emailUrl = $generatedAccess && $guardianRow->email
            ? 'mailto:'.$guardianRow->email
                .'?subject='.rawurlencode('Acceso a SchoolPass')
                .'&body='.rawurlencode($accessText)
            : null;

        $eventLabels = [
            'entry' => 'Entrada',
            'exit' => 'Salida',
        ];

        $statusLabels = [
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Muy tarde',
            'normal_exit' => 'Salida normal',
            'early_exit' => 'Salida anticipada',
            'duplicate' => 'Duplicado',
            'manual' => 'Manual',
            'guardian_required' => 'Tutor requerido',
            'student_not_enrolled' => 'Sin inscripción',
            'cycle_not_started' => 'Ciclo no iniciado',
        ];
    @endphp

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

    @if($generatedAccess)
        <div class="alert alert-warning">
            <div class="d-flex">
                <div>
                    <i class="ti ti-key icon alert-icon"></i>
                </div>

                <div class="w-100">
                    <h4 class="alert-title">
                        {{ ! empty($generatedAccess['is_reset'])
                            ? 'Nueva contraseña generada'
                            : 'Acceso generado' }}
                    </h4>

                    <div class="text-secondary mb-3">
                        Esta contraseña se muestra una sola vez.
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Usuario</label>
                            <div class="input-group">
                                <input
                                    id="guardian-access-user"
                                    class="form-control"
                                    value="{{ $generatedAccess['username'] }}"
                                    readonly
                                >
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    data-copy-target="guardian-access-user"
                                >
                                    <i class="ti ti-copy"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Contraseña temporal</label>
                            <div class="input-group">
                                <input
                                    id="guardian-access-password"
                                    class="form-control fw-bold"
                                    value="{{ $generatedAccess['password'] }}"
                                    readonly
                                >
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    data-copy-target="guardian-access-password"
                                >
                                    <i class="ti ti-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="btn-list mt-3">
                        @if($whatsappUrl)
                            <a
                                href="{{ $whatsappUrl }}"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-success"
                            >
                                <i class="ti ti-brand-whatsapp me-1"></i>
                                Enviar por WhatsApp
                            </a>
                        @endif

                        @if($emailUrl)
                            <a href="{{ $emailUrl }}" class="btn btn-outline-primary">
                                <i class="ti ti-mail me-1"></i>
                                Enviar por correo
                            </a>
                        @endif

                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-copy-text="{{ $accessText }}"
                        >
                            <i class="ti ti-copy me-1"></i>
                            Copiar mensaje
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row row-cards">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body text-center">
                    @if($guardianRow->photo_url)
                        <span
                            class="avatar avatar-2xl mb-3"
                            style="background-image: url('{{ $guardianRow->photo_url }}')"
                        ></span>
                    @else
                        <span class="avatar avatar-2xl bg-blue-lt mb-3">
                            {{ mb_strtoupper(mb_substr($guardianRow->first_name, 0, 1)) }}
                        </span>
                    @endif

                    <h2 class="mb-1">{{ $guardianName }}</h2>

                    <div class="text-secondary">
                        <i class="ti ti-phone me-1"></i>
                        {{ $guardianRow->phone ?? 'Sin teléfono' }}
                    </div>

                    <div class="text-secondary">
                        <i class="ti ti-mail me-1"></i>
                        {{ $guardianRow->email ?? 'Sin correo' }}
                    </div>

                    <div class="mt-3">
                        @if($guardianRow->status === 'active')
                            <span class="badge bg-success-lt">Tutor activo</span>
                        @elseif($guardianRow->status === 'blocked')
                            <span class="badge bg-danger-lt">Tutor bloqueado</span>
                        @else
                            <span class="badge bg-secondary-lt">Tutor inactivo</span>
                        @endif
                    </div>
                </div>

                <div class="card-footer">
                    <form
                        method="POST"
                        action="{{ route('admin.guardians.photo.upload', $guardianRow->id) }}"
                        enctype="multipart/form-data"
                    >
                        @csrf

                        <label class="form-label">
                            {{ $guardianRow->photo_url ? 'Cambiar fotografía' : 'Registrar fotografía' }}
                        </label>

                        <input
                            type="file"
                            name="photo"
                            class="form-control"
                            accept="image/jpeg,image/png,image/webp"
                            required
                        >

                        <div class="form-hint mb-3">
                            Imagen cuadrada, mínimo 500 × 500 px, máximo 5 MB.
                        </div>

                        <button class="btn btn-primary w-100">
                            <i class="ti ti-camera me-1"></i>
                            Guardar fotografía
                        </button>
                    </form>

                    @if($guardianRow->photo_url)
                        <form
                            method="POST"
                            action="{{ route('admin.guardians.photo.remove', $guardianRow->id) }}"
                            class="mt-2"
                            onsubmit="return confirm('Se revocarán los QR activos del tutor. ¿Continuar?')"
                        >
                            @csrf
                            @method('DELETE')

                            <button class="btn btn-outline-danger w-100">
                                <i class="ti ti-trash me-1"></i>
                                Eliminar fotografía
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Credencial QR</h3>
                        <div class="text-secondary">
                            Requiere tutor activo y fotografía.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    @if($activeCredential)
                        <div
                            class="guardian-qr-box mb-3"
                            data-qr-payload="{{ $activeCredential->public_code }}"
                            data-qr-size="220"
                        >
                            <div class="text-secondary py-5">
                                Generando QR…
                            </div>
                        </div>

                        <dl class="row mb-3">
                            <dt class="col-5">Estado</dt>
                            <dd class="col-7">
                                <span class="badge bg-success-lt">Activo</span>
                            </dd>

                            <dt class="col-5">Emitido</dt>
                            <dd class="col-7">
                                {{ \Illuminate\Support\Carbon::parse($activeCredential->issued_at)->format('d/m/Y H:i') }}
                            </dd>

                            <dt class="col-5">Vigencia</dt>
                            <dd class="col-7">
                                {{ $activeCredential->expires_at
                                    ? \Illuminate\Support\Carbon::parse($activeCredential->expires_at)->format('d/m/Y H:i')
                                    : 'Sin vencimiento' }}
                            </dd>
                        </dl>

                        <div class="btn-list">
                            <a
                                href="{{ route('admin.guardians.credentials.print', [
                                    $guardianRow->id,
                                    $activeCredential->id,
                                ]) }}"
                                target="_blank"
                                class="btn btn-primary"
                            >
                                <i class="ti ti-printer me-1"></i>
                                Ver / imprimir
                            </a>

                            <form
                                method="POST"
                                action="{{ route('admin.guardians.credentials.regenerate', $guardianRow->id) }}"
                                onsubmit="return confirm('El QR actual dejará de funcionar. ¿Regenerar?')"
                            >
                                @csrf

                                <button class="btn btn-outline-primary">
                                    <i class="ti ti-refresh me-1"></i>
                                    Regenerar
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('admin.guardians.credentials.revoke', [
                                    $guardianRow->id,
                                    $activeCredential->id,
                                ]) }}"
                                onsubmit="return confirm('¿Revocar esta credencial?')"
                            >
                                @csrf
                                @method('PATCH')

                                <button class="btn btn-outline-danger">
                                    <i class="ti ti-qrcode-off me-1"></i>
                                    Revocar
                                </button>
                            </form>
                        </div>
                    @else
                        @if(!$guardianRow->photo_url)
                            <div class="alert alert-warning">
                                <i class="ti ti-camera me-2"></i>
                                Registra la fotografía para habilitar el QR.
                            </div>
                        @elseif($guardianRow->status !== 'active')
                            <div class="alert alert-warning">
                                <i class="ti ti-user-off me-2"></i>
                                Activa al tutor antes de generar el QR.
                            </div>
                        @else
                            <div class="alert alert-info">
                                <i class="ti ti-qrcode me-2"></i>
                                El tutor no tiene una credencial activa.
                            </div>

                            <form
                                method="POST"
                                action="{{ route('admin.guardians.credentials.create', $guardianRow->id) }}"
                            >
                                @csrf

                                <button class="btn btn-primary w-100">
                                    <i class="ti ti-qrcode me-1"></i>
                                    Generar credencial QR
                                </button>
                            </form>
                        @endif
                    @endif
                </div>

                @if($credentials->count() > 1 || (!$activeCredential && $credentials->isNotEmpty()))
                    <div class="card-footer">
                        <details>
                            <summary class="fw-semibold">
                                Historial de credenciales ({{ $credentials->count() }})
                            </summary>

                            <div class="mt-3">
                                @foreach($credentials as $credential)
                                    <div class="border rounded p-2 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-semibold">
                                                QR #{{ $credential->id }}
                                            </span>

                                            <span class="badge {{ $credential->status === 'active'
                                                ? 'bg-success-lt'
                                                : 'bg-secondary-lt' }}">
                                                {{ ucfirst($credential->status) }}
                                            </span>
                                        </div>

                                        <div class="text-secondary small mt-1">
                                            Emitido:
                                            {{ \Illuminate\Support\Carbon::parse($credential->issued_at)->format('d/m/Y H:i') }}
                                        </div>

                                        @if($credential->revoked_at)
                                            <div class="text-secondary small">
                                                Revocado:
                                                {{ \Illuminate\Support\Carbon::parse($credential->revoked_at)->format('d/m/Y H:i') }}
                                            </div>
                                        @endif

                                        @if($credential->revoked_reason)
                                            <div class="text-secondary small">
                                                {{ $credential->revoked_reason }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </div>
                @endif
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Acceso a Family</h3>
                        <div class="text-secondary">Cuenta individual del tutor</div>
                    </div>
                </div>

                <div class="card-body">
                    @if($guardianRow->user_id)
                        <dl class="row mb-3">
                            <dt class="col-4">Usuario</dt>
                            <dd class="col-8 text-break">
                                {{ $guardianRow->access_username }}
                            </dd>

                            <dt class="col-4">Estado</dt>
                            <dd class="col-8">
                                <span class="badge {{ $guardianRow->user_status === 'active'
                                    ? 'bg-success-lt'
                                    : 'bg-danger-lt' }}">
                                    {{ $guardianRow->user_status === 'active' ? 'Activo' : 'Bloqueado' }}
                                </span>
                            </dd>

                            <dt class="col-4">Contraseña</dt>
                            <dd class="col-8">
                                {{ $guardianRow->must_change_password
                                    ? 'Cambio pendiente'
                                    : 'Actualizada por el tutor' }}
                            </dd>
                        </dl>

                        <div class="btn-list">
                            <form
                                method="POST"
                                action="{{ route('admin.guardians.account.reset', $guardianRow->id) }}"
                                onsubmit="return confirm('Se invalidará la contraseña actual. ¿Continuar?')"
                            >
                                @csrf
                                @method('PATCH')

                                <button class="btn btn-outline-primary">
                                    <i class="ti ti-refresh me-1"></i>
                                    Restablecer contraseña
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('admin.guardians.account.status', $guardianRow->id) }}"
                            >
                                @csrf
                                @method('PATCH')

                                <input
                                    type="hidden"
                                    name="status"
                                    value="{{ $guardianRow->user_status === 'active' ? 'blocked' : 'active' }}"
                                >

                                <button
                                    class="btn {{ $guardianRow->user_status === 'active'
                                        ? 'btn-outline-danger'
                                        : 'btn-outline-success' }}"
                                >
                                    <i class="ti {{ $guardianRow->user_status === 'active'
                                        ? 'ti-lock'
                                        : 'ti-lock-open' }} me-1"></i>

                                    {{ $guardianRow->user_status === 'active'
                                        ? 'Bloquear acceso'
                                        : 'Activar acceso' }}
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="ti ti-info-circle me-2"></i>
                            Este tutor todavía no tiene acceso a la app.
                        </div>

                        <form
                            method="POST"
                            action="{{ route('admin.guardians.account.create', $guardianRow->id) }}"
                        >
                            @csrf

                            <button class="btn btn-primary w-100">
                                <i class="ti ti-user-check me-1"></i>
                                Generar usuario y contraseña
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Vincular alumno</h3>

                        @if($activeCycle)
                            <div class="text-secondary">
                                Ciclo {{ $activeCycle->name }}
                            </div>
                        @endif
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.guardians.students.link', $guardianRow->id) }}"
                >
                    @csrf

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required">Alumno</label>
                            <select name="student_id" class="form-select" required>
                                <option value="">Seleccionar alumno</option>

                                @foreach($availableStudents as $student)
                                    <option value="{{ $student->id }}">
                                        {{ $student->last_name }},
                                        {{ $student->first_name }}
                                        · {{ $student->student_code }}
                                        · {{ $student->group_name ?? 'Sin grupo' }}
                                        · {{ $student->guardians_count }} tutor(es)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">Relación</label>
                            <select name="relationship" class="form-select" required>
                                <option value="madre">Madre</option>
                                <option value="padre">Padre</option>
                                <option value="tutor">Tutor</option>
                                <option value="abuelo">Abuelo</option>
                                <option value="abuela">Abuela</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Válido desde</label>
                                <input type="date" name="valid_from" class="form-control">
                            </div>

                            <div class="col-6">
                                <label class="form-label">Válido hasta</label>
                                <input type="date" name="valid_until" class="form-control">
                            </div>
                        </div>

                        @foreach([
                            'is_primary' => 'Tutor principal',
                            'can_drop_off' => 'Puede entregar',
                            'can_pick_up' => 'Puede recoger',
                            'can_receive_notifications' => 'Recibe notificaciones',
                            'can_view_attendance' => 'Puede ver asistencia',
                        ] as $permission => $label)
                            <label class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="{{ $permission }}"
                                    value="1"
                                    @checked(in_array($permission, [
                                        'can_drop_off',
                                        'can_receive_notifications',
                                        'can_view_attendance',
                                    ], true))
                                >
                                <span class="form-check-label">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="card-footer">
                        <button
                            class="btn btn-primary w-100"
                            @disabled($availableStudents->isEmpty())
                        >
                            <i class="ti ti-link me-1"></i>
                            Vincular alumno
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="row row-cards mb-3">
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">Alumnos</div>
                            <div class="h2 mb-0">{{ $summary['students'] }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">Principal</div>
                            <div class="h2 mb-0">{{ $summary['primary_students'] }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">Puede entregar</div>
                            <div class="h2 mb-0">{{ $summary['can_drop_off'] }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">Puede recoger</div>
                            <div class="h2 mb-0">{{ $summary['can_pick_up'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Alumnos asociados</h3>
                        <div class="text-secondary">
                            Permisos y vigencia por alumno.
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Relación y vigencia</th>
                                <th>Permisos</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($linkedStudents as $student)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($student->student_photo_url)
                                                <span
                                                    class="avatar avatar-sm me-2"
                                                    style="background-image: url('{{ $student->student_photo_url }}')"
                                                ></span>
                                            @else
                                                <span class="avatar avatar-sm bg-blue-lt me-2">
                                                    {{ mb_strtoupper(mb_substr($student->first_name, 0, 1)) }}
                                                </span>
                                            @endif

                                            <div>
                                                <div class="fw-bold">
                                                    {{ $student->first_name }} {{ $student->last_name }}
                                                </div>

                                                <div class="text-secondary small">
                                                    {{ $student->student_code }}
                                                    · {{ $student->level_name ?? 'Sin nivel' }}
                                                    · {{ $student->group_name ?? 'Sin grupo' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'admin.guardians.students.permissions',
                                                [$guardianRow->id, $student->student_id]
                                            ) }}"
                                            id="permissions-{{ $student->student_id }}"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <select
                                                name="relationship"
                                                class="form-select form-select-sm mb-2"
                                            >
                                                @foreach([
                                                    'madre' => 'Madre',
                                                    'padre' => 'Padre',
                                                    'tutor' => 'Tutor',
                                                    'abuelo' => 'Abuelo',
                                                    'abuela' => 'Abuela',
                                                    'otro' => 'Otro',
                                                ] as $value => $label)
                                                    <option
                                                        value="{{ $value }}"
                                                        @selected($student->relationship === $value)
                                                    >
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <input
                                                        type="date"
                                                        name="valid_from"
                                                        value="{{ $student->valid_from }}"
                                                        class="form-control form-control-sm"
                                                        title="Válido desde"
                                                    >
                                                </div>

                                                <div class="col-6">
                                                    <input
                                                        type="date"
                                                        name="valid_until"
                                                        value="{{ $student->valid_until }}"
                                                        class="form-control form-control-sm"
                                                        title="Válido hasta"
                                                    >
                                                </div>
                                            </div>
                                        </form>
                                    </td>

                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            @foreach([
                                                'is_primary' => 'Principal',
                                                'can_drop_off' => 'Puede entregar',
                                                'can_pick_up' => 'Puede recoger',
                                                'can_receive_notifications' => 'Recibe avisos',
                                                'can_view_attendance' => 'Ve asistencia',
                                            ] as $permission => $label)
                                                <label class="form-check">
                                                    <input
                                                        form="permissions-{{ $student->student_id }}"
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        name="{{ $permission }}"
                                                        value="1"
                                                        @checked($student->{$permission})
                                                    >
                                                    <span class="form-check-label">{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </td>

                                    <td>
                                        <div class="btn-list flex-nowrap">
                                            <button
                                                form="permissions-{{ $student->student_id }}"
                                                class="btn btn-sm btn-primary"
                                            >
                                                Guardar
                                            </button>

                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'admin.guardians.students.unlink',
                                                    [$guardianRow->id, $student->student_id]
                                                ) }}"
                                            >
                                                @csrf
                                                @method('PATCH')

                                                <button
                                                    class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('¿Quitar este vínculo?')"
                                                >
                                                    Quitar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="4"
                                        class="text-center text-secondary py-5"
                                    >
                                        No hay alumnos vinculados a este tutor.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Historial de movimientos</h3>
                        <div class="text-secondary">
                            Últimos {{ $accessLogs->count() }} registros asociados al tutor.
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Fecha y hora</th>
                                <th>Alumno</th>
                                <th>Movimiento</th>
                                <th>Resultado</th>
                                <th>Origen</th>
                                <th>Operador / dispositivo</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($accessLogs as $log)
                                <tr>
                                    <td class="text-nowrap">
                                        {{ \Illuminate\Support\Carbon::parse($log->scanned_at)->format('d/m/Y H:i:s') }}
                                    </td>

                                    <td>
                                        <div class="fw-semibold">
                                            {{ trim(($log->student_first_name ?? '').' '.($log->student_last_name ?? ''))
                                                ?: 'Alumno no disponible' }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ $log->student_code ?? '—' }}
                                            @if($log->group_name)
                                                · {{ $log->group_name }}
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge {{ $log->event_type === 'entry'
                                            ? 'bg-blue-lt'
                                            : 'bg-purple-lt' }}">
                                            {{ $eventLabels[$log->event_type] ?? ucfirst($log->event_type) }}
                                        </span>

                                        <div class="text-secondary small mt-1">
                                            {{ $log->event_type === 'entry'
                                                ? 'Entrega'
                                                : 'Recogida' }}
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge {{ $log->decision === 'allowed'
                                            ? 'bg-success-lt'
                                            : ($log->decision === 'duplicate'
                                                ? 'bg-yellow-lt'
                                                : 'bg-danger-lt') }}">
                                            {{ $statusLabels[$log->event_status]
                                                ?? ucfirst(str_replace('_', ' ', $log->event_status)) }}
                                        </span>

                                        @if($log->reason)
                                            <div class="text-secondary small mt-1">
                                                {{ $log->reason }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        <div>{{ ucfirst(str_replace('_', ' ', $log->source)) }}</div>
                                        <div class="text-secondary small">
                                            {{ $log->reader_type }}
                                            @if($log->area_name)
                                                · {{ $log->area_name }}
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        <div>{{ $log->performed_by_name ?? 'Sin operador' }}</div>
                                        <div class="text-secondary small">
                                            {{ $log->device_name ?? 'Sin dispositivo' }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="6"
                                        class="text-center text-secondary py-5"
                                    >
                                        Este tutor todavía no tiene movimientos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @vite('resources/js/guardian-credential.js')
@endsection
