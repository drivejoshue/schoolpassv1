@extends('layouts.app')

@section('title', 'Importación masiva | SchoolPass')
@section('section-label', 'Configuración')
@section('page-title', 'Importación de alumnos y tutores')

@section('topbar-actions')
    <a
        href="{{ route('admin.imports.students.template') }}"
        class="btn btn-success btn-sm"
    >
        <i class="ti ti-file-spreadsheet me-1"></i>
        Descargar plantilla Excel
    </a>
@endsection

@section('content')
    @include('admin.partials.tools-nav')

    @if(session('success'))
        <div class="alert alert-success">
            <i class="ti ti-circle-check me-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning">
            <i class="ti ti-alert-triangle me-2"></i>{{ session('warning') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold mb-1">No se pudo continuar</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('import_result'))
        @php($result = session('import_result'))
        <div class="card mb-3 border-success">
            <div class="card-header">
                <div>
                    <h3 class="card-title text-success">Importación completada</h3>
                    <p class="card-subtitle">Resumen de los cambios aplicados en esta escuela.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3"><div class="text-secondary">Filas procesadas</div><div class="h2 mb-0">{{ $result['rows_processed'] }}</div></div>
                    <div class="col-6 col-md-3"><div class="text-secondary">Alumnos creados</div><div class="h2 mb-0">{{ $result['students_created'] }}</div></div>
                    <div class="col-6 col-md-3"><div class="text-secondary">Alumnos actualizados</div><div class="h2 mb-0">{{ $result['students_updated'] }}</div></div>
                    <div class="col-6 col-md-3"><div class="text-secondary">Tutores creados</div><div class="h2 mb-0">{{ $result['guardians_created'] }}</div></div>
                    <div class="col-6 col-md-3"><div class="text-secondary">Tutores actualizados</div><div class="h2 mb-0">{{ $result['guardians_updated'] }}</div></div>
                    <div class="col-6 col-md-3"><div class="text-secondary">Vínculos creados</div><div class="h2 mb-0">{{ $result['links_created'] }}</div></div>
                    <div class="col-6 col-md-3"><div class="text-secondary">Vínculos actualizados</div><div class="h2 mb-0">{{ $result['links_updated'] }}</div></div>
                </div>
            </div>
        </div>
    @endif

    <div class="row row-cards mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                       <h3 class="card-title">1. Cargar archivo Excel o CSV</h3>

<p class="card-subtitle">
    Utiliza la plantilla oficial de SchoolPass en formato .xlsx.
    También se aceptan archivos CSV.
</p>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.imports.students.preview') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label required"> Formatos permitidos: Excel .xlsx y CSV. Máximo 10 MB.</label>
                            <input type="file" name="file" class="form-control" accept=".csv,text/csv,text/plain" required>
                            <small class="form-hint">Usa la plantilla oficial. Se aceptan separadores coma o punto y coma.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-eye-check me-1"></i>
                            Generar vista previa
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">Contexto de la escuela</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-secondary">Ciclo activo</div>
                        <div class="fw-bold">{{ $context['active_cycle']->name ?? 'Sin ciclo activo' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-secondary">Planteles disponibles</div>
                        <div class="fw-bold">{{ $context['campuses']->count() }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-secondary">Niveles disponibles</div>
                        <div class="fw-bold">{{ $context['levels']->count() }}</div>
                    </div>
                    <div>
                        <div class="text-secondary">Grupos disponibles</div>
                        <div class="fw-bold">{{ $context['groups']->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">Columnas requeridas</h3>
                <p class="card-subtitle">Los nombres de plantel, nivel y grupo deben coincidir con los registrados.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <h4>Alumno y grupo</h4>
                    <code>student_code, first_name, last_name, campus, level, grade_label, group</code>
                </div>
                <div class="col-md-6">
                    <h4>Tutor y permisos</h4>
                    <code>guardian_first_name, guardian_last_name, guardian_phone, guardian_email, relationship, is_primary, can_view_attendance, can_receive_notifications, can_authorize_exit</code>
                </div>
            </div>
            <div class="alert alert-info mt-3 mb-0">
                El tutor es opcional. Cuando se incluya, debe tener correo o teléfono. Los valores booleanos aceptan 1/0, sí/no o true/false.
            </div>
        </div>
    </div>

    @if(is_array($preview))
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">2. Vista previa</h3>
                    <p class="card-subtitle">{{ $preview['original_name'] }} · Generada {{ \Carbon\Carbon::parse($preview['created_at'])->diffForHumans() }}</p>
                </div>
                <div class="card-actions">
                    <form method="POST" action="{{ route('admin.imports.students.discard') }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-secondary btn-sm">Descartar</button>
                    </form>
                </div>
            </div>

            <div class="card-body border-bottom">
                <div class="row g-3 align-items-end">
                    <div class="col-4 col-md-2"><div class="text-secondary">Total</div><div class="h2 mb-0">{{ $preview['summary']['total'] }}</div></div>
                    <div class="col-4 col-md-2"><div class="text-secondary">Válidas</div><div class="h2 text-success mb-0">{{ $preview['summary']['valid'] }}</div></div>
                    <div class="col-4 col-md-2"><div class="text-secondary">Con errores</div><div class="h2 text-danger mb-0">{{ $preview['summary']['invalid'] }}</div></div>
                    <div class="col-md-6 text-md-end">
                        @if($preview['can_commit'])
                            <form method="POST" action="{{ route('admin.imports.students.commit') }}" onsubmit="return confirm('¿Confirmas la importación? Los alumnos existentes se actualizarán por matrícula.');">
                                @csrf
                                <input type="hidden" name="preview_token" value="{{ $preview['token'] }}">
                                <button class="btn btn-success">
                                    <i class="ti ti-database-import me-1"></i>
                                    3. Confirmar importación
                                </button>
                            </form>
                        @else
                            <button class="btn btn-danger" disabled>Corrige los errores para continuar</button>
                        @endif
                    </div>
                </div>
            </div>

            @if($preview['rows_truncated'] ?? false)
                <div class="alert alert-info m-3 mb-0">La tabla muestra las primeras 250 filas. La validación y la importación consideran el archivo completo.</div>
            @endif

            <div class="table-responsive" style="max-height: 650px;">
                <table class="table table-vcenter table-sm card-table">
                    <thead class="sticky-top bg-white">
                        <tr>
                            <th>Línea</th>
                            <th>Resultado</th>
                            <th>Acción</th>
                            <th>Matrícula</th>
                            <th>Alumno</th>
                            <th>Plantel / grupo</th>
                            <th>Tutor</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($preview['rows'] as $row)
                            <tr class="{{ $row['valid'] ? '' : 'table-danger' }}">
                                <td>{{ $row['line'] }}</td>
                                <td>
                                    @if($row['valid'])
                                        <span class="badge bg-success-lt">Válida</span>
                                    @else
                                        <span class="badge bg-danger-lt">Error</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $row['action'] === 'create' ? 'bg-blue-lt' : 'bg-yellow-lt' }}">
                                        {{ $row['action'] === 'create' ? 'Crear' : 'Actualizar' }}
                                    </span>
                                </td>
                                <td>{{ $row['normalized']['student_code'] }}</td>
                                <td>{{ $row['normalized']['first_name'] }} {{ $row['normalized']['last_name'] }}</td>
                                <td>
                                    <div>{{ $row['normalized']['campus'] }}</div>
                                    <small class="text-secondary">{{ $row['normalized']['level'] }} · {{ $row['normalized']['group'] }}</small>
                                </td>
                                <td>
                                    @if($row['resolved']['has_guardian'])
                                        <div>{{ $row['normalized']['guardian_first_name'] }} {{ $row['normalized']['guardian_last_name'] }}</div>
                                        <small class="text-secondary">{{ $row['normalized']['guardian_email'] ?: $row['normalized']['guardian_phone'] }}</small>
                                    @else
                                        <span class="text-secondary">Sin tutor</span>
                                    @endif
                                </td>
                                <td style="min-width: 280px;">
                                    @foreach($row['errors'] as $error)
                                        <div class="text-danger"><i class="ti ti-x me-1"></i>{{ $error }}</div>
                                    @endforeach
                                    @foreach($row['warnings'] as $warning)
                                        <div class="text-warning"><i class="ti ti-alert-triangle me-1"></i>{{ $warning }}</div>
                                    @endforeach
                                    @if(empty($row['errors']) && empty($row['warnings']))
                                        <span class="text-success">Sin observaciones</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
