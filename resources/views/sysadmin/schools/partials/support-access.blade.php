<div class="col-12">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    <i class="ti ti-headset me-2 text-secondary"></i>
                    Acceso de soporte
                </h3>

                <div class="small text-secondary">
                    Inicia una sesión temporal con identidad visible y
                    registro de auditoría.
                </div>
            </div>
        </div>

        <div class="card-body">
            @if ($administrators->isEmpty())
                <div class="alert alert-warning mb-0">
                    La escuela no tiene un administrador o director activo
                    disponible para soporte.
                </div>
            @else
                <form
                    method="POST"
                    action="{{ route(
                        'sysadmin.schools.support.impersonate',
                        $schoolData
                    ) }}"
                >
                    @csrf

                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4">
                            <label class="form-label required">
                                Usuario objetivo
                            </label>

                            <select
                                name="target_user_id"
                                class="form-select"
                                required
                            >
                                @foreach ($administrators as $administrator)
                                    @if ($administrator->status === 'active')
                                        <option value="{{ $administrator->id }}">
                                            {{ $administrator->name }}
                                            ·
                                            {{ $administrator->role }}
                                            ·
                                            {{ $administrator->email }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label required">
                                Motivo
                            </label>

                            <input
                                type="text"
                                name="reason"
                                class="form-control"
                                minlength="5"
                                maxlength="500"
                                placeholder="Ej. revisar configuración del ciclo y reporte de accesos"
                                required
                            >
                        </div>

                        <div class="col-lg-2">
                            <button
                                type="submit"
                                class="btn btn-warning w-100"
                            >
                                <i class="ti ti-login me-2"></i>
                                Entrar
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
