@php
    $supportContext = session('support_impersonation');
@endphp

@if (is_array($supportContext))
    <div
        class="alert alert-warning rounded-0 border-start-0 border-end-0 mb-0"
        role="alert"
    >
        <div class="container-xl">
            <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-2 flex-fill">
                    <i class="ti ti-shield-check fs-2"></i>

                    <div>
                        <div class="fw-bold">
                            Estás operando como soporte
                        </div>

                        <div class="small">
                            Escuela:
                            <strong>
                                {{ $supportContext['school_name'] ?? '—' }}
                            </strong>

                            · Usuario:
                            <strong>
                                {{ $supportContext['target_user_name'] ?? '—' }}
                            </strong>

                            · Sysadmin:
                            <strong>
                                {{ $supportContext['sysadmin_name'] ?? '—' }}
                            </strong>
                        </div>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('support.impersonation.stop') }}"
                >
                    @csrf

                    <button type="submit" class="btn btn-warning">
                        <i class="ti ti-logout me-2"></i>
                        Salir del modo soporte
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif
