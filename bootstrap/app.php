<?php

use App\Http\Middleware\AuditImpersonatedMutations;
use App\Http\Middleware\AuditReportExport;
use App\Http\Middleware\CheckLicensedFeature;
use App\Http\Middleware\EnsureSchoolLicense;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ShareSchoolLicenseContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use App\Http\Middleware\EnforceSupportImpersonationExpiry;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(
        function (Middleware $middleware): void {

            /*
            |--------------------------------------------------------------------------
            | Middleware web global
            |--------------------------------------------------------------------------
            */

           $middleware->web(append: [
    EnforceSupportImpersonationExpiry::class,
    AuditImpersonatedMutations::class,
    ShareSchoolLicenseContext::class,
]);

            /*
            |--------------------------------------------------------------------------
            | Aliases
            |--------------------------------------------------------------------------
            */

            $middleware->alias([
                'role' => RoleMiddleware::class,

                'report.audit' => AuditReportExport::class,

                'superadmin' => EnsureSuperadmin::class,

                'school.license' => EnsureSchoolLicense::class,

                'school.feature' => CheckLicensedFeature::class,

                /*
                |--------------------------------------------------------------------------
                | Laravel Sanctum token abilities
                |--------------------------------------------------------------------------
                */

                'abilities' => CheckAbilities::class,

                'ability' => CheckForAnyAbility::class,
            ]);
        }
    )
    ->withExceptions(
        function (Exceptions $exceptions): void {
            //
        }
    )
    ->create();