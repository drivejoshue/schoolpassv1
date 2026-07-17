<?php

namespace App\Http\Middleware;

use App\Services\Licensing\SchoolLicenseService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicensedFeature
{
    public function __construct(
        private readonly SchoolLicenseService $licenseService,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $featureKey,
    ): Response {
        $schoolId = (int) ($request->user()?->school_id ?? 0);

        if (
            $schoolId <= 0
            || ! $this->licenseService->hasFeature($schoolId, $featureKey)
        ) {
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'message' => 'La función solicitada no está incluida en la licencia.',
                    'code' => 'FEATURE_NOT_LICENSED',
                    'feature' => $featureKey,
                ], 403);
            }

            abort(
                403,
                sprintf('La función "%s" no está habilitada para esta escuela.', $featureKey)
            );
        }

        return $next($request);
    }
}
