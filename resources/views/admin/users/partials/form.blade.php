@php
    $editing = isset($userRow);

    $selectedRole = old(
        'role',
        $editing ? $userRow->role : 'prefect'
    );

    $selectedStatus = old(
        'status',
        $editing ? $userRow->status : 'active'
    );

    $selectedDeviceId = old(
        'access_device_id',
        $assignedDevice->id ?? ''
    );
@endphp

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">
                {{ $editing
                    ? 'Datos del usuario institucional'
                    : 'Nuevo usuario institucional' }}
            </h3>

            <div class="text-secondary small mt-1">
                La cuenta quedará limitada a la escuela del administrador que la registra.
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label required">
                    Nombre completo o identificador
                </label>

                <input
                    type="text"
                    name="name"
                    value="{{ old('name', $editing ? $userRow->name : '') }}"
                    maxlength="150"
                    required
                    autofocus
                    class="form-control @error('name') is-invalid @enderror"
                    placeholder="Ej. Prefectura turno matutino"
                >

                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-5">
                <label class="form-label required">Perfil</label>

                <select
                    id="system-user-role"
                    name="role"
                    required
                    class="form-select @error('role') is-invalid @enderror"
                    {{ $editing && (int) auth()->id() === (int) $userRow->id
                        ? 'disabled'
                        : '' }}
                >
                    @foreach($roles as $roleOption)
                        <option
                            value="{{ $roleOption }}"
                            @selected($selectedRole === $roleOption)
                        >
                            {{ $roleLabels[$roleOption] ?? $roleOption }}
                        </option>
                    @endforeach
                </select>

                @if($editing && (int) auth()->id() === (int) $userRow->id)
                    <input type="hidden" name="role" value="{{ $userRow->role }}">

                    <div class="form-hint">
                        No puedes cambiar tu propio perfil desde esta pantalla.
                    </div>
                @endif

                @error('role')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-7">
                <label class="form-label required">Correo electrónico</label>

                <div class="input-icon">
                    <span class="input-icon-addon">
                        <i class="ti ti-mail"></i>
                    </span>

                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $editing ? $userRow->email : '') }}"
                        maxlength="255"
                        required
                        autocomplete="off"
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="usuario@institucion.edu.mx"
                    >
                </div>

                @error('email')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-5">
                <label class="form-label">Teléfono</label>

                <div class="input-icon">
                    <span class="input-icon-addon">
                        <i class="ti ti-phone"></i>
                    </span>

                    <input
                        type="text"
                        name="phone"
                        value="{{ old('phone', $editing ? $userRow->phone : '') }}"
                        maxlength="30"
                        class="form-control @error('phone') is-invalid @enderror"
                        placeholder="Opcional"
                    >
                </div>

                @error('phone')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-5">
                <label class="form-label required">Estado de la cuenta</label>

                <select
                    name="status"
                    required
                    class="form-select @error('status') is-invalid @enderror"
                    {{ $editing && (int) auth()->id() === (int) $userRow->id
                        ? 'disabled'
                        : '' }}
                >
                    @foreach($statusLabels as $statusValue => $statusLabel)
                        <option
                            value="{{ $statusValue }}"
                            @selected($selectedStatus === $statusValue)
                        >
                            {{ $statusLabel }}
                        </option>
                    @endforeach
                </select>

                @if($editing && (int) auth()->id() === (int) $userRow->id)
                    <input type="hidden" name="status" value="{{ $userRow->status }}">
                @endif

                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div
                id="system-user-device-section"
                class="col-12"
            >
                <label class="form-label">
                    Dispositivo asignado
                    <span
                        id="system-user-device-required"
                        class="text-danger d-none"
                    >*</span>
                </label>

                <select
                    id="system-user-device"
                    name="access_device_id"
                    class="form-select @error('access_device_id') is-invalid @enderror"
                >
                    <option value="">
                        Sin dispositivo asignado
                    </option>

                    @foreach($devices as $device)
                        <option
                            value="{{ $device->id }}"
                            @selected((string) $selectedDeviceId === (string) $device->id)
                        >
                            {{ $device->name }}
                            · {{ $device->campus_name ?? 'Sin plantel' }}
                            @if($device->area_name)
                                · {{ $device->area_name }}
                            @endif
                            · {{ strtoupper($device->platform) }}
                            · {{ $device->status }}
                        </option>
                    @endforeach
                </select>

                <div id="system-user-device-hint" class="form-hint">
                    Prefectura puede operar sin un dispositivo fijo. El kiosco necesita uno asignado.
                </div>

                @error('access_device_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror

                @if($devices->isEmpty())
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="ti ti-device-desktop-plus me-2"></i>
                        No hay dispositivos disponibles.

                        <a
                            href="{{ route('admin.devices.create') }}"
                            class="alert-link"
                        >
                            Registra uno antes de crear un kiosco.
                        </a>
                    </div>
                @endif
            </div>

            @unless($editing)
                <div class="col-md-6">
                    <label class="form-label">Contraseña temporal</label>

                    <input
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        class="form-control @error('password') is-invalid @enderror"
                        placeholder="Déjala vacía para generar una segura"
                    >

                    <div class="form-hint">
                        Mínimo 8 caracteres. Al dejarla vacía, SchoolPass genera una automáticamente.
                    </div>

                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Confirmar contraseña</label>

                    <input
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="form-control"
                        placeholder="Repite la contraseña manual"
                    >
                </div>

                <div class="col-12">
                    <label class="form-check form-switch">
                        <input
                            type="checkbox"
                            name="send_credentials"
                            value="1"
                            class="form-check-input"
                            @checked(old('send_credentials', true))
                        >

                        <span class="form-check-label">
                            Enviar las credenciales temporales por correo
                        </span>
                    </label>

                    <div class="form-hint ms-5">
                        La cuenta deberá cambiar la contraseña después de iniciar sesión.
                    </div>
                </div>
            @endunless
        </div>
    </div>

    <div class="card-footer d-flex flex-wrap justify-content-between gap-2">
        <a
            href="{{ route('admin.users.index') }}"
            class="btn btn-outline-secondary"
        >
            Cancelar
        </a>

        <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy me-1"></i>
            {{ $editing ? 'Guardar cambios' : 'Crear usuario' }}
        </button>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.getElementById('system-user-role');
        const deviceSection = document.getElementById('system-user-device-section');
        const deviceSelect = document.getElementById('system-user-device');
        const requiredMark = document.getElementById('system-user-device-required');
        const hint = document.getElementById('system-user-device-hint');

        if (!roleSelect || !deviceSection || !deviceSelect) {
            return;
        }

        const syncDeviceField = function () {
            const role = roleSelect.value;
            const operational = role === 'prefect' || role === 'kiosk';
            const kiosk = role === 'kiosk';

            deviceSection.classList.toggle('d-none', !operational);
            deviceSelect.disabled = !operational;
            deviceSelect.required = kiosk;
            requiredMark?.classList.toggle('d-none', !kiosk);

            if (hint) {
                hint.textContent = kiosk
                    ? 'El kiosco debe quedar asociado a un dispositivo autorizado.'
                    : 'La asignación es opcional para prefectura.';
            }
        };

        roleSelect.addEventListener('change', syncDeviceField);
        syncDeviceField();
    });
</script>
@endpush