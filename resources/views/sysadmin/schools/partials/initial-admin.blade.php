<div class="card mb-3">
    <div class="card-header">
        <div>
            <h3 class="card-title">Administrador inicial</h3>
            <div class="small text-secondary">
                Este usuario podrá ingresar al panel de la escuela.
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required">Nombre</label>
                <input
                    type="text"
                    name="admin_name"
                    class="form-control @error('admin_name') is-invalid @enderror"
                    value="{{ old('admin_name') }}"
                    maxlength="160"
                    required
                >
                @error('admin_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label required">Correo</label>
                <input
                    type="email"
                    name="admin_email"
                    class="form-control @error('admin_email') is-invalid @enderror"
                    value="{{ old('admin_email') }}"
                    maxlength="180"
                    required
                >
                @error('admin_email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <input
                    type="text"
                    name="admin_phone"
                    class="form-control"
                    value="{{ old('admin_phone') }}"
                    maxlength="30"
                >
            </div>

            <div class="col-md-4">
                <label class="form-label required">Rol</label>
                <select
                    name="admin_role"
                    class="form-select"
                    required
                >
                    <option
                        value="director"
                        @selected(old('admin_role', 'director') === 'director')
                    >
                        Director
                    </option>
                    <option
                        value="school_admin"
                        @selected(old('admin_role') === 'school_admin')
                    >
                        Administrador escolar
                    </option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label required">
                    Contraseña inicial
                </label>
                <input
                    type="password"
                    name="admin_password"
                    class="form-control @error('admin_password') is-invalid @enderror"
                    minlength="8"
                    required
                >
                @error('admin_password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4 ms-auto">
                <label class="form-label required">
                    Confirmar contraseña
                </label>
                <input
                    type="password"
                    name="admin_password_confirmation"
                    class="form-control"
                    minlength="8"
                    required
                >
            </div>
        </div>
    </div>
</div>
