@extends('layouts.sysadmin')

@section('title', 'Configuración de apps · '.$school->name)
@section('page_title', 'Configuración de apps')

@php
    $identity = $config['identity'];
    $theme = $identity['theme'];
    $lightTheme = $theme['light'];
    $darkTheme = $theme['dark'];

    $attendance = $config['attendance'];
    $credentials = $config['credentials'];
    $notifications = $config['notifications'];
    $staff = $config['staff'];
    $navigation = $config['navigation'];
    $support = $config['support'];

    $primaryColor = old(
        'primary_color',
        $identity['primary_color']
    );

    $secondaryColor = old(
        'secondary_color',
        $identity['secondary_color']
    );

    $accentColor = old(
        'accent_color',
        $identity['accent_color']
    );

    $attendanceSwitches = [
        'check_in_enabled' => [
            'Registrar entrada',
            'check_in_enabled',
            'Permite registrar entradas desde Staff.',
        ],

        'check_out_enabled' => [
            'Registrar salida',
            'check_out_enabled',
            'Permite registrar salidas desde Staff.',
        ],

        'early_exit_enabled' => [
            'Salida anticipada',
            'early_exit_enabled',
            'Permite registrar salidas antes del horario normal.',
        ],

        'early_exit_requires_authorization' => [
            'Autorización para salida anticipada',
            'early_exit_requires_authorization',
            'Solicita autorización antes de confirmar la salida.',
        ],

        'observations_enabled' => [
            'Observaciones operativas',
            'observations_enabled',
            'Permite agregar notas al registro de acceso.',
        ],

        'temporary_passes_enabled' => [
            'Pases temporales',
            'temporary_passes_enabled',
            'Permite generar y utilizar pases con vigencia limitada.',
        ],
    ];

    $staffSwitches = [
        'staff_qr_scan_enabled' => [
            'Escáner QR',
            'qr_scan_enabled',
            'Muestra y habilita el escáner principal.',
        ],

        'staff_manual_search_enabled' => [
            'Búsqueda manual',
            'manual_search_enabled',
            'Permite buscar alumnos por nombre, matrícula o grupo.',
        ],

        'staff_recent_access_enabled' => [
            'Historial reciente',
            'recent_access_enabled',
            'Muestra los accesos registrados recientemente.',
        ],

        'staff_show_student_photo' => [
            'Mostrar foto del alumno',
            'show_student_photo',
            'Ayuda al personal a confirmar visualmente la identidad.',
        ],

        'staff_sound_enabled' => [
            'Sonidos de resultado',
            'sound_enabled',
            'Reproduce sonido al autorizar, duplicar o rechazar.',
        ],

        'staff_vibration_enabled' => [
            'Vibración',
            'vibration_enabled',
            'Activa respuesta háptica después de cada lectura.',
        ],
    ];

    $notificationSwitches = [
        'notify_entry' => [
            'Entrada registrada',
            'entry',
        ],

        'notify_exit' => [
            'Salida registrada',
            'exit',
        ],

        'notify_late' => [
            'Retardo',
            'late',
        ],

        'notify_absence' => [
            'Ausencia',
            'absence',
        ],

        'notify_early_exit' => [
            'Salida anticipada',
            'early_exit',
        ],

        'notify_denied_access' => [
            'Acceso rechazado',
            'denied_access',
        ],
    ];

    $navigationSwitches = [
        'show_notices' => [
            'Avisos',
            'notices',
        ],

        'show_attendance_history' => [
            'Historial de asistencia',
            'attendance_history',
        ],

        'show_digital_credential' => [
            'Credencial digital',
            'digital_credential',
        ],

        'show_authorizations' => [
            'Autorizaciones',
            'authorizations',
        ],

        'show_payments' => [
            'Pagos',
            'payments',
        ],

        'show_grades' => [
            'Calificaciones',
            'grades',
        ],
    ];
@endphp

@section('content')
<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">
                <a
                    href="{{ route(
                        'sysadmin.schools.show',
                        $school
                    ) }}"
                    class="text-secondary text-decoration-none"
                >
                    <i class="ti ti-arrow-left me-1"></i>
                    {{ $school->name }}
                </a>
            </div>

            <h2 class="page-title">
                Configuración de apps
            </h2>

            <div class="text-secondary mt-1">
                Marca, tema, operación y funciones entregadas
                dinámicamente a SchoolPass Staff y Familia.
            </div>
        </div>

        <div class="col-auto ms-auto">
            <div class="btn-list">
                <a
                    href="{{ route(
                        'sysadmin.schools.administrators.index',
                        $school
                    ) }}"
                    class="btn btn-outline-primary"
                >
                    <i class="ti ti-users me-2"></i>
                    Administradores
                </a>

                <span class="badge bg-blue-lt text-blue fs-6">
                    Versión
                    {{ $config['config_version'] }}
                </span>
            </div>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">
        <div class="d-flex">
            <div>
                <i class="ti ti-circle-check me-2"></i>
            </div>

            <div>
                {{ session('success') }}
            </div>
        </div>
    </div>
@endif

@if ($errors->has('app_config'))
    <div class="alert alert-danger">
        <div class="d-flex">
            <div>
                <i class="ti ti-alert-triangle me-2"></i>
            </div>

            <div>
                {{ $errors->first('app_config') }}
            </div>
        </div>
    </div>
@endif

@if ($errors->any() && !$errors->has('app_config'))
    <div class="alert alert-danger">
        <div class="d-flex">
            <div>
                <i class="ti ti-alert-triangle me-2"></i>
            </div>

            <div>
                <div class="fw-semibold">
                    Revisa los campos marcados.
                </div>

                <div class="small mt-1">
                    La configuración no fue publicada porque
                    existen datos inválidos.
                </div>
            </div>
        </div>
    </div>
@endif

@if (!$license)
    <div class="alert alert-warning">
        <div class="d-flex">
            <div>
                <i class="ti ti-license-off me-2"></i>
            </div>

            <div>
                La escuela todavía no tiene licencia.
                La configuración puede guardarse, pero las apps
                recibirán
                <strong>access_allowed: false</strong>.
            </div>
        </div>
    </div>
@else
    <div class="alert {{
        in_array(
            $license->status,
            ['active', 'trial', 'grace'],
            true
        )
            ? 'alert-info'
            : 'alert-warning'
    }}">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span>
                <i class="ti ti-license me-1"></i>
                Plan:
                <strong>
                    {{ $license->plan_name ?: '—' }}
                </strong>
            </span>

            <span class="text-secondary">·</span>

            <span>
                Estado:
                <strong>{{ $license->status }}</strong>
            </span>

            <span class="text-secondary">·</span>

            <span>
                Vencimiento:
                <strong>
                    {{ $license->expires_at ?: 'Sin fecha' }}
                </strong>
            </span>
        </div>
    </div>
@endif

<form
    method="POST"
    action="{{ route(
        'sysadmin.schools.app-config.update',
        $school
    ) }}"
    enctype="multipart/form-data"
>
    @csrf
    @method('PUT')

    <div class="row row-cards">
        <div class="col-xl-8">
            {{-- Identidad --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Identidad de las aplicaciones
                        </h3>

                        <div class="small text-secondary">
                            Se entrega a Android mediante
                            <code>/api/v1/app/config</code>.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label required">
                                Nombre mostrado
                            </label>

                            <input
                                type="text"
                                name="app_name"
                                id="app_name"
                                class="form-control @error('app_name') is-invalid @enderror"
                                value="{{ old(
                                    'app_name',
                                    $identity['app_name']
                                ) }}"
                                maxlength="80"
                                required
                            >

                            @error('app_name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                            <div class="form-hint">
                                Nombre utilizado dentro de las apps.
                                No modifica el nombre instalado de Android.
                            </div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label required">
                                Nombre corto
                            </label>

                            <input
                                type="text"
                                name="short_name"
                                id="short_name"
                                class="form-control @error('short_name') is-invalid @enderror"
                                value="{{ old(
                                    'short_name',
                                    $identity['short_name']
                                ) }}"
                                maxlength="30"
                                required
                            >

                            @error('short_name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                Mensaje de bienvenida
                            </label>

                            <input
                                type="text"
                                name="welcome_message"
                                id="welcome_message"
                                class="form-control @error('welcome_message') is-invalid @enderror"
                                value="{{ old(
                                    'welcome_message',
                                    $identity['welcome_message']
                                ) }}"
                                maxlength="180"
                            >

                            @error('welcome_message')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Colores --}}
            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Colores institucionales
                        </h3>

                        <div class="small text-secondary">
                            Estos colores se aplican después de
                            descargar la configuración de la escuela.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">
                                Color principal
                            </label>

                            <div class="input-group">
                                <input
                                    type="color"
                                    class="form-control form-control-color"
                                    value="{{ $primaryColor }}"
                                    data-color-picker="primary_color"
                                    aria-label="Seleccionar color principal"
                                >

                                <input
                                    type="text"
                                    name="primary_color"
                                    id="primary_color"
                                    class="form-control @error('primary_color') is-invalid @enderror"
                                    value="{{ $primaryColor }}"
                                    data-color-text="primary_color"
                                    maxlength="7"
                                    required
                                >
                            </div>

                            @error('primary_color')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">
                                Color secundario
                            </label>

                            <div class="input-group">
                                <input
                                    type="color"
                                    class="form-control form-control-color"
                                    value="{{ $secondaryColor }}"
                                    data-color-picker="secondary_color"
                                    aria-label="Seleccionar color secundario"
                                >

                                <input
                                    type="text"
                                    name="secondary_color"
                                    id="secondary_color"
                                    class="form-control @error('secondary_color') is-invalid @enderror"
                                    value="{{ $secondaryColor }}"
                                    data-color-text="secondary_color"
                                    maxlength="7"
                                    required
                                >
                            </div>

                            @error('secondary_color')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">
                                Color de acento
                            </label>

                            <div class="input-group">
                                <input
                                    type="color"
                                    class="form-control form-control-color"
                                    value="{{ $accentColor }}"
                                    data-color-picker="accent_color"
                                    aria-label="Seleccionar color de acento"
                                >

                                <input
                                    type="text"
                                    name="accent_color"
                                    id="accent_color"
                                    class="form-control @error('accent_color') is-invalid @enderror"
                                    value="{{ $accentColor }}"
                                    data-color-text="accent_color"
                                    maxlength="7"
                                    required
                                >
                            </div>

                            @error('accent_color')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tema --}}
            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Tema claro y oscuro
                        </h3>

                        <div class="small text-secondary">
                            Kotlin construirá su
                            <code>ColorScheme</code>
                            con estos valores.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">
                                Tema predeterminado
                            </label>

                            <select
                                name="theme_default_mode"
                                id="theme_default_mode"
                                class="form-select @error('theme_default_mode') is-invalid @enderror"
                                required
                            >
                                <option
                                    value="system"
                                    @selected(
                                        old(
                                            'theme_default_mode',
                                            $theme['default_mode']
                                        ) === 'system'
                                    )
                                >
                                    Usar configuración del sistema
                                </option>

                                <option
                                    value="light"
                                    @selected(
                                        old(
                                            'theme_default_mode',
                                            $theme['default_mode']
                                        ) === 'light'
                                    )
                                >
                                    Siempre claro
                                </option>

                                <option
                                    value="dark"
                                    @selected(
                                        old(
                                            'theme_default_mode',
                                            $theme['default_mode']
                                        ) === 'dark'
                                    )
                                >
                                    Siempre oscuro
                                </option>
                            </select>

                            @error('theme_default_mode')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <label class="form-check form-switch mb-2">
                                <input
                                    type="checkbox"
                                    name="theme_allow_user_override"
                                    value="1"
                                    class="form-check-input"
                                    @checked(old(
                                        'theme_allow_user_override',
                                        $theme['allow_user_override']
                                    ))
                                >

                                <span class="form-check-label">
                                    Permitir que el usuario cambie el tema
                                </span>
                            </label>
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                        </div>

                        <div class="col-12">
                            <div class="fw-semibold">
                                Tema claro
                            </div>

                            <div class="small text-secondary">
                                Fondos y texto utilizados cuando
                                la aplicación opera en modo claro.
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">
                                Fondo
                            </label>

                            <div class="input-group">
                                <input
                                    type="color"
                                    class="form-control form-control-color"
                                    value="{{ old(
                                        'light_background_color',
                                        $lightTheme['background_color']
                                    ) }}"
                                    data-color-picker="light_background_color"
                                >

                                <input
                                    type="text"
                                    name="light_background_color"
                                    class="form-control @error('light_background_color') is-invalid @enderror"
                                    value="{{ old(
                                        'light_background_color',
                                        $lightTheme['background_color']
                                    ) }}"
                                    data-color-text="light_background_color"
                                    maxlength="7"
                                    required
                                >
                            </div>

                            @error('light_background_color')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">
                                Superficie
                            </label>

                            <div class="input-group">
                                <input
                                    type="color"
                                    class="form-control form-control-color"
                                    value="{{ old(
                                        'light_surface_color',
                                        $lightTheme['surface_color']
                                    ) }}"
                                    data-color-picker="light_surface_color"
                                >

                                <input
                                    type="text"
                                    name="light_surface_color"
                                    class="form-control @error('light_surface_color') is-invalid @enderror"
                                    value="{{ old(
                                        'light_surface_color',
                                        $lightTheme['surface_color']
                                    ) }}"
                                    data-color-text="light_surface_color"
                                    maxlength="7"
                                    required
                                >
                            </div>

                            @error('light_surface_color')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">
                                Texto principal
                            </label>

                            <div class="input-group">
                                <input
                                    type="color"
                                    class="form-control form-control-color"
                                    value="{{ old(
                                        'light_on_surface_color',
                                        $lightTheme['on_surface_color']
                                    ) }}"
                                    data-color-picker="light_on_surface_color"
                                >

                                <input
                                    type="text"
                                    name="light_on_surface_color"
                                    class="form-control @error('light_on_surface_color') is-invalid @enderror"
                                    value="{{ old(
                                        'light_on_surface_color',
                                        $lightTheme['on_surface_color']
                                    ) }}"
                                    data-color-text="light_on_surface_color"
                                    maxlength="7"
                                    required
                                >
                            </div>

                            @error('light_on_surface_color')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                        </div>

                        <div class="col-12">
                            <div class="fw-semibold">
                                Tema oscuro
                            </div>

                            <div class="small text-secondary">
                                Se recomienda usar tonos grafito,
                                no negro absoluto.
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">
                                Fondo
                            </label>

                            <div class="input-group">
                                <input
                                    type="color"
                                    class="form-control form-control-color"
                                    value="{{ old(
                                        'dark_background_color',
                                        $darkTheme['background_color']
                                    ) }}"
                                    data-color-picker="dark_background_color"
                                >

                                <input
                                    type="text"
                                    name="dark_background_color"
                                    class="form-control @error('dark_background_color') is-invalid @enderror"
                                    value="{{ old(
                                        'dark_background_color',
                                        $darkTheme['background_color']
                                    ) }}"
                                    data-color-text="dark_background_color"
                                    maxlength="7"
                                    required
                                >
                            </div>

                            @error('dark_background_color')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">
                                Superficie
                            </label>

                            <div class="input-group">
                                <input
                                    type="color"
                                    class="form-control form-control-color"
                                    value="{{ old(
                                        'dark_surface_color',
                                        $darkTheme['surface_color']
                                    ) }}"
                                    data-color-picker="dark_surface_color"
                                >

                                <input
                                    type="text"
                                    name="dark_surface_color"
                                    class="form-control @error('dark_surface_color') is-invalid @enderror"
                                    value="{{ old(
                                        'dark_surface_color',
                                        $darkTheme['surface_color']
                                    ) }}"
                                    data-color-text="dark_surface_color"
                                    maxlength="7"
                                    required
                                >
                            </div>

                            @error('dark_surface_color')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">
                                Texto principal
                            </label>

                            <div class="input-group">
                                <input
                                    type="color"
                                    class="form-control form-control-color"
                                    value="{{ old(
                                        'dark_on_surface_color',
                                        $darkTheme['on_surface_color']
                                    ) }}"
                                    data-color-picker="dark_on_surface_color"
                                >

                                <input
                                    type="text"
                                    name="dark_on_surface_color"
                                    class="form-control @error('dark_on_surface_color') is-invalid @enderror"
                                    value="{{ old(
                                        'dark_on_surface_color',
                                        $darkTheme['on_surface_color']
                                    ) }}"
                                    data-color-text="dark_on_surface_color"
                                    maxlength="7"
                                    required
                                >
                            </div>

                            @error('dark_on_surface_color')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Imágenes --}}
            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Recursos visuales
                        </h3>

                        <div class="small text-secondary">
                            El logo se utiliza dentro de la app.
                            El ícono launcher seguirá dependiendo
                            del flavor de Android.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">
                                Logotipo
                            </label>

                            <input
                                type="file"
                                name="logo"
                                id="logo"
                                class="form-control @error('logo') is-invalid @enderror"
                                accept="image/png,image/jpeg,image/webp"
                            >

                            @error('logo')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                            <div class="form-hint">
                                PNG, JPG o WebP. Máximo 4 MB.
                                Preferible con fondo transparente.
                            </div>

                            @if ($logoUrl)
                                <div class="mt-3">
                                    <div class="border rounded p-3 bg-white text-center">
                                        <img
                                            src="{{ $logoUrl }}"
                                            alt="Logotipo actual"
                                            style="
                                                max-height: 100px;
                                                max-width: 230px;
                                                object-fit: contain;
                                            "
                                        >
                                    </div>

                                    <label class="form-check mt-2">
                                        <input
                                            type="checkbox"
                                            name="remove_logo"
                                            value="1"
                                            class="form-check-input"
                                            @checked(old('remove_logo'))
                                        >

                                        <span class="form-check-label">
                                            Eliminar logotipo actual
                                        </span>
                                    </label>
                                </div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Imagen de bienvenida
                            </label>

                            <input
                                type="file"
                                name="welcome_image"
                                id="welcome_image"
                                class="form-control @error('welcome_image') is-invalid @enderror"
                                accept="image/png,image/jpeg,image/webp"
                            >

                            @error('welcome_image')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                            <div class="form-hint">
                                Recomendado 1600 × 900.
                                Máximo 6 MB.
                            </div>

                            @if ($welcomeImageUrl)
                                <div class="mt-3">
                                    <div class="border rounded overflow-hidden">
                                        <img
                                            src="{{ $welcomeImageUrl }}"
                                            alt="Imagen de bienvenida actual"
                                            style="
                                                width: 100%;
                                                max-height: 160px;
                                                object-fit: cover;
                                            "
                                        >
                                    </div>

                                    <label class="form-check mt-2">
                                        <input
                                            type="checkbox"
                                            name="remove_welcome_image"
                                            value="1"
                                            class="form-check-input"
                                            @checked(old(
                                                'remove_welcome_image'
                                            ))
                                        >

                                        <span class="form-check-label">
                                            Eliminar imagen actual
                                        </span>
                                    </label>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Asistencia --}}
            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Operación de asistencia
                        </h3>

                        <div class="small text-secondary">
                            Reglas generales compartidas por
                            Staff y Familia.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        @foreach (
                            $attendanceSwitches
                            as $field => [$label, $key, $description]
                        )
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <label class="form-check form-switch mb-1">
                                        <input
                                            type="checkbox"
                                            name="{{ $field }}"
                                            value="1"
                                            class="form-check-input"
                                            @checked(old(
                                                $field,
                                                $attendance[$key]
                                            ))
                                        >

                                        <span class="form-check-label fw-semibold">
                                            {{ $label }}
                                        </span>
                                    </label>

                                    <div class="small text-secondary ps-4">
                                        {{ $description }}
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="col-md-6">
                            <label class="form-label required">
                                Tolerancia de retardo
                            </label>

                            <div class="input-group">
                                <input
                                    type="number"
                                    name="late_tolerance_minutes"
                                    class="form-control @error('late_tolerance_minutes') is-invalid @enderror"
                                    value="{{ old(
                                        'late_tolerance_minutes',
                                        $attendance[
                                            'late_tolerance_minutes'
                                        ]
                                    ) }}"
                                    min="0"
                                    max="180"
                                    required
                                >

                                <span class="input-group-text">
                                    minutos
                                </span>
                            </div>

                            @error('late_tolerance_minutes')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror

                            <div class="form-hint">
                                Este valor es general. Los horarios
                                específicos por grupo pueden aplicar
                                reglas adicionales.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Credenciales --}}
            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Credenciales y acceso
                        </h3>

                        <div class="small text-secondary">
                            Define los mecanismos de identificación
                            permitidos por la escuela.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-check form-switch">
                                <input
                                    type="checkbox"
                                    name="qr_enabled"
                                    value="1"
                                    class="form-check-input"
                                    @checked(old(
                                        'qr_enabled',
                                        $credentials['qr_enabled']
                                    ))
                                >

                                <span class="form-check-label">
                                    QR habilitado
                                </span>
                            </label>
                        </div>

                        <div class="col-md-4">
                            <label class="form-check form-switch">
                                <input
                                    type="checkbox"
                                    name="nfc_enabled"
                                    value="1"
                                    class="form-check-input"
                                    @checked(old(
                                        'nfc_enabled',
                                        $credentials['nfc_enabled']
                                    ))
                                >

                                <span class="form-check-label">
                                    NFC habilitado
                                </span>
                            </label>
                        </div>

                        <div class="col-md-4">
                            <label class="form-check form-switch">
                                <input
                                    type="checkbox"
                                    name="printed_credential_enabled"
                                    value="1"
                                    class="form-check-input"
                                    @checked(old(
                                        'printed_credential_enabled',
                                        $credentials[
                                            'printed_credential_enabled'
                                        ]
                                    ))
                                >

                                <span class="form-check-label">
                                    Credencial impresa
                                </span>
                            </label>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">
                                Modalidad del QR
                            </label>

                            <select
                                name="qr_mode"
                                class="form-select @error('qr_mode') is-invalid @enderror"
                                required
                            >
                                <option
                                    value="fixed"
                                    @selected(
                                        old(
                                            'qr_mode',
                                            $credentials['qr_mode']
                                        ) === 'fixed'
                                    )
                                >
                                    Fijo
                                </option>

                                <option
                                    value="dynamic"
                                    @selected(
                                        old(
                                            'qr_mode',
                                            $credentials['qr_mode']
                                        ) === 'dynamic'
                                    )
                                >
                                    Dinámico
                                </option>
                            </select>

                            @error('qr_mode')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">
                                Duración del pase temporal
                            </label>

                            <div class="input-group">
                                <input
                                    type="number"
                                    name="temporary_pass_minutes"
                                    class="form-control @error('temporary_pass_minutes') is-invalid @enderror"
                                    value="{{ old(
                                        'temporary_pass_minutes',
                                        $credentials[
                                            'temporary_pass_minutes'
                                        ]
                                    ) }}"
                                    min="5"
                                    max="1440"
                                    required
                                >

                                <span class="input-group-text">
                                    minutos
                                </span>
                            </div>

                            @error('temporary_pass_minutes')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Staff --}}
            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            SchoolPass Staff
                        </h3>

                        <div class="small text-secondary">
                            Comportamiento de Prefecto, Kiosco y
                            personal autorizado.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        @foreach (
                            $staffSwitches
                            as $field => [$label, $key, $description]
                        )
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <label class="form-check form-switch mb-1">
                                        <input
                                            type="checkbox"
                                            name="{{ $field }}"
                                            value="1"
                                            class="form-check-input"
                                            @checked(old(
                                                $field,
                                                $staff[$key]
                                            ))
                                        >

                                        <span class="form-check-label fw-semibold">
                                            {{ $label }}
                                        </span>
                                    </label>

                                    <div class="small text-secondary ps-4">
                                        {{ $description }}
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="col-md-4">
                            <label class="form-label required">
                                Reinicio automático
                            </label>

                            <div class="input-group">
                                <input
                                    type="number"
                                    name="staff_auto_reset_seconds"
                                    class="form-control @error('staff_auto_reset_seconds') is-invalid @enderror"
                                    value="{{ old(
                                        'staff_auto_reset_seconds',
                                        $staff[
                                            'auto_reset_seconds'
                                        ]
                                    ) }}"
                                    min="1"
                                    max="30"
                                    required
                                >

                                <span class="input-group-text">
                                    segundos
                                </span>
                            </div>

                            @error('staff_auto_reset_seconds')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">
                                Operación predeterminada
                            </label>

                            <select
                                name="staff_default_event_type"
                                class="form-select @error('staff_default_event_type') is-invalid @enderror"
                                required
                            >
                                <option
                                    value="entry"
                                    @selected(
                                        old(
                                            'staff_default_event_type',
                                            $staff[
                                                'default_event_type'
                                            ]
                                        ) === 'entry'
                                    )
                                >
                                    Entrada
                                </option>

                                <option
                                    value="exit"
                                    @selected(
                                        old(
                                            'staff_default_event_type',
                                            $staff[
                                                'default_event_type'
                                            ]
                                        ) === 'exit'
                                    )
                                >
                                    Salida
                                </option>
                            </select>

                            @error('staff_default_event_type')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">
                                Cámara predeterminada
                            </label>

                            <select
                                name="staff_camera_facing"
                                class="form-select @error('staff_camera_facing') is-invalid @enderror"
                                required
                            >
                                <option
                                    value="back"
                                    @selected(
                                        old(
                                            'staff_camera_facing',
                                            $staff['camera_facing']
                                        ) === 'back'
                                    )
                                >
                                    Trasera
                                </option>

                                <option
                                    value="front"
                                    @selected(
                                        old(
                                            'staff_camera_facing',
                                            $staff['camera_facing']
                                        ) === 'front'
                                    )
                                >
                                    Frontal
                                </option>
                            </select>

                            @error('staff_camera_facing')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                <div class="d-flex">
                                    <div>
                                        <i class="ti ti-info-circle me-2"></i>
                                    </div>

                                    <div>
                                        La configuración específica del
                                        dispositivo asignado puede
                                        sobrescribir cámara, área y modo
                                        predeterminado desde
                                        <code>/access/bootstrap</code>.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Columna lateral --}}
        <div class="col-xl-4">
            {{-- Vista previa --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Vista previa
                        </h3>

                        <div class="small text-secondary">
                            Referencia visual, no simulación exacta
                            de Android.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div
                        id="brand-preview"
                        class="rounded-4 overflow-hidden border"
                        style="
                            --preview-primary: {{ $primaryColor }};
                            --preview-secondary: {{ $secondaryColor }};
                            --preview-accent: {{ $accentColor }};
                        "
                    >
                        <div
                            style="
                                background:
                                    linear-gradient(
                                        135deg,
                                        var(--preview-primary),
                                        var(--preview-secondary)
                                    );
                                min-height: 170px;
                                padding: 24px;
                                position: relative;
                            "
                        >
                            <div
                                style="
                                    position: absolute;
                                    width: 120px;
                                    height: 120px;
                                    border-radius: 50%;
                                    background: var(--preview-accent);
                                    opacity: .20;
                                    right: -30px;
                                    top: -35px;
                                "
                            ></div>

                            <div
                                class="bg-white rounded-4 shadow-sm p-3"
                                style="
                                    position: relative;
                                    z-index: 1;
                                "
                            >
                                <div class="d-flex align-items-center gap-3">
                                    <div
                                        class="rounded-3 border d-flex align-items-center justify-content-center bg-white"
                                        style="
                                            width: 64px;
                                            height: 64px;
                                            flex: 0 0 64px;
                                            overflow: hidden;
                                        "
                                    >
                                        <img
                                            id="preview-logo"
                                            src="{{ $logoUrl ?: '' }}"
                                            alt=""
                                            style="
                                                max-width: 54px;
                                                max-height: 54px;
                                                object-fit: contain;
                                                display: {{
                                                    $logoUrl
                                                        ? 'block'
                                                        : 'none'
                                                }};
                                            "
                                        >

                                        <i
                                            id="preview-logo-fallback"
                                            class="ti ti-school fs-1"
                                            style="
                                                color: var(--preview-primary);
                                                display: {{
                                                    $logoUrl
                                                        ? 'none'
                                                        : 'block'
                                                }};
                                            "
                                        ></i>
                                    </div>

                                    <div class="min-width-0">
                                        <div
                                            id="preview-app-name"
                                            class="fw-bold text-truncate"
                                            style="color: #172033;"
                                        >
                                            {{ old(
                                                'app_name',
                                                $identity['app_name']
                                            ) }}
                                        </div>

                                        <div
                                            id="preview-short-name"
                                            class="small text-secondary text-truncate"
                                        >
                                            {{ old(
                                                'short_name',
                                                $identity['short_name']
                                            ) }}
                                        </div>
                                    </div>
                                </div>

                                <div
                                    id="preview-welcome-message"
                                    class="mt-3 small"
                                    style="color: #475569;"
                                >
                                    {{ old(
                                        'welcome_message',
                                        $identity['welcome_message']
                                    ) }}
                                </div>

                                <button
                                    type="button"
                                    class="btn w-100 mt-3 text-white"
                                    style="
                                        background:
                                            var(--preview-primary);
                                    "
                                >
                                    Continuar
                                </button>
                            </div>
                        </div>

                        <div class="p-3 bg-light">
                            <div class="d-flex gap-2">
                                <span
                                    class="rounded-circle border"
                                    style="
                                        width: 22px;
                                        height: 22px;
                                        background:
                                            var(--preview-primary);
                                    "
                                    title="Color principal"
                                ></span>

                                <span
                                    class="rounded-circle border"
                                    style="
                                        width: 22px;
                                        height: 22px;
                                        background:
                                            var(--preview-secondary);
                                    "
                                    title="Color secundario"
                                ></span>

                                <span
                                    class="rounded-circle border"
                                    style="
                                        width: 22px;
                                        height: 22px;
                                        background:
                                            var(--preview-accent);
                                    "
                                    title="Color de acento"
                                ></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Soporte --}}
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">
                        Soporte de la escuela
                    </h3>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">
                            Correo
                        </label>

                        <input
                            type="email"
                            name="support_email"
                            class="form-control @error('support_email') is-invalid @enderror"
                            value="{{ old(
                                'support_email',
                                $support['email']
                            ) }}"
                            maxlength="255"
                        >

                        @error('support_email')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Teléfono
                        </label>

                        <input
                            type="text"
                            name="support_phone"
                            class="form-control @error('support_phone') is-invalid @enderror"
                            value="{{ old(
                                'support_phone',
                                $support['phone']
                            ) }}"
                            maxlength="30"
                        >

                        @error('support_phone')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">
                            WhatsApp
                        </label>

                        <input
                            type="text"
                            name="support_whatsapp"
                            class="form-control @error('support_whatsapp') is-invalid @enderror"
                            value="{{ old(
                                'support_whatsapp',
                                $support['whatsapp']
                            ) }}"
                            maxlength="30"
                        >

                        @error('support_whatsapp')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Notificaciones --}}
            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Notificaciones
                        </h3>

                        <div class="small text-secondary">
                            Eventos que pueden generar aviso
                            para tutores.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    @foreach (
                        $notificationSwitches
                        as $field => [$label, $key]
                    )
                        <label class="form-check form-switch mb-3">
                            <input
                                type="checkbox"
                                name="{{ $field }}"
                                value="1"
                                class="form-check-input"
                                @checked(old(
                                    $field,
                                    $notifications[$key]
                                ))
                            >

                            <span class="form-check-label">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Navegación --}}
            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Navegación de las apps
                        </h3>

                        <div class="small text-secondary">
                            El backend también valida lo permitido
                            por la licencia.
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    @foreach (
                        $navigationSwitches
                        as $field => [$label, $key]
                    )
                        <label class="form-check form-switch mb-3">
                            <input
                                type="checkbox"
                                name="{{ $field }}"
                                value="1"
                                class="form-check-input"
                                @checked(old(
                                    $field,
                                    $navigation[$key]
                                ))
                            >

                            <span class="form-check-label">
                                Mostrar
                                {{ mb_strtolower($label) }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Features --}}
            <div class="card mt-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Funciones efectivas del plan
                        </h3>

                        <div class="small text-secondary">
                            Resultado del plan contratado más
                            las excepciones de esta escuela.
                        </div>
                    </div>
                </div>

                <div class="list-group list-group-flush">
                    @forelse ($features as $key => $enabled)
                        <div class="list-group-item">
                            <div class="d-flex align-items-center">
                                <code>{{ $key }}</code>

                                <span class="ms-auto badge {{
                                    $enabled
                                        ? 'bg-green-lt text-green'
                                        : 'bg-red-lt text-red'
                                }}">
                                    {{
                                        $enabled
                                            ? 'Habilitada'
                                            : 'Deshabilitada'
                                    }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-secondary">
                            Sin licencia o funciones configuradas.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Guardar --}}
            <div class="card mt-3 sticky-xl-top">
                <div class="card-body">
                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        <i class="ti ti-device-floppy me-2"></i>
                        Publicar configuración
                    </button>

                    <div class="small text-secondary mt-3">
                        Cada publicación aumenta
                        <code>config_version</code>.

                        Las apps detectarán el cambio y
                        actualizarán logo, colores y funciones.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const preview = document.getElementById('brand-preview');

    const appNameInput =
        document.getElementById('app_name');

    const shortNameInput =
        document.getElementById('short_name');

    const welcomeMessageInput =
        document.getElementById('welcome_message');

    const previewAppName =
        document.getElementById('preview-app-name');

    const previewShortName =
        document.getElementById('preview-short-name');

    const previewWelcomeMessage =
        document.getElementById(
            'preview-welcome-message'
        );

    const logoInput =
        document.getElementById('logo');

    const previewLogo =
        document.getElementById('preview-logo');

    const previewLogoFallback =
        document.getElementById(
            'preview-logo-fallback'
        );

    function normalizeHex(value) {
        const normalized = value
            .trim()
            .toUpperCase();

        return /^#[0-9A-F]{6}$/.test(normalized)
            ? normalized
            : null;
    }

    function updatePreviewText() {
        if (
            appNameInput
            && previewAppName
        ) {
            previewAppName.textContent =
                appNameInput.value.trim()
                || 'SchoolPass';
        }

        if (
            shortNameInput
            && previewShortName
        ) {
            previewShortName.textContent =
                shortNameInput.value.trim()
                || 'Escuela';
        }

        if (
            welcomeMessageInput
            && previewWelcomeMessage
        ) {
            previewWelcomeMessage.textContent =
                welcomeMessageInput.value.trim()
                || 'Bienvenido a SchoolPass';
        }
    }

    document
        .querySelectorAll('[data-color-picker]')
        .forEach(function (picker) {
            const field = picker.getAttribute(
                'data-color-picker'
            );

            const text = document.querySelector(
                '[data-color-text="' + field + '"]'
            );

            if (!text) {
                return;
            }

            picker.addEventListener(
                'input',
                function () {
                    const color =
                        picker.value.toUpperCase();

                    text.value = color;

                    updatePreviewColor(
                        field,
                        color
                    );
                }
            );

            text.addEventListener(
                'input',
                function () {
                    const color =
                        normalizeHex(text.value);

                    if (!color) {
                        return;
                    }

                    picker.value = color;

                    updatePreviewColor(
                        field,
                        color
                    );
                }
            );

            text.addEventListener(
                'blur',
                function () {
                    const color =
                        normalizeHex(text.value);

                    if (color) {
                        text.value = color;
                    }
                }
            );
        });

    function updatePreviewColor(field, color) {
        if (!preview) {
            return;
        }

        if (field === 'primary_color') {
            preview.style.setProperty(
                '--preview-primary',
                color
            );
        }

        if (field === 'secondary_color') {
            preview.style.setProperty(
                '--preview-secondary',
                color
            );
        }

        if (field === 'accent_color') {
            preview.style.setProperty(
                '--preview-accent',
                color
            );
        }
    }

    [
        appNameInput,
        shortNameInput,
        welcomeMessageInput,
    ].forEach(function (input) {
        if (!input) {
            return;
        }

        input.addEventListener(
            'input',
            updatePreviewText
        );
    });

    if (
        logoInput
        && previewLogo
        && previewLogoFallback
    ) {
        logoInput.addEventListener(
            'change',
            function () {
                const file =
                    logoInput.files
                    && logoInput.files[0];

                if (!file) {
                    return;
                }

                if (
                    !file.type.startsWith('image/')
                ) {
                    return;
                }

                const reader = new FileReader();

                reader.addEventListener(
                    'load',
                    function () {
                        previewLogo.src =
                            reader.result;

                        previewLogo.style.display =
                            'block';

                        previewLogoFallback.style.display =
                            'none';
                    }
                );

                reader.readAsDataURL(file);
            }
        );
    }

    updatePreviewText();
});
</script>
@endpush