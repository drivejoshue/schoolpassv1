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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Attendance\AttendancePeriodService;

class MonthlyAttendanceReportController extends Controller
{

    public function __construct(
    private readonly AttendancePeriodService $attendancePeriod
) {
}

    public function index(Request $request): View
    {
        $schoolId = $this->schoolId($request);
        $filters = $this->filters($request);
        $report = $this->buildReport($schoolId, $filters);

        return view('admin.reports.monthly-attendance', [
            'filters' => $filters,
            'campuses' => $this->campuses($schoolId),
            'groups' => $this->groups($schoolId),
            ...$report,
        ]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $schoolId = $this->schoolId($request);
        $filters = $this->filters($request);
        $report = $this->buildReport($schoolId, $filters);

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->first();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Asistencia mensual');

        $daysCount = count($report['days']);

        /*
         * Columnas:
         * A Matrícula
         * B Alumno
         * C Plantel
         * D Nivel
         * E Grupo
         * F en adelante: días
         * Después: P, R, E, A, SC
         */
        $firstDayColumn = 6;
        $lastDayColumn = $firstDayColumn + $daysCount - 1;

        $summaryStartColumn = $lastDayColumn + 1;
        $lastColumnIndex = $summaryStartColumn + 4;

        $lastColumn = Coordinate::stringFromColumnIndex(
            $lastColumnIndex
        );

        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->mergeCells('A3:'.$lastColumn.'3');

        $sheet->setCellValue(
            'A1',
            $school?->name ?? 'SchoolPass'
        );

        $sheet->setCellValue(
            'A2',
            'Reporte mensual de asistencia'
        );

        $sheet->setCellValue(
            'A3',
            sprintf(
                '%s %d · Generado el %s',
                $report['month_name'],
                $filters['year'],
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
                    'horizontal' =>
                        Alignment::HORIZONTAL_CENTER,
                    'vertical' =>
                        Alignment::VERTICAL_CENTER,
                ],
            ]);

        $sheet->getStyle('A2:'.$lastColumn.'2')
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 13,
                ],
                'alignment' => [
                    'horizontal' =>
                        Alignment::HORIZONTAL_CENTER,
                ],
            ]);

        $sheet->getStyle('A3:'.$lastColumn.'3')
            ->applyFromArray([
                'font' => [
                    'italic' => true,
                    'color' => ['rgb' => '64748B'],
                ],
                'alignment' => [
                    'horizontal' =>
                        Alignment::HORIZONTAL_CENTER,
                ],
            ]);

        $headerRow = 5;

        $headers = [
            'Matrícula',
            'Alumno',
            'Plantel',
            'Nivel',
            'Grupo',
        ];

        foreach ($report['days'] as $day) {
            $headers[] = str_pad(
                (string) $day['day'],
                2,
                '0',
                STR_PAD_LEFT
            );
        }

        $headers = [
            ...$headers,
            'P',
            'R',
            'E',
            'A',
            'SC',
        ];

        $sheet->fromArray(
            $headers,
            null,
            'A'.$headerRow
        );

        $sheet->getStyle(
            'A'.$headerRow.':'.$lastColumn.$headerRow
        )->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D4ED8'],
            ],
            'alignment' => [
                'horizontal' =>
                    Alignment::HORIZONTAL_CENTER,
                'vertical' =>
                    Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' =>
                        Border::BORDER_THIN,
                    'color' => ['rgb' => 'BFDBFE'],
                ],
            ],
        ]);

        $dataRow = 6;

        foreach ($report['students'] as $student) {
            $sheet->setCellValueExplicit(
                'A'.$dataRow,
                (string) $student['student_code'],
                DataType::TYPE_STRING
            );

            $sheet->setCellValue(
                'B'.$dataRow,
                $student['full_name']
            );

            $sheet->setCellValue(
                'C'.$dataRow,
                $student['campus_name']
            );

            $sheet->setCellValue(
                'D'.$dataRow,
                $student['level_name']
            );

            $sheet->setCellValue(
                'E'.$dataRow,
                $student['group_name']
            );

            $columnIndex = $firstDayColumn;

            foreach ($report['days'] as $day) {
                $column = Coordinate::stringFromColumnIndex(
                    $columnIndex
                );

                $status = $student['days'][
                    $day['date']
                ] ?? '—';

                $sheet->setCellValue(
                    $column.$dataRow,
                    $status
                );

                $this->styleStatusCell(
                    $sheet,
                    $column.$dataRow,
                    $status
                );

                $columnIndex++;
            }

            $summaryValues = [
                $student['totals']['P'],
                $student['totals']['R'],
                $student['totals']['E'],
                $student['totals']['A'],
                $student['totals']['SC'],
            ];

            foreach (
                $summaryValues as $offset => $value
            ) {
                $column = Coordinate::stringFromColumnIndex(
                    $summaryStartColumn + $offset
                );

                $sheet->setCellValue(
                    $column.$dataRow,
                    $value
                );
            }

            $dataRow++;
        }

        $lastDataRow = max(6, $dataRow - 1);

        $sheet->getStyle(
            'A6:'.$lastColumn.$lastDataRow
        )->applyFromArray([
            'alignment' => [
                'vertical' =>
                    Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' =>
                        Border::BORDER_HAIR,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ]);

        $sheet->getStyle(
            'F6:'.$lastColumn.$lastDataRow
        )->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_CENTER
            );

        $sheet->freezePane('F6');

        $sheet->setAutoFilter(
            'A'.$headerRow.':'.$lastColumn.$lastDataRow
        );

        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(32);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(20);

        for (
            $columnIndex = $firstDayColumn;
            $columnIndex <= $lastColumnIndex;
            $columnIndex++
        ) {
            $column = Coordinate::stringFromColumnIndex(
                $columnIndex
            );

            $sheet->getColumnDimension($column)
                ->setWidth(5);
        }

        $sheet->getPageSetup()
            ->setOrientation(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
            )
            ->setPaperSize(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL
            )
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $directory = storage_path(
            'app/private/report-exports/school_'.$schoolId
        );

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = sprintf(
            'asistencia_mensual_%d_%02d_%s.xlsx',
            $filters['year'],
            $filters['month'],
            now()->format('Ymd_His')
        );

        $path = $directory
            .DIRECTORY_SEPARATOR
            .$filename;

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
        $report = $this->buildReport($schoolId, $filters);

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->first();

        $selectedCampus = null;
        $selectedGroup = null;

        if ($filters['campus_id']) {
            $selectedCampus = DB::table('campuses')
                ->where('school_id', $schoolId)
                ->where('id', $filters['campus_id'])
                ->first();
        }

        if ($filters['group_id']) {
            $selectedGroup = DB::table('school_groups')
                ->where('school_id', $schoolId)
                ->where('id', $filters['group_id'])
                ->first();
        }

        $pdf = Pdf::loadView(
            'admin.reports.monthly-attendance-pdf',
            [
                'school' => $school,
                'selectedCampus' => $selectedCampus,
                'selectedGroup' => $selectedGroup,
                'filters' => $filters,
                'generatedAt' => now(),
                ...$report,
            ]
        )
            ->setPaper('legal', 'landscape')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);

        $filename = sprintf(
            'asistencia_mensual_%d_%02d.pdf',
            $filters['year'],
            $filters['month']
        );

        return $pdf->download($filename);
    }

    private function buildReport(
    int $schoolId,
    array $filters
): array {
    /*
     * Mes solicitado por el usuario.
     */
    $start = Carbon::create(
        (int) $filters['year'],
        (int) $filters['month'],
        1
    )->startOfMonth();

    $end = $start->copy()->endOfMonth();

    /*
     * El ciclo activo debe cumplir:
     *
     * status = active
     * is_active = 1
     *
     * Un ciclo draft no permite inferir ausencias.
     */
    $activeWindow = $this->attendancePeriod
        ->attendanceWindow($schoolId);

    /*
     * Intersección entre el mes consultado y el ciclo activo.
     *
     * Será null cuando:
     * - no haya ciclo activo;
     * - el mes esté antes del ciclo;
     * - el mes esté después del ciclo;
     * - el rango todavía no haya comenzado.
     */
    $effectiveRange = $this->attendancePeriod
        ->clampRange(
            schoolId: $schoolId,
            from: $start,
            to: $end
        );

    /*
     * Alumnos pertenecientes exclusivamente a esta escuela.
     */
   $studentRows = collect();

if ($activeWindow !== null) {
    $activeCycleId = (int) $activeWindow[
        'cycle'
    ]->id;

    $studentRows = DB::table(
        'student_enrollments as se'
    )
        ->join(
            'students as s',
            's.id',
            '=',
            'se.student_id'
        )
        ->leftJoin(
            'campuses as c',
            'c.id',
            '=',
            'se.campus_id'
        )
        ->leftJoin(
            'school_groups as g',
            'g.id',
            '=',
            'se.school_group_id'
        )
        ->leftJoin(
            'academic_levels as l',
            'l.id',
            '=',
            'g.academic_level_id'
        )
        ->where(
            'se.school_id',
            $schoolId
        )
        ->where(
            'se.academic_cycle_id',
            $activeCycleId
        )
        ->where(
            'se.status',
            'active'
        )
        ->where(
            's.status',
            'active'
        )
        ->when(
            $filters['campus_id'],
            fn ($query, $campusId) =>
                $query->where(
                    'se.campus_id',
                    $campusId
                )
        )
        ->when(
            $filters['group_id'],
            fn ($query, $groupId) =>
                $query->where(
                    'se.school_group_id',
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

            'se.enrolled_on',
            'se.completed_on',
            'se.withdrawn_on',

            'c.name as campus_name',
            'l.name as level_name',
            'g.name as group_name',
        ]);
}
    $studentIds = $studentRows
        ->pluck('id')
        ->map(
            fn ($id): int => (int) $id
        )
        ->all();

    /*
     * Solamente consultamos asistencia dentro de la parte válida
     * del ciclo activo.
     */
    $attendance = collect();

    if (
        $studentIds !== []
        && $effectiveRange !== null
    ) {
        $statusSql = $this->attendanceStatusSql();

        $minutesSql = $this->attendanceColumnSql(
            [
                'minutes_late',
                'late_minutes',
            ],
            '0'
        );

        $attendance = DB::table(
            'daily_attendance as da'
        )
            ->where('da.school_id', $schoolId)
            ->whereIn(
                'da.student_id',
                $studentIds
            )
            ->whereBetween(
                'da.date',
                [
                    $effectiveRange['from']
                        ->toDateString(),

                    $effectiveRange['to']
                        ->toDateString(),
                ]
            )
            ->select([
                'da.student_id',
                'da.date',

                DB::raw(
                    $statusSql
                    .' as attendance_status'
                ),

                DB::raw(
                    $minutesSql
                    .' as minutes_late'
                ),
            ])
            ->get()
            ->keyBy(
                fn ($row): string =>
                    (int) $row->student_id
                    .'|'
                    .$row->date
            );
    }

    /*
     * Las excepciones del calendario se consultan para todo
     * el mes visualizado.
     */
    $calendarDays = DB::table(
        'school_calendar_days'
    )
        ->where('school_id', $schoolId)
        ->where('status', 'active')
        ->whereBetween(
            'date',
            [
                $start->toDateString(),
                $end->toDateString(),
            ]
        )
        ->get()
        ->keyBy('date');

    /*
     * Construcción de las columnas del mes.
     */
    $days = [];
    $cursor = $start->copy()->startOfDay();
    $today = now()->endOfDay();

    while ($cursor->lte($end)) {
        $date = $cursor->toDateString();
        $calendarDay = $calendarDays->get($date);

        $isFuture = $cursor->gt($today);

        /*
         * Sin ciclo activo, siempre será false.
         */
        $isInsideCycle = $activeWindow !== null
            && $cursor->betweenIncluded(
                $activeWindow['start'],
                $activeWindow['end']
            );

        /*
         * Solo es día de clase cuando:
         *
         * - está dentro del ciclo activo;
         * - no es una fecha futura;
         * - no es fin de semana o excepción sin clase.
         */
        $isClassDay = $isInsideCycle
            && ! $isFuture
            && $this->isClassDay(
                $cursor,
                $calendarDay
            );

        $days[] = [
            'date' => $date,
            'day' => $cursor->day,
            'weekday' => $cursor
                ->locale('es')
                ->isoFormat('dd'),

            'is_inside_cycle' => $isInsideCycle,
            'is_class_day' => $isClassDay,
            'is_future' => $isFuture,

            'calendar_type' =>
                $calendarDay->type ?? null,

            'calendar_title' =>
                $calendarDay->title ?? null,
        ];

        $cursor->addDay();
    }

    /*
     * Matriz mensual por alumno.
     */
    $students = $studentRows->map(
        function ($student) use (
            $days,
            $attendance
        ): array {
            $dayStatuses = [];

            $totals = [
                'P' => 0,
                'R' => 0,
                'E' => 0,
                'A' => 0,
                'SC' => 0,
            ];

            foreach ($days as $day) {
                $status = $this->monthlyStatus(
                    studentId: (int) $student->id,
                    day: $day,
                    attendance: $attendance
                );

                $dayStatuses[$day['date']] = $status;

                /*
                 * El símbolo — no incrementa ningún total.
                 */
                if (
                    array_key_exists(
                        $status,
                        $totals
                    )
                ) {
                    $totals[$status]++;
                }
            }

            return [
                'id' => (int) $student->id,

                'student_code' =>
                    (string) $student->student_code,

                'full_name' => trim(
                    $student->first_name
                    .' '
                    .$student->last_name
                ),

                'campus_name' =>
                    $student->campus_name
                    ?? 'Sin plantel',

                'level_name' =>
                    $student->level_name
                    ?? 'Sin nivel',

                'group_name' =>
                    $student->group_name
                    ?? 'Sin grupo',

                'days' => $dayStatuses,
                'totals' => $totals,
            ];
        }
    );

    /*
     * Totales del reporte.
     */
    $summary = [
        'students' => $students->count(),

        'P' => $students->sum(
            fn (array $row): int =>
                (int) $row['totals']['P']
        ),

        'R' => $students->sum(
            fn (array $row): int =>
                (int) $row['totals']['R']
        ),

        'E' => $students->sum(
            fn (array $row): int =>
                (int) $row['totals']['E']
        ),

        'A' => $students->sum(
            fn (array $row): int =>
                (int) $row['totals']['A']
        ),

        'SC' => $students->sum(
            fn (array $row): int =>
                (int) $row['totals']['SC']
        ),
    ];

    /*
     * La puntualidad se calcula únicamente sobre entradas
     * clasificadas. Las ausencias y días sin clase no forman
     * parte del denominador.
     */
    $classified = $summary['P']
        + $summary['R']
        + $summary['E'];

    $summary['punctuality_rate'] = $classified > 0
        ? round(
            ($summary['P'] / $classified) * 100,
            1
        )
        : 0.0;

    return [
        'start' => $start,
        'end' => $end,

        'month_name' => ucfirst(
            $start
                ->locale('es')
                ->monthName
        ),

        'days' => $days,
        'students' => $students,
        'summary' => $summary,

        /*
         * Datos usados por el Blade para mostrar el estado
         * oficial del ciclo.
         */
        'activeCycle' =>
            $activeWindow['cycle'] ?? null,

        'hasActiveCycle' =>
            $activeWindow !== null,

        /*
         * Indica si el mes seleccionado tiene alguna
         * intersección válida con el ciclo.
         */
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

   private function monthlyStatus(
    int $studentId,
    array $day,
    Collection $attendance
): string {
    /*
     * Un día fuera del ciclo activo no genera asistencia,
     * falta ni día sin clase.
     */
    if (! $day['is_inside_cycle']) {
        return '—';
    }

    /*
     * Una fecha futura tampoco debe contabilizarse.
     */
    if ($day['is_future']) {
        return '—';
    }

    /*
     * Dentro del ciclo, sábados, domingos, vacaciones,
     * suspensiones y demás excepciones se muestran como SC.
     */
    if (! $day['is_class_day']) {
        return 'SC';
    }

    $record = $attendance->get(
        $studentId
        .'|'
        .$day['date']
    );

    /*
     * Solo aquí se infiere una ausencia:
     *
     * - hay ciclo activo;
     * - la fecha está dentro del ciclo;
     * - la fecha ya ocurrió;
     * - es día de clase;
     * - no existe registro de asistencia.
     */
    if (! $record) {
        return 'A';
    }

    $status = trim(
        (string) (
            $record->attendance_status
            ?? ''
        )
    );

    if ($status === '') {
        $status = $this->statusFromMinutes(
            (int) (
                $record->minutes_late
                ?? 0
            )
        );
    }

    return match ($status) {
        'on_time',
        'present',
        'present_on_time' => 'P',

        'late' => 'R',

        'very_late',
        'extemporaneous',
        'extemporaneo',
        'extemporáneo' => 'E',

        'absent' => 'A',

        'no_class',
        'holiday',
        'vacation',
        'suspension',
        'technical_council' => 'SC',

        /*
         * Si existe un registro real pero utiliza un estado
         * todavía no reconocido, no lo convertimos en falta.
         */
        default => 'P',
    };
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

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'month' => [
                'nullable',
                'integer',
                'between:1,12',
            ],
            'year' => [
                'nullable',
                'integer',
                'between:2020,2100',
            ],
            'campus_id' => [
                'nullable',
                'integer',
            ],
            'group_id' => [
                'nullable',
                'integer',
            ],
        ]);

        return [
            'month' => (int) (
                $validated['month'] ?? now()->month
            ),
            'year' => (int) (
                $validated['year'] ?? now()->year
            ),
            'campus_id' => ! empty(
                $validated['campus_id']
            )
                ? (int) $validated['campus_id']
                : null,
            'group_id' => ! empty(
                $validated['group_id']
            )
                ? (int) $validated['group_id']
                : null,
        ];
    }

    private function campuses(int $schoolId): Collection
    {
        return DB::table('campuses')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);
    }

    private function groups(int $schoolId): Collection
    {
        return DB::table('school_groups as g')
            ->leftJoin(
                'academic_levels as l',
                'l.id',
                '=',
                'g.academic_level_id'
            )
            ->where('g.school_id', $schoolId)
            ->where('g.status', 'active')
            ->orderBy('l.sort_order')
            ->orderBy('g.name')
            ->get([
                'g.id',
                'g.campus_id',
                'g.name',
                'l.name as level_name',
            ]);
    }

    private function attendanceStatusSql(): string
    {
        if (
            Schema::hasColumn(
                'daily_attendance',
                'status'
            )
        ) {
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

    private function styleStatusCell(
        $sheet,
        string $cell,
        string $status
    ): void {
        $colors = [
            'P' => 'DCFCE7',
            'R' => 'FEF3C7',
            'E' => 'FFEDD5',
            'A' => 'FEE2E2',
            'SC' => 'E2E8F0',
            '—' => 'F8FAFC',
        ];

        $sheet->getStyle($cell)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB(
                $colors[$status] ?? 'FFFFFF'
            );

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