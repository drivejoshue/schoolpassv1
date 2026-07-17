<div class="card mb-3">
    <div class="card-body py-2">
        <div class="nav nav-pills flex-column flex-md-row gap-1">
            <a href="{{ route('admin.tools.index') }}"
               class="nav-link {{ request()->routeIs('admin.tools.*') ? 'active' : '' }}">
                <i class="ti ti-tool me-1"></i>
                Resumen
            </a>

            <a href="{{ route('admin.imports.students.index') }}"
               class="nav-link {{ request()->routeIs('admin.imports.students.*') ? 'active' : '' }}">
                <i class="ti ti-file-import me-1"></i>
                Importación
            </a>

            <a href="{{ route('admin.cycles.index') }}"
               class="nav-link {{ request()->routeIs('admin.cycles.*') ? 'active' : '' }}">
                <i class="ti ti-calendar-stats me-1"></i>
                Ciclos
            </a>

            <a href="{{ route('admin.groups.index') }}"
               class="nav-link {{ request()->routeIs('admin.groups.*') ? 'active' : '' }}">
                <i class="ti ti-users-group me-1"></i>
                Grupos y horarios
            </a>

            <a href="{{ route('admin.devices.index') }}"
               class="nav-link {{ request()->routeIs('admin.devices.*') ? 'active' : '' }}">
                <i class="ti ti-device-tablet me-1"></i>
                Dispositivos
            </a>

            <a href="{{ route('admin.areas.index') }}"
               class="nav-link {{ request()->routeIs('admin.areas.*') || request()->routeIs('admin.area-rules.*') ? 'active' : '' }}">
                <i class="ti ti-map-pin-cog me-1"></i>
                Áreas
            </a>

            <a href="{{ route('admin.credentials.index') }}"
               class="nav-link {{ request()->routeIs('admin.credentials.*') ? 'active' : '' }}">
                <i class="ti ti-id-badge-2 me-1"></i>
                Credenciales
            </a>
        </div>
    </div>
</div>
