<?php

namespace App\Http\Controllers\Sysadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sysadmin\AssignSchoolLicenseRequest;
use App\Http\Requests\Sysadmin\RenewSchoolLicenseRequest;
use App\Http\Requests\Sysadmin\UpdateSchoolLicenseLimitsRequest;
use App\Models\School;
use App\Models\SubscriptionPlan;
use App\Services\Licensing\SchoolLicenseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class SchoolLicenseController extends Controller
{
    public function __construct(
        private readonly SchoolLicenseManager $manager,
    ) {
    }

    public function assign(
        AssignSchoolLicenseRequest $request,
        School $school,
    ): RedirectResponse {
        $data = $request->validated();

        $plan = SubscriptionPlan::query()
            ->where('status', 'active')
            ->findOrFail($data['subscription_plan_id']);

        try {
            $this->manager->assign(
                $school,
                $plan,
                $data,
                $request->user()->id,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return back()->with(
            'status',
            'Plan, fechas, precio y límites guardados.'
        );
    }

    public function renew(
        RenewSchoolLicenseRequest $request,
        School $school,
    ): RedirectResponse {
        try {
            $this->manager->renew(
                $school,
                $request->validated(),
                $request->user()->id,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Licencia renovada.');
    }

    public function extendTrial(
        Request $request,
        School $school,
    ): RedirectResponse {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->manager->extendTrial(
                $school,
                (int) $data['days'],
                $request->user()->id,
                $data['reason'] ?? null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'status',
            'Periodo de prueba extendido.'
        );
    }

    public function updateLimits(
        UpdateSchoolLicenseLimitsRequest $request,
        School $school,
    ): RedirectResponse {
        try {
            $this->manager->updateLimits(
                $school,
                $request->validated(),
                $request->user()->id,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'status',
            'Límites contractuales actualizados.'
        );
    }

    public function suspend(
        Request $request,
        School $school,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->manager->changeStatus(
                $school,
                'suspended',
                'suspended',
                $request->user()->id,
                $data['reason'] ?? null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Licencia suspendida.');
    }

    public function reactivate(
        Request $request,
        School $school,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->manager->reactivate(
                $school,
                $request->user()->id,
                $data['reason'] ?? null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Licencia reactivada.');
    }

    public function cancel(
        Request $request,
        School $school,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->manager->changeStatus(
                $school,
                'cancelled',
                'cancelled',
                $request->user()->id,
                $data['reason'],
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Licencia cancelada.');
    }
}
