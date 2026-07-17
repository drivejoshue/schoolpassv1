<?php

namespace App\Http\Controllers;

use App\Services\Licensing\SchoolLicenseStateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LicenseBlockedController extends Controller
{
    public function __construct(
        private readonly SchoolLicenseStateService $licenseStateService,
    ) {
    }

    public function show(
        Request $request,
    ): View {
        $schoolId = (int) $request->user()->school_id;

        $state = $this->licenseStateService
            ->forSchoolId($schoolId);

        return view(
            'license.blocked',
            compact('state')
        );
    }
}