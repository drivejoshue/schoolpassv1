@php
    $targetTypes = $targets->pluck('target_type')->unique()->values();

    $selectedScope = old('target_scope');

    if (!$selectedScope) {
        if ($targetTypes->contains('group')) {
            $selectedScope = 'groups';
        } elseif ($targetTypes->contains('student')) {
            $selectedScope = 'students';
        } elseif ($targetTypes->contains('guardian')) {
            $selectedScope = 'guardians';
        } else {
            $selectedScope = 'all_school';
        }
    }

    $selectedGroupIds = old(
        'group_ids',
        $targets->where('target_type', 'group')->pluck('target_id')->map(fn ($v) => (string) $v)->all()
    );

    $selectedStudentIds = old(
        'student_ids',
        $targets->where('target_type', 'student')->pluck('target_id')->map(fn ($v) => (string) $v)->all()
    );

    $selectedGuardianIds = old(
        'guardian_ids',
        $targets->where('target_type', 'guardian')->pluck('target_id')->map(fn ($v) => (string) $v)->all()
    );

    $noticeStatus = old('status', optional($notice)->status ?? 'draft');
    $noticePriority = old('priority', optional($notice)->priority ?? 'normal');

    $bannerPath = optional($notice)->banner_path;
@endphp

@if(session('success'))
    <div class="alert alert-success">
        <i class="ti ti-check me-1"></i>
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Revisa la información del aviso.</div>
        <div>{{ $errors->first() }}</div>
    </div>
@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf

    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm rounded bg-primary-lt text-primary">
                            <i class="ti ti-message-2"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">Contenido del aviso</div>
                            <div class="text-muted small">Texto principal que verá la familia.</div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input
                            type="text"
                            name="title"
                            class="form-control"
                            value="{{ old('title', optional($notice)->title) }}"
                            maxlength="160"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subtítulo</label>
                        <input
                            type="text"
                            name="subtitle"
                            class="form-control"
                            value="{{ old('subtitle', optional($notice)->subtitle) }}"
                            maxlength="220"
                            placeholder="Ej. Aviso importante"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Encabezado interno</label>
                        <input
                            type="text"
                            name="header"
                            class="form-control"
                            value="{{ old('header', optional($notice)->header) }}"
                            maxlength="180"
                            placeholder="Ej. Comunicado escolar"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contenido</label>
                        <textarea
                            name="body"
                            class="form-control"
                            rows="8"
                            required
                        >{{ old('body', optional($notice)->body) }}</textarea>
                    </div>

                    <div>
                        <label class="form-label">Cierre / footer</label>
                        <input
                            type="text"
                            name="footer"
                            class="form-control"
                            value="{{ old('footer', optional($notice)->footer) }}"
                            maxlength="220"
                            placeholder="Ej. Dirección escolar"
                        >
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm rounded bg-blue-lt text-blue">
                            <i class="ti ti-photo"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">Banner</div>
                            <div class="text-muted small">Imagen rectangular para destacar el aviso en la app.</div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Imagen banner</label>
                        <input type="file" name="banner" class="form-control" accept="image/*">
                        <div class="form-hint">
                            Recomendado: 1200 × 480 px. La app lo mostrará en formato rectangular recortado.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Texto alternativo</label>
                        <input
                            type="text"
                            name="banner_alt"
                            class="form-control"
                            value="{{ old('banner_alt', optional($notice)->banner_alt) }}"
                            maxlength="160"
                        >
                    </div>

                    @if($bannerPath)
                        <div class="border rounded-3 p-2 bg-light">
                            <div class="text-muted small mb-2">Banner actual</div>
                            <img
                                src="{{ asset(ltrim($bannerPath, '/')) }}"
                                alt=""
                                class="img-fluid rounded-3"
                                style="max-height: 220px; object-fit: cover; width: 100%;"
                            >
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm rounded bg-cyan-lt text-cyan">
                            <i class="ti ti-users"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">Destinatarios</div>
                            <div class="text-muted small">Define quién recibirá el aviso.</div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Enviar a</label>
                        <select name="target_scope" id="target_scope" class="form-select">
                            <option value="all_school" @selected($selectedScope === 'all_school')>
                                Toda la escuela
                            </option>
                            <option value="groups" @selected($selectedScope === 'groups')>
                                Uno o varios grupos
                            </option>
                            <option value="students" @selected($selectedScope === 'students')>
                                Padres de alumnos específicos
                            </option>
                            <option value="guardians" @selected($selectedScope === 'guardians')>
                                Tutores específicos
                            </option>
                        </select>
                    </div>

                    <div id="target_groups" class="target-box d-none">
                        <label class="form-label">Grupos</label>
                        <select name="group_ids[]" class="form-select" multiple size="7">
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" @selected(in_array((string) $group->id, $selectedGroupIds, true))>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="target_students" class="target-box d-none">
                        <label class="form-label">Alumnos</label>
                        <select name="student_ids[]" class="form-select" multiple size="8">
                            @foreach($students as $student)
                                <option value="{{ $student->id }}" @selected(in_array((string) $student->id, $selectedStudentIds, true))>
                                    {{ $student->student_code }} · {{ $student->first_name }} {{ $student->last_name }} · {{ $student->group_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="target_guardians" class="target-box d-none">
                        <label class="form-label">Tutores</label>
                        <select name="guardian_ids[]" class="form-select" multiple size="8">
                            @foreach($guardians as $guardian)
                                <option value="{{ $guardian->id }}" @selected(in_array((string) $guardian->id, $selectedGuardianIds, true))>
                                    {{ $guardian->first_name }} {{ $guardian->last_name }} · {{ $guardian->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-hint mt-2">
                        Si eliges alumnos específicos, el aviso llegará a sus tutores vinculados.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm rounded bg-green-lt text-green">
                            <i class="ti ti-broadcast"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">Publicación</div>
                            <div class="text-muted small">Estado y prioridad.</div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="draft" @selected($noticeStatus === 'draft')>Borrador</option>
                            <option value="published" @selected($noticeStatus === 'published')>Publicado</option>
                            <option value="archived" @selected($noticeStatus === 'archived')>Histórico</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Prioridad</label>
                        <select name="priority" class="form-select">
                            <option value="normal" @selected($noticePriority === 'normal')>Normal</option>
                            <option value="important" @selected($noticePriority === 'important')>Importante</option>
                            <option value="urgent" @selected($noticePriority === 'urgent')>Urgente</option>
                        </select>
                    </div>

                    <label class="form-check mb-2">
                        <input
                            type="checkbox"
                            name="show_as_modal"
                            value="1"
                            class="form-check-input"
                            @checked(old('show_as_modal', optional($notice)->show_as_modal ?? false))
                        >
                        <span class="form-check-label">Mostrar como modal al abrir la app</span>
                    </label>

                    <label class="form-check">
                        <input
                            type="checkbox"
                            name="requires_ack"
                            value="1"
                            class="form-check-input"
                            @checked(old('requires_ack', optional($notice)->requires_ack ?? false))
                        >
                        <span class="form-check-label">Requiere botón de enterado</span>
                    </label>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm rounded bg-orange-lt text-orange">
                            <i class="ti ti-calendar"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">Fechas</div>
                            <div class="text-muted small">Vigencia del comunicado.</div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Publicar desde</label>
                        <input
                            type="datetime-local"
                            name="publish_at"
                            class="form-control"
                            value="{{ old('publish_at', optional($notice)->publish_at ? \Illuminate\Support\Carbon::parse($notice->publish_at)->format('Y-m-d\TH:i') : '') }}"
                        >
                    </div>

                    <div>
                        <label class="form-label">Expira en</label>
                        <input
                            type="datetime-local"
                            name="expires_at"
                            class="form-control"
                            value="{{ old('expires_at', optional($notice)->expires_at ? \Illuminate\Support\Carbon::parse($notice->expires_at)->format('Y-m-d\TH:i') : '') }}"
                        >
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm rounded bg-purple-lt text-purple">
                            <i class="ti ti-link"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">Botón opcional</div>
                            <div class="text-muted small">Acción externa opcional.</div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Texto del botón</label>
                        <input
                            type="text"
                            name="cta_label"
                            class="form-control"
                            value="{{ old('cta_label', optional($notice)->cta_label) }}"
                            maxlength="80"
                            placeholder="Ver más"
                        >
                    </div>

                    <div>
                        <label class="form-label">URL</label>
                        <input
                            type="text"
                            name="cta_url"
                            class="form-control"
                            value="{{ old('cta_url', optional($notice)->cta_url) }}"
                            maxlength="255"
                            placeholder="https://..."
                        >
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>
                        Guardar aviso
                    </button>

                    <a href="{{ route('admin.notices.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    function syncNoticeTargetBoxes() {
        const scope = document.getElementById('target_scope')?.value;

        document.getElementById('target_groups')?.classList.toggle('d-none', scope !== 'groups');
        document.getElementById('target_students')?.classList.toggle('d-none', scope !== 'students');
        document.getElementById('target_guardians')?.classList.toggle('d-none', scope !== 'guardians');
    }

    document.getElementById('target_scope')?.addEventListener('change', syncNoticeTargetBoxes);
    syncNoticeTargetBoxes();
</script>