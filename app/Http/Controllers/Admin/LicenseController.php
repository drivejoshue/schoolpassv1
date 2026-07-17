<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Licensing\SchoolLicenseStateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LicenseController extends Controller
{
    public function __construct(
        private readonly SchoolLicenseStateService $licenseStateService,
    ) {
    }

    public function show(
        Request $request,
    ): View {
        $schoolId = (int) $request->user()->school_id;

        abort_if(
            $schoolId <= 0,
            403,
            'No existe una escuela asignada.'
        );

        $state = $this->licenseStateService
            ->forSchoolId($schoolId);

        $events = DB::table(
            'school_license_events as events'
        )
            ->leftJoin(
                'users',
                'users.id',
                '=',
                'events.performed_by'
            )
            ->where(
                'events.school_id',
                $schoolId
            )
            ->latest('events.created_at')
            ->limit(20)
            ->get([
                'events.id',
                'events.event_type',
                'events.previous_status',
                'events.new_status',
                'events.metadata_json',
                'events.created_at',
                'users.name as performed_by_name',
            ]);

        return view(
            'admin.license.show',
            compact(
                'state',
                'events'
            )
        );
    }
}