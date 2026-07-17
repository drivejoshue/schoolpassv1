<?php

namespace App\Http\Controllers\Prefect;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrefectAccessController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $deviceQuery = DB::table('access_devices')
            ->where('school_id', $user->school_id)
            ->where('device_type', 'prefect_app')
            ->where('status', 'active');

        if (! in_array($user->role, ['superadmin', 'school_admin', 'director'], true)) {
            $deviceQuery->where(function ($query) use ($user) {
                $query->where('assigned_to_user_id', $user->id)
                    ->orWhereNull('assigned_to_user_id');
            });
        }

        $device = $deviceQuery->orderBy('id')->first();

        $recentLogs = DB::table('access_logs')
            ->leftJoin('students', 'students.id', '=', 'access_logs.student_id')
            ->leftJoin('areas', 'areas.id', '=', 'access_logs.area_id')
            ->where('access_logs.school_id', $user->school_id)
            ->select(
                'access_logs.*',
                DB::raw("CONCAT(students.first_name, ' ', students.last_name) as student_name"),
                'areas.name as area_name'
            )
            ->orderByDesc('access_logs.scanned_at')
            ->limit(10)
            ->get();

        return view('prefect.access', compact('device', 'recentLogs'));
    }
}