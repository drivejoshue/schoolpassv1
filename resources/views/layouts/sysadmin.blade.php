<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Sysadmin') · SchoolPass</title>

    {{-- Aplica el tema antes de cargar CSS para evitar parpadeo blanco --}}
    @include('partials.theme-bootstrap')

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])

    <style>
        /*
        |--------------------------------------------------------------------------
        | Corrección de superficies del layout Sysadmin
        |--------------------------------------------------------------------------
        | Estas reglas usan clases exclusivas del layout para no depender de
        | selectores genéricos de Tabler ni de otros layouts de SchoolPass.
        */

        html,
        body {
            min-height: 100%;
            background-color: var(--sp-page-bg, var(--tblr-body-bg)) !important;
            color: var(--sp-text, var(--tblr-body-color));
        }

        body {
            margin: 0;
            overflow-x: hidden;
        }

        .sp-page {
            min-height: 100vh;
            background-color: var(--sp-page-bg, var(--tblr-body-bg)) !important;
        }

        .sp-page-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: var(--sp-page-bg, var(--tblr-body-bg)) !important;
        }

        .sp-page-body {
            flex: 1 0 auto;
            padding-top: 1.25rem;
            padding-bottom: 2rem;
            background-color: var(--sp-page-bg, var(--tblr-body-bg)) !important;
        }

        .sp-page-body > .sp-container,
        .sp-container {
            width: 100%;
            max-width: none !important;
            background: transparent !important;
        }

        .sp-sidebar {
            background-color: var(--sp-sidebar-bg, var(--tblr-bg-surface)) !important;
            border-color: var(--sp-border, var(--tblr-border-color)) !important;
        }

        .sp-topbar {
            background-color: var(--sp-topbar-bg, var(--tblr-bg-surface)) !important;
            border-bottom: 1px solid var(--sp-border, var(--tblr-border-color)) !important;
        }

        .sp-footer {
            flex: 0 0 auto;
            background: transparent !important;
            border-top: 1px solid var(--sp-border, var(--tblr-border-color));
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback explícito por tema
        |--------------------------------------------------------------------------
        | Aunque Vite conserve CSS anterior en caché, estas reglas del layout
        | eliminan la superficie blanca del contenido.
        */

        [data-bs-theme="light"] body,
        [data-bs-theme="light"] .sp-page,
        [data-bs-theme="light"] .sp-page-wrapper,
        [data-bs-theme="light"] .sp-page-body {
            background-color: #f4f6fa !important;
        }

        [data-bs-theme="dark"] body,
        [data-bs-theme="dark"] .sp-page,
        [data-bs-theme="dark"] .sp-page-wrapper,
        [data-bs-theme="dark"] .sp-page-body {
            background-color: #101827 !important;
        }

        [data-bs-theme="light"] .sp-sidebar,
        [data-bs-theme="light"] .sp-topbar {
            background-color: #ffffff !important;
        }

        [data-bs-theme="dark"] .sp-sidebar,
        [data-bs-theme="dark"] .sp-topbar {
            background-color: #172234 !important;
        }

        /*
        |--------------------------------------------------------------------------
        | Componentes propios
        |--------------------------------------------------------------------------
        */

        .sp-brand-mark {
            width: 2.25rem;
            height: 2.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: .65rem;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
        }

        .sp-sidebar-section {
            padding: 1rem 1rem .35rem;
            color: var(--sp-muted, var(--tblr-secondary-color));
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .sp-theme-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        [data-bs-theme="light"] .sp-theme-icon-sun,
        [data-bs-theme="dark"] .sp-theme-icon-moon {
            display: none;
        }

        .sp-stat-icon {
            width: 2.75rem;
            height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: .75rem;
            font-size: 1.3rem;
        }

        .sp-definition-list {
            display: grid;
            grid-template-columns: minmax(8rem, 11rem) minmax(0, 1fr);
            gap: .7rem 1rem;
            margin: 0;
        }

        .sp-definition-list dt {
            color: var(--sp-muted, var(--tblr-secondary-color));
            font-weight: 500;
        }

        .sp-definition-list dd {
            margin: 0;
            overflow-wrap: anywhere;
        }

        .sp-json {
            max-width: 34rem;
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            color: inherit;
            font-size: .75rem;
        }

        @media (max-width: 767.98px) {
            .sp-page-body {
                padding-top: 1rem;
            }

            .sp-definition-list {
                grid-template-columns: 1fr;
                gap: .2rem;
            }

            .sp-definition-list dd {
                margin-bottom: .65rem;
            }
        }
    </style>

    @stack('styles')
</head>

<body>
<div class="page sp-page">

    <aside class="navbar navbar-vertical navbar-expand-lg d-print-none sp-sidebar">
        <div class="container-fluid">

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#sysadmin-sidebar"
                aria-controls="sysadmin-sidebar"
                aria-expanded="false"
                aria-label="Mostrar menú"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <h1 class="navbar-brand">
                <a
                    href="{{ route('sysadmin.dashboard') }}"
                    class="text-decoration-none d-flex align-items-center gap-2"
                >
                    <span class="sp-brand-mark">SP</span>

                    <span class="text-start">
                        <span class="d-block fw-bold">SchoolPass</span>
                        <span class="d-block small text-secondary">
                            Administración global
                        </span>
                    </span>
                </a>
            </h1>

            <div class="navbar-nav flex-row d-lg-none">
                <div class="nav-item">
                    @include('partials.theme-toggle')
                </div>
            </div>

            <div class="collapse navbar-collapse" id="sysadmin-sidebar">
                <ul class="navbar-nav pt-lg-3 h-100">

    {{-- =========================================================
         RESUMEN
    ========================================================== --}}

    <li class="nav-item">
        <div class="sp-sidebar-section">
            Resumen
        </div>
    </li>

    <li class="nav-item">
        <a
            href="{{ route('sysadmin.dashboard') }}"
            class="nav-link {{
                request()->routeIs('sysadmin.dashboard')
                    ? 'active'
                    : ''
            }}"
        >
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="ti ti-layout-dashboard"></i>
            </span>

            <span class="nav-link-title">
                Dashboard
            </span>
        </a>
    </li>

    <a
    class="nav-link {{
        request()->routeIs(
            'sysadmin.mobile-app-versions.*'
        )
            ? 'active'
            : ''
    }}"
    href="{{ route(
        'sysadmin.mobile-app-versions.edit'
    ) }}"
>
    <span class="nav-link-icon d-md-none d-lg-inline-block">
        <i class="ti ti-device-mobile-up"></i>
    </span>

    <span class="nav-link-title">
        Versiones móviles
    </span>
</a>

    {{-- =========================================================
         ESCUELAS
    ========================================================== --}}

    <li class="nav-item">
        <div class="sp-sidebar-section">
            Escuelas
        </div>
    </li>

    <li class="nav-item">
        <a
            href="{{ route('sysadmin.schools.index') }}"
            class="nav-link {{
                request()->routeIs('sysadmin.schools.*')
                && ! request()->routeIs(
                    'sysadmin.schools.create'
                )
                    ? 'active'
                    : ''
            }}"
        >
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="ti ti-school"></i>
            </span>

            <span class="nav-link-title">
                Todas las escuelas
            </span>
        </a>
    </li>

    <li class="nav-item">
        <a
            href="{{ route('sysadmin.schools.create') }}"
            class="nav-link {{
                request()->routeIs(
                    'sysadmin.schools.create'
                )
                    ? 'active'
                    : ''
            }}"
        >
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="ti ti-school-plus"></i>
            </span>

            <span class="nav-link-title">
                Nueva escuela
            </span>
        </a>
    </li>

    {{-- =========================================================
         LICENCIAMIENTO
    ========================================================== --}}

    <li class="nav-item">
        <div class="sp-sidebar-section">
            Licenciamiento
        </div>
    </li>

    <li class="nav-item">
        <a
            href="{{ route('sysadmin.plans.index') }}"
            class="nav-link {{
                request()->routeIs('sysadmin.plans.*')
                    ? 'active'
                    : ''
            }}"
        >
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="ti ti-license"></i>
            </span>

            <span class="nav-link-title">
                Planes y licencias
            </span>
        </a>
    </li>

    {{-- =========================================================
         SEGURIDAD
    ========================================================== --}}

    <li class="nav-item">
        <div class="sp-sidebar-section">
            Seguridad
        </div>
    </li>

    <li class="nav-item">
        <a
            href="{{ route(
                'sysadmin.audit-logs.index'
            ) }}"
            class="nav-link {{
                request()->routeIs(
                    'sysadmin.audit-logs.*'
                )
                    ? 'active'
                    : ''
            }}"
        >
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="ti ti-shield-search"></i>
            </span>

            <span class="nav-link-title">
                Auditoría
            </span>
        </a>
    </li>

    {{-- =========================================================
         SESIÓN
    ========================================================== --}}

    <li class="nav-item mt-auto">
        <div class="sp-sidebar-section">
            Sesión
        </div>
    </li>

    <li class="nav-item">
        <form
            method="POST"
            action="{{ route('logout') }}"
        >
            @csrf

            <button
                type="submit"
                class="nav-link border-0 bg-transparent
                       w-100 text-start"
            >
                <span class="nav-link-icon d-md-none d-lg-inline-block">
                    <i class="ti ti-logout"></i>
                </span>

                <span class="nav-link-title">
                    Cerrar sesión
                </span>
            </button>
        </form>
    </li>

</ul>
            </div>
        </div>
    </aside>

    <div class="page-wrapper sp-page-wrapper">

        <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none sp-topbar">
            <div class="container-xl sp-container">

                <div class="navbar-nav flex-row order-md-last align-items-center">

                    <div class="nav-item me-3">
                        @include('partials.theme-toggle')
                    </div>

                    <div class="nav-item dropdown">
                        <a
                            href="#"
                            class="nav-link d-flex lh-1 text-reset p-0"
                            data-bs-toggle="dropdown"
                            aria-label="Abrir menú de usuario"
                        >
                            <span class="avatar avatar-sm bg-primary-lt">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </span>

                            <div class="d-none d-xl-block ps-2">
                                <div>{{ auth()->user()->name }}</div>
                                <div class="mt-1 small text-secondary">
                                    Superadministrador
                                </div>
                            </div>
                        </a>

                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <div class="dropdown-item-text">
                                <div class="fw-semibold">{{ auth()->user()->name }}</div>
                                <div class="small text-secondary">
                                    {{ auth()->user()->email }}
                                </div>
                            </div>

                            <div class="dropdown-divider"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <button type="submit" class="dropdown-item">
                                    <i class="ti ti-logout me-2"></i>
                                    Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>

                </div>

                <div class="navbar-nav">
                    <div class="nav-item">
                        <span class="nav-link px-0 text-secondary">
                            @yield('page_title', 'Sysadmin')
                        </span>
                    </div>
                </div>

            </div>
        </header>

        <main class="page-body sp-page-body">
            <div class="container-xl sp-container">

                @if (session('status'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <div class="d-flex">
                            <div>
                                <i class="ti ti-circle-check me-2"></i>
                            </div>
                            <div>{{ session('status') }}</div>
                        </div>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Cerrar"
                        ></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <div class="d-flex">
                            <div>
                                <i class="ti ti-alert-circle me-2"></i>
                            </div>
                            <div>{{ session('error') }}</div>
                        </div>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Cerrar"
                        ></button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <div class="d-flex">
                            <div>
                                <i class="ti ti-alert-triangle me-2"></i>
                            </div>

                            <div>
                                <h4 class="alert-title">Revisa los datos</h4>

                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                @yield('content')

            </div>
        </main>

        <footer class="footer footer-transparent d-print-none sp-footer">
            <div class="container-xl sp-container">
                <div class="row text-center align-items-center flex-row-reverse">

                    <div class="col-lg-auto ms-lg-auto">
                        <span class="text-secondary">
                            Sysadmin · SchoolPass
                        </span>
                    </div>

                    <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                        <span class="text-secondary">
                            &copy; {{ date('Y') }}
                            {{ config('app.name', 'SchoolPass') }}
                        </span>
                    </div>

                </div>
            </div>
        </footer>

    </div>
</div>

<script>
    (function () {
        const storageKey = 'schoolpass.theme';

        function currentTheme() {
            return document.documentElement.getAttribute('data-bs-theme') || 'light';
        }

        function applyTheme(theme) {
            const safeTheme = theme === 'dark' ? 'dark' : 'light';

            document.documentElement.setAttribute('data-bs-theme', safeTheme);
            localStorage.setItem(storageKey, safeTheme);

            document
                .querySelectorAll('[data-schoolpass-theme-toggle]')
                .forEach(function (button) {
                    const nextTheme = safeTheme === 'dark' ? 'light' : 'dark';

                    button.setAttribute(
                        'aria-label',
                        nextTheme === 'dark'
                            ? 'Activar tema oscuro'
                            : 'Activar tema claro'
                    );

                    button.setAttribute(
                        'title',
                        nextTheme === 'dark'
                            ? 'Activar tema oscuro'
                            : 'Activar tema claro'
                    );
                });
        }

        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-schoolpass-theme-toggle]');

            if (!button) {
                return;
            }

            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        });

        applyTheme(currentTheme());
    })();
</script>

@stack('scripts')
</body>
</html>