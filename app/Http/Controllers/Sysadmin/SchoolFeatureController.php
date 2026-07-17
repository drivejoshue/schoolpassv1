<?php

namespace App\Http\Controllers\Sysadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sysadmin\UpdateSchoolFeaturesRequest;
use App\Models\School;
use App\Models\SchoolFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SchoolFeatureController extends Controller
{
    public function update(
        UpdateSchoolFeaturesRequest $request,
        School $school,
    ): RedirectResponse {
        $features = $request->validated('features');
        $actorId = $request->user()->id;

        DB::transaction(function () use (
            $features,
            $school,
            $actorId,
        ): void {
            foreach ($features as $key => $state) {
                if ($state === 'inherit') {
                    SchoolFeature::query()
                        ->where('school_id', $school->id)
                        ->where('feature_key', $key)
                        ->delete();

                    continue;
                }

                SchoolFeature::query()->updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'feature_key' => $key,
                    ],
                    [
                        'is_enabled' => $state === 'enabled',
                        'configuration_json' => null,
                        'source' => 'override',
                        'starts_at' => null,
                        'expires_at' => null,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]
                );
            }

            DB::table('school_license_events')->insert([
                'school_id' => $school->id,
                'school_license_id' => DB::table('school_licenses')
                    ->where('school_id', $school->id)
                    ->where('is_current', true)
                    ->latest('id')
                    ->value('id'),
                'event_type' => 'features_changed',
                'previous_status' => null,
                'new_status' => null,
                'metadata_json' => json_encode([
                    'features' => $features,
                ], JSON_UNESCAPED_UNICODE),
                'performed_by' => $actorId,
                'created_at' => now(),
            ]);
        });

        return back()->with(
            'status',
            'Funciones de la escuela actualizadas.'
        );
    }
}
