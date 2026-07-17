@extends('layouts.sysadmin')

@section('title', 'Versiones móviles')
@section('page_title', 'Versiones móviles')

@section('content')
@php
    $appMetadata = [
        'family' => [
            'name' => 'SchoolPass Familia',
            'description' => 'Aplicación para tutores, padres y alumnos.',
            'icon' => 'ti ti-users',
            'color' => 'purple',
        ],

        'staff' => [
            'name' => 'SchoolPass Staff',
            'description' => 'Aplicación operativa para prefectura y acceso.',
            'icon' => 'ti ti-id-badge-2',
            'color' => 'blue',
        ],
    ];
@endphp

<style>
    .release-card {
        overflow: hidden;
    }

    .release-card__top {
        border-bottom: 1px solid var(--tblr-border-color);
        background:
            linear-gradient(
                135deg,
                rgba(var(--tblr-primary-rgb), .055),
                transparent 62%
            );
    }

    .release-app-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 23px;
        flex: 0 0 auto;
    }

    .release-rule {
        padding: 13px 14px;
        border: 1px solid var(--tblr-border-color);
        border-radius: 14px;
        background: var(--tblr-bg-surface-secondary);
        height: 100%;
    }

    .release-rule strong {
        display: block;
        margin-bottom: 3px;
    }

    .release-preview {
        border: 1px solid var(--tblr-border-color);
        border-radius: 16px;
        padding: 16px;
        background: var(--tblr-bg-surface);
    }

    .release-code {
        font-family:
            ui-monospace,
            SFMono-Regular,
            Menlo,
            Monaco,
            Consolas,
            monospace;
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .release-status-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
</style>

<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">
                SchoolPass
            </div>

            <h2 class="page-title">
                Control de versiones móviles
            </h2>

            <div class="text-secondary mt-1">
                Publicación, compatibilidad y actualización obligatoria
                para las aplicaciones Android.
            </div>
        </div>

        <div class="col-auto ms-auto">
            <a
                href="{{ route('sysadmin.dashboard') }}"
                class="btn btn-outline-secondary"
            >
                <i class="ti ti-arrow-left me-2"></i>
                Panel principal
            </a>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">
        <div class="d-flex">
            <div>
                <i class="ti ti-circle-check fs-2 me-2"></i>
            </div>

            <div>
                {{ session('success') }}
            </div>
        </div>
    </div>
@endif

<div class="alert alert-info">
    <div class="d-flex">
        <div>
            <i class="ti ti-info-circle fs-2 me-2"></i>
        </div>

        <div>
            <div class="fw-semibold">
                La versión más reciente no siempre debe ser obligatoria.
            </div>

            <div class="mt-1">
                Una publicación normal muestra el botón
                <strong>Después</strong>. Usa actualización obligatoria
                solamente cuando la versión anterior ya no sea compatible
                o exista una corrección crítica.
            </div>
        </div>
    </div>
</div>

<div class="row row-cards mb-4">
    <div class="col-md-4">
        <div class="release-rule">
            <strong>
                <i class="ti ti-download me-1 text-primary"></i>
                Versión más reciente
            </strong>

            <div class="small text-secondary">
                Indica que existe una actualización. Por sí sola,
                permite continuar usando la aplicación.
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="release-rule">
            <strong>
                <i class="ti ti-shield-check me-1 text-green"></i>
                Versión mínima compatible
            </strong>

            <div class="small text-secondary">
                Las instalaciones con un versionCode menor deberán
                actualizar antes de continuar.
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="release-rule">
            <strong>
                <i class="ti ti-alert-triangle me-1 text-orange"></i>
                Forzar actualización
            </strong>

            <div class="small text-secondary">
                Obliga a actualizar cualquier instalación anterior
                a la versión más reciente.
            </div>
        </div>
    </div>
</div>

<div class="row row-cards">
    @foreach (['family', 'staff'] as $appKey)
        @php
            $policy = $policies->get($appKey);
            $meta = $appMetadata[$appKey];
            $bag = $errors->getBag($appKey);

            $isOldForm = old('app_key') === $appKey;

            $latestCode = $isOldForm
                ? old('latest_version_code')
                : $policy->latest_version_code;

            $latestName = $isOldForm
                ? old('latest_version_name')
                : $policy->latest_version_name;

            $minimumCode = $isOldForm
                ? old('minimum_supported_version_code')
                : $policy->minimum_supported_version_code;

            $packageName = $isOldForm
                ? old('package_name')
                : $policy->package_name;

            $forceUpdate = $isOldForm
                ? (bool) old('force_update')
                : (bool) $policy->force_update;

            $title = $isOldForm
                ? old('title')
                : $policy->title;

            $message = $isOldForm
                ? old('message')
                : $policy->message;

            $storeUrl = $isOldForm
                ? old('store_url')
                : $policy->store_url;

            $publishedAt = $isOldForm
                ? old('published_at')
                : optional($policy->published_at)
                    ->format('Y-m-d\TH:i');
        @endphp

        <div class="col-xl-6">
            <form
                method="POST"
                action="{{ route(
                    'sysadmin.mobile-app-versions.update',
                    ['policy' => $appKey]
                ) }}"
                class="card release-card h-100"
                data-release-form="{{ $appKey }}"
            >
                @csrf
                @method('PUT')

                <input
                    type="hidden"
                    name="app_key"
                    value="{{ $appKey }}"
                >

                <div class="card-body release-card__top">
                    <div class="d-flex align-items-start">
                        <div
                            class="release-app-icon bg-{{ $meta['color'] }}-lt text-{{ $meta['color'] }}"
                        >
                            <i class="{{ $meta['icon'] }}"></i>
                        </div>

                        <div class="ms-3 flex-fill">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <h3 class="card-title mb-0">
                                    {{ $meta['name'] }}
                                </h3>

                                <span
                                    class="badge {{
                                        $policy->force_update
                                            ? 'bg-orange-lt text-orange'
                                            : 'bg-green-lt text-green'
                                    }}"
                                    data-status-badge="{{ $appKey }}"
                                >
                                    <span
                                        class="release-status-dot {{
                                            $policy->force_update
                                                ? 'bg-orange'
                                                : 'bg-green'
                                        }}"
                                    ></span>

                                    <span data-status-text="{{ $appKey }}">
                                        {{
                                            $policy->force_update
                                                ? 'Actualización obligatoria'
                                                : 'Publicación normal'
                                        }}
                                    </span>
                                </span>
                            </div>

                            <div class="text-secondary mt-1">
                                {{ $meta['description'] }}
                            </div>

                            <div class="small mt-2 release-code">
                                {{ $policy->package_name }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    @if ($bag->any())
                        <div class="alert alert-danger">
                            <div class="fw-semibold mb-1">
                                Revisa la configuración:
                            </div>

                            <ul class="mb-0 ps-3">
                                @foreach ($bag->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label required">
                                Paquete Android
                            </label>

                            <input
                                type="text"
                                name="package_name"
                                value="{{ $packageName }}"
                                class="form-control {{
                                    $bag->has('package_name')
                                        ? 'is-invalid'
                                        : ''
                                }}"
                                readonly
                            >

                            <div class="form-hint">
                                Identificador fijo publicado en Google Play.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">
                                VersionCode más reciente
                            </label>

                            <input
                                type="number"
                                min="1"
                                step="1"
                                name="latest_version_code"
                                value="{{ $latestCode }}"
                                class="form-control {{
                                    $bag->has('latest_version_code')
                                        ? 'is-invalid'
                                        : ''
                                }}"
                                data-latest-code="{{ $appKey }}"
                                required
                            >

                            <div class="form-hint">
                                Debe coincidir con
                                <code>versionCode</code> del APK publicado.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">
                                Nombre visible
                            </label>

                            <input
                                type="text"
                                name="latest_version_name"
                                value="{{ $latestName }}"
                                class="form-control {{
                                    $bag->has('latest_version_name')
                                        ? 'is-invalid'
                                        : ''
                                }}"
                                placeholder="1.0.1"
                                required
                            >

                            <div class="form-hint">
                                Valor visible de
                                <code>versionName</code>.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">
                                Versión mínima compatible
                            </label>

                            <input
                                type="number"
                                min="1"
                                step="1"
                                name="minimum_supported_version_code"
                                value="{{ $minimumCode }}"
                                class="form-control {{
                                    $bag->has(
                                        'minimum_supported_version_code'
                                    )
                                        ? 'is-invalid'
                                        : ''
                                }}"
                                data-minimum-code="{{ $appKey }}"
                                required
                            >

                            <div class="form-hint">
                                VersionCode menor a este valor no podrá
                                continuar.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Fecha de publicación
                            </label>

                            <input
                                type="datetime-local"
                                name="published_at"
                                value="{{ $publishedAt }}"
                                class="form-control {{
                                    $bag->has('published_at')
                                        ? 'is-invalid'
                                        : ''
                                }}"
                            >

                            <div class="form-hint">
                                Referencia administrativa.
                            </div>
                        </div>

                        <div class="col-12">
                            <input
                                type="hidden"
                                name="force_update"
                                value="0"
                            >

                            <label
                                class="form-check form-switch p-3 border rounded-3"
                            >
                                <input
                                    type="checkbox"
                                    name="force_update"
                                    value="1"
                                    class="form-check-input"
                                    data-force-update="{{ $appKey }}"
                                    @checked($forceUpdate)
                                >

                                <span class="form-check-label">
                                    <span class="fw-semibold d-block">
                                        Forzar actualización
                                    </span>

                                    <span class="small text-secondary">
                                        La aplicación mostrará el sheet sin
                                        opción de cerrarlo mientras la versión
                                        instalada sea menor a la más reciente.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                Título del aviso
                            </label>

                            <input
                                type="text"
                                name="title"
                                maxlength="120"
                                value="{{ $title }}"
                                class="form-control {{
                                    $bag->has('title')
                                        ? 'is-invalid'
                                        : ''
                                }}"
                                placeholder="Nueva versión disponible"
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                Mensaje para el usuario
                            </label>

                            <textarea
                                name="message"
                                rows="3"
                                maxlength="1000"
                                class="form-control {{
                                    $bag->has('message')
                                        ? 'is-invalid'
                                        : ''
                                }}"
                                placeholder="Describe brevemente las mejoras."
                            >{{ $message }}</textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                URL de Google Play
                            </label>

                            <input
                                type="url"
                                name="store_url"
                                value="{{ $storeUrl }}"
                                class="form-control {{
                                    $bag->has('store_url')
                                        ? 'is-invalid'
                                        : ''
                                }}"
                                placeholder="https://play.google.com/store/apps/details?id={{ $packageName }}"
                            >

                            <div class="form-hint">
                                Puede dejarse vacío mientras la app todavía
                                no esté publicada.
                            </div>
                        </div>
                    </div>

                    <div class="release-preview mt-4">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold">
                                    Resultado actual
                                </div>

                                <div
                                    class="small text-secondary mt-1"
                                    data-policy-description="{{ $appKey }}"
                                >
                                    Cargando regla…
                                </div>
                            </div>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-orange"
                                data-require-current="{{ $appKey }}"
                            >
                                <i class="ti ti-lock me-1"></i>
                                Exigir versión actual
                            </button>
                        </div>

                        <div class="release-code text-secondary mt-3">
                            GET
                            <a
                                href="{{ url(
                                    '/api/v1/app/version?app='.$appKey
                                ) }}"
                                target="_blank"
                                rel="noopener"
                            >
                                /api/v1/app/version?app={{ $appKey }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div class="small text-secondary">
                            Actualizado:
                            {{
                                optional($policy->updated_at)
                                    ->format('d/m/Y H:i')
                            }}
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="ti ti-device-floppy me-2"></i>
                            Guardar {{ $meta['name'] }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endforeach
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    ['family', 'staff'].forEach(function (appKey) {
        const latestInput = document.querySelector(
            '[data-latest-code="' + appKey + '"]'
        );

        const minimumInput = document.querySelector(
            '[data-minimum-code="' + appKey + '"]'
        );

        const forceInput = document.querySelector(
            '[data-force-update="' + appKey + '"]'
        );

        const description = document.querySelector(
            '[data-policy-description="' + appKey + '"]'
        );

        const badge = document.querySelector(
            '[data-status-badge="' + appKey + '"]'
        );

        const statusText = document.querySelector(
            '[data-status-text="' + appKey + '"]'
        );

        const requireButton = document.querySelector(
            '[data-require-current="' + appKey + '"]'
        );

        function refreshPreview() {
            const latest = parseInt(
                latestInput?.value || '0',
                10
            );

            const minimum = parseInt(
                minimumInput?.value || '0',
                10
            );

            const forced = Boolean(
                forceInput?.checked
            );

            if (!description) {
                return;
            }

            if (forced) {
                description.textContent =
                    'Toda instalación con versionCode menor a ' +
                    latest +
                    ' deberá actualizar antes de continuar.';

                badge?.classList.remove(
                    'bg-green-lt',
                    'text-green'
                );

                badge?.classList.add(
                    'bg-orange-lt',
                    'text-orange'
                );

                if (statusText) {
                    statusText.textContent =
                        'Actualización obligatoria';
                }

                return;
            }

            if (minimum === latest) {
                description.textContent =
                    'Las versiones menores a ' +
                    minimum +
                    ' deberán actualizar. La versión actual permanece operativa.';

                badge?.classList.remove(
                    'bg-green-lt',
                    'text-green'
                );

                badge?.classList.add(
                    'bg-orange-lt',
                    'text-orange'
                );

                if (statusText) {
                    statusText.textContent =
                        'Compatibilidad restringida';
                }

                return;
            }

            description.textContent =
                'La versión ' +
                latest +
                ' se ofrecerá como actualización opcional. ' +
                'Las versiones desde ' +
                minimum +
                ' seguirán funcionando.';

            badge?.classList.remove(
                'bg-orange-lt',
                'text-orange'
            );

            badge?.classList.add(
                'bg-green-lt',
                'text-green'
            );

            if (statusText) {
                statusText.textContent =
                    'Publicación normal';
            }
        }

        latestInput?.addEventListener(
            'input',
            refreshPreview
        );

        minimumInput?.addEventListener(
            'input',
            refreshPreview
        );

        forceInput?.addEventListener(
            'change',
            refreshPreview
        );

        requireButton?.addEventListener(
            'click',
            function () {
                if (
                    latestInput &&
                    minimumInput &&
                    forceInput
                ) {
                    minimumInput.value =
                        latestInput.value;

                    forceInput.checked = true;

                    refreshPreview();

                    forceInput.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }
        );

        refreshPreview();
    });
});
</script>
@endsection