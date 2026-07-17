@extends('layouts.app')

@section('title', 'Kiosco | SchoolPass')
@section('section-label', 'Kiosco')
@section('page-title', 'Punto de acceso autorizado')

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8 col-xxl-7">
            <div class="card card-lg">
                <div class="card-body text-center py-5">
                    @if(! $device)
                        <div class="alert alert-danger">
                            No hay kiosco activo asignado a esta sesión.
                        </div>
                    @else
                        <input type="hidden" id="device_uuid" value="{{ $device->device_uuid }}">

                        <div id="kiosk-idle">
                            <div class="mb-4">
                                <span class="avatar avatar-xl bg-primary-lt">
                                    <i class="ti ti-device-imac fs-1"></i>
                                </span>
                            </div>

                            <h1 class="display-5 mb-2">
                                Escanea tu credencial
                            </h1>

                            <p class="text-secondary fs-3">
                                Acerca tu QR al lector para registrar el acceso.
                            </p>

                            <div class="mt-4">
                                <input
                                    type="text"
                                    id="token"
                                    class="form-control form-control-lg text-center"
                                    placeholder="Escanea o escribe el token"
                                    autocomplete="off"
                                    autofocus
                                >
                            </div>

                            <div class="border border-dashed rounded bg-light d-flex align-items-center justify-content-center mx-auto mt-5"
                                 style="height: 260px; max-width: 640px;">
                                <div class="text-secondary">
                                    <i class="ti ti-qrcode fs-1 d-block mb-2"></i>
                                    Cámara / scanner omnidireccional
                                </div>
                            </div>

                          <div class="mt-4 text-secondary">
    {{ $device->name }} · {{ $device->area_name ?? 'Área no asignada' }}
</div>

<div class="mt-2">
    @if($device->mode === 'restricted_access')
        <span class="badge bg-warning-lt">
            Acceso restringido
        </span>
    @elseif($device->mode === 'attendance')
        <span class="badge bg-blue-lt">
            Asistencia
        </span>
    @else
        <span class="badge bg-secondary-lt">
            Solo registro
        </span>
    @endif

    @if($device->can_unlock)
        <span class="badge bg-green-lt">
            Relay habilitado
        </span>
    @endif
</div>
                        </div>

                        <div id="kiosk-result" class="d-none"></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const tokenInput = document.getElementById('token');
const deviceUuid = document.getElementById('device_uuid')?.value;
const idle = document.getElementById('kiosk-idle');
const result = document.getElementById('kiosk-result');

let scanTimer = null;

tokenInput?.addEventListener('input', function () {
    clearTimeout(scanTimer);

    scanTimer = setTimeout(() => {
        const token = tokenInput.value.trim();

        if (token.length >= 3) {
            processKioskScan(token);
        }
    }, 250);
});

tokenInput?.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
        event.preventDefault();

        const token = tokenInput.value.trim();

        if (token.length >= 3) {
            processKioskScan(token);
        }
    }
});

async function processKioskScan(token) {
    tokenInput.value = '';

    idle.classList.add('d-none');
    result.classList.remove('d-none');

    result.innerHTML = `
        <div class="py-5">
            <div class="spinner-border text-primary mb-3"></div>
            <h2>Validando...</h2>
        </div>
    `;

    try {
        const response = await fetch("{{ route('access.scan') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': "{{ csrf_token() }}",
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                token: token,
                device_uuid: deviceUuid,
            }),
        });

        const data = await response.json();

        const okClass = data.ok ? 'success' : 'danger';
        const icon = data.ok ? 'ti-circle-check' : 'ti-alert-triangle';

        const statusLabels = {
    on_time: 'Puntual',
    late: 'Retardo',
    very_late: 'Extemporánea',
    early_exit: 'Salida anticipada',
    normal_exit: 'Salida normal',
    duplicate: 'Duplicado',
    allowed: 'Autorizado',
    denied: 'Denegado',
    invalid_credential: 'Credencial inválida',
    blocked_student: 'Alumno no activo',
    device_blocked: 'Dispositivo bloqueado',
};

const statusText = statusLabels[data.status] || statusLabels[data.decision] || data.status || data.decision || '';

      const photoHtml = data.student?.photo_url
    ? `<span class="avatar avatar-xl mb-3" style="background-image: url('${data.student.photo_url}'); width: 120px; height: 120px;"></span>`
    : `<i class="ti ${icon} text-${okClass}" style="font-size: 6rem;"></i>`;

result.innerHTML = `
    <div class="py-5">
        ${photoHtml}
        <h1 class="mt-4">${data.message ?? 'Resultado recibido'}</h1>
        <h2 class="text-secondary">${data.student?.name ?? ''}</h2>
        <div class="text-secondary fs-3">${data.student?.group ?? ''}</div>
        <div class="mt-4">
            <span class="badge bg-${okClass}-lt fs-3">${statusText}</span>
        </div>
    </div>
`;

    } catch (error) {
        result.innerHTML = `
            <div class="py-5">
                <i class="ti ti-wifi-off text-danger" style="font-size: 6rem;"></i>
                <h1 class="mt-4">Error de conexión</h1>
                <div class="text-secondary fs-3">No se pudo contactar al servidor.</div>
            </div>
        `;
    }

    setTimeout(() => {
        result.classList.add('d-none');
        idle.classList.remove('d-none');
        tokenInput.focus();
    }, 3000);
}
</script>
@endpush