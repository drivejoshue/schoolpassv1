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
use Symfony\Component\HttpFoundation\Response;

class AnalyticsReportController extends Controller
{
    public function index(Request $request): View
    {
        $schoolId = $this->schoolId($request);
        $filters = $this->filters($request);

        $report = $this->buildReport(
            schoolId: $schoolId,
            filters: $filters
        );

        return view('admin.reports.analytics', [
            'filters' => $filters,
            'groups' => $this->groups($schoolId),
            ...$report,
        ]);
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

        $group = null;

        if ($filters['group_id']) {
            $group = DB::table('school_groups')
                ->where('school_id', $schoolId)
                ->where('id', $filters['group_id'])
                ->first();
        }

        $pdf = Pdf::loadView('admin.reports.analytics-pdf', [
            'filters' => $filters,
            'school' => $school,
            'selectedGroup' => $group,
            'generatedAt' => now(),
            ...$report,
        ])
            ->setPaper('letter', 'landscape')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);

        $filename = sprintf(
            'analitica_%s_%s%s.pdf',
            $filters['from'],
            $filters['to'],
            $filters['group_id']
                ? '_grupo_'.$filters['group_id']
                : ''
        );

        return $pdf->download($filename);
    }
    private function buildReport(
        int $schoolId,
        array $filters
    ): array {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();

        $activeCycle = $this->activeCycle($schoolId);

        $studentsQuery = DB::table('student_enrollments as se')
            ->join('students as s', function ($join) use ($schoolId): void {
                $join->on('s.id', '=', 'se.student_id')
                    ->where('s.school_id', '=', $schoolId)
                    ->where('s.status', '=', 'active');
            })
            ->join('school_groups as g', function ($join) use ($schoolId): void {
                $join->on('g.id', '=', 'se.school_group_id')
                    ->where('g.school_id', '=', $schoolId);
            })
            ->where('se.school_id', $schoolId)
            ->where('se.status', 'active')
            ->when(
                $activeCycle,
                fn ($query) => $query->where(
                    'se.academic_cycle_id',
                    $activeCycle->id
                ),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->when(
                $filters['group_id'],
                fn ($query, $groupId) => $query->where(
                    'se.school_group_id',
                    $groupId
                )
            );

        $activeStudents = (clone $studentsQuery)
            ->distinct()
            ->count('s.id');

        $accessQuery = DB::table('access_logs as al')
            ->leftJoin('students as s', function ($join) use (
                $schoolId
            ): void {
                $join->on('s.id', '=', 'al.student_id')
                    ->where('s.school_id', '=', $schoolId);
            })
            ->where('al.school_id', $schoolId)
            ->whereBetween('al.scanned_at', [$from, $to])
            ->when(
                $filters['group_id'],
                function ($query, $groupId): void {
                    $query->where(function ($inner) use ($groupId): void {
                        $inner
                            ->where('al.school_group_id', $groupId)
                            ->orWhere(function ($legacy) use ($groupId): void {
                                $legacy
                                    ->whereNull('al.school_group_id')
                                    ->whereNull('al.academic_cycle_id')
                                    ->where('s.current_group_id', $groupId);
                            });
                    });
                }
            );

        $summary = [
            'active_students' => $activeStudents,
            'events' => (clone $accessQuery)->count(),
            'entries' => (clone $accessQuery)
                ->where('al.event_type', 'entry')
                ->count(),
            'exits' => (clone $accessQuery)
                ->where('al.event_type', 'exit')
                ->count(),
            'on_time' => (clone $accessQuery)
                ->where('al.event_status', 'on_time')
                ->count(),
            'late' => (clone $accessQuery)
                ->where('al.event_status', 'late')
                ->count(),
            'very_late' => (clone $accessQuery)
                ->where('al.event_status', 'very_late')
                ->count(),
            'denied' => (clone $accessQuery)
                ->where('al.decision', 'denied')
                ->count(),
            'duplicates' => (clone $accessQuery)
                ->where('al.event_status', 'duplicate')
                ->count(),
        ];

        $summary['punctuality_rate'] = $this->percentage(
            $summary['on_time'],
            $summary['on_time']
                + $summary['late']
                + $summary['very_late']
        );

        $summary['late_rate'] = $this->percentage(
            $summary['late'] + $summary['very_late'],
            $summary['on_time']
                + $summary['late']
                + $summary['very_late']
        );

        $dailyTrend = $this->dailyTrend(
            schoolId: $schoolId,
            filters: $filters
        );

        $groupResults = $this->groupResults(
            schoolId: $schoolId,
            filters: $filters
        );

        $statusDistribution = [
            [
                'key' => 'on_time',
                'label' => 'Puntuales',
                'value' => $summary['on_time'],
            ],
            [
                'key' => 'late',
                'label' => 'Retardos',
                'value' => $summary['late'],
            ],
            [
                'key' => 'very_late',
                'label' => 'Extemporáneos',
                'value' => $summary['very_late'],
            ],
            [
                'key' => 'denied',
                'label' => 'Denegados',
                'value' => $summary['denied'],
            ],
            [
                'key' => 'duplicate',
                'label' => 'Duplicados',
                'value' => $summary['duplicates'],
            ],
        ];

        return [
            'summary' => $summary,
            'dailyTrend' => $dailyTrend,
            'groupResults' => $groupResults,
            'statusDistribution' => $statusDistribution,
            'activeCycle' => $activeCycle,

            'dailyChartImage' => $this->buildBarChartPng(
                rows: $dailyTrend,
                labelKey: 'label',
                series: [
                    [
                        'key' => 'on_time',
                        'label' => 'Puntuales',
                        'color' => [22, 163, 74],
                    ],
                    [
                        'key' => 'late',
                        'label' => 'Retardos',
                        'color' => [245, 158, 11],
                    ],
                    [
                        'key' => 'very_late',
                        'label' => 'Extemporáneos',
                        'color' => [234, 88, 12],
                    ],
                ],
                width: 1100,
                height: 360
            ),

            'groupsChartImage' => $this->buildBarChartPng(
                rows: $groupResults->take(12)->values()->all(),
                labelKey: 'group_short',
                series: [
                    [
                        'key' => 'on_time',
                        'label' => 'Puntuales',
                        'color' => [22, 163, 74],
                    ],
                    [
                        'key' => 'late_total',
                        'label' => 'Retardos',
                        'color' => [245, 158, 11],
                    ],
                ],
                width: 1100,
                height: 360
            ),
        ];
    }


    private function buildBarChartPng(
    array $rows,
    string $labelKey,
    array $series,
    int $width,
    int $height
): string {
    if (! extension_loaded('gd')) {
        throw new RuntimeException(
            'La extensión GD de PHP es necesaria para generar las gráficas del PDF.'
        );
    }

    $image = imagecreatetruecolor($width, $height);

    if ($image === false) {
        throw new RuntimeException(
            'No se pudo crear la imagen de la gráfica.'
        );
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    $white = imagecolorallocate($image, 255, 255, 255);
    $textColor = imagecolorallocate($image, 51, 65, 85);
    $mutedColor = imagecolorallocate($image, 100, 116, 139);
    $gridColor = imagecolorallocate($image, 226, 232, 240);
    $borderColor = imagecolorallocate($image, 203, 213, 225);

    imagefill($image, 0, 0, $white);

    $left = 65;
    $right = 25;
    $top = 30;
    $bottom = 80;

    $chartWidth = $width - $left - $right;
    $chartHeight = $height - $top - $bottom;

    if ($rows === []) {
        imagestring(
            $image,
            5,
            (int) (($width - 240) / 2),
            (int) ($height / 2),
            'No hay datos para el periodo.',
            $mutedColor
        );

        return $this->imageToDataUri($image);
    }

    $maximum = 1;

    foreach ($rows as $row) {
        foreach ($series as $item) {
            $maximum = max(
                $maximum,
                (int) ($row[$item['key']] ?? 0)
            );
        }
    }

    /*
     * Redondeamos el máximo para que la escala sea legible.
     */
    $maximum = max(
        5,
        (int) ceil($maximum / 5) * 5
    );

    /*
     * Líneas horizontales y etiquetas del eje Y.
     */
    for ($step = 0; $step <= 5; $step++) {
        $ratio = $step / 5;
        $y = (int) round(
            $top + ($chartHeight * (1 - $ratio))
        );

        $value = (int) round($maximum * $ratio);

        imageline(
            $image,
            $left,
            $y,
            $width - $right,
            $y,
            $gridColor
        );

        imagestring(
            $image,
            2,
            8,
            $y - 7,
            (string) $value,
            $mutedColor
        );
    }

    imageline(
        $image,
        $left,
        $top,
        $left,
        $top + $chartHeight,
        $borderColor
    );

    imageline(
        $image,
        $left,
        $top + $chartHeight,
        $width - $right,
        $top + $chartHeight,
        $borderColor
    );

    $rowCount = max(count($rows), 1);
    $seriesCount = max(count($series), 1);

    $groupWidth = $chartWidth / $rowCount;
    $availableBarWidth = $groupWidth * 0.72;
    $barGap = 3;

    $barWidth = max(
        4,
        (int) floor(
            (
                $availableBarWidth
                - ($barGap * ($seriesCount - 1))
            ) / $seriesCount
        )
    );

    foreach ($rows as $rowIndex => $row) {
        $groupStart = $left + ($rowIndex * $groupWidth);

        $barsTotalWidth =
            ($barWidth * $seriesCount)
            + ($barGap * ($seriesCount - 1));

        $startX = (int) round(
            $groupStart
            + (($groupWidth - $barsTotalWidth) / 2)
        );

        foreach ($series as $seriesIndex => $item) {
            $value = (int) ($row[$item['key']] ?? 0);

            $barHeight = (int) round(
                ($value / $maximum) * $chartHeight
            );

            $x1 = $startX
                + ($seriesIndex * ($barWidth + $barGap));

            $y1 = $top + $chartHeight - $barHeight;
            $x2 = $x1 + $barWidth;
            $y2 = $top + $chartHeight;

            [$red, $green, $blue] = $item['color'];

            $barColor = imagecolorallocate(
                $image,
                $red,
                $green,
                $blue
            );

            imagefilledrectangle(
                $image,
                $x1,
                $y1,
                $x2,
                $y2,
                $barColor
            );

            if ($value > 0 && $barWidth >= 11) {
                imagestring(
                    $image,
                    1,
                    $x1 + 1,
                    max($top, $y1 - 11),
                    (string) $value,
                    $textColor
                );
            }
        }

        $label = (string) ($row[$labelKey] ?? '');
        $label = $this->chartLabel($label, 16);

        $labelX = (int) round(
            $groupStart + ($groupWidth / 2)
        );

        $labelPixelWidth = imagefontwidth(1)
            * strlen($label);

        imagestring(
            $image,
            1,
            max(
                $left,
                $labelX - (int) ($labelPixelWidth / 2)
            ),
            $top + $chartHeight + 12,
            $label,
            $textColor
        );
    }

    /*
     * Leyenda.
     */
    $legendX = $left;
    $legendY = $height - 24;

    foreach ($series as $item) {
        [$red, $green, $blue] = $item['color'];

        $legendColor = imagecolorallocate(
            $image,
            $red,
            $green,
            $blue
        );

        imagefilledrectangle(
            $image,
            $legendX,
            $legendY,
            $legendX + 12,
            $legendY + 12,
            $legendColor
        );

        imagestring(
            $image,
            2,
            $legendX + 18,
            $legendY,
            $item['label'],
            $textColor
        );

        $legendX += 150;
    }

    return $this->imageToDataUri($image);
}

private function imageToDataUri(\GdImage $image): string
{
    ob_start();

    imagepng($image, null, 6);

    $contents = ob_get_clean();

    imagedestroy($image);

    if (! is_string($contents) || $contents === '') {
        throw new RuntimeException(
            'No se pudo convertir la gráfica a PNG.'
        );
    }

    return 'data:image/png;base64,'
        .base64_encode($contents);
}

private function chartLabel(
    string $value,
    int $maximumLength
): string {
    $value = trim($value);

    if ($value === '') {
        return 'Sin nombre';
    }

    /*
     * La fuente básica de GD no maneja bien algunos caracteres.
     * Para la gráfica resumida convertimos únicamente la etiqueta
     * visual a caracteres compatibles.
     */
    $ascii = iconv(
        'UTF-8',
        'ASCII//TRANSLIT//IGNORE',
        $value
    );

    $ascii = is_string($ascii) && $ascii !== ''
        ? $ascii
        : $value;

    if (mb_strlen($ascii) <= $maximumLength) {
        return $ascii;
    }

    return mb_substr(
        $ascii,
        0,
        $maximumLength - 1
    ).'.';
}
    private function dailyTrend(
        int $schoolId,
        array $filters
    ): array {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();

        $rows = DB::table('access_logs as al')
            ->leftJoin('students as s', function ($join) use (
                $schoolId
            ): void {
                $join->on('s.id', '=', 'al.student_id')
                    ->where('s.school_id', '=', $schoolId);
            })
            ->where('al.school_id', $schoolId)
            ->whereBetween('al.scanned_at', [$from, $to])
            ->where('al.event_type', 'entry')
            ->when(
                $filters['group_id'],
                function ($query, $groupId): void {
                    $query->where(function ($inner) use ($groupId): void {
                        $inner
                            ->where('al.school_group_id', $groupId)
                            ->orWhere(function ($legacy) use ($groupId): void {
                                $legacy
                                    ->whereNull('al.school_group_id')
                                    ->whereNull('al.academic_cycle_id')
                                    ->where('s.current_group_id', $groupId);
                            });
                    });
                }
            )
            ->selectRaw("
                DATE(al.scanned_at) as report_date,
                SUM(CASE
                    WHEN al.event_status = 'on_time'
                    THEN 1 ELSE 0
                END) as on_time,
                SUM(CASE
                    WHEN al.event_status = 'late'
                    THEN 1 ELSE 0
                END) as late,
                SUM(CASE
                    WHEN al.event_status = 'very_late'
                    THEN 1 ELSE 0
                END) as very_late,
                COUNT(*) as total
            ")
            ->groupByRaw('DATE(al.scanned_at)')
            ->orderBy('report_date')
            ->get()
            ->keyBy('report_date');

        $result = [];
        $cursor = $from->copy()->startOfDay();
        $lastDate = $to->copy()->startOfDay();

        while ($cursor->lte($lastDate)) {
            $dateKey = $cursor->toDateString();
            $row = $rows->get($dateKey);

            $result[] = [
                'date' => $dateKey,
                'label' => $cursor->format('d/m'),
                'on_time' => (int) ($row->on_time ?? 0),
                'late' => (int) ($row->late ?? 0),
                'very_late' => (int) ($row->very_late ?? 0),
                'total' => (int) ($row->total ?? 0),
            ];

            $cursor->addDay();
        }

        return $result;
    }
    private function groupResults(
        int $schoolId,
        array $filters
    ): Collection {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $activeCycle = $this->activeCycle($schoolId);

        $groups = DB::table('school_groups as g')
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
            ->when(
                $filters['group_id'],
                fn ($query, $groupId) => $query->where(
                    'g.id',
                    $groupId
                )
            )
            ->select([
                'g.id',
                'g.name',
                'g.grade_label',
                'l.name as level_name',
                'l.sort_order',
            ])
            ->orderBy('l.sort_order')
            ->orderBy('g.name')
            ->get();

        $events = DB::table('access_logs as al')
            ->leftJoin('students as s', function ($join) use (
                $schoolId
            ): void {
                $join->on('s.id', '=', 'al.student_id')
                    ->where('s.school_id', '=', $schoolId);
            })
            ->where('al.school_id', $schoolId)
            ->whereBetween('al.scanned_at', [$from, $to])
            ->where('al.event_type', 'entry')
            ->when(
                $filters['group_id'],
                function ($query, $groupId): void {
                    $query->where(function ($inner) use ($groupId): void {
                        $inner
                            ->where('al.school_group_id', $groupId)
                            ->orWhere(function ($legacy) use ($groupId): void {
                                $legacy
                                    ->whereNull('al.school_group_id')
                                    ->whereNull('al.academic_cycle_id')
                                    ->where('s.current_group_id', $groupId);
                            });
                    });
                }
            )
            ->selectRaw("
                CASE
                    WHEN al.school_group_id IS NOT NULL
                        THEN al.school_group_id
                    WHEN al.academic_cycle_id IS NULL
                        THEN s.current_group_id
                    ELSE NULL
                END as group_id,
                SUM(CASE
                    WHEN al.event_status = 'on_time'
                    THEN 1 ELSE 0
                END) as on_time,
                SUM(CASE
                    WHEN al.event_status = 'late'
                    THEN 1 ELSE 0
                END) as late,
                SUM(CASE
                    WHEN al.event_status = 'very_late'
                    THEN 1 ELSE 0
                END) as very_late,
                COUNT(*) as entries
            ")
            ->groupByRaw("
                CASE
                    WHEN al.school_group_id IS NOT NULL
                        THEN al.school_group_id
                    WHEN al.academic_cycle_id IS NULL
                        THEN s.current_group_id
                    ELSE NULL
                END
            ")
            ->get()
            ->keyBy('group_id');

        $studentCounts = DB::table('student_enrollments as se')
            ->join('students as s', function ($join) use ($schoolId): void {
                $join->on('s.id', '=', 'se.student_id')
                    ->where('s.school_id', '=', $schoolId)
                    ->where('s.status', '=', 'active');
            })
            ->where('se.school_id', $schoolId)
            ->where('se.status', 'active')
            ->when(
                $activeCycle,
                fn ($query) => $query->where(
                    'se.academic_cycle_id',
                    $activeCycle->id
                ),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->when(
                $filters['group_id'],
                fn ($query, $groupId) => $query->where(
                    'se.school_group_id',
                    $groupId
                )
            )
            ->selectRaw(
                'se.school_group_id as group_id, COUNT(DISTINCT s.id) as students'
            )
            ->groupBy('se.school_group_id')
            ->pluck('students', 'group_id');

        return $groups->map(function ($group) use (
            $events,
            $studentCounts
        ): array {
            $event = $events->get($group->id);

            $onTime = (int) ($event->on_time ?? 0);
            $late = (int) ($event->late ?? 0);
            $veryLate = (int) ($event->very_late ?? 0);
            $entries = (int) ($event->entries ?? 0);
            $lateTotal = $late + $veryLate;

            return [
                'group_id' => (int) $group->id,
                'group_name' => $group->name,
                'group_short' => mb_substr(
                    ($group->level_name
                        ? $group->level_name.' '
                        : '')
                    .$group->name,
                    0,
                    22
                ),
                'level_name' => $group->level_name,
                'grade_label' => $group->grade_label,
                'students' => (int) (
                    $studentCounts[$group->id] ?? 0
                ),
                'entries' => $entries,
                'on_time' => $onTime,
                'late' => $late,
                'very_late' => $veryLate,
                'late_total' => $lateTotal,
                'punctuality_rate' => $this->percentage(
                    $onTime,
                    $onTime + $lateTotal
                ),
            ];
        })
            ->sortByDesc('entries')
            ->values();
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'group_id' => ['nullable', 'integer'],
        ]);

        $from = Carbon::parse(
            $validated['from'] ?? now()->subDays(29)->toDateString()
        )->toDateString();

        $to = Carbon::parse(
            $validated['to'] ?? now()->toDateString()
        )->toDateString();

        if (Carbon::parse($from)->gt(Carbon::parse($to))) {
            [$from, $to] = [$to, $from];
        }

        $maximumTo = Carbon::parse($from)
            ->addDays(365)
            ->toDateString();

        if (Carbon::parse($to)->gt(Carbon::parse($maximumTo))) {
            $to = $maximumTo;
        }

        return [
            'from' => $from,
            'to' => $to,
            'group_id' => ! empty($validated['group_id'])
                ? (int) $validated['group_id']
                : null,
        ];
    }
    private function groups(int $schoolId): Collection
    {
        $activeCycle = $this->activeCycle($schoolId);

        return DB::table('school_groups as g')
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
            ]);
    }

    private function activeCycle(int $schoolId): ?object
    {
        return DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->first();
    }

    private function percentage(
        int|float $value,
        int|float $total
    ): float {
        if ($total <= 0) {
            return 0;
        }

        return round(($value / $total) * 100, 1);
    }

    private function schoolId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user && $user->school_id, 403);

        return (int) $user->school_id;
    }

    private function buildGroupedBarSvg(
        array $rows,
        string $labelKey,
        array $series,
        int $width,
        int $height
    ): string {
        if ($rows === []) {
            return $this->emptySvg(
                width: $width,
                height: $height,
                message: 'No hay datos para el periodo.'
            );
        }

        $left = 50;
        $right = 20;
        $top = 30;
        $bottom = 65;

        $chartWidth = $width - $left - $right;
        $chartHeight = $height - $top - $bottom;

        $maximum = 1;

        foreach ($rows as $row) {
            foreach ($series as $item) {
                $maximum = max(
                    $maximum,
                    (int) ($row[$item['key']] ?? 0)
                );
            }
        }

        $countRows = max(count($rows), 1);
        $groupWidth = $chartWidth / $countRows;
        $barGap = 3;
        $seriesCount = max(count($series), 1);
        $barWidth = max(
            4,
            (($groupWidth * 0.72) - ($barGap * ($seriesCount - 1)))
                / $seriesCount
        );

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">',
            $width,
            $height,
            $width,
            $height
        );

        $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';

        for ($step = 0; $step <= 4; $step++) {
            $ratio = $step / 4;
            $y = $top + ($chartHeight * (1 - $ratio));
            $value = (int) round($maximum * $ratio);

            $svg .= sprintf(
                '<line x1="%d" y1="%.2f" x2="%d" y2="%.2f" stroke="#e2e8f0" stroke-width="1"/>',
                $left,
                $y,
                $width - $right,
                $y
            );

            $svg .= sprintf(
                '<text x="%d" y="%.2f" font-size="9" text-anchor="end" fill="#64748b">%d</text>',
                $left - 6,
                $y + 3,
                $value
            );
        }

        foreach ($rows as $rowIndex => $row) {
            $groupX = $left + ($rowIndex * $groupWidth);
            $barsTotalWidth = ($barWidth * $seriesCount)
                + ($barGap * ($seriesCount - 1));

            $startX = $groupX
                + (($groupWidth - $barsTotalWidth) / 2);

            foreach ($series as $seriesIndex => $item) {
                $value = (int) ($row[$item['key']] ?? 0);
                $barHeight = ($value / $maximum) * $chartHeight;
                $x = $startX
                    + ($seriesIndex * ($barWidth + $barGap));
                $y = $top + $chartHeight - $barHeight;

                $svg .= sprintf(
                    '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="2" fill="%s"/>',
                    $x,
                    $y,
                    $barWidth,
                    max($barHeight, 0),
                    $item['color']
                );
            }

            $label = htmlspecialchars(
                (string) ($row[$labelKey] ?? ''),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $labelX = $groupX + ($groupWidth / 2);

            $svg .= sprintf(
                '<text x="%.2f" y="%d" font-size="8" text-anchor="middle" fill="#334155">%s</text>',
                $labelX,
                $height - 38,
                $label
            );
        }

        $legendX = $left;

        foreach ($series as $item) {
            $label = htmlspecialchars(
                $item['label'],
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $svg .= sprintf(
                '<rect x="%d" y="%d" width="10" height="10" rx="2" fill="%s"/>',
                $legendX,
                $height - 18,
                $item['color']
            );

            $svg .= sprintf(
                '<text x="%d" y="%d" font-size="9" fill="#334155">%s</text>',
                $legendX + 15,
                $height - 9,
                $label
            );

            $legendX += 115;
        }

        $svg .= '</svg>';

        return $svg;
    }

    private function emptySvg(
        int $width,
        int $height,
        string $message
    ): string {
        $safe = htmlspecialchars(
            $message,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d">
                <rect width="100%%" height="100%%" fill="#f8fafc"/>
                <text x="50%%" y="50%%" text-anchor="middle" font-size="14" fill="#64748b">%s</text>
            </svg>',
            $width,
            $height,
            $safe
        );
    }
}