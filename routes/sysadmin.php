<?php

use App\Http\Controllers\Sysadmin\DashboardController;
use App\Http\Controllers\Sysadmin\SchoolAdministratorController;
use App\Http\Controllers\Sysadmin\SchoolAppConfigController;
use App\Http\Controllers\Sysadmin\SchoolController;
use App\Http\Controllers\Sysadmin\SchoolFeatureController;
use App\Http\Controllers\Sysadmin\SchoolLicenseController;
use App\Http\Controllers\Sysadmin\SubscriptionPlanController;
use App\Http\Controllers\Sysadmin\SupportImpersonationController;
use App\Http\Middleware\EnsureSupportImpersonation;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Sysadmin\AuditLogController;
use App\Http\Controllers\Sysadmin\MobileAppReleasePolicyController;


Route::middleware([
    'auth',
    'superadmin',
])
    ->prefix('sysadmin')
    ->name('sysadmin.')
    ->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/schools', [SchoolController::class, 'index'])
            ->name('schools.index');

        Route::get('/schools/create', [SchoolController::class, 'create'])
            ->name('schools.create');

        Route::post('/schools', [SchoolController::class, 'store'])
            ->name('schools.store');

        Route::get('/schools/{school}', [SchoolController::class, 'show'])
            ->whereNumber('school')
            ->name('schools.show');

        Route::get('/schools/{school}/edit', [SchoolController::class, 'edit'])
            ->whereNumber('school')
            ->name('schools.edit');

        Route::put('/schools/{school}', [SchoolController::class, 'update'])
            ->whereNumber('school')
            ->name('schools.update');

        Route::post(
            '/schools/{school}/suspend',
            [SchoolController::class, 'suspend']
        )
            ->whereNumber('school')
            ->name('schools.suspend');

        Route::post(
            '/schools/{school}/reactivate',
            [SchoolController::class, 'reactivate']
        )
            ->whereNumber('school')
            ->name('schools.reactivate');

        Route::get(
            '/schools/{school}/administrators',
            [SchoolAdministratorController::class, 'index']
        )
            ->whereNumber('school')
            ->name('schools.administrators.index');

        Route::post(
            '/schools/{school}/administrators',
            [SchoolAdministratorController::class, 'store']
        )
            ->whereNumber('school')
            ->name('schools.administrators.store');

        Route::put(
            '/schools/{school}/administrators/{administrator}',
            [SchoolAdministratorController::class, 'update']
        )
            ->whereNumber('school')
            ->whereNumber('administrator')
            ->name('schools.administrators.update');

        Route::post(
            '/schools/{school}/administrators/{administrator}/reset-password',
            [SchoolAdministratorController::class, 'resetPassword']
        )
            ->whereNumber('school')
            ->whereNumber('administrator')
            ->name('schools.administrators.reset-password');

        Route::get(
            '/schools/{school}/app-config',
            [SchoolAppConfigController::class, 'edit']
        )
            ->whereNumber('school')
            ->name('schools.app-config.edit');

        Route::put(
            '/schools/{school}/app-config',
            [SchoolAppConfigController::class, 'update']
        )
            ->whereNumber('school')
            ->name('schools.app-config.update');

        Route::post(
            '/schools/{school}/license',
            [SchoolLicenseController::class, 'assign']
        )
            ->whereNumber('school')
            ->name('schools.license.assign');

        Route::post(
            '/schools/{school}/license/renew',
            [SchoolLicenseController::class, 'renew']
        )
            ->whereNumber('school')
            ->name('schools.license.renew');

        Route::post(
            '/schools/{school}/license/extend-trial',
            [SchoolLicenseController::class, 'extendTrial']
        )
            ->whereNumber('school')
            ->name('schools.license.extend-trial');

        Route::put(
            '/schools/{school}/license/limits',
            [SchoolLicenseController::class, 'updateLimits']
        )
            ->whereNumber('school')
            ->name('schools.license.limits');

        Route::post(
            '/schools/{school}/license/suspend',
            [SchoolLicenseController::class, 'suspend']
        )
            ->whereNumber('school')
            ->name('schools.license.suspend');

        Route::post(
            '/schools/{school}/license/reactivate',
            [SchoolLicenseController::class, 'reactivate']
        )
            ->whereNumber('school')
            ->name('schools.license.reactivate');

        Route::post(
            '/schools/{school}/license/cancel',
            [SchoolLicenseController::class, 'cancel']
        )
            ->whereNumber('school')
            ->name('schools.license.cancel');

        Route::put(
            '/schools/{school}/features',
            [SchoolFeatureController::class, 'update']
        )
            ->whereNumber('school')
            ->name('schools.features.update');

        Route::post(
            '/schools/{school}/support/impersonate',
            [SupportImpersonationController::class, 'start']
        )
            ->whereNumber('school')
            ->name('schools.support.impersonate');


Route::get(
    '/mobile-app-versions',
    [
        MobileAppReleasePolicyController::class,
        'edit',
    ]
)
    ->name('mobile-app-versions.edit');

Route::put(
    '/mobile-app-versions/{policy:app_key}',
    [
        MobileAppReleasePolicyController::class,
        'update',
    ]
)
    ->whereIn('policy', [
        'family',
        'staff',
    ])
    ->name('mobile-app-versions.update');

    

            Route::get(
    '/audit-logs',
    [AuditLogController::class, 'index']
)->name('audit-logs.index');

Route::get(
    '/audit-logs/{auditLog}',
    [AuditLogController::class, 'show']
)
    ->whereNumber('auditLog')
    ->name('audit-logs.show');

    

        Route::get('/plans', [SubscriptionPlanController::class, 'index'])
            ->name('plans.index');
    });

Route::middleware([
    'auth',
    EnsureSupportImpersonation::class,
])
    ->post(
        '/support/impersonation/stop',
        [SupportImpersonationController::class, 'stop']
    )
    ->name('support.impersonation.stop');
