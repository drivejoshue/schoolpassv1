<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SysAdmin\UpdateSchoolAppConfigRequest;
use App\Models\School;
use App\Services\SchoolAppConfigService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SchoolAppConfigController extends Controller
{
    public function __construct(
        private readonly SchoolAppConfigService $configService,
    ) {
    }

    public function edit(
        School $school,
    ): View {
        $config = $this->configService
            ->get($school);

        $license = $this->configService
            ->currentLicense(
                (int) $school->id,
            );

        $features = $this->configService
            ->effectiveFeatures(
                schoolId: (int) $school->id,
                license: $license,
            );

        return view(
           'sysadmin.schools.app-config.edit',
            [
                'school' => $school,

                'config' => $config,

                'license' => $license,

                'features' => $features,

                'logoUrl' =>
                    $this->configService
                        ->assetUrl(
                            $config['identity'][
                                'logo_path'
                            ] ?? null,
                        ),

                'welcomeImageUrl' =>
                    $this->configService
                        ->assetUrl(
                            $config['identity'][
                                'welcome_image_path'
                            ] ?? null,
                        ),
            ],
        );
    }

    public function update(
        UpdateSchoolAppConfigRequest $request,
        School $school,
    ): RedirectResponse {
        $data = $request->validated();

        $current = $this->configService
            ->get($school);

        $oldLogoPath =
            $current['identity']['logo_path']
            ?? null;

        $oldWelcomeImagePath =
            $current['identity'][
                'welcome_image_path'
            ] ?? null;

        $logoPath = $oldLogoPath;

        $welcomeImagePath =
            $oldWelcomeImagePath;

        /*
         * Estos archivos se eliminan si la transacción falla.
         */
        $newlyStoredPaths = [];

        try {
            if ($request->hasFile('logo')) {
                $logoPath = $request
                    ->file('logo')
                    ->store(
                        "schools/{$school->id}/app-branding",
                        'public',
                    );

                $newlyStoredPaths[] = $logoPath;
            } elseif (
                (bool) $data['remove_logo']
            ) {
                $logoPath = null;
            }

            if (
                $request->hasFile(
                    'welcome_image',
                )
            ) {
                $welcomeImagePath = $request
                    ->file('welcome_image')
                    ->store(
                        "schools/{$school->id}/app-branding",
                        'public',
                    );

                $newlyStoredPaths[] =
                    $welcomeImagePath;
            } elseif (
                (bool) $data[
                    'remove_welcome_image'
                ]
            ) {
                $welcomeImagePath = null;
            }

            $configPatch = [
                'identity' => [
                    'app_name' =>
                        trim($data['app_name']),

                    'short_name' =>
                        trim($data['short_name']),

                    'logo_path' =>
                        $logoPath,

                    'welcome_message' =>
                        $this->nullableString(
                            $data[
                                'welcome_message'
                            ] ?? null,
                        ),

                    'welcome_image_path' =>
                        $welcomeImagePath,

                    'primary_color' =>
                        $this->normalizeColor(
                            $data[
                                'primary_color'
                            ],
                        ),

                    'secondary_color' =>
                        $this->normalizeColor(
                            $data[
                                'secondary_color'
                            ],
                        ),

                    'accent_color' =>
                        $this->normalizeColor(
                            $data[
                                'accent_color'
                            ],
                        ),

                    'theme' => [
                        'default_mode' =>
                            $data[
                                'theme_default_mode'
                            ],

                        'allow_user_override' =>
                            (bool) $data[
                                'theme_allow_user_override'
                            ],

                        'light' => [
                            'background_color' =>
                                $this->normalizeColor(
                                    $data[
                                        'light_background_color'
                                    ],
                                ),

                            'surface_color' =>
                                $this->normalizeColor(
                                    $data[
                                        'light_surface_color'
                                    ],
                                ),

                            'on_surface_color' =>
                                $this->normalizeColor(
                                    $data[
                                        'light_on_surface_color'
                                    ],
                                ),
                        ],

                        'dark' => [
                            'background_color' =>
                                $this->normalizeColor(
                                    $data[
                                        'dark_background_color'
                                    ],
                                ),

                            'surface_color' =>
                                $this->normalizeColor(
                                    $data[
                                        'dark_surface_color'
                                    ],
                                ),

                            'on_surface_color' =>
                                $this->normalizeColor(
                                    $data[
                                        'dark_on_surface_color'
                                    ],
                                ),
                        ],
                    ],
                ],

                'support' => [
                    'email' =>
                        $this->nullableString(
                            $data[
                                'support_email'
                            ] ?? null,
                        ),

                    'phone' =>
                        $this->nullableString(
                            $data[
                                'support_phone'
                            ] ?? null,
                        ),

                    'whatsapp' =>
                        $this->nullableString(
                            $data[
                                'support_whatsapp'
                            ] ?? null,
                        ),
                ],

                'attendance' => [
                    'check_in_enabled' =>
                        (bool) $data[
                            'check_in_enabled'
                        ],

                    'check_out_enabled' =>
                        (bool) $data[
                            'check_out_enabled'
                        ],

                    'late_tolerance_minutes' =>
                        (int) $data[
                            'late_tolerance_minutes'
                        ],

                    'early_exit_enabled' =>
                        (bool) $data[
                            'early_exit_enabled'
                        ],

                    'early_exit_requires_authorization' =>
                        (bool) $data[
                            'early_exit_requires_authorization'
                        ],

                    'observations_enabled' =>
                        (bool) $data[
                            'observations_enabled'
                        ],

                    'temporary_passes_enabled' =>
                        (bool) $data[
                            'temporary_passes_enabled'
                        ],
                ],

                'credentials' => [
                    'qr_enabled' =>
                        (bool) $data[
                            'qr_enabled'
                        ],

                    'qr_mode' =>
                        $data['qr_mode'],

                    'nfc_enabled' =>
                        (bool) $data[
                            'nfc_enabled'
                        ],

                    'printed_credential_enabled' =>
                        (bool) $data[
                            'printed_credential_enabled'
                        ],

                    'temporary_pass_minutes' =>
                        (int) $data[
                            'temporary_pass_minutes'
                        ],
                ],

                'notifications' => [
                    'entry' =>
                        (bool) $data[
                            'notify_entry'
                        ],

                    'exit' =>
                        (bool) $data[
                            'notify_exit'
                        ],

                    'late' =>
                        (bool) $data[
                            'notify_late'
                        ],

                    'absence' =>
                        (bool) $data[
                            'notify_absence'
                        ],

                    'early_exit' =>
                        (bool) $data[
                            'notify_early_exit'
                        ],

                    'denied_access' =>
                        (bool) $data[
                            'notify_denied_access'
                        ],
                ],

                'staff' => [
                    'qr_scan_enabled' =>
                        (bool) $data[
                            'staff_qr_scan_enabled'
                        ],

                    'manual_search_enabled' =>
                        (bool) $data[
                            'staff_manual_search_enabled'
                        ],

                    'recent_access_enabled' =>
                        (bool) $data[
                            'staff_recent_access_enabled'
                        ],

                    'show_student_photo' =>
                        (bool) $data[
                            'staff_show_student_photo'
                        ],

                    'sound_enabled' =>
                        (bool) $data[
                            'staff_sound_enabled'
                        ],

                    'vibration_enabled' =>
                        (bool) $data[
                            'staff_vibration_enabled'
                        ],

                    'auto_reset_seconds' =>
                        (int) $data[
                            'staff_auto_reset_seconds'
                        ],

                    'default_event_type' =>
                        $data[
                            'staff_default_event_type'
                        ],

                    'camera_facing' =>
                        $data[
                            'staff_camera_facing'
                        ],
                ],

                'navigation' => [
                    'notices' =>
                        (bool) $data[
                            'show_notices'
                        ],

                    'attendance_history' =>
                        (bool) $data[
                            'show_attendance_history'
                        ],

                    'digital_credential' =>
                        (bool) $data[
                            'show_digital_credential'
                        ],

                    'authorizations' =>
                        (bool) $data[
                            'show_authorizations'
                        ],

                    'payments' =>
                        (bool) $data[
                            'show_payments'
                        ],

                    'grades' =>
                        (bool) $data[
                            'show_grades'
                        ],
                ],
            ];

            DB::transaction(
                function () use (
                    $school,
                    $configPatch,
                    $logoPath,
                    $data,
                    $request,
                ): void {
                    /*
                     * Sincronizamos los campos base de schools para
                     * que otros módulos también puedan usar el logo
                     * y los colores institucionales.
                     */
                    DB::table('schools')
                        ->where('id', $school->id)
                        ->update([
                            'logo_path' => $logoPath,

                            'primary_color' =>
                                $this->normalizeColor(
                                    $data[
                                        'primary_color'
                                    ],
                                ),

                            'secondary_color' =>
                                $this->normalizeColor(
                                    $data[
                                        'secondary_color'
                                    ],
                                ),

                            'updated_at' => now(),
                        ]);

                    $this->configService->save(
                        school: $school,
                        config: $configPatch,
                        actorId: (int) $request
                            ->user()
                            ->id,
                    );
                },
            );

            /*
             * Los archivos anteriores se eliminan únicamente cuando
             * la publicación terminó correctamente.
             */
            if ($oldLogoPath !== $logoPath) {
                $this->deleteLocalAsset(
                    $oldLogoPath,
                );
            }

            if (
                $oldWelcomeImagePath
                !== $welcomeImagePath
            ) {
                $this->deleteLocalAsset(
                    $oldWelcomeImagePath,
                );
            }

            return redirect()
                ->route(
                    'sysadmin.schools.app-config.edit',
                    $school,
                )
                ->with(
                    'success',
                    'La configuración de las aplicaciones fue publicada correctamente.',
                );
        } catch (Throwable $exception) {
            report($exception);

            foreach (
                $newlyStoredPaths
                as $storedPath
            ) {
                $this->deleteLocalAsset(
                    $storedPath,
                );
            }

            return back()
                ->withInput()
                ->withErrors([
                    'app_config' => (
                        'No fue posible publicar la configuración. '
                        .'Revisa los datos e intenta nuevamente.'
                    ),
                ]);
        }
    }

    private function normalizeColor(
        string $color,
    ): string {
        return Str::upper(
            trim($color),
        );
    }

    private function nullableString(
        mixed $value,
    ): ?string {
        $value = trim(
            (string) $value,
        );

        return $value !== ''
            ? $value
            : null;
    }

    private function deleteLocalAsset(
        ?string $path,
    ): void {
        $path = trim(
            (string) $path,
        );

        if ($path === '') {
            return;
        }

        if (
            Str::startsWith(
                $path,
                [
                    'http://',
                    'https://',
                ],
            )
        ) {
            return;
        }

        Storage::disk('public')
            ->delete(
                ltrim($path, '/'),
            );
    }
}