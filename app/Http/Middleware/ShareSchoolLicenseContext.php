<?php

namespace App\Http\Middleware;

use App\Services\Licensing\SchoolLicenseStateService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ShareSchoolLicenseContext
{
    public function __construct(
        private readonly SchoolLicenseStateService $licenseStateService,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        if (
            $user !== null
            && $user->school_id !== null
        ) {
            try {
                $state = $request->attributes->get(
                    'school_license_state'
                );

                if (! is_array($state)) {
                    $state = $this->licenseStateService
                        ->forSchoolId(
                            (int) $user->school_id
                        );

                    $request->attributes->set(
                        'school_license_state',
                        $state
                    );
                }

                View::share(
                    'schoolLicenseContext',
                    $state
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $next($request);
    }
}