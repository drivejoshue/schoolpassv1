<?php

namespace App\Http\Controllers\Sysadmin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();
        $nextThirtyDays = now()->addDays(30)->toDateString();

        $metrics = [
            'active_schools' => DB::table('schools')
                ->where('status', 'active')
                ->count(),

            'trial_licenses' => DB::table('school_licenses')
                ->where('is_current', true)
                ->where('status', 'trial')
                ->count(),

            'expiring_soon' => DB::table('school_licenses')
                ->where('is_current', true)
                ->whereIn('status', ['active', 'grace'])
                ->whereBetween('expires_at', [$today, $nextThirtyDays])
                ->count(),

            'expired_licenses' => DB::table('school_licenses')
                ->where('is_current', true)
                ->where(function ($query) use ($today): void {
                    $query->where('status', 'expired')
                        ->orWhere(function ($inner) use ($today): void {
                            $inner->whereIn('status', ['active', 'trial', 'grace'])
                                ->whereNotNull('expires_at')
                                ->whereDate('expires_at', '<', $today);
                        });
                })
                ->count(),

            'students' => DB::table('students')
                ->where('status', 'active')
                ->count(),

            'devices' => DB::table('access_devices')
                ->where('status', 'active')
                ->count(),
        ];

        $mrr = (float) DB::table('school_licenses')
            ->where('is_current', true)
            ->whereIn('status', ['active', 'grace'])
            ->selectRaw(
                "COALESCE(SUM(CASE
                    WHEN billing_cycle = 'monthly' THEN contract_price
                    WHEN billing_cycle = 'annual' THEN contract_price / 12
                    WHEN billing_cycle = 'custom' THEN contract_price
                    ELSE 0
                END), 0) AS mrr"
            )
            ->value('mrr');

        $metrics['mrr'] = $mrr;
        $metrics['arr'] = $mrr * 12;

        $planDistribution = DB::table('subscription_plans as plans')
            ->leftJoin('school_licenses as licenses', function ($join): void {
                $join->on('licenses.subscription_plan_id', '=', 'plans.id')
                    ->where('licenses.is_current', true);
            })
            ->select([
                'plans.id',
                'plans.name',
                'plans.code',
                DB::raw('COUNT(licenses.id) AS total'),
            ])
            ->groupBy('plans.id', 'plans.name', 'plans.code', 'plans.sort_order')
            ->orderBy('plans.sort_order')
            ->get();

        $schoolsNearLimit = DB::table('schools as schools')
            ->join('school_licenses as licenses', function ($join): void {
                $join->on('licenses.school_id', '=', 'schools.id')
                    ->where('licenses.is_current', true);
            })
            ->leftJoin('subscription_plans as plans', 'plans.id', '=', 'licenses.subscription_plan_id')
            ->whereNotNull('licenses.student_limit')
            ->select([
                'schools.id',
                'schools.name',
                'licenses.status as license_status',
                'licenses.student_limit',
                'plans.name as plan_name',
            ])
            ->selectSub(function ($query): void {
                $query->from('students')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('students.school_id', 'schools.id')
                    ->where('students.status', 'active');
            }, 'students_used')
            ->get()
            ->map(function ($school) {
                $school->usage_percent = $school->student_limit > 0
                    ? round(($school->students_used / $school->student_limit) * 100, 1)
                    : 0;

                return $school;
            })
            ->filter(fn ($school): bool => $school->usage_percent >= 80)
            ->sortByDesc('usage_percent')
            ->take(10)
            ->values();

        $recentEvents = DB::table('school_license_events as events')
            ->join('schools', 'schools.id', '=', 'events.school_id')
            ->leftJoin('users', 'users.id', '=', 'events.performed_by')
            ->select([
                'events.id',
                'events.event_type',
                'events.previous_status',
                'events.new_status',
                'events.created_at',
                'schools.id as school_id',
                'schools.name as school_name',
                'users.name as performed_by_name',
            ])
            ->latest('events.created_at')
            ->limit(12)
            ->get();

        return view('sysadmin.dashboard', compact(
            'metrics',
            'planDistribution',
            'schoolsNearLimit',
            'recentEvents',
        ));
    }
}
