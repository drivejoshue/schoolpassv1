<?php

namespace App\Http\Controllers\Sysadmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Contracts\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        $plans = SubscriptionPlan::query()
            ->withCount([
                'features',
                'licenses as current_licenses_count' => fn ($query) => $query
                    ->where('is_current', true),
            ])
            ->with([
                'features' => fn ($query) => $query
                    ->orderBy('feature_key'),
            ])
            ->orderBy('sort_order')
            ->get();

        return view('sysadmin.plans.index', compact('plans'));
    }
}
