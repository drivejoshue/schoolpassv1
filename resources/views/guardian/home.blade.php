@extends('layouts.app')

@section('title', 'Padres | SchoolPass')
@section('section-label', 'Padres / Tutores')
@section('page-title', 'Mis hijos')

@section('content')
    @php
        $attendanceLabels = [
            'present' => 'Presente',
            'late' => 'Retardo',
            'partial' => 'Registro parcial',
            'absent' => 'Falta',
            'justified' => 'Justificada',
        ];

        $notificationLabels = [
            'entry' => 'Entrada',
            'late' => 'Retardo',
            'exit' => 'Salida',
            'early_exit' => 'Salida anticipada',
            'access' => 'Acceso',
            'general' => 'General',
        ];
    @endphp

    @if(! $guardian)
        <div class="alert alert-danger">
            <i class="ti ti-alert-triangle me-2"></i>
            Tu cuenta no tiene un tutor vinculado. Contacta a la institución.
        </div>
    @else
        <div class="row row-cards">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-lg bg-blue-lt me-3">
                                {{ strtoupper(substr($guardian->first_name ?? 'T', 0, 1)) }}
                            </span>

                            <div>
                                <h2 class="mb-1">
                                    {{ $guardian->first_name }} {{ $guardian->last_name }}
                                </h2>

                                <div class="text-secondary">
                                    Tutor vinculado · {{ $guardian->phone ?? 'Sin teléfono' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @forelse($students as $student)
                <div class="col-md-6 col-xl-4">
                    <div class="card">
                        <div class="card-body text-center">
                           @if($student->photo_url)
    <span
        class="avatar avatar-xl mb-3"
        style="background-image: url('{{ asset($student->photo_url) }}')"
    ></span>
@else
    <span class="avatar avatar-xl bg-blue-lt mb-3">
        {{ strtoupper(substr($student->first_name, 0, 1)) }}
    </span>
@endif

                            <h2 class="mb-1">
                                {{ $student->first_name }} {{ $student->last_name }}
                            </h2>

                            <div class="text-secondary">
                                {{ $student->level_name }} · {{ $student->group_name }}
                            </div>

                            <div class="mt-3">
                                <span class="badge bg-blue-lt">
                                    {{ $student->student_code }}
                                </span>

                                @if($student->status === 'active')
                                    <span class="badge bg-success-lt">Activo</span>
                                @else
                                    <span class="badge bg-warning-lt">{{ $student->status }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="list-group list-group-flush">
                            <div class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-secondary small">Relación</div>
                                        <div class="fw-bold">
                                            {{ ucfirst($student->relationship) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="list-group-item">
                                <div class="text-secondary small">Última asistencia</div>

                                @if($student->last_attendance)
                                    <div class="fw-bold">
                                        {{ $attendanceLabels[$student->last_attendance->attendance_status] ?? $student->last_attendance->attendance_status }}
                                    </div>

                                    <div class="text-secondary small">
                                        Fecha: {{ $student->last_attendance->date }}
                                    </div>

                                    <div class="text-secondary small">
                                        Entrada: {{ $student->last_attendance->entry_at ?? '—' }}
                                    </div>

                                    <div class="text-secondary small">
                                        Salida: {{ $student->last_attendance->exit_at ?? '—' }}
                                    </div>

                                    @if((int) $student->last_attendance->minutes_late > 0)
                                        <div class="text-warning small mt-1">
                                            Retardo: {{ $student->last_attendance->minutes_late }} min.
                                        </div>
                                    @endif
                                @else
                                    <div class="text-secondary">
                                        Sin registros todavía.
                                    </div>
                                @endif
                            </div>

                            <div class="list-group-item">
                                <div class="text-secondary small mb-1">Permisos</div>

                                @if($student->can_receive_notifications)
                                    <span class="badge bg-success-lt">Recibe avisos</span>
                                @else
                                    <span class="badge bg-secondary-lt">Sin avisos</span>
                                @endif

                                @if($student->can_authorize_exit)
                                    <span class="badge bg-blue-lt">Autoriza salida</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="empty">
                        <div class="empty-icon">
                            <i class="ti ti-users"></i>
                        </div>

                        <p class="empty-title">
                            Sin alumnos vinculados
                        </p>

                        <p class="empty-subtitle text-secondary">
                            La institución todavía no ha vinculado alumnos a esta cuenta.
                        </p>
                    </div>
                </div>
            @endforelse

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Notificaciones recientes
                        </h3>
                    </div>

                    <div class="list-group list-group-flush">
                        @forelse($notifications as $notification)
                            <div class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        @if($notification->read_at)
                                            <span class="status-dot status-dot-animated bg-secondary"></span>
                                        @else
                                            <span class="status-dot status-dot-animated bg-blue"></span>
                                        @endif
                                    </div>

                                    <div class="col">
                                        <div class="fw-bold">
                                            {{ $notification->title }}
                                        </div>

                                        <div class="text-secondary">
                                            {{ $notification->body }}
                                        </div>

                                        <div class="text-secondary small mt-1">
                                            {{ $notificationLabels[$notification->type] ?? $notification->type }}
                                            · {{ $notification->student_name ?? 'Sin alumno' }}
                                            · {{ $notification->created_at }}
                                        </div>
                                    </div>

                                    <div class="col-auto">
                                        @if($notification->read_at)
                                            <span class="badge bg-secondary-lt">Leída</span>
                                        @else
                                            <span class="badge bg-blue-lt">Nueva</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="list-group-item text-secondary text-center py-5">
                                Sin notificaciones.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection