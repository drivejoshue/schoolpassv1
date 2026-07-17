@php
    $role = auth()->user()->role ?? null;

    $isAdministrativeRole = in_array(
        $role,
        [
            'superadmin',
            'school_admin',
            'director',
        ],
        true
    );

    $sectionLabelClass =
        'px-3 pt-3 pb-1 text-uppercase text-secondary fw-bold';

    $sectionLabelStyle =
        'font-size: .67rem; letter-spacing: .08em;';
@endphp

<aside
    class="navbar navbar-vertical navbar-expand-lg sp-sidebar"
    data-bs-theme="dark"
>
    <div class="container-fluid">
        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu"
            aria-expanded="false"
            aria-label="Abrir menú"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <h1 class="navbar-brand navbar-brand-autodark">
            <a
                href="{{ route('home') }}"
                class="text-decoration-none"
            >
                <span class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm bg-primary text-white">
                        <i class="ti ti-shield-lock"></i>
                    </span>

                    <span>
                        SchoolPass
                    </span>
                </span>
            </a>
        </h1>

        <div
            class="collapse navbar-collapse"
            id="sidebar-menu"
        >
            <ul class="navbar-nav pt-lg-3">

                {{-- ===================================================== --}}
                {{-- ADMINISTRACIÓN, DIRECCIÓN Y SUPERADMIN                --}}
                {{-- ===================================================== --}}

                @if($isAdministrativeRole)

                    {{-- INICIO --}}
                    <li
                        class="{{ $sectionLabelClass }}"
                        style="{{ $sectionLabelStyle }}"
                    >
                        Inicio
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.dashboard'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.dashboard'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-layout-dashboard"></i>
                            </span>

                            <span class="nav-link-title">
                                Dashboard
                            </span>
                        </a>
                    </li>

                   {{-- OPERACIÓN DIARIA --}}
<li
    class="{{ $sectionLabelClass }}"
    style="{{ $sectionLabelStyle }}"
>
    Operación diaria
</li>

<li class="nav-item">
    <a
        class="nav-link {{
            request()->routeIs('prefect.access')
                ? 'active'
                : ''
        }}"
        href="{{ route('prefect.access') }}"
    >
        <span class="nav-link-icon d-md-none d-lg-inline-block">
            <i class="ti ti-scan"></i>
        </span>

        <span class="nav-link-title">
            Escáner de prefectura
        </span>
    </a>
</li>

<li class="nav-item">
    <a
        class="nav-link {{
            request()->routeIs('kiosk.access')
                ? 'active'
                : ''
        }}"
        href="{{ route('kiosk.access') }}"
    >
        <span class="nav-link-icon d-md-none d-lg-inline-block">
            <i class="ti ti-device-desktop"></i>
        </span>

        <span class="nav-link-title">
            Kiosco de acceso
        </span>
    </a>
</li>

@if(
    \Illuminate\Support\Facades\Route::has(
        'admin.reports.attendance'
    )
)
    <li class="nav-item">
        <a
            class="nav-link {{
                request()->routeIs(
                    'admin.reports.attendance'
                )
                    ? 'active'
                    : ''
            }}"
            href="{{ route(
                'admin.reports.attendance'
            ) }}"
        >
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="ti ti-calendar-check"></i>
            </span>

            <span class="nav-link-title">
                Asistencia diaria
            </span>
        </a>
    </li>
@endif

@if(
    \Illuminate\Support\Facades\Route::has(
        'admin.direction-live.index'
    )
)
    <li class="nav-item">
        <a
            class="nav-link {{
                request()->routeIs(
                    'admin.direction-live.*'
                )
                    ? 'active'
                    : ''
            }}"
            href="{{ route(
                'admin.direction-live.index'
            ) }}"
        >
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="ti ti-device-desktop-analytics"></i>
            </span>

            <span class="nav-link-title">
                Pantalla en vivo
            </span>
        </a>
    </li>
@endif

{{-- ALUMNOS Y FAMILIAS --}}
<li
    class="{{ $sectionLabelClass }}"
    style="{{ $sectionLabelStyle }}"
>
    Alumnos y familias
</li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.students.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.students.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-users"></i>
                            </span>

                            <span class="nav-link-title">
                                Alumnos
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.guardians.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.guardians.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-user-heart"></i>
                            </span>

                            <span class="nav-link-title">
                                Tutores
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.credentials.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.credentials.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-id-badge-2"></i>
                            </span>

                            <span class="nav-link-title">
                                Credenciales
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.notices.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.notices.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-speakerphone"></i>
                            </span>

                            <span class="nav-link-title">
                                Avisos escolares
                            </span>
                        </a>
                    </li>

                    {{-- CICLO Y ORGANIZACIÓN ESCOLAR --}}
                    <li
                        class="{{ $sectionLabelClass }}"
                        style="{{ $sectionLabelStyle }}"
                    >
                        Ciclo y organización
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.cycles.*'
                                )
                                || request()->routeIs(
                                    'admin.cycle-enrollments.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.cycles.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-calendar-stats"></i>
                            </span>

                            <span class="nav-link-title">
                                Ciclos escolares
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.groups.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.groups.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-users-group"></i>
                            </span>

                            <span class="nav-link-title">
                                Grupos y horarios
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.calendar.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.calendar.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-calendar-event"></i>
                            </span>

                            <span class="nav-link-title">
                                Calendario escolar
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.promotions.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.promotions.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-arrow-big-up-lines"></i>
                            </span>

                            <span class="nav-link-title">
                                Promoción y reinscripción
                            </span>
                        </a>
                    </li>

                    {{-- CONTROL DE ACCESO --}}
                    <li
                        class="{{ $sectionLabelClass }}"
                        style="{{ $sectionLabelStyle }}"
                    >
                        Control de acceso
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.areas.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.areas.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-map-pin"></i>
                            </span>

                            <span class="nav-link-title">
                                Áreas
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.area-rules.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.area-rules.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-shield-check"></i>
                            </span>

                            <span class="nav-link-title">
                                Reglas de acceso
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.devices.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.devices.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-device-tablet"></i>
                            </span>

                            <span class="nav-link-title">
                                Dispositivos
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
    <a
        class="nav-link {{
            request()->routeIs('admin.users.*')
                ? 'active'
                : ''
        }}"
        href="{{ route('admin.users.index') }}"
    >
        <span class="nav-link-icon d-md-none d-lg-inline-block">
            <i class="ti ti-users-cog"></i>
        </span>

        <span class="nav-link-title">
            Usuarios del sistema
        </span>
    </a>
</li>

                    @if(
                        \Illuminate\Support\Facades\Route::has(
                            'admin.reports.access'
                        )
                    )
                        <li class="nav-item">
                            <a
                                class="nav-link {{
                                    request()->routeIs(
                                        'admin.reports.access*'
                                    )
                                        ? 'active'
                                        : ''
                                }}"
                                href="{{ route(
                                    'admin.reports.access'
                                ) }}"
                            >
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="ti ti-door-enter"></i>
                                </span>

                                <span class="nav-link-title">
                                    Bitácora de accesos
                                </span>
                            </a>
                        </li>
                    @endif

                    {{-- REPORTES --}}
                    <li
                        class="{{ $sectionLabelClass }}"
                        style="{{ $sectionLabelStyle }}"
                    >
                        Reportes y seguimiento
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.reports.monthly-attendance.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.reports.monthly-attendance.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-calendar-month"></i>
                            </span>

                            <span class="nav-link-title">
                                Asistencia mensual
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.reports.student-individual.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.reports.student-individual.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-user-search"></i>
                            </span>

                            <span class="nav-link-title">
                                Reporte individual
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.reports.student-incidents.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.reports.student-incidents.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-alert-triangle"></i>
                            </span>

                            <span class="nav-link-title">
                                Incidencias
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.reports.analytics.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.reports.analytics.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-chart-histogram"></i>
                            </span>

                            <span class="nav-link-title">
                                Analítica
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.reports.exports.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.reports.exports.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-file-export"></i>
                            </span>

                            <span class="nav-link-title">
                                Exportaciones
                            </span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.reports.export-audit.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.reports.export-audit.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-file-check"></i>
                            </span>

                            <span class="nav-link-title">
                                Auditoría de reportes
                            </span>
                        </a>
                    </li>

                    {{-- CONFIGURACIÓN --}}
                    <li
                        class="{{ $sectionLabelClass }}"
                        style="{{ $sectionLabelStyle }}"
                    >
                        Administración
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'admin.tools.*'
                                )
                                || request()->routeIs(
                                    'admin.imports.*'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'admin.tools.index'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-tool"></i>
                            </span>

                            <span class="nav-link-title">
                                Configuración y herramientas
                            </span>
                        </a>
                    </li>
                @endif

                {{-- ===================================================== --}}
                {{-- PREFECTURA                                           --}}
                {{-- ===================================================== --}}

                @if($role === 'prefect')
                    <li
                        class="{{ $sectionLabelClass }}"
                        style="{{ $sectionLabelStyle }}"
                    >
                        Operación
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'prefect.access'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'prefect.access'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-scan"></i>
                            </span>

                            <span class="nav-link-title">
                                Control de acceso
                            </span>
                        </a>
                    </li>
                @endif

                {{-- ===================================================== --}}
                {{-- TUTOR                                                --}}
                {{-- ===================================================== --}}

                @if($role === 'guardian')
                    <li
                        class="{{ $sectionLabelClass }}"
                        style="{{ $sectionLabelStyle }}"
                    >
                        Familia
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'guardian.home'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'guardian.home'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-user-heart"></i>
                            </span>

                            <span class="nav-link-title">
                                Mis hijos
                            </span>
                        </a>
                    </li>
                @endif

                {{-- ===================================================== --}}
                {{-- ALUMNO                                               --}}
                {{-- ===================================================== --}}

                @if($role === 'student')
                    <li
                        class="{{ $sectionLabelClass }}"
                        style="{{ $sectionLabelStyle }}"
                    >
                        Alumno
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'student.home'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'student.home'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-id-badge"></i>
                            </span>

                            <span class="nav-link-title">
                                Mi credencial
                            </span>
                        </a>
                    </li>
                @endif

                {{-- ===================================================== --}}
                {{-- KIOSCO                                               --}}
                {{-- ===================================================== --}}

                @if($role === 'kiosk')
                    <li
                        class="{{ $sectionLabelClass }}"
                        style="{{ $sectionLabelStyle }}"
                    >
                        Kiosco
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{
                                request()->routeIs(
                                    'kiosk.access'
                                )
                                    ? 'active'
                                    : ''
                            }}"
                            href="{{ route(
                                'kiosk.access'
                            ) }}"
                        >
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-device-desktop"></i>
                            </span>

                            <span class="nav-link-title">
                                Punto de acceso
                            </span>
                        </a>
                    </li>
                @endif

            </ul>
        </div>
    </div>
</aside>