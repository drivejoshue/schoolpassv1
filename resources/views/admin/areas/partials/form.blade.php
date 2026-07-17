@if($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Campus</label>
        <select name="campus_id" class="form-select" required>
            <option value="">Seleccionar campus</option>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}"
                    @selected(old('campus_id', $areaRow->campus_id ?? '') == $campus->id)>
                    {{ $campus->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Tipo</label>
        <select name="type" class="form-select" required>
            @foreach([
                'entrance' => 'Entrada',
                'restricted' => 'Restringida',
                'lab' => 'Laboratorio / taller',
                'storage' => 'Almacén',
                'library' => 'Biblioteca',
                'classroom' => 'Aula',
                'other' => 'Otra',
            ] as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $areaRow->type ?? 'entrance') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-8">
        <label class="form-label">Nombre</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $areaRow->name ?? '') }}"
            class="form-control"
            required
            placeholder="Entrada Principal"
        >
    </div>

    <div class="col-md-4">
        <label class="form-label">Código</label>
        <input
            type="text"
            name="code"
            value="{{ old('code', $areaRow->code ?? '') }}"
            class="form-control"
            placeholder="entrada-principal"
        >
    </div>

    <div class="col-md-6">
        <label class="form-label">Estado</label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $areaRow->status ?? 'active') === 'active')>
                Activa
            </option>
            <option value="inactive" @selected(old('status', $areaRow->status ?? '') === 'inactive')>
                Inactiva
            </option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Asistencia</label>

        <label class="form-check form-switch mt-2">
            <input
                class="form-check-input"
                type="checkbox"
                name="affects_attendance"
                value="1"
                @checked(old('affects_attendance', $areaRow->affects_attendance ?? false))
            >
            <span class="form-check-label">
                Esta área afecta entrada/salida escolar
            </span>
        </label>
    </div>
</div>