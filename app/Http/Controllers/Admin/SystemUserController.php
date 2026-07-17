<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\TemporaryCredentialsNotification;
use App\Services\Auditing\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SystemUserController extends Controller
{
    private const ROLE_LABELS = [
        'school_admin' => 'Administrador escolar',
        'director' => 'Director',
        'prefect' => 'Prefecto',
        'kiosk' => 'Kiosco',
    ];

    private const STATUS_LABELS = [
        'active' => 'Activo',
        'blocked' => 'Suspendido',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $schoolId = (int) $actor->school_id;
        $roles = $this->manageableRoles($actor);

        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $status = trim((string) $request->query('status', ''));

        $users = DB::table('users')
            ->where('school_id', $schoolId)
            ->whereIn('role', $roles)
            ->when(
                $q !== '',
                function ($query) use ($q): void {
                    $query->where(function ($inner) use ($q): void {
                        $inner
                            ->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%");
                    });
                }
            )
            ->when(
                $role !== '' && in_array($role, $roles, true),
                fn ($query) => $query->where('role', $role)
            )
            ->when(
                $status !== '' && array_key_exists($status, self::STATUS_LABELS),
                fn ($query) => $query->where('status', $status)
            )
            ->orderByRaw("CASE role
                WHEN 'school_admin' THEN 1
                WHEN 'director' THEN 2
                WHEN 'prefect' THEN 3
                WHEN 'kiosk' THEN 4
                ELSE 5
            END")
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $userIds = collect($users->items())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $devicesByUser = empty($userIds)
            ? collect()
            : DB::table('access_devices')
                ->where('school_id', $schoolId)
                ->whereIn('assigned_to_user_id', $userIds)
                ->orderBy('id')
                ->get()
                ->groupBy('assigned_to_user_id');

        foreach ($users->items() as $userRow) {
            $device = $devicesByUser
                ->get($userRow->id, collect())
                ->first();

            $userRow->device_id = $device?->id;
            $userRow->device_name = $device?->name;
            $userRow->device_status = $device?->status;
        }

        $summaryRows = DB::table('users')
            ->where('school_id', $schoolId)
            ->whereIn('role', $roles)
            ->selectRaw('role, status, COUNT(*) as total')
            ->groupBy('role', 'status')
            ->get();

        $summary = [
            'total' => (int) $summaryRows->sum('total'),
            'active' => (int) $summaryRows
                ->where('status', 'active')
                ->sum('total'),
            'blocked' => (int) $summaryRows
                ->where('status', 'blocked')
                ->sum('total'),
            'prefect' => (int) $summaryRows
                ->where('role', 'prefect')
                ->sum('total'),
            'kiosk' => (int) $summaryRows
                ->where('role', 'kiosk')
                ->sum('total'),
        ];

        return view('admin.users.index', [
            'users' => $users,
            'summary' => $summary,
            'roles' => $roles,
            'roleLabels' => self::ROLE_LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'filters' => [
                'q' => $q,
                'role' => $role,
                'status' => $status,
            ],
            'actor' => $actor,
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $this->actor($request);

        return view('admin.users.create', [
            'roles' => $this->manageableRoles($actor),
            'roleLabels' => self::ROLE_LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'devices' => $this->deviceOptions($actor),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $schoolId = (int) $actor->school_id;
        $roles = $this->manageableRoles($actor);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('users', 'phone'),
            ],
            'role' => ['required', Rule::in($roles)],
            'status' => ['required', Rule::in(array_keys(self::STATUS_LABELS))],
            'access_device_id' => [
                Rule::requiredIf(
                    fn (): bool => $request->input('role') === 'kiosk'
                ),
                'nullable',
                'integer',
                Rule::exists('access_devices', 'id')
                    ->where('school_id', $schoolId),
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'max:100',
                'confirmed',
            ],
            'send_credentials' => ['nullable', 'boolean'],
        ], [
            'access_device_id.required' => (
                'Selecciona el dispositivo que utilizará la cuenta de kiosco.'
            ),
        ]);

        $email = mb_strtolower(trim($data['email']));
        $phone = $this->nullableString($data['phone'] ?? null);
        $temporaryPassword = $this->nullableString($data['password'] ?? null)
            ?: $this->temporaryPassword();
        $deviceId = $this->operationalDeviceId(
            $data['role'],
            $data['access_device_id'] ?? null,
        );

        $userId = DB::transaction(function () use (
            $data,
            $schoolId,
            $email,
            $phone,
            $temporaryPassword,
            $deviceId,
        ): int {
            $this->assertDeviceAvailable(
                schoolId: $schoolId,
                deviceId: $deviceId,
                targetUserId: null,
            );

            $userId = DB::table('users')->insertGetId([
                'school_id' => $schoolId,
                'name' => trim($data['name']),
                'email' => $email,
                'phone' => $phone,
                'email_verified_at' => null,
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'password_changed_at' => null,
                'last_login_at' => null,
                'role' => $data['role'],
                'status' => $data['status'],
                'remember_token' => Str::random(60),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($deviceId !== null) {
                DB::table('access_devices')
                    ->where('school_id', $schoolId)
                    ->where('id', $deviceId)
                    ->update([
                        'assigned_to_user_id' => $userId,
                        'updated_at' => now(),
                    ]);
            }

            return (int) $userId;
        });

        $user = User::query()->findOrFail($userId);
        $mailSent = false;

        if ($request->boolean('send_credentials')) {
            $mailSent = $this->sendCredentials(
                user: $user,
                temporaryPassword: $temporaryPassword,
            );
        }

        $this->auditLogger->record(
            action: 'admin_user_created',
            schoolId: $schoolId,
            actorId: (int) $actor->id,
            actorType: (string) $actor->role,
            entityType: 'user',
            entityId: $userId,
            oldValues: [],
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'access_device_id' => $deviceId,
                'must_change_password' => true,
                'credentials_emailed' => $mailSent,
            ],
            request: $request,
        );

        $redirect = redirect()
            ->route('admin.users.edit', $userId)
            ->with('success', 'Usuario institucional creado correctamente.')
            ->with('generated_credentials', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $temporaryPassword,
                'role' => $user->role,
                'is_reset' => false,
                'mail_sent' => $mailSent,
            ]);

        if ($request->boolean('send_credentials') && ! $mailSent) {
            $redirect->with(
                'warning',
                'La cuenta fue creada, pero el correo no pudo enviarse. '
                .'Copia las credenciales mostradas en pantalla.'
            );
        }

        return $redirect;
    }

    public function edit(Request $request, int $user): View
    {
        $actor = $this->actor($request);
        $userRow = $this->findManagedUser($actor, $user);

        $assignedDevice = DB::table('access_devices')
            ->where('school_id', $actor->school_id)
            ->where('assigned_to_user_id', $userRow->id)
            ->orderBy('id')
            ->first();

        return view('admin.users.edit', [
            'userRow' => $userRow,
            'assignedDevice' => $assignedDevice,
            'roles' => $this->manageableRoles($actor),
            'roleLabels' => self::ROLE_LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'devices' => $this->deviceOptions($actor, (int) $userRow->id),
            'actor' => $actor,
        ]);
    }

    public function update(
        Request $request,
        int $user,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $schoolId = (int) $actor->school_id;
        $userRow = $this->findManagedUser($actor, $user);
        $roles = $this->manageableRoles($actor);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userRow->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('users', 'phone')->ignore($userRow->id),
            ],
            'role' => ['required', Rule::in($roles)],
            'status' => ['required', Rule::in(array_keys(self::STATUS_LABELS))],
            'access_device_id' => [
                Rule::requiredIf(
                    fn (): bool => $request->input('role') === 'kiosk'
                ),
                'nullable',
                'integer',
                Rule::exists('access_devices', 'id')
                    ->where('school_id', $schoolId),
            ],
        ], [
            'access_device_id.required' => (
                'Selecciona el dispositivo que utilizará la cuenta de kiosco.'
            ),
        ]);

        if ((int) $actor->id === (int) $userRow->id) {
            $data['role'] = $userRow->role;
            $data['status'] = $userRow->status;
        }

        $email = mb_strtolower(trim($data['email']));
        $phone = $this->nullableString($data['phone'] ?? null);
        $deviceId = $this->operationalDeviceId(
            $data['role'],
            $data['access_device_id'] ?? null,
        );

        $oldValues = [
            'name' => $userRow->name,
            'email' => $userRow->email,
            'phone' => $userRow->phone,
            'role' => $userRow->role,
            'status' => $userRow->status,
            'access_device_id' => DB::table('access_devices')
                ->where('school_id', $schoolId)
                ->where('assigned_to_user_id', $userRow->id)
                ->orderBy('id')
                ->value('id'),
        ];

        DB::transaction(function () use (
            $data,
            $schoolId,
            $userRow,
            $email,
            $phone,
            $deviceId,
        ): void {
            DB::table('users')
                ->where('school_id', $schoolId)
                ->where('id', $userRow->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDeviceAvailable(
                schoolId: $schoolId,
                deviceId: $deviceId,
                targetUserId: (int) $userRow->id,
            );

            DB::table('users')
                ->where('school_id', $schoolId)
                ->where('id', $userRow->id)
                ->update([
                    'name' => trim($data['name']),
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $data['role'],
                    'status' => $data['status'],
                    'updated_at' => now(),
                ]);

            DB::table('access_devices')
                ->where('school_id', $schoolId)
                ->where('assigned_to_user_id', $userRow->id)
                ->when(
                    $deviceId !== null,
                    fn ($query) => $query->where('id', '<>', $deviceId)
                )
                ->update([
                    'assigned_to_user_id' => null,
                    'updated_at' => now(),
                ]);

            if ($deviceId !== null) {
                DB::table('access_devices')
                    ->where('school_id', $schoolId)
                    ->where('id', $deviceId)
                    ->update([
                        'assigned_to_user_id' => $userRow->id,
                        'updated_at' => now(),
                    ]);
            }
        });

        if (
            $userRow->role !== $data['role']
            || $userRow->status !== $data['status']
        ) {
            User::query()
                ->find($userRow->id)
                ?->tokens()
                ->delete();
        }

        $newValues = [
            'name' => trim($data['name']),
            'email' => $email,
            'phone' => $phone,
            'role' => $data['role'],
            'status' => $data['status'],
            'access_device_id' => $deviceId,
        ];

        $this->auditLogger->record(
            action: 'admin_user_updated',
            schoolId: $schoolId,
            actorId: (int) $actor->id,
            actorType: (string) $actor->role,
            entityType: 'user',
            entityId: (int) $userRow->id,
            oldValues: $oldValues,
            newValues: $newValues,
            request: $request,
        );

        return redirect()
            ->route('admin.users.edit', $userRow->id)
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function updateStatus(
        Request $request,
        int $user,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $schoolId = (int) $actor->school_id;
        $userRow = $this->findManagedUser($actor, $user);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUS_LABELS))],
        ]);

        if ((int) $actor->id === (int) $userRow->id) {
            throw ValidationException::withMessages([
                'status' => 'No puedes suspender ni reactivar tu propia cuenta desde este módulo.',
            ]);
        }

        DB::table('users')
            ->where('school_id', $schoolId)
            ->where('id', $userRow->id)
            ->update([
                'status' => $data['status'],
                'updated_at' => now(),
            ]);

        if ($data['status'] !== 'active') {
            User::query()
                ->find($userRow->id)
                ?->tokens()
                ->delete();
        }

        $this->auditLogger->record(
            action: $data['status'] === 'active'
                ? 'admin_user_reactivated'
                : 'admin_user_suspended',
            schoolId: $schoolId,
            actorId: (int) $actor->id,
            actorType: (string) $actor->role,
            entityType: 'user',
            entityId: (int) $userRow->id,
            oldValues: ['status' => $userRow->status],
            newValues: ['status' => $data['status']],
            request: $request,
        );

        return back()->with(
            'success',
            $data['status'] === 'active'
                ? 'La cuenta fue reactivada.'
                : 'La cuenta fue suspendida y sus tokens fueron revocados.'
        );
    }

    public function resetPassword(
        Request $request,
        int $user,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $schoolId = (int) $actor->school_id;
        $userRow = $this->findManagedUser($actor, $user);

        $data = $request->validate([
            'password' => [
                'nullable',
                'string',
                'min:8',
                'max:100',
                'confirmed',
            ],
            'send_credentials' => ['nullable', 'boolean'],
        ]);

        $temporaryPassword = $this->nullableString($data['password'] ?? null)
            ?: $this->temporaryPassword();

        DB::table('users')
            ->where('school_id', $schoolId)
            ->where('id', $userRow->id)
            ->update([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'password_changed_at' => null,
                'remember_token' => Str::random(60),
                'updated_at' => now(),
            ]);

        $userModel = User::query()->findOrFail($userRow->id);
        $userModel->tokens()->delete();

        $mailSent = false;

        if ($request->boolean('send_credentials')) {
            $mailSent = $this->sendCredentials(
                user: $userModel,
                temporaryPassword: $temporaryPassword,
            );
        }

        $this->auditLogger->record(
            action: 'admin_user_password_reset',
            schoolId: $schoolId,
            actorId: (int) $actor->id,
            actorType: (string) $actor->role,
            entityType: 'user',
            entityId: (int) $userRow->id,
            oldValues: [],
            newValues: [
                'must_change_password' => true,
                'tokens_revoked' => true,
                'credentials_emailed' => $mailSent,
            ],
            request: $request,
        );

        $redirect = redirect()
            ->route('admin.users.edit', $userRow->id)
            ->with('success', 'Se generó una nueva contraseña temporal.')
            ->with('generated_credentials', [
                'name' => $userModel->name,
                'email' => $userModel->email,
                'password' => $temporaryPassword,
                'role' => $userModel->role,
                'is_reset' => true,
                'mail_sent' => $mailSent,
            ]);

        if ($request->boolean('send_credentials') && ! $mailSent) {
            $redirect->with(
                'warning',
                'La contraseña fue restablecida, pero el correo no pudo enviarse. '
                .'Copia las credenciales mostradas en pantalla.'
            );
        }

        return $redirect;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();

        if (
            ! $actor instanceof User
            || $actor->school_id === null
            || ! in_array(
                $actor->role,
                ['superadmin', 'school_admin', 'director'],
                true
            )
        ) {
            abort(403);
        }

        return $actor;
    }

    private function manageableRoles(User $actor): array
    {
        return match ($actor->role) {
            'superadmin', 'school_admin' => [
                'school_admin',
                'director',
                'prefect',
                'kiosk',
            ],
            'director' => [
                'director',
                'prefect',
                'kiosk',
            ],
            default => [],
        };
    }

    private function findManagedUser(User $actor, int $userId): object
    {
        return DB::table('users')
            ->where('school_id', $actor->school_id)
            ->whereIn('role', $this->manageableRoles($actor))
            ->where('id', $userId)
            ->firstOrFail();
    }

    private function deviceOptions(
        User $actor,
        ?int $targetUserId = null,
    ) {
        return DB::table('access_devices')
            ->leftJoin('campuses', 'campuses.id', '=', 'access_devices.campus_id')
            ->leftJoin('areas', 'areas.id', '=', 'access_devices.area_id')
            ->where('access_devices.school_id', $actor->school_id)
            ->where(function ($query) use ($targetUserId): void {
                $query->whereNull('access_devices.assigned_to_user_id');

                if ($targetUserId !== null) {
                    $query->orWhere(
                        'access_devices.assigned_to_user_id',
                        $targetUserId
                    );
                }
            })
            ->select([
                'access_devices.id',
                'access_devices.name',
                'access_devices.device_uuid',
                'access_devices.device_type',
                'access_devices.platform',
                'access_devices.status',
                'access_devices.assigned_to_user_id',
                'campuses.name as campus_name',
                'areas.name as area_name',
            ])
            ->orderBy('campuses.name')
            ->orderBy('areas.name')
            ->orderBy('access_devices.name')
            ->get();
    }

    private function operationalDeviceId(
        string $role,
        mixed $deviceId,
    ): ?int {
        if (! in_array($role, ['prefect', 'kiosk'], true)) {
            return null;
        }

        if ($deviceId === null || $deviceId === '') {
            return null;
        }

        return (int) $deviceId;
    }

    private function assertDeviceAvailable(
        int $schoolId,
        ?int $deviceId,
        ?int $targetUserId,
    ): void {
        if ($deviceId === null) {
            return;
        }

        $device = DB::table('access_devices')
            ->where('school_id', $schoolId)
            ->where('id', $deviceId)
            ->lockForUpdate()
            ->first();

        if ($device === null) {
            throw ValidationException::withMessages([
                'access_device_id' => 'El dispositivo seleccionado no existe en esta escuela.',
            ]);
        }

        if (
            $device->assigned_to_user_id !== null
            && (int) $device->assigned_to_user_id !== (int) $targetUserId
        ) {
            throw ValidationException::withMessages([
                'access_device_id' => 'El dispositivo ya está asignado a otra cuenta.',
            ]);
        }
    }

    private function sendCredentials(
        User $user,
        string $temporaryPassword,
    ): bool {
        try {
            $user->notify(
                new TemporaryCredentialsNotification(
                    temporaryPassword: $temporaryPassword,
                    roleLabel: self::ROLE_LABELS[$user->role] ?? $user->role,
                )
            );

            return true;
        } catch (Throwable $exception) {
            Log::warning('No se pudieron enviar credenciales de SchoolPass.', [
                'user_id' => $user->id,
                'school_id' => $user->school_id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function temporaryPassword(): string
    {
        return 'Sp'
            .random_int(100000, 999999)
            .Str::upper(Str::random(4));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}