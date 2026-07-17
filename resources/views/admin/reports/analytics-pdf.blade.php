<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <title>Analítica SchoolPass</title>

    <style>
        @page {
            margin: 22px 26px;
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

        h1,
        h2,
        p {
            margin-top: 0;
        }

        .header {
            padding: 13px 17px;
            margin-bottom: 10px;
            color: #ffffff;
            background: #0f172a;
            border-radius: 5px;
        }

        .header h1 {
            margin: 0 0 4px;
            font-size: 19px;
        }

        .header p {
            margin: 0;
            color: #cbd5e1;
            font-size: 10px;
        }

        .meta {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }

        .meta td {
            width: 33.33%;
            padding: 6px 8px;
            background: #f1f5f9;
            border: 1px solid #dbe3ee;
        }

        .cards {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: separate;
            border-spacing: 5px;
        }

        .cards td {
            width: 25%;
            padding: 10px;
            vertical-align: top;
            background: #f8fafc;
            border: 1px solid #cfe0fa;
            border-radius: 5px;
        }

        .metric-label {
            color: #64748b;
            font-size: 8px;
        }

        .metric-value {
            margin-top: 4px;
            font-size: 19px;
            font-weight: bold;
        }

        .section {
            margin-bottom: 11px;
            page-break-inside: avoid;
        }

        .section-title {
            padding-bottom: 4px;
            margin: 0 0 6px;
            font-size: 12px;
            border-bottom: 2px solid #2563eb;
        }

        .chart-box {
            width: 100%;
            padding: 5px;
            text-align: center;
            background: #ffffff;
            border: 1px solid #dbe3ee;
            border-radius: 5px;
        }

        .chart-image {
            display: block;
            width: 100%;
            max-height: 235px;
            margin: 0 auto;
        }

        .page-break {
            page-break-before: always;
        }

        table.results {
            width: 100%;
            border-collapse: collapse;
        }

        table.results th {
            padding: 6px;
            color: #ffffff;
            text-align: left;
            background: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        table.results td {
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
        }

        table.results tr:nth-child(even) td {
            background: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .footer {
            margin-top: 9px;
            color: #64748b;
            text-align: center;
            font-size: 8px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ $school->name ?? 'SchoolPass' }}</h1>
        <p>Analítica de asistencia y accesos</p>
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
                <strong>Generado:</strong>
                {{ $generatedAt->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td>
                <div class="metric-label">
                    Alumnos activos
                </div>

                <div class="metric-value">
                    {{ number_format(
                        $summary['active_students']
                    ) }}
                </div>
            </td>

            <td>
                <div class="metric-label">
                    Entradas registradas
                </div>

                <div class="metric-value">
                    {{ number_format(
                        $summary['entries']
                    ) }}
                </div>
            </td>

            <td>
                <div class="metric-label">
                    Puntualidad
                </div>

                <div class="metric-value">
                    {{ number_format(
                        $summary['punctuality_rate'],
                        1
                    ) }}%
                </div>
            </td>

            <td>
                <div class="metric-label">
                    Retardos y extemporáneos
                </div>

                <div class="metric-value">
                    {{ number_format(
                        $summary['late']
                        + $summary['very_late']
                    ) }}
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h2 class="section-title">
            Tendencia diaria
        </h2>

        <div class="chart-box">
            <img
                src="{{ $dailyChartImage }}"
                class="chart-image"
                alt="Tendencia diaria"
            >
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">
            Comparativo por grupo
        </h2>

        <div class="chart-box">
            <img
                src="{{ $groupsChartImage }}"
                class="chart-image"
                alt="Comparativo por grupo"
            >
        </div>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <h2 class="section-title">
            Resultados por grupo
        </h2>

        <table class="results">
            <thead>
                <tr>
                    <th>Grupo</th>
                    <th>Nivel</th>
                    <th class="text-right">Alumnos</th>
                    <th class="text-right">Entradas</th>
                    <th class="text-right">Puntuales</th>
                    <th class="text-right">Retardos</th>
                    <th class="text-right">Extemporáneos</th>
                    <th class="text-right">Puntualidad</th>
                </tr>
            </thead>

            <tbody>
                @forelse($groupResults as $row)
                    <tr>
                        <td>{{ $row['group_name'] }}</td>
                        <td>{{ $row['level_name'] }}</td>

                        <td class="text-right">
                            {{ number_format($row['students']) }}
                        </td>

                        <td class="text-right">
                            {{ number_format($row['entries']) }}
                        </td>

                        <td class="text-right">
                            {{ number_format($row['on_time']) }}
                        </td>

                        <td class="text-right">
                            {{ number_format($row['late']) }}
                        </td>

                        <td class="text-right">
                            {{ number_format($row['very_late']) }}
                        </td>

                        <td class="text-right">
                            {{ number_format(
                                $row['punctuality_rate'],
                                1
                            ) }}%
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            No hay resultados para este periodo.
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