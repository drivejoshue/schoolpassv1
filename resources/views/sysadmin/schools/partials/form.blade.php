@php
    $editing = isset($school);
@endphp

<div class="row row-cards">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Información general</h3>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">
                            Nombre comercial
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $school->name ?? '') }}"
                            maxlength="150"
                            required
                        >

                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Razón social</label>

                        <input
                            type="text"
                            name="legal_name"
                            class="form-control"
                            value="{{ old(
                                'legal_name',
                                $school->legal_name ?? ''
                            ) }}"
                            maxlength="180"
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label {{ $editing ? 'required' : '' }}">
                            Slug
                        </label>

                        <input
                            type="text"
                            name="slug"
                            class="form-control"
                            value="{{ old('slug', $school->slug ?? '') }}"
                            maxlength="100"
                            placeholder="{{ $editing
                                ? ''
                                : 'Se genera automáticamente'
                            }}"
                            {{ $editing ? 'required' : '' }}
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="form-label required">Estado</label>

                        <select name="status" class="form-select" required>
                            @foreach ([
                                'active' => 'Activa',
                                'suspended' => 'Suspendida',
                                'cancelled' => 'Cancelada',
                            ] as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected(
                                        old(
                                            'status',
                                            $school->status ?? 'active'
                                        ) === $value
                                    )
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label required">
                            Zona horaria
                        </label>

                        <input
                            type="text"
                            name="timezone"
                            class="form-control"
                            value="{{ old(
                                'timezone',
                                $school->timezone
                                    ?? 'America/Mexico_City'
                            ) }}"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">RFC / Tax ID</label>

                        <input
                            type="text"
                            name="tax_id"
                            class="form-control"
                            value="{{ old('tax_id', $school->tax_id ?? '') }}"
                            maxlength="30"
                        >
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Dirección</label>

                        <input
                            type="text"
                            name="address"
                            class="form-control"
                            value="{{ old(
                                'address',
                                $school->address ?? ''
                            ) }}"
                            maxlength="255"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Contacto y soporte</h3>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">
                            Contacto principal
                        </label>

                        <input
                            type="text"
                            name="contact_name"
                            class="form-control"
                            value="{{ old(
                                'contact_name',
                                $school->contact_name ?? ''
                            ) }}"
                            maxlength="160"
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Correo de contacto
                        </label>

                        <input
                            type="email"
                            name="contact_email"
                            class="form-control"
                            value="{{ old(
                                'contact_email',
                                $school->contact_email ?? ''
                            ) }}"
                            maxlength="180"
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Teléfono</label>

                        <input
                            type="text"
                            name="contact_phone"
                            class="form-control"
                            value="{{ old(
                                'contact_phone',
                                $school->contact_phone ?? ''
                            ) }}"
                            maxlength="30"
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">WhatsApp</label>

                        <input
                            type="text"
                            name="whatsapp_number"
                            class="form-control"
                            value="{{ old(
                                'whatsapp_number',
                                $school->whatsapp_number ?? ''
                            ) }}"
                            maxlength="30"
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Correo de soporte
                        </label>

                        <input
                            type="email"
                            name="support_email"
                            class="form-control"
                            value="{{ old(
                                'support_email',
                                $school->support_email ?? ''
                            ) }}"
                            maxlength="180"
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Identidad visual</h3>
            </div>

            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Ruta del logotipo</label>

                    <input
                        type="text"
                        name="logo_path"
                        class="form-control"
                        value="{{ old(
                            'logo_path',
                            $school->logo_path ?? ''
                        ) }}"
                        maxlength="255"
                        placeholder="school_logos/logo.png"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Color principal</label>

                    <input
                        type="text"
                        name="primary_color"
                        class="form-control"
                        value="{{ old(
                            'primary_color',
                            $school->primary_color ?? '#2563EB'
                        ) }}"
                        maxlength="7"
                    >
                </div>

                <div class="mb-0">
                    <label class="form-label">Color secundario</label>

                    <input
                        type="text"
                        name="secondary_color"
                        class="form-control"
                        value="{{ old(
                            'secondary_color',
                            $school->secondary_color ?? '#0F172A'
                        ) }}"
                        maxlength="7"
                    >
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <div class="d-flex gap-2">
                    <button
                        type="submit"
                        class="btn btn-primary flex-fill"
                    >
                        <i class="ti ti-device-floppy me-2"></i>
                        {{ $editing
                            ? 'Guardar cambios'
                            : 'Crear escuela'
                        }}
                    </button>

                    <a
                        href="{{ $editing
                            ? route(
                                'sysadmin.schools.show',
                                $school
                            )
                            : route('sysadmin.schools.index')
                        }}"
                        class="btn btn-outline-secondary"
                    >
                        Cancelar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
