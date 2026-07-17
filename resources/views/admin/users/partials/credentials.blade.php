@php
    $credentials = session('generated_credentials');
@endphp

@if($credentials)
    <div class="alert alert-warning">
        <div class="d-flex">
            <div>
                <i class="ti ti-key icon alert-icon"></i>
            </div>

            <div class="w-100">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <h4 class="alert-title mb-1">
                            {{ ! empty($credentials['is_reset'])
                                ? 'Nueva contraseña temporal'
                                : 'Credenciales creadas' }}
                        </h4>

                        <div class="text-secondary">
                            Estas credenciales se muestran una sola vez.
                        </div>
                    </div>

                    @if(! empty($credentials['mail_sent']))
                        <span class="badge bg-success-lt text-success align-self-start">
                            <i class="ti ti-mail-check me-1"></i>
                            Enviadas por correo
                        </span>
                    @else
                        <span class="badge bg-orange-lt text-orange align-self-start">
                            <i class="ti ti-copy me-1"></i>
                            Copia requerida
                        </span>
                    @endif
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-5">
                        <label class="form-label">Correo de acceso</label>

                        <div class="input-group">
                            <input
                                id="generated-user-email"
                                class="form-control"
                                value="{{ $credentials['email'] }}"
                                readonly
                            >

                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                onclick="navigator.clipboard.writeText(document.getElementById('generated-user-email').value)"
                            >
                                <i class="ti ti-copy"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Contraseña temporal</label>

                        <div class="input-group">
                            <input
                                id="generated-user-password"
                                class="form-control font-monospace"
                                value="{{ $credentials['password'] }}"
                                readonly
                            >

                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                onclick="navigator.clipboard.writeText(document.getElementById('generated-user-password').value)"
                            >
                                <i class="ti ti-copy"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button
                            type="button"
                            class="btn btn-outline-primary w-100"
                            onclick="navigator.clipboard.writeText(
                                'SchoolPass\nUsuario: {{ $credentials['email'] }}\nContraseña temporal: {{ $credentials['password'] }}\n\nCambia la contraseña después de iniciar sesión.'
                            )"
                        >
                            <i class="ti ti-copy me-1"></i>
                            Copiar todo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif