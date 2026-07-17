<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditReportExport
{
    public function handle(
        Request $request,
        Closure $next,
        ?string $reportKey = null,
        ?string $format = null
    ): Response {
        $startedAt = hrtime(true);

        try {
            /** @var Response $response */
            $response = $next($request);

            $this->register(
                request: $request,
                response: $response,
                reportKey: $reportKey,
                format: $format,
                status: $response->isSuccessful()
                    ? 'success'
                    : 'failed',
                durationMs: $this->durationMs($startedAt),
                errorMessage: null
            );

            return $response;
        } catch (Throwable $exception) {
            $this->register(
                request: $request,
                response: null,
                reportKey: $reportKey,
                format: $format,
                status: 'error',
                durationMs: $this->durationMs($startedAt),
                errorMessage: mb_substr(
                    $exception->getMessage(),
                    0,
                    4000
                )
            );

            throw $exception;
        }
    }

    private function register(
        Request $request,
        ?Response $response,
        ?string $reportKey,
        ?string $format,
        string $status,
        int $durationMs,
        ?string $errorMessage
    ): void {
        $user = $request->user();

        if (! $user || ! $user->school_id) {
            return;
        }

        $resolvedReportKey = $reportKey
            ?: $this->reportKeyFromRoute($request);

        $resolvedFormat = $format
            ?: $this->formatFromResponse(
                request: $request,
                response: $response
            );

        DB::table('report_export_logs')->insert([
            'school_id' => (int) $user->school_id,
            'user_id' => (int) $user->id,

            'report_key' => $resolvedReportKey,
            'report_name' => $this->reportName(
                $resolvedReportKey
            ),
            'format' => $resolvedFormat,

            'route_name' => $request->route()?->getName(),
            'request_path' => mb_substr(
                $request->path(),
                0,
                255
            ),

            'filters_json' => json_encode(
                $this->safeFilters($request),
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),

            'status' => $status,
            'http_status' => $response?->getStatusCode(),
            'duration_ms' => $durationMs,

            'download_filename' =>
                $this->downloadFilename($response),

            'ip_address' => mb_substr(
                (string) $request->ip(),
                0,
                45
            ),

            'user_agent' => mb_substr(
                (string) $request->userAgent(),
                0,
                4000
            ),

            'error_message' => $errorMessage,
            'exported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function safeFilters(Request $request): array
    {
        $excluded = [
            '_token',
            'password',
            'password_confirmation',
        ];

        return collect($request->query())
            ->except($excluded)
            ->map(function ($value) {
                if (is_array($value)) {
                    return collect($value)
                        ->map(
                            fn ($item) => is_scalar($item)
                                ? mb_substr(
                                    (string) $item,
                                    0,
                                    500
                                )
                                : null
                        )
                        ->filter(
                            fn ($item) => $item !== null
                        )
                        ->values()
                        ->all();
                }

                return is_scalar($value)
                    ? mb_substr(
                        (string) $value,
                        0,
                        500
                    )
                    : null;
            })
            ->filter(
                fn ($value) => $value !== null
            )
            ->all();
    }

    private function reportKeyFromRoute(
        Request $request
    ): string {
        $routeName = (string) (
            $request->route()?->getName()
            ?? 'unknown'
        );

        return str($routeName)
            ->after('admin.reports.')
            ->replace('.', '_')
            ->toString();
    }

    private function reportName(string $key): string
    {
        return match ($key) {
            'students' => 'Directorio de alumnos',
            'guardians' => 'Directorio de tutores',
            'relationships' => 'Relaciones alumno-tutor',

            'attendance' => 'Asistencia diaria',
            'access' => 'Bitácora de accesos',

            'analytics' => 'Analítica de asistencia',
            'monthly_attendance' =>
                'Asistencia mensual',

            'student_individual' =>
                'Reporte individual del alumno',

            'student_incidents' =>
                'Incidencias por alumno',

            default => str($key)
                ->replace('_', ' ')
                ->title()
                ->toString(),
        };
    }

    private function formatFromResponse(
        Request $request,
        ?Response $response
    ): string {
        $routeName = strtolower(
            (string) $request->route()?->getName()
        );

        if (str_ends_with($routeName, '.pdf')) {
            return 'pdf';
        }

        if (
            str_ends_with($routeName, '.excel')
            || str_contains($routeName, 'exports.')
        ) {
            return 'xlsx';
        }

        $contentType = strtolower(
            (string) $response?->headers->get(
                'content-type'
            )
        );

        if (str_contains($contentType, 'pdf')) {
            return 'pdf';
        }

        if (
            str_contains(
                $contentType,
                'spreadsheet'
            )
        ) {
            return 'xlsx';
        }

        return 'file';
    }

    private function downloadFilename(
        ?Response $response
    ): ?string {
        if (! $response) {
            return null;
        }

        if ($response instanceof BinaryFileResponse) {
            $disposition = $response->headers->get(
                'content-disposition'
            );

            if (
                $disposition
                && preg_match(
                    '/filename="?([^";]+)"?/i',
                    $disposition,
                    $matches
                )
            ) {
                return mb_substr(
                    trim($matches[1]),
                    0,
                    255
                );
            }
        }

        $disposition = $response->headers->get(
            'content-disposition'
        );

        if (
            $disposition
            && preg_match(
                '/filename="?([^";]+)"?/i',
                $disposition,
                $matches
            )
        ) {
            return mb_substr(
                trim($matches[1]),
                0,
                255
            );
        }

        return null;
    }

    private function durationMs(int $startedAt): int
    {
        return max(
            0,
            (int) round(
                (hrtime(true) - $startedAt)
                / 1_000_000
            )
        );
    }
}