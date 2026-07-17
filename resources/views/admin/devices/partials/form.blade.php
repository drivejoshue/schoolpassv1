<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Campus</label>
        <select name="campus_id" class="form-select" required>
            <option value="">Seleccionar campus</option>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected(old('campus_id', $deviceRow->campus_id ?? '') == $campus->id)>
                    {{ $campus->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Área</label>
        <select name="area_id" class="form-select">
            <option value="">Sin área</option>
            @foreach($areas as $area)
                <option value="{{ $area->id }}" @selected(old('area_id', $deviceRow->area_id ?? '') == $area->id)>
                    {{ $area->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-7">
        <label class="form-label">Nombre</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $deviceRow->name ?? '') }}"
            class="form-control"
            required
            placeholder="Kiosco Entrada Principal"
        >
    </div>

    <div class="col-md-5">
        <label class="form-label">UUID del dispositivo</label>
        <input
            type="text"
            name="device_uuid"
            value="{{ old('device_uuid', $deviceRow->device_uuid ?? '') }}"
            class="form-control"
            placeholder="Se genera automático si lo dejas vacío"
            @if($deviceRow ?? false) required @endif
        >
        <div class="form-hint">
            Identificador técnico usado por kioscos, scanners y apps.
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Plataforma</label>
        <select name="platform" class="form-select" required>
            @foreach([
                'web' => 'Web',
                'android' => 'Android',
                'ios' => 'iOS',
                'hardware' => 'Hardware',
                'other' => 'Otro',
            ] as $value => $label)
                <option value="{{ $value }}" @selected(old('platform', $deviceRow->platform ?? 'web') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Tipo</label>
        <select name="device_type" class="form-select" required>
            @foreach([
                'prefect_app' => 'Prefectura',
                'kiosk' => 'Kiosco',
                'scanner' => 'Scanner',
                'door_controller' => 'Controlador puerta',
                'mobile' => 'Móvil',
                'other' => 'Otro',
            ] as $value => $label)
                <option value="{{ $value }}" @selected(old('device_type', $deviceRow->device_type ?? 'prefect_app') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Modo</label>
        <select name="mode" class="form-select" required>
            @foreach([
                'attendance' => 'Asistencia',
                'restricted_access' => 'Acceso restringido',
                'log_only' => 'Solo registro',
            ] as $value => $label)
                <option value="{{ $value }}" @selected(old('mode', $deviceRow->mode ?? 'attendance') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
    <label class="form-label">
        Evento predeterminado
    </label>

    <select
        name="default_event_type"
        class="form-select"
        required
    >
        @foreach([
            'auto' => 'Automático según grupo',
            'entry' => 'Solo entrada',
            'exit' => 'Solo salida',
            'access' => 'Acceso a área',
        ] as $value => $label)
            <option
                value="{{ $value }}"
                @selected(
                    old(
                        'default_event_type',
                        $deviceRow->default_event_type ?? 'auto'
                    ) === $value
                )
            >
                {{ $label }}
            </option>
        @endforeach
    </select>

    <div class="form-hint">
        Automático usa la entrada, salida y anticipación
        configuradas para el grupo del alumno.
    </div>
</div>

    <div class="col-md-4">
        <label class="form-label">Asignado a usuario</label>
        <select name="assigned_to_user_id" class="form-select">
            <option value="">Sin usuario</option>
            @foreach($users as $assignableUser)
                <option value="{{ $assignableUser->id }}" @selected(old('assigned_to_user_id', $deviceRow->assigned_to_user_id ?? '') == $assignableUser->id)>
                    {{ $assignableUser->name }} · {{ $assignableUser->role }}
                </option>
            @endforeach
        </select>
        <div class="form-hint">
            Para kioscos reales, conviene un usuario propio por dispositivo.
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Auto reset</label>
        <input
            type="number"
            name="auto_reset_seconds"
            value="{{ old('auto_reset_seconds', $deviceRow->auto_reset_seconds ?? 3) }}"
            min="1"
            max="60"
            class="form-control"
            required
        >
    </div>

    <div class="col-md-4">
        <label class="form-label">Estado</label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $deviceRow->status ?? 'active') === 'active')>
                Activo
            </option>
            <option value="inactive" @selected(old('status', $deviceRow->status ?? '') === 'inactive')>
                Inactivo
            </option>
            <option value="blocked" @selected(old('status', $deviceRow->status ?? '') === 'blocked')>
                Bloqueado
            </option>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label">Opciones</label>

        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="can_unlock"
                        value="1"
                        @checked(old('can_unlock', $deviceRow->can_unlock ?? false))
                    >
                    <span class="form-check-label">Puede abrir cerradura/relay</span>
                </label>
            </div>

            <div class="col-md-4">
                <label class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="allow_manual_search"
                        value="1"
                        @checked(old('allow_manual_search', $deviceRow->allow_manual_search ?? true))
                    >
                    <span class="form-check-label">Permite búsqueda manual</span>
                </label>
            </div>

            <div class="col-md-4">
                <label class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="show_student_photo"
                        value="1"
                        @checked(old('show_student_photo', $deviceRow->show_student_photo ?? true))
                    >
                    <span class="form-check-label">Muestra foto del alumno</span>
                </label>
            </div>
        </div>
    </div>
</div>