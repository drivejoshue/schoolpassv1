@if($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

@if($activeCycle)
    <div class="alert alert-info">
        <i class="ti ti-calendar-stats me-2"></i>
        Ciclo activo detectado:
        <strong>{{ $activeCycle->name }}</strong>.
        Se usará por defecto en esta fecha especial.
    </div>
@else
    <div class="alert alert-warning">
        <i class="ti ti-alert-triangle me-2"></i>
        No hay ciclo activo. Puedes guardar la fecha sin ciclo, pero lo correcto es activar primero un ciclo escolar.
    </div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Ciclo escolar</label>
        <select name="academic_cycle_id" class="form-select">
            <option value="">Sin ciclo específico</option>
            @foreach($cycles as $cycle)
                <option
                    value="{{ $cycle->id }}"
                    @selected(old('academic_cycle_id', $dayRow->academic_cycle_id ?? $activeCycle?->id) == $cycle->id)
                >
                    {{ $cycle->name }}
                    @if($cycle->is_active)
                        · activo
                    @endif
                </option>
            @endforeach
        </select>
        <div class="form-hint">
            Este dato permitirá reportes históricos y futuras calificaciones por ciclo.
        </div>
    </div>

    @if($isEdit)
        <div class="col-md-6">
            <label class="form-label">Fecha</label>
            <input
                type="date"
                name="date"
                value="{{ old('date', $dayRow->date ?? now()->toDateString()) }}"
                class="form-control"
                required
            >
        </div>
    @else
        <div class="col-md-3">
            <label class="form-label">Desde</label>
            <input
                type="date"
                name="date_from"
                value="{{ old('date_from', now()->toDateString()) }}"
                class="form-control"
                required
            >
        </div>

        <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <input
                type="date"
                name="date_to"
                value="{{ old('date_to') }}"
                class="form-control"
            >
            <div class="form-hint">
                Déjalo vacío si solo es un día.
            </div>
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label">Tipo</label>
        <select name="type" class="form-select" required>
            @foreach($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $dayRow->type ?? 'suspension') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <div class="form-hint">
            Vacaciones, suspensión, festivo y consejo técnico no cuentan ausencias.
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Estado</label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $dayRow->status ?? 'active') === 'active')>
                Activo
            </option>
            <option value="inactive" @selected(old('status', $dayRow->status ?? '') === 'inactive')>
                Inactivo
            </option>
        </select>
    </div>

    <div class="col-md-12">
        <label class="form-label">Título</label>
        <input
            type="text"
            name="title"
            value="{{ old('title', $dayRow->title ?? '') }}"
            class="form-control"
            placeholder="Ej. Vacaciones de invierno / Suspensión por consejo técnico"
            required
        >
    </div>

    <div class="col-md-12">
        <label class="form-label">Notas</label>
        <textarea
            name="notes"
            rows="4"
            class="form-control"
            placeholder="Observaciones internas"
        >{{ old('notes', $dayRow->notes ?? '') }}</textarea>
    </div>
</div>