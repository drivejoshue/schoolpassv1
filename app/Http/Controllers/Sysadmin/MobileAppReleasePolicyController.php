<?php

namespace App\Http\Controllers\Sysadmin;

use App\Http\Controllers\Controller;
use App\Models\MobileAppReleasePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MobileAppReleasePolicyController extends Controller
{
    public function edit(): View
    {
        $this->ensureDefaultPoliciesExist();

        $policies = MobileAppReleasePolicy::query()
            ->whereIn('app_key', [
                'family',
                'staff',
            ])
            ->get()
            ->keyBy('app_key');

        return view(
            'sysadmin.mobile-app-versions.edit',
            [
                'policies' => $policies,
            ]
        );
    }

    public function update(
        Request $request,
        MobileAppReleasePolicy $policy
    ): RedirectResponse {
        abort_unless(
            in_array(
                $policy->app_key,
                ['family', 'staff'],
                true
            ),
            404
        );

        $expectedPackage = match ($policy->app_key) {
            'family' => 'com.schoolpass.family',
            'staff' => 'com.schoolpass.staff',
        };

        $validator = Validator::make(
            $request->all(),
            [
                'app_key' => [
                    'required',
                    Rule::in([$policy->app_key]),
                ],

                'package_name' => [
                    'required',
                    'string',
                    Rule::in([$expectedPackage]),
                ],

                'latest_version_code' => [
                    'required',
                    'integer',
                    'min:1',
                ],

                'latest_version_name' => [
                    'required',
                    'string',
                    'max:40',
                ],

                'minimum_supported_version_code' => [
                    'required',
                    'integer',
                    'min:1',
                ],

                'force_update' => [
                    'nullable',
                    'boolean',
                ],

                'title' => [
                    'nullable',
                    'string',
                    'max:120',
                ],

                'message' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'store_url' => [
                    'nullable',
                    'url',
                    'max:500',
                ],

                'published_at' => [
                    'nullable',
                    'date',
                ],
            ],
            [
                'package_name.in' =>
                    'El paquete debe ser '.$expectedPackage.'.',

                'latest_version_code.required' =>
                    'Escribe el versionCode más reciente.',

                'minimum_supported_version_code.required' =>
                    'Escribe la versión mínima compatible.',

                'latest_version_name.required' =>
                    'Escribe el nombre visible de la versión.',

                'store_url.url' =>
                    'La dirección de Google Play no es válida.',
            ]
        );

        $validator->after(
            function ($validator) use ($request): void {
                $latest = (int) $request->input(
                    'latest_version_code',
                    0
                );

                $minimum = (int) $request->input(
                    'minimum_supported_version_code',
                    0
                );

                if ($minimum > $latest) {
                    $validator->errors()->add(
                        'minimum_supported_version_code',
                        'La versión mínima no puede ser mayor que la versión más reciente.'
                    );
                }
            }
        );

        $validated = $validator->validateWithBag(
            $policy->app_key
        );

        $policy->update([
            'package_name' =>
                $expectedPackage,

            'latest_version_code' =>
                (int) $validated['latest_version_code'],

            'latest_version_name' =>
                trim($validated['latest_version_name']),

            'minimum_supported_version_code' =>
                (int) $validated[
                    'minimum_supported_version_code'
                ],

            'force_update' =>
                $request->boolean('force_update'),

            'title' =>
                $this->nullableString(
                    $validated['title'] ?? null
                ),

            'message' =>
                $this->nullableString(
                    $validated['message'] ?? null
                ),

            'store_url' =>
                $this->nullableString(
                    $validated['store_url'] ?? null
                ),

            'published_at' =>
                $validated['published_at'] ?? null,
        ]);

        $appLabel = $policy->app_key === 'family'
            ? 'SchoolPass Familia'
            : 'SchoolPass Staff';

        return redirect()
            ->route(
                'sysadmin.mobile-app-versions.edit'
            )
            ->with(
                'success',
                "Política de versión de {$appLabel} actualizada."
            );
    }

    private function ensureDefaultPoliciesExist(): void
    {
        MobileAppReleasePolicy::query()
            ->firstOrCreate(
                [
                    'app_key' => 'family',
                ],
                [
                    'package_name' =>
                        'com.schoolpass.family',

                    'latest_version_code' => 1,
                    'latest_version_name' => '1.0.0',

                    'minimum_supported_version_code' => 1,
                    'force_update' => false,

                    'title' =>
                        'Nueva versión disponible',

                    'message' =>
                        'Actualiza SchoolPass Familia para recibir las últimas mejoras.',

                    'published_at' => now(),
                ]
            );

        MobileAppReleasePolicy::query()
            ->firstOrCreate(
                [
                    'app_key' => 'staff',
                ],
                [
                    'package_name' =>
                        'com.schoolpass.staff',

                    'latest_version_code' => 1,
                    'latest_version_name' => '1.0.0',

                    'minimum_supported_version_code' => 1,
                    'force_update' => false,

                    'title' =>
                        'Nueva versión disponible',

                    'message' =>
                        'Actualiza SchoolPass Staff para continuar con la versión más reciente.',

                    'published_at' => now(),
                ]
            );
    }

    private function nullableString(
        mixed $value
    ): ?string {
        $normalized = trim(
            (string) ($value ?? '')
        );

        return $normalized !== ''
            ? $normalized
            : null;
    }
}