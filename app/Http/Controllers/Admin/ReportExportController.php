<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = $this->schoolId($request);
        $activeCycle = $this->activeCycle($schoolId);

        return view('admin.reports.exports', [
            'campuses' => DB::table('campuses')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),

            'groups' => DB::table('school_groups as g')
                ->leftJoin(
                    'academic_levels as l',
                    'l.id',
                    '=',
                    'g.academic_level_id'
                )
                ->where('g.school_id', $schoolId)
                ->where('g.status', 'active')
                ->when(
                    $activeCycle,
                    fn ($query) => $query->where(
                        'g.academic_cycle_id',
                        $activeCycle->id
                    ),
                    fn ($query) => $query->whereRaw('1 = 0')
                )
                ->orderBy('l.sort_order')
                ->orderBy('g.name')
                ->get([
                    'g.id',
                    'g.name',
                    'l.name as level_name',
                ]),

            'areas' => DB::table('areas')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),

            'devices' => DB::table('access_devices')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
    public function students(Request $request): BinaryFileResponse
    {
        $schoolId = $this->schoolId($request);
        $activeCycle = $this->activeCycle($schoolId);

        $filters = $request->validate([
            'campus_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $rows = DB::table('students as s')
            ->leftJoin('student_enrollments as se', function ($join) use (
                $activeCycle,
                $schoolId
            ): void {
                $join->on('se.student_id', '=', 's.id')
                    ->where('se.school_id', '=', $schoolId);

                if ($activeCycle) {
                    $join->where(
                        'se.academic_cycle_id',
                        '=',
                        $activeCycle->id
                    );
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->leftJoin('campuses as c', function ($join): void {
                $join->on('c.id', '=', 'se.campus_id');
            })
            ->leftJoin('school_groups as g', function ($join): void {
                $join->on('g.id', '=', 'se.school_group_id');
            })
            ->leftJoin(
                'academic_levels as l',
                'l.id',
                '=',
                'g.academic_level_id'
            )
            ->where('s.school_id', $schoolId)
            ->when(
                $filters['campus_id'] ?? null,
                fn ($query, $value) => $query->where('se.campus_id', $value)
            )
            ->when(
                $filters['group_id'] ?? null,
                fn ($query, $value) => $query->where(
                    'se.school_group_id',
                    $value
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $value) => $query->where('s.status', $value)
            )
            ->orderBy('l.sort_order')
            ->orderBy('g.name')
            ->orderBy('s.last_name')
            ->orderBy('s.first_name')
            ->get([
                's.student_code',
                's.first_name',
                's.last_name',
                'c.name as campus_name',
                'l.name as level_name',
                'g.grade_label',
                'g.name as group_name',
                's.status',
                's.notes',
                's.created_at',
                'se.status as enrollment_status',
                'se.enrolled_on',
                'se.withdrawn_on',
                'se.completed_on',
            ]);

        $data = $rows->map(fn ($row): array => [
            $row->student_code,
            $row->first_name,
            $row->last_name,
            $row->campus_name,
            $row->level_name,
            $row->grade_label,
            $row->group_name,
            $this->statusLabel($row->status),
            $row->enrollment_status
                ? $this->statusLabel($row->enrollment_status)
                : 'Sin inscripción',
            $row->enrolled_on,
            $row->withdrawn_on,
            $row->completed_on,
            $row->notes,
            $this->dateTime($row->created_at),
        ])->all();

        return $this->downloadWorkbook(
            request: $request,
            reportTitle: 'Directorio de alumnos',
            sheetTitle: 'Alumnos',
            headers: [
                'Matrícula',
                'Nombre',
                'Apellidos',
                'Plantel',
                'Nivel',
                'Grado',
                'Grupo',
                'Estado del alumno',
                'Estado de inscripción',
                'Fecha de inscripción',
                'Fecha de baja',
                'Fecha de conclusión',
                'Notas',
                'Fecha de registro',
            ],
            rows: $data,
            textColumns: ['A'],
            filePrefix: 'alumnos'
        );
    }

    public function guardians(Request $request): BinaryFileResponse
    {
        $schoolId = $this->schoolId($request);

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $rows = DB::table('guardians as ga')
            ->leftJoin('users as u', function ($join) use ($schoolId): void {
                $join->on('u.id', '=', 'ga.user_id')
                    ->where('u.school_id', '=', $schoolId);
            })
            ->where('ga.school_id', $schoolId)
            ->when(
                $filters['status'] ?? null,
                fn ($query, $value) => $query->where('ga.status', $value)
            )
            ->selectRaw('
                ga.id,
                ga.first_name,
                ga.last_name,
                ga.phone,
                ga.email,
                ga.status,
                ga.created_at,
                u.id as account_user_id,
                (
                    select count(*)
                    from student_guardians sg
                    inner join students s
                        on s.id = sg.student_id
                        and s.school_id = ?
                    where sg.guardian_id = ga.id
                    and sg.status = ?
                ) as students_count
            ', [$schoolId, 'active'])
            ->orderBy('ga.last_name')
            ->orderBy('ga.first_name')
            ->get();

        $data = $rows->map(fn ($row): array => [
            $row->first_name,
            $row->last_name,
            $row->phone,
            $row->email,
            (int) $row->students_count,
            $row->account_user_id ? 'Sí' : 'No',
            $this->statusLabel($row->status),
            $this->dateTime($row->created_at),
        ])->all();

        return $this->downloadWorkbook(
            request: $request,
            reportTitle: 'Directorio de tutores',
            sheetTitle: 'Tutores',
            headers: [
                'Nombre',
                'Apellidos',
                'Teléfono',
                'Correo electrónico',
                'Alumnos vinculados',
                'Cuenta de acceso',
                'Estado',
                'Fecha de registro',
            ],
            rows: $data,
            textColumns: ['C'],
            filePrefix: 'tutores'
        );
    }
    public function relationships(Request $request): BinaryFileResponse
    {
        $schoolId = $this->schoolId($request);
        $activeCycle = $this->activeCycle($schoolId);

        $filters = $request->validate([
            'campus_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $rows = DB::table('student_guardians as sg')
            ->join('students as s', function ($join) use ($schoolId): void {
                $join->on('s.id', '=', 'sg.student_id')
                    ->where('s.school_id', '=', $schoolId);
            })
            ->join('guardians as ga', function ($join) use ($schoolId): void {
                $join->on('ga.id', '=', 'sg.guardian_id')
                    ->where('ga.school_id', '=', $schoolId);
            })
            ->leftJoin('student_enrollments as se', function ($join) use (
                $activeCycle,
                $schoolId
            ): void {
                $join->on('se.student_id', '=', 's.id')
                    ->where('se.school_id', '=', $schoolId);

                if ($activeCycle) {
                    $join->where(
                        'se.academic_cycle_id',
                        '=',
                        $activeCycle->id
                    );
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->leftJoin('campuses as c', 'c.id', '=', 'se.campus_id')
            ->leftJoin('school_groups as g', 'g.id', '=', 'se.school_group_id')
            ->leftJoin(
                'academic_levels as l',
                'l.id',
                '=',
                'g.academic_level_id'
            )
            ->when(
                $filters['campus_id'] ?? null,
                fn ($query, $value) => $query->where('se.campus_id', $value)
            )
            ->when(
                $filters['group_id'] ?? null,
                fn ($query, $value) => $query->where(
                    'se.school_group_id',
                    $value
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $value) => $query->where('sg.status', $value)
            )
            ->orderBy('l.sort_order')
            ->orderBy('g.name')
            ->orderBy('s.last_name')
            ->orderByDesc('sg.is_primary')
            ->get([
                's.student_code',
                's.first_name as student_first_name',
                's.last_name as student_last_name',
                'c.name as campus_name',
                'l.name as level_name',
                'g.name as group_name',
                'ga.first_name as guardian_first_name',
                'ga.last_name as guardian_last_name',
                'ga.phone',
                'ga.email',
                'sg.relationship',
                'sg.is_primary',
                'sg.can_view_attendance',
                'sg.can_receive_notifications',
                'sg.can_authorize_exit',
                'sg.status',
                'se.status as enrollment_status',
            ]);

        $data = $rows->map(fn ($row): array => [
            $row->student_code,
            trim($row->student_first_name.' '.$row->student_last_name),
            $row->campus_name,
            $row->level_name,
            $row->group_name,
            $row->enrollment_status
                ? $this->statusLabel($row->enrollment_status)
                : 'Sin inscripción',
            trim($row->guardian_first_name.' '.$row->guardian_last_name),
            $row->phone,
            $row->email,
            $row->relationship,
            $this->yesNo($row->is_primary),
            $this->yesNo($row->can_view_attendance),
            $this->yesNo($row->can_receive_notifications),
            $this->yesNo($row->can_authorize_exit),
            $this->statusLabel($row->status),
        ])->all();

        return $this->downloadWorkbook(
            request: $request,
            reportTitle: 'Relaciones entre alumnos y tutores',
            sheetTitle: 'Alumno tutor',
            headers: [
                'Matrícula',
                'Alumno',
                'Plantel',
                'Nivel',
                'Grupo',
                'Estado de inscripción',
                'Tutor',
                'Teléfono',
                'Correo',
                'Parentesco',
                'Tutor principal',
                'Consulta asistencia',
                'Recibe notificaciones',
                'Autoriza salida',
                'Estado del vínculo',
            ],
            rows: $data,
            textColumns: ['A', 'H'],
            filePrefix: 'alumnos_tutores'
        );
    }
    public function attendance(Request $request): BinaryFileResponse
    {
        $schoolId = $this->schoolId($request);

        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'group_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $date = Carbon::parse(
            $filters['date'] ?? now()->toDateString()
        )->toDateString();

        $cycle = $this->cycleForDate($schoolId, $date);

        $calendarDay = DB::table('school_calendar_days')
            ->where('school_id', $schoolId)
            ->where('date', $date)
            ->where('status', 'active')
            ->first();

        $isNoClassDay = $calendarDay
            && in_array($calendarDay->type, [
                'holiday',
                'vacation',
                'suspension',
                'technical_council',
                'no_class',
            ], true);

        $statusSql = $this->attendanceStatusSql();

        $entrySql = $this->attendanceColumnSql(
            ['entry_time', 'first_entry_at', 'entry_at'],
            'null'
        );

        $exitSql = $this->attendanceColumnSql(
            ['exit_time', 'last_exit_at', 'exit_at'],
            'null'
        );

        $minutesSql = $this->attendanceColumnSql(
            ['minutes_late', 'late_minutes'],
            '0'
        );

        $rows = DB::table('student_enrollments as se')
            ->join('students as s', function ($join) use ($schoolId): void {
                $join->on('s.id', '=', 'se.student_id')
                    ->where('s.school_id', '=', $schoolId)
                    ->where('s.status', '=', 'active');
            })
            ->join('school_groups as g', 'g.id', '=', 'se.school_group_id')
            ->leftJoin(
                'academic_levels as l',
                'l.id',
                '=',
                'g.academic_level_id'
            )
            ->leftJoin('daily_attendance as da', function ($join) use (
                $schoolId,
                $date
            ): void {
                $join->on('da.student_id', '=', 's.id')
                    ->where('da.school_id', '=', $schoolId)
                    ->where('da.date', '=', $date);
            })
            ->where('se.school_id', $schoolId)
            ->when(
                $cycle,
                fn ($query) => $query->where(
                    'se.academic_cycle_id',
                    $cycle->id
                ),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->whereDate('se.enrolled_on', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('se.withdrawn_on')
                    ->orWhereDate('se.withdrawn_on', '>=', $date);
            })
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('se.completed_on')
                    ->orWhereDate('se.completed_on', '>=', $date);
            })
            ->when(
                $filters['group_id'] ?? null,
                fn ($query, $value) => $query->where(
                    'se.school_group_id',
                    $value
                )
            )
            ->select([
                's.student_code',
                's.first_name',
                's.last_name',
                'g.name as group_name',
                'l.name as level_name',
                'da.id as attendance_id',
                DB::raw($statusSql.' as attendance_status'),
                DB::raw($entrySql.' as entry_time'),
                DB::raw($exitSql.' as exit_time'),
                DB::raw($minutesSql.' as minutes_late'),
            ])
            ->orderBy('l.sort_order')
            ->orderBy('g.name')
            ->orderBy('s.last_name')
            ->get()
            ->map(function ($row) use ($isNoClassDay, $cycle): object {
                if (! $cycle) {
                    $row->final_status = 'no_class';
                } elseif (! $row->attendance_id) {
                    $row->final_status = $isNoClassDay
                        ? 'no_class'
                        : 'absent';
                } else {
                    $row->final_status = $row->attendance_status
                        ?: $this->statusFromMinutes(
                            (int) $row->minutes_late
                        );
                }

                return $row;
            });

        if (! empty($filters['status'])) {
            $rows = $rows
                ->filter(
                    fn ($row) =>
                        $row->final_status === $filters['status']
                )
                ->values();
        }

        $data = $rows->map(fn ($row): array => [
            $date,
            $row->student_code,
            trim($row->first_name.' '.$row->last_name),
            $row->level_name,
            $row->group_name,
            $this->time($row->entry_time),
            $this->time($row->exit_time),
            (int) $row->minutes_late,
            $this->attendanceStatusLabel($row->final_status),
        ])->all();

        return $this->downloadWorkbook(
            request: $request,
            reportTitle: 'Reporte de asistencia · '.$date,
            sheetTitle: 'Asistencia',
            headers: [
                'Fecha',
                'Matrícula',
                'Alumno',
                'Nivel',
                'Grupo',
                'Entrada',
                'Salida',
                'Minutos de retardo',
                'Estado',
            ],
            rows: $data,
            textColumns: ['B'],
            filePrefix: 'asistencia_'.$date
        );
    }
    public function access(Request $request): BinaryFileResponse
    {
        $schoolId = $this->schoolId($request);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'student_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
            'device_id' => ['nullable', 'integer'],
            'event_type' => ['nullable', 'string', 'max:30'],
            'event_status' => ['nullable', 'string', 'max:50'],
        ]);

        $filters['from'] = $filters['from'] ?? now()->toDateString();
        $filters['to'] = $filters['to'] ?? now()->toDateString();

        $deviceColumn = $this->deviceLogColumn();

        $rows = $this->accessBaseQuery($schoolId, $filters)
            ->select([
                'access_logs.scanned_at',
                'students.student_code',
                'students.first_name',
                'students.last_name',
                DB::raw(
                    'COALESCE(historical_level.name, current_level.name) as level_name'
                ),
                DB::raw(
                    'COALESCE(historical_group.name, current_group.name) as group_name'
                ),
                'academic_cycles.name as cycle_name',
                'access_logs.student_enrollment_id',
                'areas.name as area_name',
                'access_devices.name as device_name',
                'access_logs.event_type',
                'access_logs.event_status',
                'access_logs.decision',
                'access_logs.action',
                'access_logs.reason',
                'access_logs.source',
                DB::raw(
                    'access_logs.'.$deviceColumn.' as exported_device_id'
                ),
            ])
            ->orderByDesc('access_logs.scanned_at')
            ->get();

        $data = $rows->map(fn ($row): array => [
            $this->dateTime($row->scanned_at),
            $row->student_code,
            trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
            $row->cycle_name,
            $row->level_name,
            $row->group_name,
            $row->student_enrollment_id,
            $row->area_name,
            $row->device_name,
            $this->eventTypeLabel($row->event_type),
            $this->accessStatusLabel($row->event_status),
            $row->decision,
            $row->action,
            $row->reason,
            $row->source,
        ])->all();

        return $this->downloadWorkbook(
            request: $request,
            reportTitle: sprintf(
                'Bitácora de accesos · %s al %s',
                $filters['from'],
                $filters['to']
            ),
            sheetTitle: 'Accesos',
            headers: [
                'Fecha y hora',
                'Matrícula',
                'Alumno',
                'Ciclo',
                'Nivel',
                'Grupo',
                'Inscripción ID',
                'Área',
                'Dispositivo',
                'Evento',
                'Estado',
                'Decisión',
                'Acción',
                'Razón',
                'Origen',
            ],
            rows: $data,
            textColumns: ['B'],
            filePrefix: sprintf(
                'accesos_%s_%s',
                $filters['from'],
                $filters['to']
            )
        );
    }
    private function accessBaseQuery(
        int $schoolId,
        array $filters
    ): Builder {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $deviceColumn = $this->deviceLogColumn();

        return DB::table('access_logs')
            ->leftJoin('students', function ($join) use ($schoolId): void {
                $join->on('students.id', '=', 'access_logs.student_id')
                    ->where('students.school_id', '=', $schoolId);
            })
            ->leftJoin(
                'academic_cycles',
                'academic_cycles.id',
                '=',
                'access_logs.academic_cycle_id'
            )
            ->leftJoin(
                'school_groups as historical_group',
                'historical_group.id',
                '=',
                'access_logs.school_group_id'
            )
            ->leftJoin(
                'academic_levels as historical_level',
                'historical_level.id',
                '=',
                'historical_group.academic_level_id'
            )
            ->leftJoin(
                'school_groups as current_group',
                'current_group.id',
                '=',
                'students.current_group_id'
            )
            ->leftJoin(
                'academic_levels as current_level',
                'current_level.id',
                '=',
                'current_group.academic_level_id'
            )
            ->leftJoin('areas', function ($join) use ($schoolId): void {
                $join->on('areas.id', '=', 'access_logs.area_id')
                    ->where('areas.school_id', '=', $schoolId);
            })
            ->leftJoin(
                'access_devices',
                'access_devices.id',
                '=',
                'access_logs.'.$deviceColumn
            )
            ->where('access_logs.school_id', $schoolId)
            ->whereBetween('access_logs.scanned_at', [$from, $to])
            ->when(
                $filters['student_id'] ?? null,
                fn ($query, $value) => $query->where(
                    'access_logs.student_id',
                    $value
                )
            )
            ->when(
                $filters['group_id'] ?? null,
                function ($query, $value): void {
                    $query->where(function ($inner) use ($value): void {
                        $inner
                            ->where('access_logs.school_group_id', $value)
                            ->orWhere(function ($legacy) use ($value): void {
                                $legacy
                                    ->whereNull('access_logs.school_group_id')
                                    ->whereNull('access_logs.academic_cycle_id')
                                    ->where('students.current_group_id', $value);
                            });
                    });
                }
            )
            ->when(
                $filters['area_id'] ?? null,
                fn ($query, $value) => $query->where(
                    'access_logs.area_id',
                    $value
                )
            )
            ->when(
                $filters['device_id'] ?? null,
                fn ($query, $value) => $query->where(
                    'access_logs.'.$deviceColumn,
                    $value
                )
            )
            ->when(
                $filters['event_type'] ?? null,
                fn ($query, $value) => $query->where(
                    'access_logs.event_type',
                    $value
                )
            )
            ->when(
                $filters['event_status'] ?? null,
                fn ($query, $value) => $query->where(
                    'access_logs.event_status',
                    $value
                )
            );
    }

    private function downloadWorkbook(
        Request $request,
        string $reportTitle,
        string $sheetTitle,
        array $headers,
        array $rows,
        array $textColumns,
        string $filePrefix,
    ): BinaryFileResponse {
        $schoolId = $this->schoolId($request);

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->first();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        $this->writeReportHeader(
            sheet: $sheet,
            schoolName: $school?->name ?? 'SchoolPass',
            reportTitle: $reportTitle,
            columnsCount: count($headers)
        );

        $headerRow = 5;
        $firstDataRow = 6;

        $sheet->fromArray($headers, null, 'A'.$headerRow);

        foreach ($rows as $index => $row) {
            $excelRow = $firstDataRow + $index;
            $sheet->fromArray($row, null, 'A'.$excelRow);

            foreach ($textColumns as $column) {
                $sheet->setCellValueExplicit(
                    $column.$excelRow,
                    (string) ($row[$this->columnIndex($column)] ?? ''),
                    DataType::TYPE_STRING
                );
            }
        }

        $lastColumn = $this->columnLetter(count($headers));
        $lastRow = max($headerRow, $firstDataRow + count($rows) - 1);

        $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)
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

        if ($rows !== []) {
            $sheet->getStyle(
                'A'.$firstDataRow.':'.$lastColumn.$lastRow
            )->applyFromArray([
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
        }

        $sheet->freezePane('A'.$firstDataRow);
        $sheet->setAutoFilter(
            'A'.$headerRow.':'.$lastColumn.$lastRow
        );

        for ($column = 1; $column <= count($headers); $column++) {
            $letter = $this->columnLetter($column);

            $sheet->getColumnDimension($letter)
                ->setAutoSize(true);
        }

        $directory = storage_path(
            'app/private/report-exports/school_'.$schoolId
        );

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $filePrefix.'_'.now()->format('Ymd_His').'.xlsx';
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

    private function writeReportHeader(
        Worksheet $sheet,
        string $schoolName,
        string $reportTitle,
        int $columnsCount
    ): void {
        $lastColumn = $this->columnLetter($columnsCount);

        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->mergeCells('A3:'.$lastColumn.'3');

        $sheet->setCellValue('A1', $schoolName);
        $sheet->setCellValue('A2', $reportTitle);
        $sheet->setCellValue(
            'A3',
            'Generado el '.now()->format('d/m/Y H:i')
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

        $sheet->getRowDimension(1)->setRowHeight(28);
    }
    private function activeCycle(int $schoolId): ?object
    {
        return DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->first();
    }

    private function cycleForDate(
        int $schoolId,
        string $date
    ): ?object {
        return DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->whereIn('status', ['active', 'closed'])
            ->orderByDesc('is_active')
            ->orderByDesc('starts_on')
            ->first();
    }


    private function schoolId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user && $user->school_id, 403);

        return (int) $user->school_id;
    }

    private function deviceLogColumn(): string
    {
        return Schema::hasColumn('access_logs', 'device_id')
            ? 'device_id'
            : 'access_device_id';
    }

    private function attendanceStatusSql(): string
    {
        if (Schema::hasColumn('daily_attendance', 'status')) {
            return 'da.status';
        }

        if (Schema::hasColumn('daily_attendance', 'entry_status')) {
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

        if (Schema::hasColumn('daily_attendance', 'minutes_late')) {
            return "
                case
                    when da.id is null then null
                    when da.minutes_late is null then 'on_time'
                    when da.minutes_late <= 0 then 'on_time'
                    when da.minutes_late <= 20 then 'late'
                    else 'very_late'
                end
            ";
        }

        return "
            case
                when da.id is null then null
                else 'on_time'
            end
        ";
    }

    private function attendanceColumnSql(
        array $possibleColumns,
        string $fallback
    ): string {
        foreach ($possibleColumns as $column) {
            if (Schema::hasColumn('daily_attendance', $column)) {
                return 'da.'.$column;
            }
        }

        return $fallback;
    }

    private function statusFromMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'on_time';
        }

        if ($minutes <= 20) {
            return 'late';
        }

        return 'very_late';
    }

    private function dateTime(mixed $value): string
    {
        return $value
            ? Carbon::parse($value)->format('d/m/Y H:i:s')
            : '';
    }

    private function time(mixed $value): string
    {
        return $value
            ? Carbon::parse($value)->format('H:i')
            : '';
    }

    private function yesNo(mixed $value): string
    {
        return (bool) $value ? 'Sí' : 'No';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'suspended' => 'Suspendido',
            default => $status ?: '',
        };
    }

    private function attendanceStatusLabel(?string $status): string
    {
        return match ($status) {
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Extemporáneo',
            'absent' => 'Ausente',
            'no_class' => 'Sin clase',
            default => $status ?: '',
        };
    }

    private function eventTypeLabel(?string $type): string
    {
        return match ($type) {
            'entry' => 'Entrada',
            'exit' => 'Salida',
            'access' => 'Acceso',
            default => $type ?: '',
        };
    }

    private function accessStatusLabel(?string $status): string
    {
        return match ($status) {
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Extemporáneo',
            'duplicate' => 'Duplicado',
            'normal_exit' => 'Salida normal',
            'early_exit' => 'Salida anticipada',
            'allowed' => 'Autorizado',
            'denied' => 'Denegado',
            default => $status ?: '',
        };
    }

    private function columnLetter(int $number): string
    {
        $letter = '';

        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)).$letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }

    private function columnIndex(string $column): int
    {
        $result = 0;

        foreach (str_split(strtoupper($column)) as $character) {
            $result = ($result * 26)
                + (ord($character) - 64);
        }

        return $result - 1;
    }
}