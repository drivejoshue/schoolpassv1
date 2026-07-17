@if($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

@php
    $selectedType = old('applies_to_type', $ruleRow->applies_to_type ?? 'group');
    $selectedTarget = old('applies_to_id', $ruleRow->applies_to_id ?? '');
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Área</label>
        <select name="area_id" class="form-select" required>
            <option value="">Seleccionar área</option>
            @foreach($areas as $area)
                <option value="{{ $area->id }}" @selected(old('area_id', $ruleRow->area_id ?? '') == $area->id)>
                    {{ $area->name }} · {{ $area->type }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Aplica a</label>
        <select name="applies_to_type" id="applies_to_type" class="form-select" required>
            <option value="group" @selected($selectedType === 'group')>Grupo</option>
            <option value="student" @selected($selectedType === 'student')>Alumno específico</option>
        </select>
    </div>

    <div class="col-md-12 target target-group">
        <label class="form-label">Grupo autorizado</label>
        <select name="applies_to_id_group" id="applies_to_id_group" class="form-select">
            <option value="">Seleccionar grupo</option>
            @foreach($groups as $group)
                <option value="{{ $group->id }}" @selected($selectedType === 'group' && (string) $selectedTarget === (string) $group->id)>
                    {{ $group->level_name }} · {{ $group->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-12 target target-student">
        <label class="form-label">Alumno autorizado</label>
        <select name="applies_to_id_student" id="applies_to_id_student" class="form-select">
            <option value="">Seleccionar alumno</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}" @selected($selectedType === 'student' && (string) $selectedTarget === (string) $student->id)>
                    {{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_code }} · {{ $student->group_name }}
                </option>
            @endforeach
        </select>
    </div>

    <input type="hidden" name="applies_to_id" id="applies_to_id" value="{{ $selectedTarget }}">

    <div class="col-md-4">
        <label class="form-label">Día</label>
        <select name="weekday" class="form-select">
            @foreach($weekdays as $value => $label)
                <option value="{{ $value }}" @selected((string) old('weekday', $ruleRow->weekday ?? '') === (string) $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <div class="form-hint">Vacío = todos los días.</div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Hora inicio</label>
        <input
            type="time"
            name="starts_at"
            value="{{ old('starts_at', isset($ruleRow->starts_at) && $ruleRow->starts_at ? substr($ruleRow->starts_at, 0, 5) : '') }}"
            class="form-control"
        >
    </div>

    <div class="col-md-4">
        <label class="form-label">Hora fin</label>
        <input
            type="time"
            name="ends_at"
            value="{{ old('ends_at', isset($ruleRow->ends_at) && $ruleRow->ends_at ? substr($ruleRow->ends_at, 0, 5) : '') }}"
            class="form-control"
        >
    </div>

    <div class="col-md-4">
        <label class="form-label">Estado</label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $ruleRow->status ?? 'active') === 'active')>
                Activa
            </option>
            <option value="inactive" @selected(old('status', $ruleRow->status ?? '') === 'inactive')>
                Inactiva
            </option>
        </select>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('applies_to_type');
    const hiddenTarget = document.getElementById('applies_to_id');
    const groupTarget = document.getElementById('applies_to_id_group');
    const studentTarget = document.getElementById('applies_to_id_student');
    const groupBox = document.querySelector('.target-group');
    const studentBox = document.querySelector('.target-student');

    function syncTarget() {
        const type = typeSelect.value;

        if (type === 'group') {
            groupBox.classList.remove('d-none');
            studentBox.classList.add('d-none');
            hiddenTarget.value = groupTarget.value;
        } else {
            groupBox.classList.add('d-none');
            studentBox.classList.remove('d-none');
            hiddenTarget.value = studentTarget.value;
        }
    }

    typeSelect.addEventListener('change', syncTarget);
    groupTarget.addEventListener('change', syncTarget);
    studentTarget.addEventListener('change', syncTarget);

    syncTarget();
});
</script>
@endpush