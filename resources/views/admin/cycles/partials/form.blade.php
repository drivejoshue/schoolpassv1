@php
    $hasCycle = isset($cycleRow) && $cycleRow;

    $isClosed = $hasCycle
        && $cycleRow->status === 'closed';

    $isActive = $hasCycle
        && $cycleRow->status === 'active'
        && (bool) $cycleRow->is_active;

    $statusLabels = [
        'draft' => 'Borrador',
        'active' => 'Activo',
        'closed' => 'Cerrado',
    ];

    $statusColors = [
        'draft' => 'warning',
        'active' => 'success',
        'closed' => 'secondary',
    ];
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <div class="d-flex">
            <div>
                <i class="ti ti-alert-circle me-2"></i>
            </div>

            <div>
                <strong>
                    No se pudieron guardar los cambios.
                </strong>

                <div class="mt-1">
                    {{ $errors->first() }}
                </div>
            </div>
        </div>
    </div>
@endif

@if($isClosed)
    <div class="alert alert-secondary">
        <i class="ti ti-lock me-2"></i>

        Este ciclo está cerrado. Sus datos se conservan
        únicamente para consulta histórica.
    </div>
@elseif($isActive)
    <div class="alert alert-info">
        <i class="ti ti-info-circle me-2"></i>

        Este ciclo está activo. Cambiar sus fechas puede afectar
        reportes y cálculo de asistencia. Revisa los datos antes
        de guardar.
    </div>
@endif

<div class="row g-3">
    <div class="col-md-12">
        <label class="form-label">
            Nombre del ciclo
        </label>

        <input
            type="text"
            name="name"
            value="{{ old(
                'name',
                $cycleRow->name ?? ''
            ) }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="Ej. Ciclo escolar 2027-2028"
            maxlength="100"
            required
            @disabled($isClosed)
        >

        <div class="form-hint">
            Nombre administrativo utilizado en matrícula,
            grupos, asistencia y reportes.
        </div>

        @error('name')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">
            Fecha de inicio
        </label>

        <input
            type="date"
            name="starts_on"
            value="{{ old(
                'starts_on',
                $cycleRow->starts_on ?? ''
            ) }}"
            class="form-control @error('starts_on') is-invalid @enderror"
            required
            @disabled($isClosed)
        >

        <div class="form-hint">
            Primera fecha en la que el ciclo puede generar
            asistencia oficial.
        </div>

        @error('starts_on')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">
            Fecha final
        </label>

        <input
            type="date"
            name="ends_on"
            value="{{ old(
                'ends_on',
                $cycleRow->ends_on ?? ''
            ) }}"
            class="form-control @error('ends_on') is-invalid @enderror"
            required
            @disabled($isClosed)
        >

        <div class="form-hint">
            Después de esta fecha no se inferirán nuevas
            asistencias para este ciclo.
        </div>

        @error('ends_on')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="col-md-12">
        <label class="form-label">
            Notas internas
        </label>

        <textarea
            name="notes"
            rows="4"
            class="form-control @error('notes') is-invalid @enderror"
            placeholder="Observaciones administrativas del ciclo escolar"
            maxlength="2000"
            @disabled($isClosed)
        >{{ old(
            'notes',
            $cycleRow->notes ?? ''
        ) }}</textarea>

        <div class="form-hint">
            Estas notas son administrativas y no se muestran
            a alumnos ni tutores.
        </div>

        @error('notes')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    @if($hasCycle)
        <div class="col-md-12">
            <div class="border rounded p-3 bg-light">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-secondary small">
                            Estado administrativo
                        </div>

                        <div class="mt-1">
                            <span class="badge bg-{{
                                $statusColors[
                                    $cycleRow->status
                                ] ?? 'secondary'
                            }}-lt">
                                {{ $statusLabels[
                                    $cycleRow->status
                                ] ?? ucfirst(
                                    $cycleRow->status
                                ) }}
                            </span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-secondary small">
                            Ciclo operativo
                        </div>

                        <div class="fw-bold mt-1">
                            @if($cycleRow->is_active)
                                <span class="text-success">
                                    Sí
                                </span>
                            @else
                                No
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-secondary small">
                            Cerrado el
                        </div>

                        <div class="fw-bold mt-1">
                            {{ $cycleRow->closed_at
                                ? \Illuminate\Support\Carbon::parse(
                                    $cycleRow->closed_at
                                )->format('d/m/Y H:i')
                                : '—'
                            }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-secondary small">
                            Inicio
                        </div>

                        <div class="fw-bold mt-1">
                            {{ $cycleRow->starts_on
                                ? \Illuminate\Support\Carbon::parse(
                                    $cycleRow->starts_on
                                )->format('d/m/Y')
                                : '—'
                            }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-secondary small">
                            Final
                        </div>

                        <div class="fw-bold mt-1">
                            {{ $cycleRow->ends_on
                                ? \Illuminate\Support\Carbon::parse(
                                    $cycleRow->ends_on
                                )->format('d/m/Y')
                                : '—'
                            }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-secondary small">
                            Duración estimada
                        </div>

                        <div class="fw-bold mt-1">
                            @if(
                                $cycleRow->starts_on
                                && $cycleRow->ends_on
                            )
                                {{
                                    \Illuminate\Support\Carbon::parse(
                                        $cycleRow->starts_on
                                    )->diffInDays(
                                        \Illuminate\Support\Carbon::parse(
                                            $cycleRow->ends_on
                                        )
                                    ) + 1
                                }}
                                días
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>