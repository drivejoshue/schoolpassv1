@php
    $licenseContext = $schoolLicenseContext ?? null;

    $warningClasses = match (
        $licenseContext['warning_level'] ?? 'none'
    ) {
        'info' => 'alert-info',
        'warning' => 'alert-warning',
        'critical',
        'grace' => 'alert-danger',
        default => 'alert-warning',
    };
@endphp

@if (
    is_array($licenseContext)
    && ($licenseContext['show_warning'] ?? false)
    && ($licenseContext['access_allowed'] ?? false)
)
    <div
        class="alert {{ $warningClasses }}
               rounded-0 border-start-0 border-end-0 mb-0"
        role="alert"
    >
        <div class="container-xl">
            <div
                class="d-flex flex-column flex-md-row
                       align-items-md-center gap-3"
            >
                <div class="d-flex align-items-center gap-2 flex-fill">
                    <i class="ti ti-license fs-2"></i>

                    <div>
                        <div class="fw-bold">
                            Aviso de licencia
                        </div>

                        <div>
                            {{ $licenseContext['message'] }}
                        </div>
                    </div>
                </div>

                @if (
                    auth()->check()
                    && in_array(
                        auth()->user()->role,
                        ['school_admin', 'director'],
                        true
                    )
                )
                    <a
                        href="{{ route('admin.license.show') }}"
                        class="btn btn-outline-primary"
                    >
                        Ver licencia
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if ($licenseContext['show_modal'] ?? false)
        <div
            class="modal modal-blur fade"
            id="schoolpass-license-warning-modal"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Licencia próxima a vencer
                        </h5>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Cerrar"
                        ></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert {{ $warningClasses }}">
                            {{ $licenseContext['message'] }}
                        </div>

                        <dl class="row mb-0">
                            <dt class="col-5">Plan</dt>
                            <dd class="col-7">
                                {{ $licenseContext['plan_name'] ?: '—' }}
                            </dd>

                            <dt class="col-5">Vencimiento</dt>
                            <dd class="col-7">
                                {{ $licenseContext['expires_at']
                                    ? \Illuminate\Support\Carbon::parse(
                                        $licenseContext['expires_at']
                                    )->format('d/m/Y')
                                    : '—'
                                }}
                            </dd>

                            <dt class="col-5">Días restantes</dt>
                            <dd class="col-7">
                                {{ $licenseContext['days_remaining']
                                    !== null
                                        ? max(
                                            0,
                                            $licenseContext['days_remaining']
                                        )
                                        : '—'
                                }}
                            </dd>
                        </dl>
                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn me-auto"
                            data-bs-dismiss="modal"
                        >
                            Entendido
                        </button>

                        @if (
                            auth()->check()
                            && in_array(
                                auth()->user()->role,
                                ['school_admin', 'director'],
                                true
                            )
                        )
                            <a
                                href="{{ route(
                                    'admin.license.show'
                                ) }}"
                                class="btn btn-primary"
                            >
                                Ver licencia
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
        <script>
            document.addEventListener(
                'DOMContentLoaded',
                function () {
                    const modalElement = document.getElementById(
                        'schoolpass-license-warning-modal'
                    );

                    if (!modalElement || typeof bootstrap === 'undefined') {
                        return;
                    }

                    const schoolId = @json(
                        $licenseContext['school_id'] ?? 0
                    );

                    const licenseId = @json(
                        $licenseContext['license_id'] ?? 0
                    );

                    const today = new Date()
                        .toISOString()
                        .slice(0, 10);

                    const storageKey = [
                        'schoolpass',
                        'license-warning',
                        schoolId,
                        licenseId,
                        today,
                    ].join(':');

                    if (
                        localStorage.getItem(storageKey)
                        === 'acknowledged'
                    ) {
                        return;
                    }

                    const modal = new bootstrap.Modal(
                        modalElement
                    );

                    modal.show();

                    modalElement.addEventListener(
                        'hidden.bs.modal',
                        function () {
                            localStorage.setItem(
                                storageKey,
                                'acknowledged'
                            );
                        },
                        {
                            once: true,
                        }
                    );
                }
            );
        </script>
        @endpush
    @endif
@endif