@extends('layouts.app')

@section('title', 'Prefectura | SchoolPass')
@section('section-label', 'Prefectura')
@section('page-title', 'Control de acceso')

@section('content')
    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Scanner de credencial</h3>
                        <p class="card-subtitle">
                            Registro de entrada, salida o acceso interno.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    @if(! $device)
                        <div class="alert alert-danger">
                            No hay dispositivo de prefectura activo para este usuario.
                        </div>
                    @else
                        <form id="scan-form">
                            @csrf

                            <input type="hidden" id="device_uuid" value="{{ $device->device_uuid }}">

                            <div class="mb-3">
                                <label class="form-label">Modo de registro</label>

                                <div class="btn-list">
                                    <label class="btn">
                                        <input class="form-check-input me-2" type="radio" name="event_type" value="entry" checked>
                                        <i class="ti ti-login me-1"></i>
                                        Entrada
                                    </label>

                                    <label class="btn">
                                        <input class="form-check-input me-2" type="radio" name="event_type" value="exit">
                                        <i class="ti ti-logout me-1"></i>
                                        Salida
                                    </label>

                                    <label class="btn">
                                        <input class="form-check-input me-2" type="radio" name="event_type" value="access">
                                        <i class="ti ti-door-enter me-1"></i>
                                        Acceso interno
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Token QR / Scanner</label>

                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">
                                        <i class="ti ti-qrcode"></i>
                                    </span>

                                    <input
                                        type="text"
                                        id="token"
                                        class="form-control"
                                        placeholder="QR-JUAN-0001"
                                        autocomplete="off"
                                        autofocus
                                    >

                                    <button class="btn btn-primary" type="submit">
                                        Validar
                                    </button>
                                </div>

                                <div class="form-hint">
                                    Aquí funcionará también un scanner USB que escriba el código y presione Enter.
                                </div>
                            </div>
                        </form>

                        <div class="border border-dashed rounded d-flex align-items-center justify-content-center bg-light mt-4"
                             style="height: 280px;">
                            <div class="text-center text-secondary">
                                <i class="ti ti-scan fs-1 d-block mb-2"></i>
                                Cámara / scanner físico pendiente
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Últimos accesos</h3>
                </div>
@php
    $eventLabels = [
        'entry' => 'Entrada',
        'exit' => 'Salida',
        'access' => 'Acceso',
    ];

    $statusLabels = [
        'on_time' => 'Puntual',
        'late' => 'Retardo',
        'very_late' => 'Extemporánea',
        'early_exit' => 'Salida anticipada',
        'normal_exit' => 'Salida normal',
        'duplicate' => 'Duplicado',
        'allowed' => 'Autorizado',
        'denied' => 'Denegado',
        'invalid_credential' => 'Credencial inválida',
        'blocked_student' => 'Alumno no activo',
        'device_blocked' => 'Dispositivo bloqueado',
    ];
@endphp
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Área</th>
                                <th>Evento</th>
                                <th>Estado</th>
                                <th>Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogs as $log)
                                <tr>
                                    <td>{{ $log->student_name ?? 'Sin alumno' }}</td>
                                    <td>{{ $log->area_name ?? 'Sin área' }}</td>
                                   <td>{{ $eventLabels[$log->event_type] ?? $log->event_type }}</td>
<td>{{ $statusLabels[$log->event_status] ?? $log->event_status }}</td>
                                    <td>{{ $log->scanned_at }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4">
                                        No hay accesos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div id="scan-result" class="card">
                <div class="card-header">
                    <h3 class="card-title">Resultado</h3>
                </div>

                <div class="card-body text-center text-secondary py-5">
                    <i class="ti ti-scan fs-1 d-block mb-2"></i>
                    Esperando escaneo
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.getElementById('scan-form')?.addEventListener('submit', async function (event) {
    event.preventDefault();

    const tokenInput = document.getElementById('token');
    const token = tokenInput.value.trim();
    const deviceUuid = document.getElementById('device_uuid').value;
    const eventType = document.querySelector('input[name="event_type"]:checked').value;
    const result = document.getElementById('scan-result');

    if (!token) {
        return;
    }

    result.innerHTML = `
        <div class="card-body text-center py-5">
            <div class="spinner-border text-primary mb-3"></div>
            <div>Validando credencial...</div>
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
                event_type: eventType,
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
    ? `<span class="avatar avatar-xl mb-3" style="background-image: url('${data.student.photo_url}')"></span>`
    : `<i class="ti ${icon} text-${okClass}" style="font-size: 4rem;"></i>`;

result.innerHTML = `
    <div class="card-header">
        <h3 class="card-title">Resultado</h3>
    </div>
    <div class="card-body text-center py-5">
        ${photoHtml}
        <h2 class="mt-3">${data.message ?? 'Resultado recibido'}</h2>
        <div class="fw-bold">${data.student?.name ?? ''}</div>
        <div class="text-secondary">${data.student?.group ?? ''}</div>
        <div class="mt-3">
            <span class="badge bg-${okClass}-lt">${statusText}</span>
        </div>
    </div>
`;

        tokenInput.value = '';
        tokenInput.focus();

    } catch (error) {
        result.innerHTML = `
            <div class="card-body text-center py-5">
                <i class="ti ti-wifi-off text-danger" style="font-size: 4rem;"></i>
                <h2 class="mt-3">Error de conexión</h2>
                <div class="text-secondary">No se pudo contactar al servidor.</div>
            </div>
        `;
    }
});
</script>
@endpush