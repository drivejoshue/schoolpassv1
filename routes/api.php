<?php

use App\Http\Controllers\Api\V1\AccessScanController;
use App\Http\Controllers\Api\V1\AdminSchoolNoticeController;
use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FamilyController;
use App\Http\Controllers\Api\V1\FamilyDeviceController;
use App\Http\Controllers\Api\V1\FamilyNoticeController;
use App\Http\Controllers\Api\V1\GuardianController;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AppVersionController;

Route::get(
    '/v1/app/version',
    AppVersionController::class
);


Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {

        /*
        |--------------------------------------------------------------------------
        | Public authentication
        |--------------------------------------------------------------------------
        */

        Route::post('/auth/login', [AuthController::class, 'login'])
            ->name('auth.login');

        /*
        |--------------------------------------------------------------------------
        | Authenticated API
        |--------------------------------------------------------------------------
        */

        Route::middleware('auth:sanctum')
            ->group(function (): void {

                /*
                |--------------------------------------------------------------------------
                | Session
                |--------------------------------------------------------------------------
                */

                Route::get('/me', [AuthController::class, 'me'])
                    ->name('me');

                Route::post('/auth/logout', [AuthController::class, 'logout'])
                    ->name('auth.logout');

                Route::post('/auth/change-password', [AuthController::class, 'changePassword'])
                    ->name('auth.change-password');

                        Route::get('/app/config', [AppConfigController::class, 'show'])
                    ->name('app.config');

                /*
                |--------------------------------------------------------------------------
                | Staff / Access Scan
                |--------------------------------------------------------------------------
                */

              Route::prefix('access')
    ->middleware([
        'role:superadmin,school_admin,director,prefect,kiosk',
        'school.license',
    ])
    ->name('access.')
    ->group(function (): void {
        Route::get(
            '/bootstrap',
            [AccessScanController::class, 'bootstrap']
        )->name('bootstrap');

        Route::post(
            '/scan',
            [AccessScanController::class, 'scan']
        )->name('scan');



        Route::post(
    '/guardian/confirm',
    [AccessScanController::class, 'guardianConfirm']
)->name('guardian.confirm');




        Route::get(
            '/recent',
            [AccessScanController::class, 'recent']
        )->name('recent');

        Route::get(
            '/students/search',
            [AccessScanController::class, 'searchStudents']
        )->name('students.search');


        Route::get(
    '/students/{student}/guardians',
    [AccessScanController::class, 'studentGuardians']
)->name('students.guardians');

        

        Route::post(
            '/manual',
            [AccessScanController::class, 'manual']
        )->name('manual');
    });



                /*
                |--------------------------------------------------------------------------
                | Family App
                |--------------------------------------------------------------------------
                |
                | Por ahora solo guardian.
                | El rol student se habilitará después de revisar los controladores
                | y definir respuestas específicas para cada tipo de usuario.
                |
                */

               Route::prefix('family')
    ->middleware([
        'role:guardian',
        'ability:family:read',
         'school.license',
    ])
    ->name('family.')
                    ->group(function (): void {

                        /*
                        |--------------------------------------------------------------------------
                        | Family Profile
                        |--------------------------------------------------------------------------
                        */

                        Route::get('/me', [FamilyController::class, 'me'])
                            ->name('me');


                            Route::post(
    '/profile/photo',
    [FamilyController::class, 'uploadPhoto']
)->name('profile.photo');

Route::get(
    '/credential',
    [FamilyController::class, 'guardianCredential']
)->name('credential');

                        /*
                        |--------------------------------------------------------------------------
                        | Family Devices / FCM
                        |--------------------------------------------------------------------------
                        */

                      Route::prefix('devices')
    ->middleware('ability:family:devices')
    ->name('devices.')
    ->group(function (): void {
                                Route::post('/', [FamilyDeviceController::class, 'store'])
                                    ->name('store');

                                Route::patch('/current/preferences', [FamilyDeviceController::class, 'preferences'])
                                    ->name('preferences');

                                Route::delete('/current', [FamilyDeviceController::class, 'destroyCurrent'])
                                    ->name('destroy-current');
                            });

                        /*
                        |--------------------------------------------------------------------------
                        | Students
                        |--------------------------------------------------------------------------
                        */

                        Route::get('/students', [FamilyController::class, 'students'])
                            ->name('students');

                        Route::get('/students/{student}/attendance', [FamilyController::class, 'attendance'])
                            ->whereNumber('student')
                            ->name('students.attendance');

                        Route::get('/students/{student}/credential', [FamilyController::class, 'credential'])
                            ->whereNumber('student')
                            ->name('students.credential');

                        /*
                        |--------------------------------------------------------------------------
                        | Notifications
                        |--------------------------------------------------------------------------
                        */

                        Route::get('/notifications', [FamilyController::class, 'notifications'])
                            ->name('notifications');

                        Route::patch(
                            '/notifications/{notification}/read',
                            [FamilyController::class, 'markNotificationAsRead']
                        )
                            ->whereNumber('notification')
                            ->name('notifications.read');

                        /*
                        |--------------------------------------------------------------------------
                        | School Notices
                        |--------------------------------------------------------------------------
                        */

                        Route::prefix('notices')
                            ->name('notices.')
                            ->group(function (): void {
                                Route::get('/', [FamilyNoticeController::class, 'index'])
                                    ->name('index');

                                Route::get('/modal', [FamilyNoticeController::class, 'modal'])
                                    ->name('modal');

                                Route::get('/{notice}', [FamilyNoticeController::class, 'show'])
                                    ->whereNumber('notice')
                                    ->name('show');

                                Route::patch('/{notice}/read', [FamilyNoticeController::class, 'markAsRead'])
                                    ->whereNumber('notice')
                                    ->name('read');

                                Route::patch('/{notice}/acknowledge', [FamilyNoticeController::class, 'acknowledge'])
                                    ->whereNumber('notice')
                                    ->name('acknowledge');
                            });
                    });

                /*
                |--------------------------------------------------------------------------
                | Admin / Director Notices API
                |--------------------------------------------------------------------------
                */

             Route::prefix('admin/notices')
    ->middleware([
        'role:superadmin,school_admin,director',
        'school.license',
    ])
    ->name('admin.notices.')
    ->group(function (): void {
        Route::get(
            '/',
            [AdminSchoolNoticeController::class, 'index']
        )->name('index');

        Route::post(
            '/',
            [AdminSchoolNoticeController::class, 'store']
        )->name('store');

        Route::get(
            '/{notice}',
            [AdminSchoolNoticeController::class, 'show']
        )
            ->whereNumber('notice')
            ->name('show');

        Route::post(
            '/{notice}',
            [AdminSchoolNoticeController::class, 'update']
        )
            ->whereNumber('notice')
            ->name('update');

        Route::post(
            '/{notice}/publish',
            [AdminSchoolNoticeController::class, 'publish']
        )
            ->whereNumber('notice')
            ->name('publish');

        Route::post(
            '/{notice}/archive',
            [AdminSchoolNoticeController::class, 'archive']
        )
            ->whereNumber('notice')
            ->name('archive');
    });

                /*
                |--------------------------------------------------------------------------
                | Legacy Guardian API
                |--------------------------------------------------------------------------
                |
                | Mantener temporalmente mientras confirmamos que ninguna versión
                | anterior de la aplicación continúa consumiendo estos endpoints.
                | No agregar nuevas funciones a este grupo.
                |
                */

               Route::prefix('guardian')
    ->middleware([
        'role:guardian',
        'school.license',
    ])
    ->name('guardian.')
    ->group(function (): void {
        Route::get(
            '/students',
            [GuardianController::class, 'students']
        )->name('students');

        Route::get(
            '/students/{student}/attendance',
            [GuardianController::class, 'attendance']
        )
            ->whereNumber('student')
            ->name('students.attendance');

        Route::get(
            '/notifications',
            [GuardianController::class, 'notifications']
        )->name('notifications');

        Route::patch(
            '/notifications/{notification}/read',
            [GuardianController::class, 'markNotificationAsRead']
        )
            ->whereNumber('notification')
            ->name('notifications.read');
    });
            });
    });