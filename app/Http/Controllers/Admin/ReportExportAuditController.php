<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportExportAuditController extends Controller
{
    public function index(Request $request): View
    {
        $schoolId = $this->schoolId($request);
        $filters = $this->filters($request);

        $base = DB::table('report_export_logs as rel')
            ->leftJoin('users as u', function (
                $join
            ) use ($schoolId): void {
                $join->on('u.id', '=', 'rel.user_id')
                    ->where(
                        'u.school_id',
                        '=',
                        $schoolId
                    );
            })
            ->where('rel.school_id', $schoolId)
            ->whereBetween('rel.exported_at', [
                Carbon::parse($filters['from'])
                    ->startOfDay(),
                Carbon::parse($filters['to'])
                    ->endOfDay(),
            ])
            ->when(
                $filters['user_id'],
                fn ($query, $userId) =>
                    $query->where(
                        'rel.user_id',
                        $userId
                    )
            )
            ->when(
                $filters['report_key'],
                fn ($query, $reportKey) =>
                    $query->where(
                        'rel.report_key',
                        $reportKey
                    )
            )
            ->when(
                $filters['format'],
                fn ($query, $format) =>
                    $query->where(
                        'rel.format',
                        $format
                    )
            )
            ->when(
                $filters['status'],
                fn ($query, $status) =>
                    $query->where(
                        'rel.status',
                        $status
                    )
            );

        $logs = (clone $base)
            ->select([
                'rel.id',
                'rel.user_id',
                'rel.report_key',
                'rel.report_name',
                'rel.format',
                'rel.route_name',
                'rel.request_path',
                'rel.filters_json',
                'rel.status',
                'rel.http_status',
                'rel.duration_ms',
                'rel.download_filename',
                'rel.ip_address',
                'rel.user_agent',
                'rel.error_message',
                'rel.exported_at',
                'u.name as user_name',
                'u.email as user_email',
                'u.role as user_role',
            ])
            ->orderByDesc('rel.exported_at')
            ->paginate(30)
            ->withQueryString();

        $summary = [
            'total' => (clone $base)->count(),

            'success' => (clone $base)
                ->where('rel.status', 'success')
                ->count(),

            'failed' => (clone $base)
                ->whereIn(
                    'rel.status',
                    ['failed', 'error']
                )
                ->count(),

            'pdf' => (clone $base)
                ->where('rel.format', 'pdf')
                ->count(),

            'xlsx' => (clone $base)
                ->where('rel.format', 'xlsx')
                ->count(),

            'users' => (clone $base)
                ->whereNotNull('rel.user_id')
                ->distinct()
                ->count('rel.user_id'),

            'average_duration_ms' => (int) round(
                (float) (
                    (clone $base)->avg(
                        'rel.duration_ms'
                    ) ?? 0
                )
            ),
        ];

        $reportOptions = DB::table(
            'report_export_logs'
        )
            ->where('school_id', $schoolId)
            ->select([
                'report_key',
                'report_name',
            ])
            ->distinct()
            ->orderBy('report_name')
            ->get();

        $users = DB::table('users')
            ->where('school_id', $schoolId)
            ->whereIn('role', [
                'superadmin',
                'school_admin',
                'director',
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
            ]);

        $topReports = (clone $base)
            ->selectRaw(
                'rel.report_key,
                 rel.report_name,
                 COUNT(*) as exports_count'
            )
            ->groupBy(
                'rel.report_key',
                'rel.report_name'
            )
            ->orderByDesc('exports_count')
            ->limit(8)
            ->get();

        return view(
            'admin.reports.export-audit',
            [
                'filters' => $filters,
                'logs' => $logs,
                'summary' => $summary,
                'reportOptions' => $reportOptions,
                'users' => $users,
                'topReports' => $topReports,
            ]
        );
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => [
                'nullable',
                'date',
            ],
            'to' => [
                'nullable',
                'date',
            ],
            'user_id' => [
                'nullable',
                'integer',
            ],
            'report_key' => [
                'nullable',
                'string',
                'max:100',
            ],
            'format' => [
                'nullable',
                'in:pdf,xlsx,file',
            ],
            'status' => [
                'nullable',
                'in:success,failed,error',
            ],
        ]);

        $from = Carbon::parse(
            $validated['from']
                ?? now()->subDays(29)->toDateString()
        )->toDateString();

        $to = Carbon::parse(
            $validated['to']
                ?? now()->toDateString()
        )->toDateString();

        if (
            Carbon::parse($from)
                ->gt(Carbon::parse($to))
        ) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from,
            'to' => $to,

            'user_id' => ! empty(
                $validated['user_id']
            )
                ? (int) $validated['user_id']
                : null,

            'report_key' =>
                $validated['report_key']
                ?? null,

            'format' =>
                $validated['format']
                ?? null,

            'status' =>
                $validated['status']
                ?? null,
        ];
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