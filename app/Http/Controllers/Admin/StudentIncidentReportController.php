<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Attendance\AttendancePeriodService;

class StudentIncidentReportController extends Controller
{
    public function __construct(
    private readonly AttendancePeriodService $attendancePeriod
) {
}

    public function index(Request $request): View
    {
        $schoolId = $this->schoolId($request);
        $filters = $this->filters($request);

        $report = $this->buildReport(
            schoolId: $schoolId,
            filters: $filters
        );

        return view('admin.reports.student-incidents', [
            'filters' => $filters,
            'groups' => $this->groups($schoolId),
         $report,
        ]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $schoolId = $this->schoolId($request);
        $filters = $this->filters($request);

        $report = $this->buildReport(
            schoolId: $schoolId,
            filters: $filters
        );

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->first();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Incidencias');

        $lastColumn = 'M';

        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->mergeCells('A3:'.$lastColumn.'3');

        $sheet->setCellValue(
            'A1',
            $school?->name ?? 'SchoolPass'
        );

        $sheet->setCellValue(
            'A2',
            'Reporte de incidencias por alumno'
        );

        $sheet->setCellValue(
            'A3',
            sprintf(
                'Periodo %s al %s · Generado %s',
                Carbon::parse($filters['from'])->format('d/m/Y'),
                Carbon::parse($filters['to'])->format('d/m/Y'),
                now()->format('d/m/Y H:i')
            )
        );

        $sheet->getStyle('A1:'.$lastColumn.'1')
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 16,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0F172A'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);

        $sheet->getStyle('A2:'.$lastColumn.'2')
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 13,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);

        $sheet->getStyle('A3:'.$lastColumn.'3')
            ->applyFromArray([
                'font' => [
                    'italic' => true,
                    'color' => ['rgb' => '64748B'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);

        $headers = [
            'Matrícula',
            'Alumno',
            'Nivel',
            'Grupo',
            'Tipo de incidencia',
            'Cantidad',
            'Última fecha',
            'Riesgo',
            'Puntualidad',
            'Retardos',
            'Extemporáneos',
            'Ausencias',
            'Acción sugerida',
        ];

        $sheet->fromArray($headers, null, 'A5');

        $sheet->getStyle('A5:'.$lastColumn.'5')
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1D4ED8'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'BFDBFE'],
                    ],
                ],
            ]);

        $rowNumber = 6;

        foreach ($report['incidents'] as $incident) {
            $sheet->setCellValueExplicit(
                'A'.$rowNumber,
                (string) $incident['student_code'],
                DataType::TYPE_STRING
            );

            $sheet->fromArray([
                $incident['full_name'],
                $incident['level_name'],
                $incident['group_name'],
                $incident['type_label'],
                $incident['quantity'],
                $incident['last_date']
                    ? Carbon::parse($incident['last_date'])->format('d/m/Y')
                    : '',
                $incident['risk_label'],
                $incident['punctuality_rate'].'%',
                $incident['late'],
                $incident['very_late'],
                $incident['absent'],
                $incident['suggested_action'],
            ], null, 'B'.$rowNumber);

            $this->styleRiskCell(
                $sheet,
                'H'.$rowNumber,
                $incident['risk']
            );

            $rowNumber++;
        }

        $lastRow = max(6, $rowNumber - 1);

        $sheet->getStyle('A6:'.$lastColumn.$lastRow)
            ->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_HAIR,
                        'color' => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);

        $widths = [
            'A' => 16,
            'B' => 30,
            'C' => 18,
            'D' => 20,
            'E' => 30,
            'F' => 12,
            'G' => 16,
            'H' => 14,
            'I' => 14,
            'J' => 12,
            'K' => 18,
            'L' => 12,
            'M' => 46,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)
                ->setWidth($width);
        }

        $sheet->freezePane('A6');
        $sheet->setAutoFilter('A5:'.$lastColumn.$lastRow);

        $directory = storage_path(
            'app/private/report-exports/school_'.$schoolId
        );

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = sprintf(
            'incidencias_%s_%s_%s.xlsx',
            $filters['from'],
            $filters['to'],
            now()->format('Ymd_His')
        );

        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $spreadsheet->disconnectWorksheets();

        return response()
            ->download(
                $path,
                $filename,
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(true);
    }

    public function pdf(Request $request): Response
    {
        $schoolId = $this->schoolId($request);
        $filters = $this->filters($request);

        $report = $this->buildReport(
            schoolId: $schoolId,
            filters: $filters
        );

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->first();

        $selectedGroup = null;

        if ($filters['group_id']) {
            $selectedGroup = DB::table('school_groups')
                ->where('school_id', $schoolId)
                ->where('id', $filters['group_id'])
                ->first();
        }

        $pdf = Pdf::loadView(
            'admin.reports.student-incidents-pdf',
            [
                'school' => $school,
                'selectedGroup' => $selectedGroup,
                'filters' => $filters,
                'generatedAt' => now(),
                ...$report,
            ]
        )
            ->setPaper('letter', 'landscape')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);

        $filename = sprintf(
            'incidencias_%s_%s.pdf',
            $filters['from'],
            $filters['to']
        );

        return $pdf->download($filename);
    }

   private function buildReport(
    int $schoolId,
    array $filters
): array {
    $requestedFrom = Carbon::parse(
        $filters['from']
    )->startOfDay();

    $requestedTo = Carbon::parse(
        $filters['to']
    )->endOfDay();

    /*
     * Ventana de asistencia válida.
     *
     * Puede ser null si:
     * - no hay ciclo activo;
     * - el rango está fuera del ciclo;
     * - el ciclo todavía no comienza.
     */
    $activeWindow = $this->attendancePeriod
        ->attendanceWindow($schoolId);

    $effectiveRange = $this->attendancePeriod
        ->clampRange(
            schoolId: $schoolId,
            from: $requestedFrom,
            to: $requestedTo
        );

    $students = DB::table('students as s')
        ->leftJoin(
            'school_groups as g',
            'g.id',
            '=',
            's.current_group_id'
        )
        ->leftJoin(
            'academic_levels as l',
            'l.id',
            '=',
            'g.academic_level_id'
        )
        ->where('s.school_id', $schoolId)
        ->where('s.status', 'active')
        ->when(
            $filters['group_id'],
            fn ($query, $groupId) =>
                $query->where(
                    's.current_group_id',
                    $groupId
                )
        )
        ->orderBy('l.sort_order')
        ->orderBy('g.name')
        ->orderBy('s.last_name')
        ->orderBy('s.first_name')
        ->get([
            's.id',
            's.student_code',
            's.first_name',
            's.last_name',
            's.current_group_id',

            'g.name as group_name',
            'l.name as level_name',
        ]);

    $studentIds = $students
        ->pluck('id')
        ->map(
            fn ($id): int => (int) $id
        )
        ->all();

    $attendanceByStudent = collect();
    $accessByStudent = collect();

    /*
     * La asistencia solo se consulta dentro del ciclo activo.
     */
    if (
        $studentIds !== []
        && $effectiveRange !== null
    ) {
        $attendanceByStudent = $this
            ->attendanceRows(
                schoolId: $schoolId,
                studentIds: $studentIds,
                from: $effectiveRange['from'],
                to: $effectiveRange['to']
            )
            ->groupBy('student_id');
    }

    /*
     * Los accesos son eventos reales y se consultan en todo
     * el rango solicitado, exista o no ciclo activo.
     */
    if ($studentIds !== []) {
        $accessByStudent = DB::table(
            'access_logs'
        )
            ->where('school_id', $schoolId)
            ->whereIn(
                'student_id',
                $studentIds
            )
            ->whereBetween(
                'scanned_at',
                [
                    $requestedFrom,
                    $requestedTo,
                ]
            )
            ->orderBy('scanned_at')
            ->get([
                'student_id',
                'event_type',
                'event_status',
                'scanned_at',
            ])
            ->groupBy('student_id');
    }

    /*
     * Solo se generan fechas de clase cuando existe
     * una intersección válida con el ciclo activo.
     */
    $classDates = [];

    if ($effectiveRange !== null) {
        $calendarDays = $this->calendarDays(
            schoolId: $schoolId,
            from: $effectiveRange['from'],
            to: $effectiveRange['to']
        );

        $classDates = $this->classDates(
            from: $effectiveRange['from'],
            to: $effectiveRange['to'],
            calendarDays: $calendarDays
        );
    }

    $incidents = collect();

    foreach ($students as $student) {
        $attendance = $attendanceByStudent
            ->get(
                $student->id,
                collect()
            )
            ->keyBy('date');

        $accessLogs = $accessByStudent
            ->get(
                $student->id,
                collect()
            );

        /*
         * Si no hay ciclo activo, classDates estará vacío:
         *
         * - no habrá ausencias;
         * - no habrá retardos calculados;
         * - no habrá baja puntualidad;
         *
         * pero sí podrán aparecer accesos denegados
         * y salidas anticipadas reales.
         */
        $metrics = $this->studentMetrics(
            attendance: $attendance,
            accessLogs: $accessLogs,
            classDates: $classDates
        );

        $studentIncidents = $this->detectIncidents(
            student: $student,
            metrics: $metrics
        );

        foreach ($studentIncidents as $incident) {
            $incidents->push($incident);
        }
    }

    $incidents = $incidents
        ->when(
            $filters['type'],
            fn (Collection $rows) =>
                $rows->filter(
                    fn (array $row): bool =>
                        $row['type']
                        === $filters['type']
                )
        )
        ->when(
            $filters['risk'],
            fn (Collection $rows) =>
                $rows->filter(
                    fn (array $row): bool =>
                        $row['risk']
                        === $filters['risk']
                )
        )
        ->sortBy(function (array $row): string {
            $riskOrder = match ($row['risk']) {
                'high' => '1',
                'medium' => '2',
                default => '3',
            };

            return $riskOrder
                .'|'
                .mb_strtolower(
                    $row['full_name']
                );
        })
        ->values();

    $summary = [
        'total' => $incidents->count(),

        'students' => $incidents
            ->pluck('student_id')
            ->unique()
            ->count(),

        'high' => $incidents
            ->where('risk', 'high')
            ->count(),

        'medium' => $incidents
            ->where('risk', 'medium')
            ->count(),

        'low' => $incidents
            ->where('risk', 'low')
            ->count(),

        'late' => $incidents
            ->where('type', 'repeated_late')
            ->count(),

        'absences' => $incidents
            ->where(
                'type',
                'consecutive_absence'
            )
            ->count(),

        'denied' => $incidents
            ->where(
                'type',
                'denied_access'
            )
            ->count(),
    ];

    $topStudents = $incidents
        ->groupBy('student_id')
        ->map(function (
            Collection $rows
        ): array {
            $first = $rows->first();

            return [
                'student_id' =>
                    $first['student_id'],

                'student_code' =>
                    $first['student_code'],

                'full_name' =>
                    $first['full_name'],

                'group_name' =>
                    $first['group_name'],

                'incidents_count' =>
                    $rows->count(),

                'high_count' => $rows
                    ->where('risk', 'high')
                    ->count(),
            ];
        })
        ->sortByDesc(
            function (array $row): int {
                return (
                    $row['high_count'] * 100
                ) + $row['incidents_count'];
            }
        )
        ->take(10)
        ->values();

    return [
        'incidents' => $incidents,
        'summary' => $summary,
        'topStudents' => $topStudents,

        'activeCycle' =>
            $activeWindow['cycle'] ?? null,

        'hasActiveCycle' =>
            $activeWindow !== null,

        'hasEffectiveRange' =>
            $effectiveRange !== null,

        'effectiveFrom' =>
            $effectiveRange !== null
                ? $effectiveRange['from']
                : null,

        'effectiveTo' =>
            $effectiveRange !== null
                ? $effectiveRange['to']
                : null,
    ];
}

    private function attendanceRows(
        int $schoolId,
        array $studentIds,
        Carbon $from,
        Carbon $to
    ): Collection {
        $statusSql = $this->attendanceStatusSql();

        $minutesSql = $this->attendanceColumnSql(
            ['minutes_late', 'late_minutes'],
            '0'
        );

        return DB::table('daily_attendance as da')
            ->where('da.school_id', $schoolId)
            ->whereIn('da.student_id', $studentIds)
            ->whereBetween('da.date', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->select([
                'da.student_id',
                'da.date',
                DB::raw($statusSql.' as attendance_status'),
                DB::raw($minutesSql.' as minutes_late'),
            ])
            ->get()
            ->map(function ($row): object {
                $status = (string) (
                    $row->attendance_status ?? ''
                );

                if ($status === '') {
                    $status = $this->statusFromMinutes(
                        (int) ($row->minutes_late ?? 0)
                    );
                }

                $row->final_status = $status;

                return $row;
            });
    }

    private function studentMetrics(
        Collection $attendance,
        Collection $accessLogs,
        array $classDates
    ): array {
        $lateDates = [];
        $veryLateDates = [];
        $absentDates = [];

        foreach ($classDates as $date) {
            $record = $attendance->get($date);

            if (! $record) {
                $absentDates[] = $date;
                continue;
            }

            if ($record->final_status === 'late') {
                $lateDates[] = $date;
            }

            if ($record->final_status === 'very_late') {
                $veryLateDates[] = $date;
            }

            if ($record->final_status === 'absent') {
                $absentDates[] = $date;
            }
        }

        $onTime = $attendance
            ->where('final_status', 'on_time')
            ->count();

        $late = count($lateDates);
        $veryLate = count($veryLateDates);
        $classified = $onTime + $late + $veryLate;

        $punctualityRate = $classified > 0
            ? round(($onTime / $classified) * 100, 1)
            : 0;

        $earlyExitLogs = $accessLogs
            ->where('event_status', 'early_exit')
            ->values();

        $deniedLogs = $accessLogs
            ->where('decision', 'denied')
            ->values();

        return [
            'on_time' => $onTime,
            'late' => $late,
            'very_late' => $veryLate,
            'absent' => count($absentDates),
            'late_dates' => $lateDates,
            'very_late_dates' => $veryLateDates,
            'absent_dates' => $absentDates,
            'max_absence_streak' =>
                $this->maximumConsecutiveDates($absentDates),

            'last_absence_streak_date' =>
                $absentDates !== []
                    ? end($absentDates)
                    : null,

            'early_exits' => $earlyExitLogs->count(),
            'last_early_exit' => $earlyExitLogs
                ->max('scanned_at'),

            'denied_accesses' => $deniedLogs->count(),
            'last_denied_access' => $deniedLogs
                ->max('scanned_at'),

            'punctuality_rate' => $punctualityRate,
        ];
    }

    private function detectIncidents(
        object $student,
        array $metrics
    ): array {
        $rows = [];

        $base = [
            'student_id' => (int) $student->id,
            'student_code' => $student->student_code,
            'full_name' => trim(
                $student->first_name.' '.$student->last_name
            ),
            'level_name' => $student->level_name ?? 'Sin nivel',
            'group_name' => $student->group_name ?? 'Sin grupo',
            'punctuality_rate' => $metrics['punctuality_rate'],
            'late' => $metrics['late'],
            'very_late' => $metrics['very_late'],
            'absent' => $metrics['absent'],
        ];

        if ($metrics['late'] >= 3) {
            $risk = $metrics['late'] >= 6
                ? 'high'
                : 'medium';

            $rows[] = [
                ...$base,
                'type' => 'repeated_late',
                'type_label' => 'Retardos recurrentes',
                'quantity' => $metrics['late'],
                'last_date' => $this->lastDate(
                    $metrics['late_dates']
                ),
                'risk' => $risk,
                'risk_label' => $this->riskLabel($risk),
                'suggested_action' =>
                    'Revisar horario de llegada y contactar al tutor.',
            ];
        }

        if ($metrics['very_late'] >= 2) {
            $risk = $metrics['very_late'] >= 4
                ? 'high'
                : 'medium';

            $rows[] = [
                ...$base,
                'type' => 'repeated_very_late',
                'type_label' => 'Entradas extemporáneas recurrentes',
                'quantity' => $metrics['very_late'],
                'last_date' => $this->lastDate(
                    $metrics['very_late_dates']
                ),
                'risk' => $risk,
                'risk_label' => $this->riskLabel($risk),
                'suggested_action' =>
                    'Solicitar seguimiento con tutor y coordinación.',
            ];
        }

        if ($metrics['max_absence_streak'] >= 2) {
            $risk = $metrics['max_absence_streak'] >= 3
                ? 'high'
                : 'medium';

            $rows[] = [
                ...$base,
                'type' => 'consecutive_absence',
                'type_label' => 'Ausencias consecutivas',
                'quantity' => $metrics['max_absence_streak'],
                'last_date' =>
                    $metrics['last_absence_streak_date'],
                'risk' => $risk,
                'risk_label' => $this->riskLabel($risk),
                'suggested_action' =>
                    'Confirmar causa de las ausencias con el tutor.',
            ];
        }

        if ($metrics['early_exits'] >= 1) {
            $risk = $metrics['early_exits'] >= 3
                ? 'medium'
                : 'low';

            $rows[] = [
                ...$base,
                'type' => 'early_exit',
                'type_label' => 'Salidas anticipadas',
                'quantity' => $metrics['early_exits'],
                'last_date' => $metrics['last_early_exit'],
                'risk' => $risk,
                'risk_label' => $this->riskLabel($risk),
                'suggested_action' =>
                    'Verificar autorizaciones y motivos de salida.',
            ];
        }

        if ($metrics['denied_accesses'] >= 1) {
            $risk = $metrics['denied_accesses'] >= 3
                ? 'high'
                : 'medium';

            $rows[] = [
                ...$base,
                'type' => 'denied_access',
                'type_label' => 'Accesos denegados',
                'quantity' => $metrics['denied_accesses'],
                'last_date' => $metrics['last_denied_access'],
                'risk' => $risk,
                'risk_label' => $this->riskLabel($risk),
                'suggested_action' =>
                    'Revisar credencial, área, horario y permisos.',
            ];
        }

        if (
            ($metrics['on_time']
                + $metrics['late']
                + $metrics['very_late']) >= 3
            && $metrics['punctuality_rate'] < 80
        ) {
            $risk = $metrics['punctuality_rate'] < 60
                ? 'high'
                : 'medium';

            $rows[] = [
                ...$base,
                'type' => 'low_punctuality',
                'type_label' => 'Baja puntualidad',
                'quantity' => $metrics['punctuality_rate'].'%',
                'last_date' => null,
                'risk' => $risk,
                'risk_label' => $this->riskLabel($risk),
                'suggested_action' =>
                    'Definir plan de mejora y seguimiento semanal.',
            ];
        }

        return $rows;
    }

    private function calendarDays(
        int $schoolId,
        Carbon $from,
        Carbon $to
    ): Collection {
        return DB::table('school_calendar_days')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereBetween('date', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->get()
            ->keyBy('date');
    }

    private function classDates(
        Carbon $from,
        Carbon $to,
        Collection $calendarDays
    ): array {
        $dates = [];
        $cursor = $from->copy()->startOfDay();
        $limit = min(
            $to->copy()->endOfDay(),
            now()->endOfDay()
        );

        while ($cursor->lte($limit)) {
            $date = $cursor->toDateString();
            $calendarDay = $calendarDays->get($date);

            if ($this->isClassDay($cursor, $calendarDay)) {
                $dates[] = $date;
            }

            $cursor->addDay();
        }

        return $dates;
    }

    private function maximumConsecutiveDates(
        array $dates
    ): int {
        if ($dates === []) {
            return 0;
        }

        sort($dates);

        $maximum = 1;
        $current = 1;
        $previous = null;

        foreach ($dates as $date) {
            $currentDate = Carbon::parse($date);

            if (
                $previous
                && $previous->copy()->addWeekday()
                    ->isSameDay($currentDate)
            ) {
                $current++;
            } elseif ($previous !== null) {
                $current = 1;
            }

            $maximum = max($maximum, $current);
            $previous = $currentDate;
        }

        return $maximum;
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'group_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:50'],
            'risk' => ['nullable', 'in:low,medium,high'],
        ]);

        $from = Carbon::parse(
            $validated['from']
                ?? now()->subDays(29)->toDateString()
        )->toDateString();

        $to = Carbon::parse(
            $validated['to']
                ?? now()->toDateString()
        )->toDateString();

        if (Carbon::parse($from)->gt(Carbon::parse($to))) {
            [$from, $to] = [$to, $from];
        }

        $maximumTo = Carbon::parse($from)
            ->addDays(365)
            ->toDateString();

        if (Carbon::parse($to)->gt($maximumTo)) {
            $to = $maximumTo;
        }

        return [
            'from' => $from,
            'to' => $to,

            'group_id' => ! empty($validated['group_id'])
                ? (int) $validated['group_id']
                : null,

            'type' => $validated['type'] ?? null,
            'risk' => $validated['risk'] ?? null,
        ];
    }

    private function groups(int $schoolId): Collection
    {
        $activeCycleId = DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->value('id');

        return DB::table('school_groups as g')
            ->leftJoin(
                'academic_levels as l',
                'l.id',
                '=',
                'g.academic_level_id'
            )
            ->where('g.school_id', $schoolId)
            ->when(
                $activeCycleId,
                fn ($query, $cycleId) => $query->where(
                    'g.academic_cycle_id',
                    $cycleId
                )
            )
            ->where('g.status', 'active')
            ->orderBy('l.sort_order')
            ->orderBy('g.name')
            ->get([
                'g.id',
                'g.name',
                'g.grade_label',
                'l.name as level_name',
            ]);
    }

    private function attendanceStatusSql(): string
    {
        if (Schema::hasColumn('daily_attendance', 'status')) {
            return 'da.status';
        }

        if (
            Schema::hasColumn(
                'daily_attendance',
                'entry_status'
            )
        ) {
            return 'da.entry_status';
        }

        if (
            Schema::hasColumn(
                'daily_attendance',
                'attendance_status'
            )
        ) {
            return 'da.attendance_status';
        }

        if (
            Schema::hasColumn(
                'daily_attendance',
                'minutes_late'
            )
        ) {
            return "
                CASE
                    WHEN da.minutes_late IS NULL
                        THEN 'on_time'
                    WHEN da.minutes_late <= 0
                        THEN 'on_time'
                    WHEN da.minutes_late <= 20
                        THEN 'late'
                    ELSE 'very_late'
                END
            ";
        }

        return "'on_time'";
    }

    private function attendanceColumnSql(
        array $possibleColumns,
        string $fallback
    ): string {
        foreach ($possibleColumns as $column) {
            if (
                Schema::hasColumn(
                    'daily_attendance',
                    $column
                )
            ) {
                return 'da.'.$column;
            }
        }

        return $fallback;
    }

    private function statusFromMinutes(
        int $minutesLate
    ): string {
        if ($minutesLate <= 0) {
            return 'on_time';
        }

        if ($minutesLate <= 20) {
            return 'late';
        }

        return 'very_late';
    }

    private function isClassDay(
        Carbon $date,
        ?object $calendarDay
    ): bool {
        if ($calendarDay) {
            $type = (string) $calendarDay->type;

            if (in_array($type, [
                'holiday',
                'vacation',
                'suspension',
                'technical_council',
                'no_class',
            ], true)) {
                return false;
            }

            if (in_array($type, [
                'class',
                'school_day',
                'special_class',
                'makeup_class',
            ], true)) {
                return true;
            }
        }

        return ! $date->isWeekend();
    }

    private function riskLabel(string $risk): string
    {
        return match ($risk) {
            'high' => 'Alto',
            'medium' => 'Medio',
            default => 'Bajo',
        };
    }

    private function lastDate(array $dates): ?string
    {
        if ($dates === []) {
            return null;
        }

        sort($dates);

        return end($dates) ?: null;
    }

    private function styleRiskCell(
        $sheet,
        string $cell,
        string $risk
    ): void {
        $colors = [
            'high' => 'FEE2E2',
            'medium' => 'FEF3C7',
            'low' => 'DBEAFE',
        ];

        $sheet->getStyle($cell)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB($colors[$risk] ?? 'FFFFFF');

        $sheet->getStyle($cell)
            ->getFont()
            ->setBold(true);
    }

    private function schoolId(Request $request): int
    {
        $user = $request->user();

        abort_unless(
            $user && $user->school_id,
            403
        );

        return (int) $user->school_id;
    }
}