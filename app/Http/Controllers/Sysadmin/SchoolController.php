<?php

namespace App\Http\Controllers\Sysadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sysadmin\StoreSchoolRequest;
use App\Http\Requests\Sysadmin\UpdateSchoolRequest;
use App\Models\School;
use App\Models\SubscriptionPlan;
use App\Services\Auditing\AuditLogger;
use App\Services\Licensing\SchoolLicenseService;
use App\Services\SchoolAppConfigService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SchoolController extends Controller
{
    public function __construct(
        private readonly SchoolLicenseService $licenseService,
        private readonly SchoolAppConfigService $configService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => [
                'nullable',
                'in:active,suspended,cancelled',
            ],
            'license_status' => [
                'nullable',
                'in:trial,active,grace,expired,suspended,cancelled',
            ],
        ]);

        $schools = DB::table('schools as schools')
            ->leftJoin('school_licenses as licenses', function ($join): void {
                $join->on(
                    'licenses.school_id',
                    '=',
                    'schools.id'
                )->where('licenses.is_current', true);
            })
            ->leftJoin(
                'subscription_plans as plans',
                'plans.id',
                '=',
                'licenses.subscription_plan_id'
            )
            ->select([
                'schools.id',
                'schools.name',
                'schools.legal_name',
                'schools.slug',
                'schools.status',
                'schools.contact_email',
                'licenses.status as license_status',
                'licenses.expires_at',
                'licenses.student_limit',
                'plans.name as plan_name',
            ])
            ->selectSub(function ($query): void {
                $query->from('students')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'students.school_id',
                        'schools.id'
                    )
                    ->where('students.status', 'active');
            }, 'students_used')
            ->when(
                $filters['q'] ?? null,
                function ($query, string $search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where(
                            'schools.name',
                            'like',
                            "%{$search}%"
                        )
                            ->orWhere(
                                'schools.legal_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'schools.slug',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'schools.contact_email',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, string $status) => $query
                    ->where('schools.status', $status)
            )
            ->when(
                $filters['license_status'] ?? null,
                fn ($query, string $status) => $query
                    ->where('licenses.status', $status)
            )
            ->orderBy('schools.name')
            ->paginate(20)
            ->withQueryString();

        return view(
            'sysadmin.schools.index',
            compact('schools', 'filters')
        );
    }

    public function create(): View
    {
        return view('sysadmin.schools.create');
    }

    public function store(
        StoreSchoolRequest $request,
    ): RedirectResponse {
        $data = $request->validated();

        $schoolData = collect($data)
            ->except([
                'admin_name',
                'admin_email',
                'admin_phone',
                'admin_role',
                'admin_password',
                'admin_password_confirmation',
            ])
            ->all();

        $schoolData['slug'] = $schoolData['slug']
            ?: $this->uniqueSlug($schoolData['name']);

        [$school, $administratorId] = DB::transaction(
            function () use (
                $request,
                $data,
                $schoolData,
            ): array {
                $school = School::query()->create($schoolData);

                $administratorId = DB::table('users')
                    ->insertGetId([
                        'school_id' => $school->id,
                        'name' => $data['admin_name'],
                        'email' => mb_strtolower(
                            $data['admin_email']
                        ),
                        'phone' => $data['admin_phone'] ?? null,
                        'email_verified_at' => now(),
                        'password' => Hash::make(
                            $data['admin_password']
                        ),
                        'role' => $data['admin_role'],
                        'status' => 'active',
                        'remember_token' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $this->configService->save(
                    $school,
                    $this->configService->defaults($school),
                    $request->user()->id,
                );

                $this->auditLogger->record(
                    action: 'school_created',
                    schoolId: $school->id,
                    actorId: $request->user()->id,
                    actorType: 'superadmin',
                    entityType: School::class,
                    entityId: $school->id,
                    newValues: [
                        'name' => $school->name,
                        'slug' => $school->slug,
                        'status' => $school->status,
                        'initial_administrator_id' =>
                            $administratorId,
                        'initial_administrator_email' =>
                            mb_strtolower($data['admin_email']),
                    ],
                    request: $request,
                );

                return [$school, $administratorId];
            }
        );

        return redirect()
            ->route('sysadmin.schools.show', $school)
            ->with(
                'status',
                'Escuela y administrador inicial creados. '
                .'Ahora asigna la licencia y configura las apps.'
            );
    }

    public function show(School $school): View
    {
        $schoolData = $school;

        $license = $this->configService->currentLicense(
            $school->id
        );

        $usage = $this->licenseService->usageSummary(
            $school->id
        );

        $plans = SubscriptionPlan::query()
            ->where('status', 'active')
            ->with('features')
            ->orderBy('sort_order')
            ->get();

        $featureKeys = DB::table('subscription_plan_features')
            ->select('feature_key')
            ->distinct()
            ->orderBy('feature_key')
            ->pluck('feature_key');

        $overrides = DB::table('school_features')
            ->where('school_id', $school->id)
            ->get()
            ->keyBy('feature_key');

        $licenseSnapshot = $license?->features_snapshot
            ? json_decode(
                (string) $license->features_snapshot,
                true
            )
            : [];

        $featureMatrix = $featureKeys->map(function (
            string $key
        ) use (
            $overrides,
            $licenseSnapshot,
        ): array {
            $override = $overrides->get($key);
            $inherited = (bool) (
                $licenseSnapshot[$key] ?? false
            );

            return [
                'key' => $key,
                'inherited_enabled' => $inherited,
                'override' => $override === null
                    ? 'inherit'
                    : ((bool) $override->is_enabled
                        ? 'enabled'
                        : 'disabled'),
                'effective_enabled' => $override === null
                    ? $inherited
                    : (bool) $override->is_enabled,
                'source' => $override?->source ?? 'license',
            ];
        });

        $settings = DB::table('school_settings')
            ->where('school_id', $school->id)
            ->orderBy('key')
            ->get();

        $administrators = DB::table('users')
            ->where('school_id', $school->id)
            ->whereIn('role', ['school_admin', 'director'])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'phone',
                'role',
                'status',
            ]);

        $events = DB::table(
            'school_license_events as events'
        )
            ->leftJoin(
                'users',
                'users.id',
                '=',
                'events.performed_by'
            )
            ->where('events.school_id', $school->id)
            ->latest('events.created_at')
            ->limit(30)
            ->get([
                'events.*',
                'users.name as performed_by_name',
            ]);

        return view('sysadmin.schools.show', compact(
            'schoolData',
            'license',
            'usage',
            'plans',
            'featureMatrix',
            'settings',
            'administrators',
            'events',
        ));
    }

    public function edit(School $school): View
    {
        return view(
            'sysadmin.schools.edit',
            compact('school')
        );
    }

    public function update(
        UpdateSchoolRequest $request,
        School $school,
    ): RedirectResponse {
        $oldValues = $school->only([
            'name',
            'legal_name',
            'slug',
            'status',
            'timezone',
            'contact_name',
            'contact_email',
            'contact_phone',
            'address',
            'tax_id',
            'support_email',
            'whatsapp_number',
        ]);

        $data = $request->validated();

        $data['suspended_at'] =
            $data['status'] === 'suspended'
                ? ($school->suspended_at ?: now())
                : null;

        $data['cancelled_at'] =
            $data['status'] === 'cancelled'
                ? ($school->cancelled_at ?: now())
                : null;

        $school->update($data);

        $this->auditLogger->record(
            action: 'school_updated',
            schoolId: $school->id,
            actorId: $request->user()->id,
            actorType: 'superadmin',
            entityType: School::class,
            entityId: $school->id,
            oldValues: $oldValues,
            newValues: $school->only(array_keys($oldValues)),
            request: $request,
        );

        return redirect()
            ->route('sysadmin.schools.show', $school)
            ->with(
                'status',
                'Datos de la escuela actualizados.'
            );
    }

    public function suspend(
        Request $request,
        School $school,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $previous = $school->status;

        $school->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        $this->schoolEvent(
            $school,
            'school_suspended',
            $previous,
            'suspended',
            $request->user()->id,
            $data['reason'] ?? null,
        );

        return back()->with('status', 'Escuela suspendida.');
    }

    public function reactivate(
        Request $request,
        School $school,
    ): RedirectResponse {
        $previous = $school->status;

        $school->update([
            'status' => 'active',
            'suspended_at' => null,
            'cancelled_at' => null,
        ]);

        $this->schoolEvent(
            $school,
            'school_reactivated',
            $previous,
            'active',
            $request->user()->id,
            null,
        );

        return back()->with('status', 'Escuela reactivada.');
    }

    private function schoolEvent(
        School $school,
        string $eventType,
        ?string $previousStatus,
        ?string $newStatus,
        int $actorId,
        ?string $reason,
    ): void {
        DB::table('school_license_events')->insert([
            'school_id' => $school->id,
            'school_license_id' => DB::table(
                'school_licenses'
            )
                ->where('school_id', $school->id)
                ->where('is_current', true)
                ->latest('id')
                ->value('id'),
            'event_type' => $eventType,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'metadata_json' => json_encode(
                ['reason' => $reason],
                JSON_UNESCAPED_UNICODE
            ),
            'performed_by' => $actorId,
            'created_at' => now(),
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'escuela';
        $slug = $base;
        $counter = 2;

        while (
            School::query()
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
