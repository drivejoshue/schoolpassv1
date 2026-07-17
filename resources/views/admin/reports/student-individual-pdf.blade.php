<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <title>Reporte individual del alumno</title>

    <style>
        @page {
            margin: 25px 30px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
        }

        .header {
            padding: 14px 17px;
            margin-bottom: 10px;
            color: #ffffff;
            background: #0f172a;
        }

        .header h1 {
            margin: 0 0 3px;
            font-size: 18px;
        }

        .header p {
            margin: 0;
            color: #cbd5e1;
        }

        .student-card {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }

        .student-card td {
            padding: 8px;
            vertical-align: top;
            background: #f8fafc;
            border: 1px solid #dbe3ee;
        }

        .student-name {
            margin-bottom: 4px;
            font-size: 16px;
            font-weight: bold;
        }

        .metrics {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: separate;
            border-spacing: 4px;
        }

        .metrics td {
            width: 16.66%;
            padding: 8px;
            text-align: center;
            background: #f8fafc;
            border: 1px solid #dbeafe;
        }

        .metric-label {
            color: #64748b;
            font-size: 7px;
        }

        .metric-value {
            margin-top: 3px;
            font-size: 16px;
            font-weight: bold;
        }

        .section {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .section-title {
            padding-bottom: 4px;
            margin: 0 0 6px;
            font-size: 12px;
            border-bottom: 2px solid #2563eb;
        }

        .chart {
            width: 100%;
            max-height: 220px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
        }

        table.report th {
            padding: 5px;
            color: #ffffff;
            text-align: left;
            background: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        table.report td {
            padding: 4px 5px;
            border: 1px solid #e2e8f0;
        }

        table.report tr:nth-child(even) td {
            background: #f8fafc;
        }

        .status {
            font-weight: bold;
        }

        .page-break {
            page-break-before: always;
        }

        .footer {
            margin-top: 8px;
            color: #64748b;
            text-align: center;
            font-size: 7px;
        }
    </style>
</head>

<body>
    @php
        $statusLabels = [
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Extemporáneo',
            'absent' => 'Ausente',
            'no_class' => 'Sin clase',
        ];

        $eventLabels = [
            'entry' => 'Entrada',
            'exit' => 'Salida',
            'access' => 'Acceso',
        ];

        $formatTime = function ($value) {
            return $value
                ? \Illuminate\Support\Carbon::parse($value)
                    ->format('H:i')
                : '—';
        };
    @endphp

    <div class="header">
        <h1>{{ $school->name ?? 'SchoolPass' }}</h1>

        <p>
            Reporte individual de asistencia y accesos
        </p>
    </div>

    <table class="student-card">
        <tr>
            <td style="width: 58%;">
                <div class="student-name">
                    {{ $report['student']->first_name }}
                    {{ $report['student']->last_name }}
                </div>

                <div>
                    <strong>Matrícula:</strong>
                    {{ $report['student']->student_code }}
                </div>

                <div>
                    <strong>Plantel:</strong>
                    {{ $report['student']->campus_name ?? 'Sin plantel' }}
                </div>

                <div>
                    <strong>Nivel y grupo:</strong>
                    {{ $report['student']->level_name ?? 'Sin nivel' }}
                    ·
                    {{ $report['student']->group_name ?? 'Sin grupo' }}
                </div>
            </td>

            <td>
                <div>
                    <strong>Periodo:</strong>
                    {{ \Illuminate\Support\Carbon::parse(
                        $filters['from']
                    )->format('d/m/Y') }}
                    al
                    {{ \Illuminate\Support\Carbon::parse(
                        $filters['to']
                    )->format('d/m/Y') }}
                </div>

                <div>
                    <strong>Generado:</strong>
                    {{ $generatedAt->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="metrics">
        <tr>
            <td>
                <div class="metric-label">Puntuales</div>
                <div class="metric-value">
                    {{ $report['summary']['on_time'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">Retardos</div>
                <div class="metric-value">
                    {{ $report['summary']['late'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">Extemporáneos</div>
                <div class="metric-value">
                    {{ $report['summary']['very_late'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">Ausencias</div>
                <div class="metric-value">
                    {{ $report['summary']['absent'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">Puntualidad</div>
                <div class="metric-value">
                    {{ number_format(
                        $report['summary']['punctuality_rate'],
                        1
                    ) }}%
                </div>
            </td>

            <td>
                <div class="metric-label">Incidencias</div>
                <div class="metric-value">
                    {{ $report['summary']['incidents'] }}
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h2 class="section-title">
            Evolución semanal
        </h2>

        <img
            src="{{ $chartImage }}"
            class="chart"
            alt="Gráfica de asistencia"
        >
    </div>

    <div class="section">
        <h2 class="section-title">
            Tutores vinculados
        </h2>

        <table class="report">
            <thead>
                <tr>
                    <th>Tutor</th>
                    <th>Parentesco</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Principal</th>
                </tr>
            </thead>

            <tbody>
                @forelse($report['guardians'] as $guardian)
                    <tr>
                        <td>
                            {{ $guardian->first_name }}
                            {{ $guardian->last_name }}
                        </td>

                        <td>{{ ucfirst($guardian->relationship) }}</td>
                        <td>{{ $guardian->phone ?: '—' }}</td>
                        <td>{{ $guardian->email ?: '—' }}</td>
                        <td>{{ $guardian->is_primary ? 'Sí' : 'No' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            Sin tutores vinculados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <h2 class="section-title">
            Historial de asistencia
        </h2>

        <table class="report">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Minutos tarde</th>
                    <th>Estado</th>
                </tr>
            </thead>

            <tbody>
                @forelse(
                    $report['attendance']->sortByDesc('date')
                    as $row
                )
                    <tr>
                        <td>
                            {{ \Illuminate\Support\Carbon::parse(
                                $row->date
                            )->format('d/m/Y') }}
                        </td>

                        <td>{{ $row->weekday }}</td>
                        <td>{{ $formatTime($row->entry_time) }}</td>
                        <td>{{ $formatTime($row->exit_time) }}</td>
                        <td>{{ $row->minutes_late }}</td>

                        <td class="status">
                            {{
                                $statusLabels[$row->final_status]
                                ?? $row->final_status
                            }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            Sin datos de asistencia.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">
            Actividad de acceso
        </h2>

        <table class="report">
            <thead>
                <tr>
                    <th>Fecha/hora</th>
                    <th>Evento</th>
                    <th>Estado</th>
                    <th>Área</th>
                    <th>Dispositivo</th>
                    <th>Razón</th>
                </tr>
            </thead>

            <tbody>
                @forelse($report['access_logs'] as $log)
                    <tr>
                        <td>
                            {{ \Illuminate\Support\Carbon::parse(
                                $log->scanned_at
                            )->format('d/m/Y H:i:s') }}
                        </td>

                        <td>
                            {{
                                $eventLabels[$log->event_type]
                                ?? $log->event_type
                            }}
                        </td>

                        <td>{{ $log->event_status }}</td>
                        <td>{{ $log->area_name ?? '—' }}</td>
                        <td>{{ $log->device_name ?? '—' }}</td>
                        <td>{{ $log->reason ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            Sin eventos durante el periodo.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        Documento generado automáticamente por SchoolPass.
    </div>
</body>
</html>