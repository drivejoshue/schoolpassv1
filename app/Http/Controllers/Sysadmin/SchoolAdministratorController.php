<?php

namespace App\Http\Controllers\Sysadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sysadmin\ResetSchoolAdministratorPasswordRequest;
use App\Http\Requests\Sysadmin\StoreSchoolAdministratorRequest;
use App\Http\Requests\Sysadmin\UpdateSchoolAdministratorRequest;
use App\Models\School;
use App\Services\Auditing\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SchoolAdministratorController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(School $school): View
    {
        $administrators = DB::table('users')
            ->where('school_id', $school->id)
            ->whereIn('role', ['director', 'school_admin'])
            ->orderByRaw(
                "CASE role
                    WHEN 'director' THEN 1
                    WHEN 'school_admin' THEN 2
                    ELSE 3
                END"
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'phone',
                'role',
                'status',
                'email_verified_at',
                'created_at',
            ]);

        return view(
            'sysadmin.schools.administrators.index',
            compact('school', 'administrators')
        );
    }

    public function store(
        StoreSchoolAdministratorRequest $request,
        School $school,
    ): RedirectResponse {
        $data = $request->validated();

        $administratorId = DB::table('users')->insertGetId([
            'school_id' => $school->id,
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'email_verified_at' => now(),
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => 'active',
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->auditLogger->record(
            action: 'school_administrator_created',
            schoolId: $school->id,
            actorId: $request->user()->id,
            actorType: 'superadmin',
            entityType: 'users',
            entityId: $administratorId,
            newValues: [
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'role' => $data['role'],
                'status' => 'active',
            ],
            request: $request,
        );

        return back()->with(
            'status',
            'Administrador creado correctamente.'
        );
    }

    public function update(
        UpdateSchoolAdministratorRequest $request,
        School $school,
        int $administrator,
    ): RedirectResponse {
        $current = $this->administratorOrFail(
            $school,
            $administrator
        );

        $data = $request->validated();

        if (
            $current->status === 'active'
            && $data['status'] !== 'active'
        ) {
            $this->ensureAnotherActiveAdministrator(
                $school,
                $administrator
            );
        }

        DB::table('users')
            ->where('id', $administrator)
            ->update([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
                'status' => $data['status'],
                'updated_at' => now(),
            ]);

        if ($data['status'] !== 'active') {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->where('tokenable_id', $administrator)
                ->delete();
        }

        $this->auditLogger->record(
            action: 'school_administrator_updated',
            schoolId: $school->id,
            actorId: $request->user()->id,
            actorType: 'superadmin',
            entityType: 'users',
            entityId: $administrator,
            oldValues: [
                'name' => $current->name,
                'email' => $current->email,
                'phone' => $current->phone,
                'role' => $current->role,
                'status' => $current->status,
            ],
            newValues: [
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
                'status' => $data['status'],
            ],
            request: $request,
        );

        return back()->with(
            'status',
            'Administrador actualizado.'
        );
    }

    public function resetPassword(
        ResetSchoolAdministratorPasswordRequest $request,
        School $school,
        int $administrator,
    ): RedirectResponse {
        $current = $this->administratorOrFail(
            $school,
            $administrator
        );

        DB::transaction(function () use (
            $request,
            $administrator,
        ): void {
            DB::table('users')
                ->where('id', $administrator)
                ->update([
                    'password' => Hash::make(
                        $request->validated('password')
                    ),
                    'remember_token' => null,
                    'updated_at' => now(),
                ]);

            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->where('tokenable_id', $administrator)
                ->delete();
        });

        $this->auditLogger->record(
            action: 'school_administrator_password_reset',
            schoolId: $school->id,
            actorId: $request->user()->id,
            actorType: 'superadmin',
            entityType: 'users',
            entityId: $administrator,
            newValues: [
                'email' => $current->email,
                'tokens_revoked' => true,
            ],
            request: $request,
        );

        return back()->with(
            'status',
            'Contraseña restablecida y sesiones revocadas.'
        );
    }

    private function administratorOrFail(
        School $school,
        int $administratorId,
    ): object {
        $administrator = DB::table('users')
            ->where('id', $administratorId)
            ->where('school_id', $school->id)
            ->whereIn('role', ['director', 'school_admin'])
            ->first();

        abort_unless($administrator, 404);

        return $administrator;
    }

    private function ensureAnotherActiveAdministrator(
        School $school,
        int $administratorId,
    ): void {
        $others = DB::table('users')
            ->where('school_id', $school->id)
            ->whereIn('role', ['director', 'school_admin'])
            ->where('status', 'active')
            ->where('id', '<>', $administratorId)
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages([
                'status' => (
                    'No puedes desactivar al último administrador '
                    .'activo de la escuela.'
                ),
            ]);
        }
    }
}
