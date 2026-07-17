<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccessDeviceController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $devices = DB::table('access_devices')
            ->leftJoin('areas', 'areas.id', '=', 'access_devices.area_id')
            ->leftJoin('campuses', 'campuses.id', '=', 'access_devices.campus_id')
            ->leftJoin('users', 'users.id', '=', 'access_devices.assigned_to_user_id')
            ->where('access_devices.school_id', $schoolId)
            ->select(
    'access_devices.*',
    'areas.name as area_name',
    'campuses.name as campus_name',
    'users.name as assigned_user_name',
    'users.email as assigned_user_email'
)
            ->orderBy('access_devices.name')
            ->get();

        return view('admin.devices.index', compact('devices'));
    }

    public function create(): View
    {
        return view('admin.devices.create', [
            'campuses' => $this->campuses(),
            'areas' => $this->areas(),
            'users' => $this->assignableUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $data = $request->validate([
            'campus_id' => [
                'required',
                'integer',
                Rule::exists('campuses', 'id')->where('school_id', $schoolId),
            ],
            'area_id' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id')->where('school_id', $schoolId),
            ],
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $schoolId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'device_uuid' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('access_devices', 'device_uuid'),
            ],
            'platform' => ['required', Rule::in(['web', 'android', 'ios', 'hardware', 'other'])],
            'device_type' => ['required', Rule::in(['prefect_app', 'kiosk', 'scanner', 'door_controller', 'mobile', 'other'])],
            'mode' => ['required', Rule::in(['attendance', 'restricted_access', 'log_only'])],
         'default_event_type' => [
    'required',
    Rule::in([
        'auto',
        'entry',
        'exit',
        'access',
    ]),
],
            'can_unlock' => ['nullable', 'boolean'],
            'allow_manual_search' => ['nullable', 'boolean'],
            'show_student_photo' => ['nullable', 'boolean'],
            'auto_reset_seconds' => ['required', 'integer', 'min:1', 'max:60'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
        ]);

        $deviceUuid = $data['device_uuid'] ?: Str::slug($data['name']) . '-' . Str::lower(Str::random(8));

        DB::table('access_devices')->insert([
            'school_id' => $schoolId,
            'campus_id' => $data['campus_id'],
            'area_id' => $data['area_id'] ?: null,
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?: null,
            'name' => $data['name'],
            'device_uuid' => $deviceUuid,
            'platform' => $data['platform'],
            'device_type' => $data['device_type'],
            'mode' => $data['mode'],
            'default_event_type' => $data['default_event_type'],
            'can_unlock' => $request->boolean('can_unlock'),
            'allow_manual_search' => $request->boolean('allow_manual_search'),
            'show_student_photo' => $request->boolean('show_student_photo'),
            'auto_reset_seconds' => $data['auto_reset_seconds'],
            'status' => $data['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Dispositivo registrado correctamente.');
    }

    public function edit(int $device): View
{
    $user = auth()->user();
    $schoolId = (int) $user->school_id;

    $deviceRow = DB::table('access_devices')
        ->where('school_id', $schoolId)
        ->where('id', $device)
        ->firstOrFail();

    $assignedUser = null;

    if ($deviceRow->assigned_to_user_id) {
        $assignedUser = DB::table('users')
            ->where('school_id', $schoolId)
            ->where('id', $deviceRow->assigned_to_user_id)
            ->first();
    }

    return view('admin.devices.edit', [
        'deviceRow' => $deviceRow,
        'assignedUser' => $assignedUser,
        'campuses' => $this->campuses(),
        'areas' => $this->areas(),
        'users' => $this->assignableUsers(),
    ]);
}

    public function update(Request $request, int $device): RedirectResponse
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $deviceRow = DB::table('access_devices')
            ->where('school_id', $schoolId)
            ->where('id', $device)
            ->firstOrFail();

        $data = $request->validate([
            'campus_id' => [
                'required',
                'integer',
                Rule::exists('campuses', 'id')->where('school_id', $schoolId),
            ],
            'area_id' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id')->where('school_id', $schoolId),
            ],
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $schoolId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'device_uuid' => [
                'required',
                'string',
                'max:120',
                Rule::unique('access_devices', 'device_uuid')->ignore($deviceRow->id),
            ],
            'platform' => ['required', Rule::in(['web', 'android', 'ios', 'hardware', 'other'])],
            'device_type' => ['required', Rule::in(['prefect_app', 'kiosk', 'scanner', 'door_controller', 'mobile', 'other'])],
            'mode' => ['required', Rule::in(['attendance', 'restricted_access', 'log_only'])],
           'default_event_type' => [
    'required',
    Rule::in([
        'auto',
        'entry',
        'exit',
        'access',
    ]),
],
            'can_unlock' => ['nullable', 'boolean'],
            'allow_manual_search' => ['nullable', 'boolean'],
            'show_student_photo' => ['nullable', 'boolean'],
            'auto_reset_seconds' => ['required', 'integer', 'min:1', 'max:60'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
        ]);

        DB::table('access_devices')
            ->where('id', $deviceRow->id)
            ->where('school_id', $schoolId)
            ->update([
                'campus_id' => $data['campus_id'],
                'area_id' => $data['area_id'] ?: null,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?: null,
                'name' => $data['name'],
                'device_uuid' => $data['device_uuid'],
                'platform' => $data['platform'],
                'device_type' => $data['device_type'],
                'mode' => $data['mode'],
                'default_event_type' => $data['default_event_type'],
                'can_unlock' => $request->boolean('can_unlock'),
                'allow_manual_search' => $request->boolean('allow_manual_search'),
                'show_student_photo' => $request->boolean('show_student_photo'),
                'auto_reset_seconds' => $data['auto_reset_seconds'],
                'status' => $data['status'],
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Dispositivo actualizado correctamente.');
    }


    public function createAccount(Request $request, int $device): RedirectResponse
{
    $user = auth()->user();
    $schoolId = (int) $user->school_id;

    $deviceRow = DB::table('access_devices')
        ->where('school_id', $schoolId)
        ->where('id', $device)
        ->firstOrFail();

    if ($deviceRow->assigned_to_user_id) {
        return back()->withErrors([
            'account' => 'Este dispositivo ya tiene un usuario asignado.',
        ]);
    }

    $data = $request->validate([
        'email' => ['required', 'email', 'max:150', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8', 'max:100'],
    ]);

    $userId = DB::table('users')->insertGetId([
    'school_id' => $schoolId,
    'name' => $deviceRow->name,
    'email' => $data['email'],
    'phone' => null,
    'password' => Hash::make($data['password']),
    'must_change_password' => true,
    'password_changed_at' => null,
    'last_login_at' => null,
    'role' => 'kiosk',
    'status' => 'active',
    'remember_token' => Str::random(60),
    'created_at' => now(),
    'updated_at' => now(),
]);

    DB::table('access_devices')
        ->where('id', $deviceRow->id)
        ->where('school_id', $schoolId)
        ->update([
            'assigned_to_user_id' => $userId,
            'updated_at' => now(),
        ]);

    return back()->with('success', 'Cuenta operativa creada y asignada correctamente.');
}

public function resetPassword(Request $request, int $device): RedirectResponse
{
    $user = auth()->user();
    $schoolId = (int) $user->school_id;

    $deviceRow = DB::table('access_devices')
        ->where('school_id', $schoolId)
        ->where('id', $device)
        ->firstOrFail();

    if (! $deviceRow->assigned_to_user_id) {
        return back()->withErrors([
            'password' => 'Este dispositivo no tiene usuario asignado.',
        ]);
    }

    $data = $request->validate([
        'password' => ['required', 'string', 'min:8', 'max:100'],
    ]);

   DB::table('users')
    ->where('school_id', $schoolId)
    ->where('id', $deviceRow->assigned_to_user_id)
    ->update([
        'password' => Hash::make($data['password']),
        'must_change_password' => true,
        'password_changed_at' => null,
        'remember_token' => Str::random(60),
        'updated_at' => now(),
    ]);

User::query()
    ->where('school_id', $schoolId)
    ->whereKey($deviceRow->assigned_to_user_id)
    ->first()
    ?->tokens()
    ->delete();

    return back()->with('success', 'Contraseña operativa actualizada correctamente.');
}

    private function campuses()
    {
        $user = auth()->user();

        return DB::table('campuses')
            ->where('school_id', $user->school_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function areas()
    {
        $user = auth()->user();

        return DB::table('areas')
            ->where('school_id', $user->school_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function assignableUsers()
    {
        $user = auth()->user();

        return DB::table('users')
            ->where('school_id', $user->school_id)
            ->whereIn('role', ['prefect', 'kiosk', 'director', 'school_admin'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }
}