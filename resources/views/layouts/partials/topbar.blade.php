<header class="navbar navbar-expand-md d-print-none sp-topbar">
    <div class="container-fluid sp-container">
        <div class="d-flex align-items-center">
            <div>
                <div class="text-secondary small">
                    @yield('section-label', 'Sistema')
                </div>

                <h2 class="page-title mb-0">
                    @yield('page-title', 'SchoolPass')
                </h2>
            </div>
        </div>

        <div class="navbar-nav flex-row order-md-last ms-auto align-items-center gap-2">

            @hasSection('topbar-actions')
                <div class="d-none d-md-flex align-items-center gap-2 me-2">
                    @yield('topbar-actions')
                </div>
            @endif

            <a href="#" class="nav-link px-2 disabled" title="Buscar">
                <i class="ti ti-search fs-2"></i>
            </a>

            <a href="#" class="nav-link px-2 disabled" title="Notificaciones">
                <i class="ti ti-bell fs-2"></i>
            </a>

            <a href="#" class="nav-link px-2 disabled" title="Herramientas">
                <i class="ti ti-settings fs-2"></i>
            </a>

            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Abrir menú de usuario">
                    <span class="avatar avatar-sm">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </span>

                    <div class="d-none d-xl-block ps-2">
                        <div>{{ auth()->user()->name ?? 'Usuario' }}</div>
                        <div class="mt-1 small text-secondary">
                            {{ auth()->user()->role ?? '' }}
                        </div>
                    </div>
                </a>

                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <div class="dropdown-header">
                        Sesión
                    </div>

                    <a href="#" class="dropdown-item disabled">
                        <i class="ti ti-user me-2"></i>
                        Perfil
                    </a>

                    <a href="#" class="dropdown-item disabled">
                        <i class="ti ti-building me-2"></i>
                        Institución
                    </a>

                    <div class="dropdown-divider"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="ti ti-logout me-2"></i>
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="d-none d-md-block ms-2">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="ti ti-logout me-1"></i>
                    Salir
                </button>
            </form>
        </div>
    </div>
</header>