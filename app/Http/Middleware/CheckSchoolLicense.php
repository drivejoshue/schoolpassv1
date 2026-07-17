<?php

namespace App\Http\Middleware;

use App\Services\Licensing\SchoolLicenseService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSchoolLicense
{
    public function __construct(
        private readonly SchoolLicenseService $licenseService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if ($user->role === 'superadmin' && $user->school_id === null) {
            return $request->expectsJson()
                ? new JsonResponse([
                    'message' => 'El superadministrador debe operar desde el panel Sysadmin.',
                ], 403)
                : redirect()->route('sysadmin.dashboard');
        }

        $schoolId = (int) $user->school_id;

        if ($schoolId <= 0 || ! $this->licenseService->isActive($schoolId)) {
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'message' => 'La licencia de la escuela no está activa.',
                    'code' => 'SCHOOL_LICENSE_INACTIVE',
                ], 402);
            }

            return response()->view('errors.school-license-inactive', [
                'license' => $schoolId > 0
                    ? $this->licenseService->current($schoolId)
                    : null,
            ], 402);
        }

        $request->attributes->set(
            'school_license',
            $this->licenseService->current($schoolId)
        );

        return $next($request);
    }
}
