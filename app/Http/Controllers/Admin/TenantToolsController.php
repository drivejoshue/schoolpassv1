<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class TenantToolsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->firstOrFail();

        $stats = [
            'students' => DB::table('students')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->count(),
            'guardians' => DB::table('guardians')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->count(),
            'groups' => DB::table('school_groups')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->count(),
            'campuses' => DB::table('campuses')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->count(),
        ];

        return view('admin.tools.index', compact('school', 'stats'));
    }
}
