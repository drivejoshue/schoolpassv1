<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileAppReleasePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppVersionController extends Controller
{
    public function __invoke(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'app' => [
                'required',
                'string',
                Rule::in([
                    'family',
                    'staff',
                ]),
            ],
        ]);

        $appKey = $validated['app'];

        $policy = MobileAppReleasePolicy::query()
            ->where('app_key', $appKey)
            ->first();

        if (!$policy) {
            return response()->json([
                'ok' => false,
                'message' => 'No existe política de versión para esta aplicación.',
            ], 404);
        }

        return response()
            ->json([
                'ok' => true,

                'app' => $policy->app_key,

                'package_name' =>
                    $policy->package_name,

                'latest_version_code' =>
                    $policy->latest_version_code,

                'latest_version_name' =>
                    $policy->latest_version_name,

                'minimum_supported_version_code' =>
                    $policy->minimum_supported_version_code,

                'force_update' =>
                    $policy->force_update,

                'title' =>
                    $policy->title
                        ?: 'Nueva versión disponible',

                'update_message' =>
                    $policy->message
                        ?: 'Hay una nueva versión disponible.',

                'store_url' =>
                    $policy->store_url,

                'published_at' =>
                    $policy->published_at
                        ?->toIso8601String(),
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }
}