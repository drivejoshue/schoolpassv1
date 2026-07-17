<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| Acceso general
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Access\AccessScanController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
/*
|--------------------------------------------------------------------------
| Administración
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\GuardianController;
use App\Http\Controllers\Admin\CredentialController;
use App\Http\Controllers\Admin\CredentialBatchController;
use App\Http\Controllers\Admin\GroupScheduleController;
use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\AreaAccessRuleController;
use App\Http\Controllers\Admin\AccessDeviceController;
use App\Http\Controllers\Admin\AcademicCycleController;
use App\Http\Controllers\Admin\SchoolCalendarController;
use App\Http\Controllers\Admin\SchoolNoticeController;
use App\Http\Controllers\Admin\CycleEnrollmentController;
use App\Http\Controllers\Admin\StudentPromotionController;

/*
|--------------------------------------------------------------------------
| Herramientas e importación
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Admin\TenantToolsController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\StudentImportTemplateController;
use App\Http\Controllers\Admin\SystemUserController;
/*
|--------------------------------------------------------------------------
| Reportes
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Admin\AccessReportController;
use App\Http\Controllers\Admin\AttendanceReportController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\AnalyticsReportController;
use App\Http\Controllers\Admin\MonthlyAttendanceReportController;
use App\Http\Controllers\Admin\StudentIndividualReportController;
use App\Http\Controllers\Admin\StudentIncidentReportController;
use App\Http\Controllers\Admin\ReportExportAuditController;
/*
|--------------------------------------------------------------------------
| Portales por rol
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Guardian\GuardianHomeController;
use App\Http\Controllers\Kiosk\KioskAccessController;
use App\Http\Controllers\Prefect\PrefectAccessController;
use App\Http\Controllers\Student\StudentHomeController;

use App\Http\Controllers\Admin\DirectionLiveController;


use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\LicenseBlockedController;


/*
|--------------------------------------------------------------------------
| Inicio
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

  return match (auth()->user()->role) {
    'superadmin' => redirect()->route('sysadmin.dashboard'),

    'school_admin',
    'director' => redirect()->route('admin.dashboard'),

        'prefect' => redirect()->route('prefect.access'),
        'guardian' => redirect()->route('guardian.home'),
        'student' => redirect()->route('student.home'),
        'kiosk' => redirect()->route('kiosk.access'),

        default => abort(403),
    };
})->name('home');

/*
|--------------------------------------------------------------------------
| Autenticación web
|--------------------------------------------------------------------------
*/

Route::middleware('guest')
    ->group(function (): void {

        /*
        |--------------------------------------------------------------------------
        | Inicio de sesión
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/login',
            [LoginController::class, 'show']
        )->name('login');

        Route::post(
            '/login',
            [LoginController::class, 'login']
        )
            ->middleware('throttle:8,1')
            ->name('login.store');

        /*
        |--------------------------------------------------------------------------
        | Recuperación de contraseña
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/forgot-password',
            [ForgotPasswordController::class, 'show']
        )->name('password.request');

        Route::post(
            '/forgot-password',
            [ForgotPasswordController::class, 'send']
        )
            ->middleware('throttle:5,1')
            ->name('password.email');

        Route::get(
            '/reset-password/{token}',
            [ResetPasswordController::class, 'show']
        )->name('password.reset');

        Route::post(
            '/reset-password',
            [ResetPasswordController::class, 'update']
        )
            ->middleware('throttle:5,1')
            ->name('password.update');
    });

/*
|--------------------------------------------------------------------------
| Cierre de sesión
|--------------------------------------------------------------------------
*/

Route::post(
    '/logout',
    [LoginController::class, 'logout']
)
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Licencia bloqueada
|--------------------------------------------------------------------------
*/

Route::get(
    '/license/blocked',
    [LicenseBlockedController::class, 'show']
)
    ->middleware('auth')
    ->name('license.blocked');
/*
|--------------------------------------------------------------------------
| Panel administrativo
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'role:superadmin,school_admin,director',
    'school.license',
])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard',
            [DashboardController::class, 'index']
        )->name('dashboard');

        Route::get(
    '/license',
    [LicenseController::class, 'show']
)->name('license.show');

        /*
        |--------------------------------------------------------------------------
        | Herramientas del tenant
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/tools',
            [TenantToolsController::class, 'index']
        )->name('tools.index');

        /*
        |--------------------------------------------------------------------------
        | Importaciones
        |--------------------------------------------------------------------------
        */

        Route::prefix('imports')
            ->name('imports.')
            ->group(function () {
                Route::get(
                    '/students',
                    [StudentImportController::class, 'index']
                )->name('students.index');

                Route::get(
                    '/students/template',
                    StudentImportTemplateController::class
                )->name('students.template');

                Route::post(
                    '/students/preview',
                    [StudentImportController::class, 'preview']
                )->name('students.preview');

                Route::post(
                    '/students/commit',
                    [StudentImportController::class, 'commit']
                )->name('students.commit');

                Route::delete(
                    '/students/preview',
                    [StudentImportController::class, 'discard']
                )->name('students.discard');
            });

/*
|--------------------------------------------------------------------------
| Pantalla en vivo de dirección
|--------------------------------------------------------------------------
*/

Route::get(
    '/direction-live',
    [DirectionLiveController::class, 'index']
)->name('direction-live.index');

Route::get(
    '/direction-live/data',
    [DirectionLiveController::class, 'data']
)
    ->middleware('throttle:120,1')
    ->name('direction-live.data');

/*
|--------------------------------------------------------------------------
| Usuarios institucionales
|--------------------------------------------------------------------------
*/




Route::get(
    '/users',
    [SystemUserController::class, 'index']
)->name('users.index');

Route::get(
    '/users/create',
    [SystemUserController::class, 'create']
)->name('users.create');

Route::post(
    '/users',
    [SystemUserController::class, 'store']
)->name('users.store');

Route::get(
    '/users/{user}/edit',
    [SystemUserController::class, 'edit']
)
    ->whereNumber('user')
    ->name('users.edit');

Route::put(
    '/users/{user}',
    [SystemUserController::class, 'update']
)
    ->whereNumber('user')
    ->name('users.update');

Route::patch(
    '/users/{user}/status',
    [SystemUserController::class, 'updateStatus']
)
    ->whereNumber('user')
    ->name('users.status');

Route::patch(
    '/users/{user}/password',
    [SystemUserController::class, 'resetPassword']
)
    ->whereNumber('user')
    ->name('users.password.reset');



    
        /*
        |--------------------------------------------------------------------------
        | Alumnos
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/students',
            [StudentController::class, 'index']
        )->name('students.index');

        Route::get(
            '/students/create',
            [StudentController::class, 'create']
        )->name('students.create');

        Route::post(
            '/students',
            [StudentController::class, 'store']
        )->name('students.store');

        Route::get(
            '/students/{student}',
            [StudentController::class, 'show']
        )
            ->whereNumber('student')
            ->name('students.show');

        Route::post(
            '/students/{student}/photo',
            [StudentController::class, 'uploadPhoto']
        )
            ->whereNumber('student')
            ->name('students.photo.upload');

        Route::delete(
            '/students/{student}/photo',
            [StudentController::class, 'removePhoto']
        )
            ->whereNumber('student')
            ->name('students.photo.remove');

        Route::post(
            '/students/{student}/credentials',
            [CredentialController::class, 'store']
        )
            ->whereNumber('student')
            ->name('students.credentials.store');


            Route::get(
    '/students/{student}/manage',
    [StudentController::class, 'manage']
)
    ->whereNumber('student')
    ->name('students.manage');

Route::patch(
    '/students/{student}/enrollment',
    [StudentController::class, 'updateEnrollment']
)
    ->whereNumber('student')
    ->name('students.enrollment.update');

       /*
|--------------------------------------------------------------------------
| Tutores
|--------------------------------------------------------------------------
*/

Route::get(
    '/guardians',
    [GuardianController::class, 'index']
)->name('guardians.index');

Route::get(
    '/guardians/create',
    [GuardianController::class, 'create']
)->name('guardians.create');

Route::post(
    '/guardians',
    [GuardianController::class, 'store']
)->name('guardians.store');

Route::get(
    '/guardians/{guardian}',
    [GuardianController::class, 'show']
)
    ->whereNumber('guardian')
    ->name('guardians.show');

Route::get(
    '/guardians/{guardian}/edit',
    [GuardianController::class, 'edit']
)
    ->whereNumber('guardian')
    ->name('guardians.edit');

Route::put(
    '/guardians/{guardian}',
    [GuardianController::class, 'update']
)
    ->whereNumber('guardian')
    ->name('guardians.update');

Route::post(
    '/guardians/{guardian}/photo',
    [GuardianController::class, 'uploadPhoto']
)
    ->whereNumber('guardian')
    ->name('guardians.photo.upload');

Route::delete(
    '/guardians/{guardian}/photo',
    [GuardianController::class, 'removePhoto']
)
    ->whereNumber('guardian')
    ->name('guardians.photo.remove');

Route::post(
    '/guardians/{guardian}/students',
    [GuardianController::class, 'linkStudent']
)
    ->whereNumber('guardian')
    ->name('guardians.students.link');

Route::patch(
    '/guardians/{guardian}/students/{student}/permissions',
    [GuardianController::class, 'updateStudentPermissions']
)
    ->whereNumber('guardian')
    ->whereNumber('student')
    ->name('guardians.students.permissions');

Route::patch(
    '/guardians/{guardian}/students/{student}/unlink',
    [GuardianController::class, 'unlinkStudent']
)
    ->whereNumber('guardian')
    ->whereNumber('student')
    ->name('guardians.students.unlink');

Route::post(
    '/guardians/{guardian}/credentials',
    [GuardianController::class, 'createCredential']
)
    ->whereNumber('guardian')
    ->name('guardians.credentials.create');

Route::post(
    '/guardians/{guardian}/credentials/regenerate',
    [GuardianController::class, 'regenerateCredential']
)
    ->whereNumber('guardian')
    ->name('guardians.credentials.regenerate');

Route::patch(
    '/guardians/{guardian}/credentials/{credential}/revoke',
    [GuardianController::class, 'revokeCredential']
)
    ->whereNumber('guardian')
    ->whereNumber('credential')
    ->name('guardians.credentials.revoke');

Route::get(
    '/guardians/{guardian}/credentials/{credential}/print',
    [GuardianController::class, 'printCredential']
)
    ->whereNumber('guardian')
    ->whereNumber('credential')
    ->name('guardians.credentials.print');

Route::post(
    '/guardians/{guardian}/account',
    [GuardianController::class, 'createAccount']
)
    ->whereNumber('guardian')
    ->name('guardians.account.create');

Route::patch(
    '/guardians/{guardian}/account/password',
    [GuardianController::class, 'resetAccountPassword']
)
    ->whereNumber('guardian')
    ->name('guardians.account.reset');

Route::patch(
    '/guardians/{guardian}/account/status',
    [GuardianController::class, 'updateAccountStatus']
)
    ->whereNumber('guardian')
    ->name('guardians.account.status');


        /*
        |--------------------------------------------------------------------------
        | Grupos y horarios
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/groups',
            [GroupScheduleController::class, 'index']
        )->name('groups.index');

        Route::get(
            '/groups/{group}/schedules',
            [GroupScheduleController::class, 'edit']
        )
            ->whereNumber('group')
            ->name('groups.schedules.edit');

        Route::put(
            '/groups/{group}/schedules',
            [GroupScheduleController::class, 'update']
        )
            ->whereNumber('group')
            ->name('groups.schedules.update');

        /*
        |--------------------------------------------------------------------------
        | Áreas
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/areas',
            [AreaController::class, 'index']
        )->name('areas.index');

        Route::get(
            '/areas/create',
            [AreaController::class, 'create']
        )->name('areas.create');

        Route::post(
            '/areas',
            [AreaController::class, 'store']
        )->name('areas.store');

        Route::get(
            '/areas/{area}/edit',
            [AreaController::class, 'edit']
        )
            ->whereNumber('area')
            ->name('areas.edit');

        Route::put(
            '/areas/{area}',
            [AreaController::class, 'update']
        )
            ->whereNumber('area')
            ->name('areas.update');

        /*
        |--------------------------------------------------------------------------
        | Dispositivos
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/devices',
            [AccessDeviceController::class, 'index']
        )->name('devices.index');

        Route::get(
            '/devices/create',
            [AccessDeviceController::class, 'create']
        )->name('devices.create');

        Route::post(
            '/devices',
            [AccessDeviceController::class, 'store']
        )->name('devices.store');

        Route::get(
            '/devices/{device}/edit',
            [AccessDeviceController::class, 'edit']
        )
            ->whereNumber('device')
            ->name('devices.edit');

        Route::put(
            '/devices/{device}',
            [AccessDeviceController::class, 'update']
        )
            ->whereNumber('device')
            ->name('devices.update');

        Route::post(
            '/devices/{device}/account',
            [AccessDeviceController::class, 'createAccount']
        )
            ->whereNumber('device')
            ->name('devices.account.create');

        Route::patch(
            '/devices/{device}/password',
            [AccessDeviceController::class, 'resetPassword']
        )
            ->whereNumber('device')
            ->name('devices.password.reset');

        /*
        |--------------------------------------------------------------------------
        | Reglas de acceso por área
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/area-rules',
            [AreaAccessRuleController::class, 'index']
        )->name('area-rules.index');

        Route::get(
            '/area-rules/create',
            [AreaAccessRuleController::class, 'create']
        )->name('area-rules.create');

        Route::post(
            '/area-rules',
            [AreaAccessRuleController::class, 'store']
        )->name('area-rules.store');

        Route::get(
            '/area-rules/{rule}/edit',
            [AreaAccessRuleController::class, 'edit']
        )
            ->whereNumber('rule')
            ->name('area-rules.edit');

        Route::put(
            '/area-rules/{rule}',
            [AreaAccessRuleController::class, 'update']
        )
            ->whereNumber('rule')
            ->name('area-rules.update');




            /*
|--------------------------------------------------------------------------
| Promoción y reinscripción
|--------------------------------------------------------------------------
*/

Route::prefix('promotions')
    ->name('promotions.')
    ->group(function () {
        Route::get(
            '/',
            [StudentPromotionController::class, 'index']
        )->name('index');

        Route::post(
            '/copy-structure',
            [
                StudentPromotionController::class,
                'copyStructure',
            ]
        )->name('copy-structure');

        Route::post(
            '/save',
            [StudentPromotionController::class, 'save']
        )->name('save');

        Route::post(
            '/apply',
            [StudentPromotionController::class, 'apply']
        )->name('apply');
    });


    Route::prefix('cycles/{cycle}/enrollments')
    ->whereNumber('cycle')
    ->name('cycle-enrollments.')
    ->group(function () {
        Route::get(
            '/',
            [
                CycleEnrollmentController::class,
                'index',
            ]
        )->name('index');

        Route::post(
            '/assign',
            [
                CycleEnrollmentController::class,
                'assign',
            ]
        )->name('assign');

        Route::post(
            '/copy-group',
            [
                CycleEnrollmentController::class,
                'copyGroup',
            ]
        )->name('copy-group');

        Route::post(
            '/sync',
            [
                CycleEnrollmentController::class,
                'syncActiveCycle',
            ]
        )->name('sync');
    });

               /*
        |--------------------------------------------------------------------------
        | Reportes
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | Reporte de accesos
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/reports/access',
            [AccessReportController::class, 'index']
        )->name('reports.access');

        /*
        |--------------------------------------------------------------------------
        | Reporte de asistencia diaria
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/reports/attendance',
            [AttendanceReportController::class, 'index']
        )->name('reports.attendance');

        /*
        |--------------------------------------------------------------------------
        | Analítica y exportación PDF
        |--------------------------------------------------------------------------
        */

        Route::prefix('reports/analytics')
            ->name('reports.analytics.')
            ->group(function () {
                Route::get(
                    '/',
                    [AnalyticsReportController::class, 'index']
                )->name('index');

                Route::get(
                    '/pdf',
                    [AnalyticsReportController::class, 'pdf']
                )
                    ->middleware('report.audit:analytics,pdf')
                    ->name('pdf');
            });

        /*
        |--------------------------------------------------------------------------
        | Asistencia mensual
        |--------------------------------------------------------------------------
        */

        Route::prefix('reports/monthly-attendance')
            ->name('reports.monthly-attendance.')
            ->group(function () {
                Route::get(
                    '/',
                    [MonthlyAttendanceReportController::class, 'index']
                )->name('index');

                Route::get(
                    '/excel',
                    [MonthlyAttendanceReportController::class, 'excel']
                )
                    ->middleware('report.audit:monthly_attendance,xlsx')
                    ->name('excel');

                Route::get(
                    '/pdf',
                    [MonthlyAttendanceReportController::class, 'pdf']
                )
                    ->middleware('report.audit:monthly_attendance,pdf')
                    ->name('pdf');
            });

        /*
        |--------------------------------------------------------------------------
        | Reporte individual del alumno
        |--------------------------------------------------------------------------
        */

        Route::prefix('reports/student-individual')
            ->name('reports.student-individual.')
            ->group(function () {
                Route::get(
                    '/',
                    [StudentIndividualReportController::class, 'index']
                )->name('index');

                Route::get(
                    '/pdf',
                    [StudentIndividualReportController::class, 'pdf']
                )
                    ->middleware('report.audit:student_individual,pdf')
                    ->name('pdf');
            });

        /*
        |--------------------------------------------------------------------------
        | Incidencias por alumno
        |--------------------------------------------------------------------------
        */

        Route::prefix('reports/student-incidents')
            ->name('reports.student-incidents.')
            ->group(function () {
                Route::get(
                    '/',
                    [StudentIncidentReportController::class, 'index']
                )->name('index');

                Route::get(
                    '/excel',
                    [StudentIncidentReportController::class, 'excel']
                )
                    ->middleware('report.audit:student_incidents,xlsx')
                    ->name('excel');

                Route::get(
                    '/pdf',
                    [StudentIncidentReportController::class, 'pdf']
                )
                    ->middleware('report.audit:student_incidents,pdf')
                    ->name('pdf');
            });

        /*
        |--------------------------------------------------------------------------
        | Exportaciones Excel
        |--------------------------------------------------------------------------
        */

        Route::prefix('reports/exports')
            ->name('reports.exports.')
            ->group(function () {
                Route::get(
                    '/',
                    [ReportExportController::class, 'index']
                )->name('index');

                Route::get(
                    '/students',
                    [ReportExportController::class, 'students']
                )
                    ->middleware('report.audit:students,xlsx')
                    ->name('students');

                Route::get(
                    '/guardians',
                    [ReportExportController::class, 'guardians']
                )
                    ->middleware('report.audit:guardians,xlsx')
                    ->name('guardians');

                Route::get(
                    '/relationships',
                    [ReportExportController::class, 'relationships']
                )
                    ->middleware('report.audit:relationships,xlsx')
                    ->name('relationships');

                Route::get(
                    '/attendance',
                    [ReportExportController::class, 'attendance']
                )
                    ->middleware('report.audit:attendance,xlsx')
                    ->name('attendance');

                Route::get(
                    '/access',
                    [ReportExportController::class, 'access']
                )
                    ->middleware('report.audit:access,xlsx')
                    ->name('access');
            });

        /*
        |--------------------------------------------------------------------------
        | Auditoría de exportaciones
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/reports/export-audit',
            [ReportExportAuditController::class, 'index']
        )->name('reports.export-audit.index');

        /*
        |--------------------------------------------------------------------------
        | Credenciales
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/credentials',
            [CredentialBatchController::class, 'index']
        )->name('credentials.index');

        Route::post(
            '/credentials/generate-missing',
            [CredentialBatchController::class, 'generateMissing']
        )->name('credentials.generate-missing');

        Route::get(
            '/credentials/print',
            [CredentialBatchController::class, 'print']
        )->name('credentials.print');

        Route::patch(
            '/credentials/{credential}/revoke',
            [CredentialController::class, 'revoke']
        )
            ->whereNumber('credential')
            ->name('credentials.revoke');

        /*
        |--------------------------------------------------------------------------
        | Calendario escolar
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/calendar',
            [SchoolCalendarController::class, 'index']
        )->name('calendar.index');

        Route::get(
            '/calendar/create',
            [SchoolCalendarController::class, 'create']
        )->name('calendar.create');

        Route::post(
            '/calendar',
            [SchoolCalendarController::class, 'store']
        )->name('calendar.store');

        Route::get(
            '/calendar/{day}/edit',
            [SchoolCalendarController::class, 'edit']
        )
            ->whereNumber('day')
            ->name('calendar.edit');

        Route::put(
            '/calendar/{day}',
            [SchoolCalendarController::class, 'update']
        )
            ->whereNumber('day')
            ->name('calendar.update');

     /*
|--------------------------------------------------------------------------
| Ciclos escolares
|--------------------------------------------------------------------------
*/

Route::prefix('cycles')
    ->name('cycles.')
    ->group(function () {
        Route::get(
            '/',
            [AcademicCycleController::class, 'index']
        )->name('index');

        Route::get(
            '/create',
            [AcademicCycleController::class, 'create']
        )->name('create');

        Route::post(
            '/',
            [AcademicCycleController::class, 'store']
        )->name('store');

        Route::get(
            '/{cycle}/edit',
            [AcademicCycleController::class, 'edit']
        )
            ->whereNumber('cycle')
            ->name('edit');

        Route::put(
            '/{cycle}',
            [AcademicCycleController::class, 'update']
        )
            ->whereNumber('cycle')
            ->name('update');

        Route::patch(
            '/{cycle}/activate',
            [AcademicCycleController::class, 'activate']
        )
            ->whereNumber('cycle')
            ->name('activate');

        Route::patch(
            '/{cycle}/close',
            [AcademicCycleController::class, 'close']
        )
            ->whereNumber('cycle')
            ->name('close');
    });

        /*
        |--------------------------------------------------------------------------
        | Avisos escolares
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/notices',
            [SchoolNoticeController::class, 'index']
        )->name('notices.index');

        Route::get(
            '/notices/create',
            [SchoolNoticeController::class, 'create']
        )->name('notices.create');

        Route::post(
            '/notices',
            [SchoolNoticeController::class, 'store']
        )->name('notices.store');

        Route::get(
            '/notices/{notice}/edit',
            [SchoolNoticeController::class, 'edit']
        )
            ->whereNumber('notice')
            ->name('notices.edit');

        Route::put(
            '/notices/{notice}',
            [SchoolNoticeController::class, 'update']
        )
            ->whereNumber('notice')
            ->name('notices.update');

        Route::post(
            '/notices/{notice}/publish',
            [SchoolNoticeController::class, 'publish']
        )
            ->whereNumber('notice')
            ->name('notices.publish');

        Route::post(
            '/notices/{notice}/archive',
            [SchoolNoticeController::class, 'archive']
        )
            ->whereNumber('notice')
            ->name('notices.archive');
    });

/*
|--------------------------------------------------------------------------
| Portal de prefectura
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'role:prefect,director,school_admin,superadmin',
    'school.license',
])
    ->prefix('prefect')
    ->name('prefect.')
    ->group(function (): void {
        Route::get(
            '/access',
            [PrefectAccessController::class, 'index']
        )->name('access');
    });

/*
|--------------------------------------------------------------------------
| Portal de tutores
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'role:guardian',
    'school.license',
])
    ->prefix('guardian')
    ->name('guardian.')
    ->group(function (): void {
        Route::get(
            '/home',
            [GuardianHomeController::class, 'index']
        )->name('home');
    });

/*
|--------------------------------------------------------------------------
| Portal de alumnos
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'role:student',
    'school.license',
])
    ->prefix('student')
    ->name('student.')
    ->group(function (): void {
        Route::get(
            '/home',
            [StudentHomeController::class, 'index']
        )->name('home');
    });

/*
|--------------------------------------------------------------------------
| Portal kiosco
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'role:kiosk,superadmin,school_admin,director',
    'school.license',
])
    ->prefix('kiosk')
    ->name('kiosk.')
    ->group(function (): void {
        Route::get(
            '/access',
            [KioskAccessController::class, 'index']
        )->name('access');
    });
/*
|--------------------------------------------------------------------------
| Escaneo web compartido
|--------------------------------------------------------------------------
*/

Route::post(
    '/access/scan',
    [AccessScanController::class, 'scan']
)
    ->middleware([
        'auth',
        'role:superadmin,school_admin,director,prefect,kiosk',
        'school.license',
    ])
    ->name('access.scan');













    require __DIR__.'/sysadmin.php';