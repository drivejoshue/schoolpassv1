<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <title>Asistencia mensual</title>

    <style>
        @page {
            margin: 18px 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 6px;
        }

        .header {
            padding: 9px 12px;
            margin-bottom: 7px;
            color: #ffffff;
            background: #0f172a;
        }

        .header h1 {
            margin: 0 0 2px;
            font-size: 15px;
        }

        .header p {
            margin: 0;
            color: #cbd5e1;
            font-size: 8px;
        }

        .meta {
            width: 100%;
            margin-bottom: 7px;
            border-collapse: collapse;
        }

        .meta td {
            padding: 4px 5px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
        }

        .legend {
            margin-bottom: 6px;
            padding: 4px 6px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.report th {
            padding: 3px 1px;
            color: #ffffff;
            text-align: center;
            background: #1d4ed8;
            border: 1px solid #93c5fd;
            font-size: 5px;
        }

        table.report td {
            padding: 2px 1px;
            text-align: center;
            border: 1px solid #cbd5e1;
            overflow: hidden;
        }

        table.report .code {
            width: 42px;
            text-align: left;
        }

        table.report .student {
            width: 105px;
            text-align: left;
        }

        table.report .group {
            width: 62px;
            text-align: left;
        }

        table.report .day {
            width: 18px;
        }

        table.report .total {
            width: 20px;
            font-weight: bold;
        }

        .status-p {
            background: #dcfce7;
            color: #166534;
            font-weight: bold;
        }

        .status-r {
            background: #fef3c7;
            color: #92400e;
            font-weight: bold;
        }

        .status-e {
            background: #ffedd5;
            color: #9a3412;
            font-weight: bold;
        }

        .status-a {
            background: #fee2e2;
            color: #991b1b;
            font-weight: bold;
        }

        .status-sc {
            background: #e2e8f0;
            color: #475569;
            font-weight: bold;
        }

        .status-future {
            background: #f8fafc;
            color: #94a3b8;
        }

        .footer {
            margin-top: 6px;
            color: #64748b;
            text-align: center;
            font-size: 6px;
        }
    </style>
</head>

<body>
    @php
        $statusClasses = [
            'P' => 'status-p',
            'R' => 'status-r',
            'E' => 'status-e',
            'A' => 'status-a',
            'SC' => 'status-sc',
            '—' => 'status-future',
        ];
    @endphp

    <div class="header">
        <h1>
            {{ $school->name ?? 'SchoolPass' }}
        </h1>

        <p>
            Reporte mensual de asistencia
        </p>
    </div>

    <table class="meta">
        <tr>
            <td>
                <strong>Periodo:</strong>
                {{ $month_name }} {{ $filters['year'] }}
            </td>

            <td>
                <strong>Plantel:</strong>
                {{ $selectedCampus->name ?? 'Todos' }}
            </td>

            <td>
                <strong>Grupo:</strong>
                {{ $selectedGroup->name ?? 'Todos' }}
            </td>

            <td>
                <strong>Generado:</strong>
                {{ $generatedAt->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    <div class="legend">
        <strong>P</strong> Puntual ·
        <strong>R</strong> Retardo ·
        <strong>E</strong> Extemporáneo ·
        <strong>A</strong> Ausente ·
        <strong>SC</strong> Sin clase ·
        <strong>—</strong> Fecha futura
    </div>

    <table class="report">
        <thead>
            <tr>
                <th class="code">Matrícula</th>
                <th class="student">Alumno</th>
                <th class="group">Grupo</th>

                @foreach($days as $day)
                    <th class="day">
                        {{ str_pad(
                            $day['day'],
                            2,
                            '0',
                            STR_PAD_LEFT
                        ) }}
                    </th>
                @endforeach

                <th class="total">P</th>
                <th class="total">R</th>
                <th class="total">E</th>
                <th class="total">A</th>
                <th class="total">SC</th>
            </tr>
        </thead>

        <tbody>
            @forelse($students as $student)
                <tr>
                    <td class="code">
                        {{ $student['student_code'] }}
                    </td>

                    <td class="student">
                        {{ $student['full_name'] }}
                    </td>

                    <td class="group">
                        {{ $student['group_name'] }}
                    </td>

                    @foreach($days as $day)
                        @php
                            $status = $student['days'][
                                $day['date']
                            ] ?? '—';
                        @endphp

                        <td class="{{
                            $statusClasses[$status]
                            ?? ''
                        }}">
                            {{ $status }}
                        </td>
                    @endforeach

                    <td class="total status-p">
                        {{ $student['totals']['P'] }}
                    </td>

                    <td class="total status-r">
                        {{ $student['totals']['R'] }}
                    </td>

                    <td class="total status-e">
                        {{ $student['totals']['E'] }}
                    </td>

                    <td class="total status-a">
                        {{ $student['totals']['A'] }}
                    </td>

                    <td class="total status-sc">
                        {{ $student['totals']['SC'] }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($days) + 8 }}">
                        No hay alumnos para este periodo.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Alumnos: {{ $summary['students'] }} ·
        Puntuales: {{ $summary['P'] }} ·
        Retardos: {{ $summary['R'] }} ·
        Extemporáneos: {{ $summary['E'] }} ·
        Ausencias: {{ $summary['A'] }} ·
        Puntualidad:
        {{ number_format(
            $summary['punctuality_rate'],
            1
        ) }}%
    </div>
</body>
</html>