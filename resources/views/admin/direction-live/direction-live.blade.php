@extends('layouts.app')

@section('title', 'Pantalla en vivo | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Pantalla en vivo')

@section('content')
    @php
        $logoUrl = $school->logo_path
            ? asset('storage/'.$school->logo_path)
            : null;
    @endphp

    <style>
        body { overflow-x: hidden; }
        .navbar-vertical, .page-header, footer, .footer { display: none !important; }
        .page-wrapper { margin-left: 0 !important; }
        .page-body { margin-top: 0 !important; min-height: 100vh; }
        .container-xl { max-width: 100% !important; padding: 0 !important; }

        .direction-live {
            min-height: 100vh;
            padding: 20px;
            color: #f8fafc;
            background:
                radial-gradient(circle at top right, rgba(32, 107, 196, .18), transparent 32%),
                #0f172a;
        }

        .live-panel {
            background: rgba(30, 41, 59, .94);
            border: 1px solid rgba(148, 163, 184, .18);
            box-shadow: 0 14px 35px rgba(0, 0, 0, .22);
            border-radius: 18px;
        }

        .live-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 22px;
        }

        .brand-wrap { display: flex; align-items: center; gap: 14px; }
        .school-logo, .school-logo-placeholder {
            width: 58px;
            height: 58px;
            border-radius: 14px;
        }
        .school-logo { object-fit: cover; background: #fff; }
        .school-logo-placeholder {
            display: grid;
            place-items: center;
            background: #206bc4;
            font-size: 26px;
        }

        .live-clock { text-align: right; }
        .clock-time {
            font-size: clamp(2rem, 4vw, 3.6rem);
            line-height: 1;
            font-weight: 800;
            letter-spacing: -.05em;
        }
        .clock-date { color: #cbd5e1; margin-top: 4px; }
        .live-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 10px;
        }

        .connection-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 11px;
            border-radius: 999px;
            background: rgba(100, 116, 139, .25);
            font-size: .8rem;
        }
        .connection-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #f59e0b;
        }
        .connection-pill.is-online .connection-dot {
            background: #22c55e;
            box-shadow: 0 0 12px rgba(34, 197, 94, .8);
        }
        .connection-pill.is-offline .connection-dot { background: #ef4444; }

        .filter-panel { padding: 14px 18px; }
        .direction-live .form-label { color: #cbd5e1; }
        .direction-live .form-select {
            background-color: #172033;
            color: #f8fafc;
            border-color: rgba(148, 163, 184, .3);
        }

        .live-stat {
            min-height: 132px;
            padding: 18px;
            position: relative;
            overflow: hidden;
        }
        .live-stat::after {
            content: '';
            position: absolute;
            right: -28px;
            bottom: -38px;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .045);
        }
        .stat-label { color: #cbd5e1; font-weight: 600; }
        .stat-value {
            margin-top: 8px;
            font-size: clamp(2.2rem, 5vw, 4rem);
            line-height: .95;
            font-weight: 800;
            letter-spacing: -.05em;
        }
        .stat-detail { color: #94a3b8; margin-top: 10px; font-size: .85rem; }

        .groups-panel, .activity-panel { min-height: 480px; }
        .panel-title {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(148, 163, 184, .15);
        }
        .groups-grid {
            padding: 14px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(245px, 1fr));
            gap: 12px;
            max-height: 570px;
            overflow: auto;
        }
        .group-card {
            padding: 14px;
            border-radius: 14px;
            background: rgba(15, 23, 42, .72);
            border: 1px solid rgba(148, 163, 184, .14);
        }
        .group-rate { font-size: 1.45rem; font-weight: 800; }
        .group-meta { color: #94a3b8; font-size: .78rem; }
        .group-counters {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 7px;
            margin-top: 12px;
        }
        .group-counter {
            padding: 7px;
            border-radius: 9px;
            background: rgba(51, 65, 85, .55);
            text-align: center;
        }
        .group-counter strong { display: block; font-size: 1.08rem; }
        .group-counter span { color: #94a3b8; font-size: .68rem; }

        .activity-list { max-height: 570px; overflow: auto; }
        .activity-item {
            display: grid;
            grid-template-columns: 52px 1fr auto;
            gap: 12px;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .12);
        }
        .activity-avatar {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            object-fit: cover;
            display: grid;
            place-items: center;
            background: #334155;
            font-weight: 800;
        }
        .activity-name { font-weight: 700; }
        .activity-meta { color: #94a3b8; font-size: .78rem; }
        .activity-time { font-size: 1.06rem; font-weight: 800; text-align: right; }
        .activity-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 700;
        }
        .badge-ok { color: #bbf7d0; background: rgba(34, 197, 94, .18); }
        .badge-warn { color: #fde68a; background: rgba(245, 158, 11, .18); }
        .badge-error { color: #fecaca; background: rgba(239, 68, 68, .18); }
        .empty-live { padding: 50px 20px; color: #94a3b8; text-align: center; }
        .cycle-alert {
            display: none;
            padding: 12px 16px;
            margin-top: 14px;
            border-radius: 12px;
            background: rgba(245, 158, 11, .16);
            border: 1px solid rgba(245, 158, 11, .28);
            color: #fde68a;
        }

        @media (max-width: 991px) {
            .live-header { align-items: flex-start; flex-direction: column; }
            .live-clock { text-align: left; }
            .live-actions { justify-content: flex-start; }
        }
    </style>

    <div
        id="direction-live-root"
        class="direction-live"
        data-endpoint="{{ route('admin.direction-live.data') }}"
        data-timezone="{{ $school->timezone ?: config('app.timezone') }}"
    >
        <header class="live-panel live-header">
            <div class="brand-wrap">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $school->name }}" class="school-logo">
                @else
                    <div class="school-logo-placeholder"><i class="ti ti-school"></i></div>
                @endif

                <div>
                    <div class="text-uppercase text-secondary small">SchoolPass · Dirección</div>
                    <h1 class="m-0">{{ $school->name }}</h1>
                    <div class="text-secondary">Resumen operativo en tiempo real</div>
                </div>
            </div>

            <div class="live-clock">
                <div id="live-clock-time" class="clock-time">--:--:--</div>
                <div id="live-clock-date" class="clock-date">Cargando fecha…</div>

                <div class="live-actions">
                    <div id="live-connection" class="connection-pill">
                        <span class="connection-dot"></span>
                        <span id="live-connection-label">Conectando…</span>
                    </div>

                    <button type="button" id="live-fullscreen" class="btn btn-outline-light btn-sm">
                        <i class="ti ti-maximize me-1"></i>
                        Pantalla completa
                    </button>

                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-light btn-sm">
                        <i class="ti ti-arrow-left me-1"></i>
                        Dashboard
                    </a>
                </div>
            </div>
        </header>

        <div id="live-cycle-alert" class="cycle-alert"></div>

        <section class="live-panel filter-panel mt-3">
            <form method="GET" action="{{ route('admin.direction-live.index') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Plantel</label>
                        <select name="campus_id" class="form-select">
                            <option value="">Todos los planteles</option>
                            @foreach($campuses as $campus)
                                <option value="{{ $campus->id }}" @selected((string) $filters['campus_id'] === (string) $campus->id)>
                                    {{ $campus->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Nivel</label>
                        <select name="level_id" class="form-select">
                            <option value="">Todos los niveles</option>
                            @foreach($levels as $level)
                                <option value="{{ $level->id }}" @selected((string) $filters['level_id'] === (string) $level->id)>
                                    {{ $level->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Grupo</label>
                        <select name="group_id" class="form-select">
                            <option value="">Todos los grupos</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" @selected((string) $filters['group_id'] === (string) $group->id)>
                                    {{ $group->campus_name }} · {{ $group->level_name }} · {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 d-grid">
                        <button class="btn btn-primary">
                            <i class="ti ti-filter me-1"></i>
                            Aplicar
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section class="row g-3 mt-0">
            @foreach([
                ['total', 'Inscritos', 'ti-users', 'text-primary'],
                ['present', 'Presentes', 'ti-user-check', 'text-success'],
                ['on_time', 'Puntuales', 'ti-clock-check', 'text-success'],
                ['late_total', 'Retardos', 'ti-clock-exclamation', 'text-warning'],
                ['absent', 'Ausentes', 'ti-user-x', 'text-danger'],
                ['exited', 'Con salida', 'ti-logout-2', 'text-info'],
                ['early_exit', 'Anticipadas', 'ti-walk', 'text-warning'],
                ['attendance_rate', 'Asistencia', 'ti-chart-donut', 'text-primary'],
            ] as [$key, $label, $icon, $color])
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="live-panel live-stat">
                        <div class="d-flex justify-content-between">
                            <div class="stat-label">{{ $label }}</div>
                            <i class="ti {{ $icon }} {{ $color }} fs-2"></i>
                        </div>

                        <div id="live-stat-{{ $key }}" class="stat-value">—</div>
                        <div id="live-stat-detail-{{ $key }}" class="stat-detail">Cargando…</div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="row g-3 mt-0">
            <div class="col-xl-7">
                <div class="live-panel groups-panel">
                    <div class="panel-title">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h2 class="m-0">Resumen por grupos</h2>
                                <div class="text-secondary">Asistencia y salidas del día</div>
                            </div>
                            <div id="live-group-count" class="text-secondary">—</div>
                        </div>
                    </div>

                    <div id="live-groups" class="groups-grid">
                        <div class="empty-live">Cargando grupos…</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="live-panel activity-panel">
                    <div class="panel-title">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h2 class="m-0">Actividad reciente</h2>
                                <div class="text-secondary">Entradas, salidas e incidencias</div>
                            </div>
                            <div id="live-last-update" class="text-secondary small">—</div>
                        </div>
                    </div>

                    <div id="live-activity" class="activity-list">
                        <div class="empty-live">Cargando actividad…</div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const root = document.getElementById('direction-live-root');
            if (!root) return;

            const endpoint = new URL(root.dataset.endpoint, window.location.origin);
            const currentQuery = new URLSearchParams(window.location.search);
            for (const [key, value] of currentQuery.entries()) endpoint.searchParams.set(key, value);

            let timezone = root.dataset.timezone;
            let requestRunning = false;

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const statusLabel = (status, decision) => {
                const labels = {
                    on_time: 'Puntual',
                    late: 'Retardo',
                    very_late: 'Muy tarde',
                    early_exit: 'Salida anticipada',
                    normal_exit: 'Salida normal',
                    duplicate: 'Duplicado',
                    guardian_required: 'Tutor requerido',
                    student_not_enrolled: 'Sin inscripción',
                    cycle_not_started: 'Ciclo no iniciado',
                    manual: 'Manual',
                };

                return labels[status]
                    ?? (decision === 'denied'
                        ? 'Denegado'
                        : String(status ?? 'Evento').replaceAll('_', ' '));
            };

            const sourceLabel = (source) => ({
                qr: 'QR alumno',
                guardian_qr: 'QR tutor',
                manual: 'Manual',
                kiosk: 'Kiosco',
                nfc: 'NFC',
            })[source] ?? String(source ?? 'Acceso').replaceAll('_', ' ');

            const badgeClass = (item) => {
                if (item.decision === 'denied' || item.event_status === 'guardian_required') return 'badge-error';
                if (['late', 'very_late', 'early_exit', 'duplicate'].includes(item.event_status)) return 'badge-warn';
                return 'badge-ok';
            };

            const setConnection = (state, label) => {
                const pill = document.getElementById('live-connection');
                pill.classList.remove('is-online', 'is-offline');
                if (state) pill.classList.add(state);
                document.getElementById('live-connection-label').textContent = label;
            };

            const setStat = (key, value, detail = '') => {
                document.getElementById(`live-stat-${key}`).textContent = value;
                document.getElementById(`live-stat-detail-${key}`).textContent = detail;
            };

            const renderSummary = (summary) => {
                const lateTotal = Number(summary.late ?? 0) + Number(summary.very_late ?? 0);
                setStat('total', summary.total, `${summary.pending} pendientes`);
                setStat('present', summary.present, `${summary.exited} ya tienen salida`);
                setStat('on_time', summary.on_time, 'Entradas dentro del horario');
                setStat('late_total', lateTotal, `${summary.late} retardo · ${summary.very_late} muy tarde`);
                setStat('absent', summary.absent, `${summary.no_class} sin clase`);
                setStat('exited', summary.exited, 'Salidas registradas');
                setStat('early_exit', summary.early_exit, 'Antes del horario regular');
                setStat(
                    'attendance_rate',
                    `${Number(summary.attendance_rate).toFixed(1)}%`,
                    `${summary.online_devices}/${summary.active_devices} dispositivos en línea`
                );
            };

            const renderGroups = (groups) => {
                const container = document.getElementById('live-groups');
                document.getElementById('live-group-count').textContent = `${groups.length} grupo(s)`;

                if (!groups.length) {
                    container.innerHTML = '<div class="empty-live">No hay grupos para mostrar.</div>';
                    return;
                }

                container.innerHTML = groups.map((group) => {
                    const rate = Number(group.attendance_rate ?? 0);
                    const rateClass = rate >= 80 ? 'text-success' : rate >= 60 ? 'text-warning' : 'text-danger';

                    return `
                        <article class="group-card">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold fs-3">${escapeHtml(group.name)}</div>
                                    <div class="group-meta">${escapeHtml(group.level)} · ${escapeHtml(group.campus)}</div>
                                </div>
                                <div class="group-rate ${rateClass}">${rate.toFixed(1)}%</div>
                            </div>

                            <div class="group-counters">
                                <div class="group-counter"><strong>${group.present}/${group.total}</strong><span>Presentes</span></div>
                                <div class="group-counter"><strong>${group.late + group.very_late}</strong><span>Retardos</span></div>
                                <div class="group-counter"><strong>${group.absent}</strong><span>Ausentes</span></div>
                                <div class="group-counter"><strong>${group.exited}</strong><span>Salidas</span></div>
                            </div>
                        </article>`;
                }).join('');
            };

            const renderActivity = (activity) => {
                const container = document.getElementById('live-activity');
                if (!activity.length) {
                    container.innerHTML = '<div class="empty-live">No hay movimientos registrados hoy.</div>';
                    return;
                }

                container.innerHTML = activity.map((item) => {
                    const initial = escapeHtml(item.student_name.charAt(0).toUpperCase());
                    const avatar = item.photo_url
                        ? `<img src="${escapeHtml(item.photo_url)}" alt="" class="activity-avatar">`
                        : `<div class="activity-avatar">${initial}</div>`;
                    const guardian = item.guardian_name
                        ? ` · Tutor: ${escapeHtml(item.guardian_name)}`
                        : '';

                    return `
                        <article class="activity-item">
                            ${avatar}
                            <div>
                                <div class="activity-name">${escapeHtml(item.student_name)}</div>
                                <div class="activity-meta">${escapeHtml(item.student_code)} · ${escapeHtml(item.group_name)}</div>
                                <div class="activity-meta">${escapeHtml(sourceLabel(item.source))} · ${escapeHtml(item.device_name)}${guardian}</div>
                                <span class="activity-badge ${badgeClass(item)}">${escapeHtml(statusLabel(item.event_status, item.decision))}</span>
                            </div>
                            <div class="activity-time">${escapeHtml(item.time)}</div>
                        </article>`;
                }).join('');
            };

            const renderCycle = (cycle) => {
                const alert = document.getElementById('live-cycle-alert');
                let message = '';

                if (!cycle.exists) message = 'No existe un ciclo escolar activo.';
                else if (!cycle.inside_cycle) message = 'La fecha actual está fuera de la vigencia del ciclo.';
                else if (cycle.no_class_day) message = cycle.calendar_title
                    ? `Hoy está marcado como: ${cycle.calendar_title}.`
                    : 'Hoy está marcado como día sin clase.';

                alert.textContent = message;
                alert.style.display = message ? 'block' : 'none';
            };

            const updateClock = () => {
                const current = new Date();
                document.getElementById('live-clock-time').textContent = new Intl.DateTimeFormat('es-MX', {
                    timeZone: timezone,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                }).format(current);

                const dateText = new Intl.DateTimeFormat('es-MX', {
                    timeZone: timezone,
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                }).format(current);

                document.getElementById('live-clock-date').textContent = dateText.charAt(0).toUpperCase() + dateText.slice(1);
            };

            const loadData = async () => {
                if (requestRunning) return;
                requestRunning = true;
                setConnection('', 'Actualizando…');

                try {
                    const response = await fetch(endpoint.toString(), {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    });

                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const data = await response.json();
                    timezone = data.school.timezone ?? timezone;

                    renderSummary(data.summary);
                    renderGroups(data.groups);
                    renderActivity(data.activity);
                    renderCycle(data.cycle);
                    document.getElementById('live-last-update').textContent = `Actualizado ${data.clock.time}`;
                    setConnection('is-online', 'En línea · actualización cada 15 s');
                } catch (error) {
                    console.error('No se pudo actualizar la pantalla en vivo.', error);
                    setConnection('is-offline', 'Sin conexión · reintentando');
                } finally {
                    requestRunning = false;
                }
            };

            document.getElementById('live-fullscreen').addEventListener('click', async () => {
                try {
                    if (!document.fullscreenElement) await document.documentElement.requestFullscreen();
                    else await document.exitFullscreen();
                } catch (error) {
                    console.error('No se pudo cambiar el modo de pantalla completa.', error);
                }
            });

            updateClock();
            window.setInterval(updateClock, 1000);
            loadData();
            window.setInterval(loadData, 15000);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) loadData();
            });
        })();
    </script>
@endsection
