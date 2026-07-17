<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <title>Incidencias por alumno</title>

    <style>
        @page {
            margin: 22px 25px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
        }

        .header {
            padding: 13px 16px;
            margin-bottom: 9px;
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

        .meta {
            width: 100%;
            margin-bottom: 8px;
            border-collapse: collapse;
        }

        .meta td {
            padding: 5px 7px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
        }

        .metrics {
            width: 100%;
            margin-bottom: 9px;
            border-collapse: separate;
            border-spacing: 4px;
        }

        .metrics td {
            width: 20%;
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
            vertical-align: top;
            border: 1px solid #e2e8f0;
        }

        table.report tr:nth-child(even) td {
            background: #f8fafc;
        }

        .risk-high {
            color: #991b1b;
            background: #fee2e2 !important;
            font-weight: bold;
        }

        .risk-medium {
            color: #92400e;
            background: #fef3c7 !important;
            font-weight: bold;
        }

        .risk-low {
            color: #1e40af;
            background: #dbeafe !important;
            font-weight: bold;
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
    <div class="header">
        <h1>{{ $school->name ?? 'SchoolPass' }}</h1>

        <p>
            Reporte de incidencias por alumno
        </p>
    </div>

    <table class="meta">
        <tr>
            <td>
                <strong>Periodo:</strong>

                {{ \Illuminate\Support\Carbon::parse(
                    $filters['from']
                )->format('d/m/Y') }}

                al

                {{ \Illuminate\Support\Carbon::parse(
                    $filters['to']
                )->format('d/m/Y') }}
            </td>

            <td>
                <strong>Grupo:</strong>
                {{ $selectedGroup->name ?? 'Todos los grupos' }}
            </td>

            <td>
                <strong>Riesgo:</strong>
                {{ $filters['risk']
                    ? ucfirst($filters['risk'])
                    : 'Todos'
                }}
            </td>

            <td>
                <strong>Generado:</strong>
                {{ $generatedAt->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    <table class="metrics">
        <tr>
            <td>
                <div class="metric-label">
                    Incidencias
                </div>

                <div class="metric-value">
                    {{ $summary['total'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">
                    Alumnos
                </div>

                <div class="metric-value">
                    {{ $summary['students'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">
                    Riesgo alto
                </div>

                <div class="metric-value">
                    {{ $summary['high'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">
                    Riesgo medio
                </div>

                <div class="metric-value">
                    {{ $summary['medium'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">
                    Riesgo bajo
                </div>

                <div class="metric-value">
                    {{ $summary['low'] }}
                </div>
            </td>
        </tr>
    </table>

    <table class="report">
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Grupo</th>
                <th>Incidencia</th>
                <th>Cantidad</th>
                <th>Última fecha</th>
                <th>Riesgo</th>
                <th>Puntualidad</th>
                <th>Acción sugerida</th>
            </tr>
        </thead>

        <tbody>
            @forelse($incidents as $incident)
                <tr>
                    <td>
                        <strong>
                            {{ $incident['full_name'] }}
                        </strong>

                        <br>

                        {{ $incident['student_code'] }}
                    </td>

                    <td>
                        {{ $incident['group_name'] }}

                        <br>

                        {{ $incident['level_name'] }}
                    </td>

                    <td>
                        {{ $incident['type_label'] }}
                    </td>

                    <td>
                        {{ $incident['quantity'] }}
                    </td>

                    <td>
                        @if($incident['last_date'])
                            {{ \Illuminate\Support\Carbon::parse(
                                $incident['last_date']
                            )->format('d/m/Y') }}
                        @else
                            —
                        @endif
                    </td>

                    <td class="risk-{{ $incident['risk'] }}">
                        {{ $incident['risk_label'] }}
                    </td>

                    <td>
                        {{ number_format(
                            $incident['punctuality_rate'],
                            1
                        ) }}%
                    </td>

                    <td>
                        {{ $incident['suggested_action'] }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        No se detectaron incidencias.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Documento generado automáticamente por SchoolPass.
    </div>
</body>
</html>