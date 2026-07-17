<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class GuardianController extends Controller
{
    public function index(Request $request): View
    {
        $schoolId = $this->schoolId($request);

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
            'account' => trim((string) $request->query('account', '')),
            'qr' => trim((string) $request->query('qr', '')),
        ];

        $guardians = DB::table('guardians as g')
            ->leftJoin('users as u', 'u.id', '=', 'g.user_id')
            ->where('g.school_id', $schoolId)
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('g.first_name', 'like', "%{$search}%")
                        ->orWhere('g.last_name', 'like', "%{$search}%")
                        ->orWhere('g.email', 'like', "%{$search}%")
                        ->orWhere('g.phone', 'like', "%{$search}%")
                        ->orWhere('u.email', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($filters['status'], ['active', 'inactive', 'blocked'], true),
                fn ($query) => $query->where('g.status', $filters['status'])
            )
            ->when($filters['account'] === 'with', fn ($query) => $query->whereNotNull('g.user_id'))
            ->when($filters['account'] === 'without', fn ($query) => $query->whereNull('g.user_id'))
            ->when($filters['qr'] === 'active', function ($query): void {
                $query->whereExists(function ($subquery): void {
                    $subquery
                        ->select(DB::raw(1))
                        ->from('guardian_credentials as gc_filter')
                        ->whereColumn('gc_filter.guardian_id', 'g.id')
                        ->where('gc_filter.status', 'active')
                        ->where(function ($expiration): void {
                            $expiration
                                ->whereNull('gc_filter.expires_at')
                                ->orWhere('gc_filter.expires_at', '>', now());
                        });
                });
            })
            ->when($filters['qr'] === 'without', function ($query): void {
                $query->whereNotExists(function ($subquery): void {
                    $subquery
                        ->select(DB::raw(1))
                        ->from('guardian_credentials as gc_filter')
                        ->whereColumn('gc_filter.guardian_id', 'g.id')
                        ->where('gc_filter.status', 'active')
                        ->where(function ($expiration): void {
                            $expiration
                                ->whereNull('gc_filter.expires_at')
                                ->orWhere('gc_filter.expires_at', '>', now());
                        });
                });
            })
            ->select([
                'g.*',
                'u.email as access_username',
                'u.status as user_status',
                DB::raw('(
                    SELECT COUNT(*)
                    FROM student_guardians sg
                    WHERE sg.guardian_id = g.id
                      AND sg.status = "active"
                ) as students_count'),
                DB::raw('(
                    SELECT COUNT(*)
                    FROM student_guardians sg_primary
                    WHERE sg_primary.guardian_id = g.id
                      AND sg_primary.status = "active"
                      AND sg_primary.is_primary = 1
                ) as primary_students_count'),
                DB::raw('(
                    SELECT COUNT(*)
                    FROM guardian_credentials gc
                    WHERE gc.guardian_id = g.id
                      AND gc.status = "active"
                      AND (gc.expires_at IS NULL OR gc.expires_at > NOW())
                ) as active_credentials_count'),
                DB::raw('(
                    SELECT MAX(al.scanned_at)
                    FROM access_logs al
                    WHERE al.guardian_id = g.id
                ) as last_access_at'),
            ])
            ->orderBy('g.last_name')
            ->orderBy('g.first_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.guardians.index', [
            'guardians' => $guardians,
            'filters' => $filters,
            'search' => $filters['search'],
        ]);
    }

    public function create(): View
    {
        return view('admin.guardians.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $schoolId = $this->schoolId($request);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('guardians', 'email')->where('school_id', $schoolId),
            ],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
            'create_user' => ['nullable', 'boolean'],
        ]);

        $createUser = $request->boolean('create_user');

        try {
            [$guardianId, $credentials] = DB::transaction(
                function () use ($data, $schoolId, $createUser): array {
                    $guardianId = DB::table('guardians')->insertGetId([
                        'school_id' => $schoolId,
                        'user_id' => null,
                        'first_name' => trim($data['first_name']),
                        'last_name' => trim($data['last_name']),
                        'phone' => $this->nullableTrim($data['phone'] ?? null),
                        'email' => $this->nullableTrim($data['email'] ?? null),
                        'photo_url' => null,
                        'status' => $data['status'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $credentials = null;

                    if ($createUser) {
                        $credentials = $this->createGuardianUser(
                            schoolId: $schoolId,
                            guardianId: $guardianId,
                            firstName: trim($data['first_name']),
                            lastName: trim($data['last_name']),
                            phone: $this->nullableTrim($data['phone'] ?? null),
                            guardianStatus: $data['status']
                        );
                    }

                    return [$guardianId, $credentials];
                },
                3
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'guardian' => app()->environment('local')
                        ? $exception->getMessage()
                        : 'No se pudo registrar al tutor.',
                ]);
        }

        $redirect = redirect()
            ->route('admin.guardians.show', $guardianId)
            ->with('success', 'Tutor registrado correctamente.');

        if ($credentials) {
            $redirect->with('generated_credentials', $credentials);
        }

        return $redirect;
    }

    public function show(Request $request, int $guardian): View
    {
        $schoolId = $this->schoolId($request);
        $activeCycle = $this->activeCycle($schoolId);

        $guardianRow = $this->guardianForSchool($schoolId, $guardian);

        $linkedStudents = DB::table('student_guardians as sg')
            ->join('students as s', function ($join) use ($schoolId): void {
                $join->on('s.id', '=', 'sg.student_id')
                    ->where('s.school_id', '=', $schoolId);
            })
            ->leftJoin('student_enrollments as se', function ($join) use ($schoolId, $activeCycle): void {
                $join->on('se.student_id', '=', 's.id')
                    ->where('se.school_id', '=', $schoolId);

                if ($activeCycle) {
                    $join->where('se.academic_cycle_id', '=', $activeCycle->id);
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->leftJoin('school_groups as gr', 'gr.id', '=', 'se.school_group_id')
            ->leftJoin('academic_levels as al', 'al.id', '=', 'gr.academic_level_id')
            ->where('sg.guardian_id', $guardian)
            ->where('sg.status', 'active')
            ->select([
                'sg.*',
                's.first_name',
                's.last_name',
                's.student_code',
                's.photo_url as student_photo_url',
                's.status as student_status',
                'se.id as enrollment_id',
                'se.status as enrollment_status',
                'gr.name as group_name',
                'al.name as level_name',
            ])
            ->orderByDesc('sg.is_primary')
            ->orderBy('s.last_name')
            ->orderBy('s.first_name')
            ->get();

        $availableStudents = DB::table('students as s')
            ->leftJoin('student_enrollments as se', function ($join) use ($schoolId, $activeCycle): void {
                $join->on('se.student_id', '=', 's.id')
                    ->where('se.school_id', '=', $schoolId);

                if ($activeCycle) {
                    $join->where('se.academic_cycle_id', '=', $activeCycle->id);
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->leftJoin('school_groups as gr', 'gr.id', '=', 'se.school_group_id')
            ->where('s.school_id', $schoolId)
            ->where('s.status', 'active')
            ->whereNotExists(function ($query) use ($guardian): void {
                $query->select(DB::raw(1))
                    ->from('student_guardians as existing_sg')
                    ->whereColumn('existing_sg.student_id', 's.id')
                    ->where('existing_sg.guardian_id', $guardian)
                    ->where('existing_sg.status', 'active');
            })
            ->select([
                's.id',
                's.first_name',
                's.last_name',
                's.student_code',
                'se.status as enrollment_status',
                'gr.name as group_name',
                DB::raw('(
                    SELECT COUNT(*)
                    FROM student_guardians all_sg
                    WHERE all_sg.student_id = s.id
                      AND all_sg.status = "active"
                ) as guardians_count'),
            ])
            ->orderBy('s.last_name')
            ->orderBy('s.first_name')
            ->get();

        $credentials = DB::table('guardian_credentials as gc')
            ->leftJoin('users as creator', 'creator.id', '=', 'gc.created_by')
            ->where('gc.school_id', $schoolId)
            ->where('gc.guardian_id', $guardian)
            ->select([
                'gc.*',
                'creator.name as created_by_name',
            ])
            ->orderByRaw("
                CASE gc.status
                    WHEN 'active' THEN 1
                    WHEN 'revoked' THEN 2
                    ELSE 3
                END
            ")
            ->orderByDesc('gc.id')
            ->get();

        $activeCredential = $credentials
            ->first(function ($credential): bool {
                if ($credential->status !== 'active') {
                    return false;
                }

                return $credential->expires_at === null
                    || now()->lt($credential->expires_at);
            });

        $accessLogs = DB::table('access_logs as log')
            ->leftJoin('students as s', 's.id', '=', 'log.student_id')
            ->leftJoin('school_groups as gr', 'gr.id', '=', 'log.school_group_id')
            ->leftJoin('access_devices as d', 'd.id', '=', 'log.access_device_id')
            ->leftJoin('areas as a', 'a.id', '=', 'log.area_id')
            ->leftJoin('users as u', 'u.id', '=', 'log.user_id')
            ->leftJoin('guardian_credentials as gc', 'gc.id', '=', 'log.guardian_credential_id')
            ->where('log.school_id', $schoolId)
            ->where('log.guardian_id', $guardian)
            ->select([
                'log.*',
                's.first_name as student_first_name',
                's.last_name as student_last_name',
                's.student_code',
                'gr.name as group_name',
                'd.name as device_name',
                'a.name as area_name',
                'u.name as performed_by_name',
                'u.role as performed_by_role',
                'gc.public_code as guardian_public_code',
            ])
            ->orderByDesc('log.scanned_at')
            ->limit(50)
            ->get();

        $summary = [
            'students' => $linkedStudents->count(),
            'primary_students' => $linkedStudents->where('is_primary', 1)->count(),
            'can_drop_off' => $linkedStudents->where('can_drop_off', 1)->count(),
            'can_pick_up' => $linkedStudents->where('can_pick_up', 1)->count(),
            'movements' => $accessLogs->count(),
        ];

        return view('admin.guardians.show', compact(
            'guardianRow',
            'linkedStudents',
            'availableStudents',
            'activeCycle',
            'credentials',
            'activeCredential',
            'accessLogs',
            'summary'
        ));
    }

    public function edit(Request $request, int $guardian): View
    {
        $schoolId = $this->schoolId($request);

        $guardianRow = $this->guardianForSchool($schoolId, $guardian);

        return view('admin.guardians.edit', compact('guardianRow'));
    }

    public function update(Request $request, int $guardian): RedirectResponse
    {
        $schoolId = $this->schoolId($request);
        $guardianRow = $this->guardianForSchool($schoolId, $guardian);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('guardians', 'email')
                    ->ignore($guardianRow->id)
                    ->where('school_id', $schoolId),
            ],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
        ]);

        DB::transaction(function () use ($data, $schoolId, $guardianRow): void {
            DB::table('guardians')
                ->where('school_id', $schoolId)
                ->where('id', $guardianRow->id)
                ->update([
                    'first_name' => trim($data['first_name']),
                    'last_name' => trim($data['last_name']),
                    'phone' => $this->nullableTrim($data['phone'] ?? null),
                    'email' => $this->nullableTrim($data['email'] ?? null),
                    'status' => $data['status'],
                    'updated_at' => now(),
                ]);

            if ($guardianRow->user_id) {
                $userUpdate = [
                    'name' => trim($data['first_name'].' '.$data['last_name']),
                    'phone' => $this->nullableTrim($data['phone'] ?? null),
                    'updated_at' => now(),
                ];

                if ($data['status'] !== 'active') {
                    $userUpdate['status'] = 'blocked';
                }

                DB::table('users')
                    ->where('school_id', $schoolId)
                    ->where('id', $guardianRow->user_id)
                    ->update($userUpdate);
            }

            if ($data['status'] !== 'active') {
                DB::table('guardian_credentials')
                    ->where('school_id', $schoolId)
                    ->where('guardian_id', $guardianRow->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'revoked',
                        'revoked_at' => now(),
                        'revoked_reason' => 'Tutor desactivado o bloqueado desde administración.',
                        'updated_at' => now(),
                    ]);
            }
        }, 3);

        return redirect()
            ->route('admin.guardians.show', $guardian)
            ->with('success', 'Datos del tutor actualizados correctamente.');
    }

    public function uploadPhoto(Request $request, int $guardian): RedirectResponse
    {
        $schoolId = $this->schoolId($request);

        $data = $request->validate([
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=500,min_height=500,max_width=5000,max_height=5000',
            ],
        ]);

        $uploadedPhoto = $data['photo'];
        $storedPath = null;
        $previousPhotoPath = null;
        $credentialCreated = false;

        try {
            DB::transaction(function () use (
                $request,
                $schoolId,
                $guardian,
                $uploadedPhoto,
                &$storedPath,
                &$previousPhotoPath,
                &$credentialCreated
            ): void {
                $guardianRow = DB::table('guardians')
                    ->where('school_id', $schoolId)
                    ->where('id', $guardian)
                    ->lockForUpdate()
                    ->firstOrFail();

                $extension = strtolower($uploadedPhoto->getClientOriginalExtension());

                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $extension = 'jpg';
                }

                $directory = sprintf(
                    'guardians/school_%d/guardian_%d',
                    $schoolId,
                    $guardianRow->id
                );

                $filename = sprintf(
                    'guardian_%d_%s.%s',
                    $guardianRow->id,
                    Str::lower(Str::random(20)),
                    $extension
                );

                $storedPath = $uploadedPhoto->storeAs(
                    $directory,
                    $filename,
                    'public'
                );

                if (! $storedPath) {
                    throw new RuntimeException('No se pudo guardar la fotografía.');
                }

                $previousPhotoPath = $this->normalizeStoragePath($guardianRow->photo_url);

                DB::table('guardians')
                    ->where('school_id', $schoolId)
                    ->where('id', $guardianRow->id)
                    ->update([
                        'photo_url' => '/storage/'.$storedPath,
                        'updated_at' => now(),
                    ]);

                if (
                    $guardianRow->status === 'active'
                    && ! $this->hasActiveCredential($schoolId, (int) $guardianRow->id)
                ) {
                    $this->insertGuardianCredential(
                        schoolId: $schoolId,
                        guardianId: (int) $guardianRow->id,
                        createdBy: (int) $request->user()->id
                    );

                    $credentialCreated = true;
                }
            }, 3);
        } catch (Throwable $exception) {
            if ($storedPath && Storage::disk('public')->exists($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }

            report($exception);

            return back()->withErrors([
                'photo' => app()->environment('local')
                    ? $exception->getMessage()
                    : 'No se pudo actualizar la fotografía.',
            ]);
        }

        if (
            $previousPhotoPath
            && $previousPhotoPath !== $storedPath
            && Storage::disk('public')->exists($previousPhotoPath)
        ) {
            Storage::disk('public')->delete($previousPhotoPath);
        }

        return back()->with(
            'success',
            $credentialCreated
                ? 'Fotografía actualizada y credencial QR generada.'
                : 'Fotografía del tutor actualizada correctamente.'
        );
    }

    public function removePhoto(Request $request, int $guardian): RedirectResponse
    {
        $schoolId = $this->schoolId($request);
        $previousPhotoPath = null;

        DB::transaction(function () use (
            $schoolId,
            $guardian,
            &$previousPhotoPath
        ): void {
            $guardianRow = DB::table('guardians')
                ->where('school_id', $schoolId)
                ->where('id', $guardian)
                ->lockForUpdate()
                ->firstOrFail();

            $previousPhotoPath = $this->normalizeStoragePath($guardianRow->photo_url);

            DB::table('guardians')
                ->where('school_id', $schoolId)
                ->where('id', $guardianRow->id)
                ->update([
                    'photo_url' => null,
                    'updated_at' => now(),
                ]);

            DB::table('guardian_credentials')
                ->where('school_id', $schoolId)
                ->where('guardian_id', $guardianRow->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revoked_reason' => 'Fotografía eliminada desde administración.',
                    'updated_at' => now(),
                ]);
        }, 3);

        if (
            $previousPhotoPath
            && Storage::disk('public')->exists($previousPhotoPath)
        ) {
            Storage::disk('public')->delete($previousPhotoPath);
        }

        return back()->with(
            'success',
            'Fotografía eliminada. Las credenciales QR activas fueron revocadas.'
        );
    }

    public function linkStudent(
        Request $request,
        int $guardian
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $guardianRow = DB::table('guardians')
            ->where('school_id', $schoolId)
            ->where('id', $guardian)
            ->firstOrFail();

        $data = $request->validate($this->studentPermissionRules($schoolId));

        DB::transaction(function () use (
            $request,
            $data,
            $guardianRow
        ): void {
            if ($request->boolean('is_primary')) {
                DB::table('student_guardians')
                    ->where('student_id', $data['student_id'])
                    ->where('status', 'active')
                    ->update([
                        'is_primary' => false,
                        'updated_at' => now(),
                    ]);
            }

            $existing = DB::table('student_guardians')
                ->where('student_id', $data['student_id'])
                ->where('guardian_id', $guardianRow->id)
                ->first();

            $values = $this->studentPermissionValues($request, $data);
            $values['status'] = 'active';
            $values['updated_at'] = now();

            if ($existing) {
                DB::table('student_guardians')
                    ->where('id', $existing->id)
                    ->update($values);
            } else {
                $values['student_id'] = $data['student_id'];
                $values['guardian_id'] = $guardianRow->id;
                $values['created_at'] = now();

                DB::table('student_guardians')->insert($values);
            }
        }, 3);

        return back()->with(
            'success',
            'Alumno vinculado correctamente.'
        );
    }

    public function updateStudentPermissions(
        Request $request,
        int $guardian,
        int $student
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        DB::table('guardians')
            ->where('school_id', $schoolId)
            ->where('id', $guardian)
            ->firstOrFail();

        DB::table('students')
            ->where('school_id', $schoolId)
            ->where('id', $student)
            ->firstOrFail();

        DB::table('student_guardians')
            ->where('guardian_id', $guardian)
            ->where('student_id', $student)
            ->where('status', 'active')
            ->firstOrFail();

        $rules = $this->studentPermissionRules($schoolId);
        unset($rules['student_id']);

        $data = $request->validate($rules);

        DB::transaction(function () use (
            $request,
            $data,
            $guardian,
            $student
        ): void {
            if ($request->boolean('is_primary')) {
                DB::table('student_guardians')
                    ->where('student_id', $student)
                    ->where('guardian_id', '!=', $guardian)
                    ->where('status', 'active')
                    ->update([
                        'is_primary' => false,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('student_guardians')
                ->where('guardian_id', $guardian)
                ->where('student_id', $student)
                ->where('status', 'active')
                ->update([
                    ...$this->studentPermissionValues($request, $data),
                    'updated_at' => now(),
                ]);
        }, 3);

        return back()->with(
            'success',
            'Permisos y vigencia actualizados correctamente.'
        );
    }

    public function unlinkStudent(
        Request $request,
        int $guardian,
        int $student
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        DB::table('guardians')
            ->where('school_id', $schoolId)
            ->where('id', $guardian)
            ->firstOrFail();

        DB::table('students')
            ->where('school_id', $schoolId)
            ->where('id', $student)
            ->firstOrFail();

        DB::table('student_guardians')
            ->where('guardian_id', $guardian)
            ->where('student_id', $student)
            ->update([
                'status' => 'inactive',
                'is_primary' => false,
                'updated_at' => now(),
            ]);

        return back()->with(
            'success',
            'Vínculo desactivado correctamente.'
        );
    }

    public function createCredential(
        Request $request,
        int $guardian
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        try {
            DB::transaction(function () use (
                $request,
                $schoolId,
                $guardian
            ): void {
                $guardianRow = DB::table('guardians')
                    ->where('school_id', $schoolId)
                    ->where('id', $guardian)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertGuardianCanUseQr($guardianRow);

                if ($this->hasActiveCredential($schoolId, $guardian)) {
                    throw new RuntimeException(
                        'El tutor ya tiene una credencial QR activa.'
                    );
                }

                $this->insertGuardianCredential(
                    schoolId: $schoolId,
                    guardianId: $guardian,
                    createdBy: (int) $request->user()->id
                );
            }, 3);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'credential' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Credencial QR generada correctamente.'
        );
    }

    public function regenerateCredential(
        Request $request,
        int $guardian
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        try {
            DB::transaction(function () use (
                $request,
                $schoolId,
                $guardian
            ): void {
                $guardianRow = DB::table('guardians')
                    ->where('school_id', $schoolId)
                    ->where('id', $guardian)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertGuardianCanUseQr($guardianRow);

                DB::table('guardian_credentials')
                    ->where('school_id', $schoolId)
                    ->where('guardian_id', $guardian)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'revoked',
                        'revoked_at' => now(),
                        'revoked_reason' => 'Regenerada desde panel administrativo.',
                        'updated_at' => now(),
                    ]);

                $this->insertGuardianCredential(
                    schoolId: $schoolId,
                    guardianId: $guardian,
                    createdBy: (int) $request->user()->id
                );
            }, 3);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'credential' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Credencial QR regenerada. La credencial anterior quedó revocada.'
        );
    }

    public function revokeCredential(
        Request $request,
        int $guardian,
        int $credential
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $credentialRow = DB::table('guardian_credentials')
            ->where('school_id', $schoolId)
            ->where('guardian_id', $guardian)
            ->where('id', $credential)
            ->firstOrFail();

        if ($credentialRow->status !== 'active') {
            return back()->withErrors([
                'credential' => 'La credencial ya no está activa.',
            ]);
        }

        DB::table('guardian_credentials')
            ->where('school_id', $schoolId)
            ->where('guardian_id', $guardian)
            ->where('id', $credential)
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_reason' => 'Revocada desde panel administrativo.',
                'updated_at' => now(),
            ]);

        return back()->with(
            'success',
            'Credencial QR revocada correctamente.'
        );
    }

    public function printCredential(
        Request $request,
        int $guardian,
        int $credential
    ): View {
        $schoolId = $this->schoolId($request);

        $guardianRow = DB::table('guardians')
            ->where('school_id', $schoolId)
            ->where('id', $guardian)
            ->firstOrFail();

        $credentialRow = DB::table('guardian_credentials')
            ->where('school_id', $schoolId)
            ->where('guardian_id', $guardian)
            ->where('id', $credential)
            ->firstOrFail();

        abort_unless($credentialRow->status === 'active', 404);
        abort_if(
            $credentialRow->expires_at
            && now()->gte($credentialRow->expires_at),
            404
        );
        abort_if(empty($guardianRow->photo_url), 403);

        $school = DB::table('schools')
            ->where('id', $schoolId)
            ->first();

        $students = DB::table('student_guardians as sg')
            ->join('students as s', 's.id', '=', 'sg.student_id')
            ->leftJoin('school_groups as g', 'g.id', '=', 's.current_group_id')
            ->where('sg.guardian_id', $guardian)
            ->where('sg.status', 'active')
            ->where('s.school_id', $schoolId)
            ->select([
                's.first_name',
                's.last_name',
                's.student_code',
                'g.name as group_name',
                'sg.relationship',
                'sg.can_drop_off',
                'sg.can_pick_up',
            ])
            ->orderByDesc('sg.is_primary')
            ->orderBy('s.first_name')
            ->get();

        return view('admin.guardians.print-credential', compact(
            'guardianRow',
            'credentialRow',
            'school',
            'students'
        ));
    }

    public function createAccount(
        Request $request,
        int $guardian
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $guardianRow = DB::table('guardians')
            ->where('school_id', $schoolId)
            ->where('id', $guardian)
            ->firstOrFail();

        if ($guardianRow->user_id) {
            return back()->withErrors([
                'account' => 'Este tutor ya tiene una cuenta vinculada.',
            ]);
        }

        try {
            $credentials = DB::transaction(
                fn (): array => $this->createGuardianUser(
                    schoolId: $schoolId,
                    guardianId: (int) $guardianRow->id,
                    firstName: $guardianRow->first_name,
                    lastName: $guardianRow->last_name,
                    phone: $guardianRow->phone,
                    guardianStatus: $guardianRow->status
                ),
                3
            );
        } catch (Throwable $exception) {
            return back()->withErrors([
                'account' => app()->environment('local')
                    ? $exception->getMessage()
                    : 'No se pudo crear la cuenta del tutor.',
            ]);
        }

        return back()
            ->with('success', 'Cuenta web/app creada correctamente.')
            ->with('generated_credentials', $credentials);
    }

    public function resetAccountPassword(
        Request $request,
        int $guardian
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $guardianRow = DB::table('guardians')
            ->where('school_id', $schoolId)
            ->where('id', $guardian)
            ->firstOrFail();

        if (! $guardianRow->user_id) {
            return back()->withErrors([
                'account' => 'El tutor todavía no tiene cuenta web/app.',
            ]);
        }

        $password = $this->generateInitialPassword();

        DB::table('users')
            ->where('id', $guardianRow->user_id)
            ->where('school_id', $schoolId)
            ->update([
                'password' => Hash::make($password),
                'must_change_password' => true,
                'password_changed_at' => null,
                'updated_at' => now(),
            ]);

        $username = DB::table('users')
            ->where('id', $guardianRow->user_id)
            ->value('email');

        return back()
            ->with('success', 'Contraseña restablecida correctamente.')
            ->with('generated_credentials', [
                'username' => $username,
                'password' => $password,
                'is_reset' => true,
            ]);
    }

    public function updateAccountStatus(
        Request $request,
        int $guardian
    ): RedirectResponse {
        $schoolId = $this->schoolId($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'blocked'])],
        ]);

        $guardianRow = DB::table('guardians')
            ->where('school_id', $schoolId)
            ->where('id', $guardian)
            ->firstOrFail();

        if (! $guardianRow->user_id) {
            return back()->withErrors([
                'account' => 'El tutor todavía no tiene cuenta web/app.',
            ]);
        }

        if ($data['status'] === 'active' && $guardianRow->status !== 'active') {
            return back()->withErrors([
                'account' => 'Primero debes activar el estado administrativo del tutor.',
            ]);
        }

        DB::table('users')
            ->where('id', $guardianRow->user_id)
            ->where('school_id', $schoolId)
            ->update([
                'status' => $data['status'],
                'updated_at' => now(),
            ]);

        return back()->with(
            'success',
            $data['status'] === 'active'
                ? 'Acceso del tutor activado.'
                : 'Acceso del tutor bloqueado.'
        );
    }

    private function guardianForSchool(int $schoolId, int $guardian): object
    {
        return DB::table('guardians as g')
            ->leftJoin('users as u', 'u.id', '=', 'g.user_id')
            ->where('g.school_id', $schoolId)
            ->where('g.id', $guardian)
            ->select([
                'g.*',
                'u.email as access_username',
                'u.status as user_status',
                'u.role as user_role',
                'u.must_change_password',
                'u.password_changed_at',
                'u.created_at as account_created_at',
                'u.updated_at as account_updated_at',
            ])
            ->firstOrFail();
    }

    private function studentPermissionRules(int $schoolId): array
    {
        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'relationship' => [
                'required',
                'string',
                'max:50',
                Rule::in(['madre', 'padre', 'tutor', 'abuelo', 'abuela', 'otro']),
            ],
            'is_primary' => ['nullable', 'boolean'],
            'can_receive_notifications' => ['nullable', 'boolean'],
            'can_view_attendance' => ['nullable', 'boolean'],
            'can_drop_off' => ['nullable', 'boolean'],
            'can_pick_up' => ['nullable', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    private function studentPermissionValues(
        Request $request,
        array $data
    ): array {
        $canPickUp = $request->boolean('can_pick_up');

        return [
            'relationship' => $data['relationship'],
            'is_primary' => $request->boolean('is_primary'),
            'can_receive_notifications' =>
                $request->boolean('can_receive_notifications'),
            'can_view_attendance' =>
                $request->boolean('can_view_attendance'),
            'can_drop_off' =>
                $request->boolean('can_drop_off'),
            'can_pick_up' => $canPickUp,
            'can_authorize_exit' => $canPickUp,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
        ];
    }

    private function assertGuardianCanUseQr(object $guardian): void
    {
        if ($guardian->status !== 'active') {
            throw new RuntimeException(
                'El tutor debe estar activo para utilizar una credencial QR.'
            );
        }

        if (empty($guardian->photo_url)) {
            throw new RuntimeException(
                'Debes registrar una fotografía antes de generar el QR.'
            );
        }
    }

    private function hasActiveCredential(
        int $schoolId,
        int $guardianId
    ): bool {
        return DB::table('guardian_credentials')
            ->where('school_id', $schoolId)
            ->where('guardian_id', $guardianId)
            ->where('type', 'qr')
            ->where('status', 'active')
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    private function insertGuardianCredential(
        int $schoolId,
        int $guardianId,
        int $createdBy
    ): int {
        $rawToken = Str::random(64);

        return DB::table('guardian_credentials')->insertGetId([
            'school_id' => $schoolId,
            'guardian_id' => $guardianId,
            'type' => 'qr',
            'token_hash' => hash('sha256', $rawToken),
            'public_code' => $rawToken,
            'status' => 'active',
            'issued_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
            'revoked_reason' => null,
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createGuardianUser(
        int $schoolId,
        int $guardianId,
        string $firstName,
        string $lastName,
        ?string $phone,
        string $guardianStatus
    ): array {
        $username = $this->generateGuardianUsername(
            schoolId: $schoolId,
            guardianId: $guardianId
        );

        if (User::query()->where('email', $username)->exists()) {
            throw new RuntimeException(
                'No se pudo generar un usuario único para el tutor.'
            );
        }

        $password = $this->generateInitialPassword();

        $userId = DB::table('users')->insertGetId([
            'school_id' => $schoolId,
            'name' => trim($firstName.' '.$lastName),
            'email' => $username,
            'phone' => $phone,
            'password' => Hash::make($password),
            'role' => 'guardian',
            'status' => $guardianStatus === 'active'
                ? 'active'
                : 'blocked',
            'must_change_password' => true,
            'password_changed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('guardians')
            ->where('school_id', $schoolId)
            ->where('id', $guardianId)
            ->update([
                'user_id' => $userId,
                'updated_at' => now(),
            ]);

        return [
            'username' => $username,
            'password' => $password,
            'is_reset' => false,
        ];
    }

    private function generateGuardianUsername(
        int $schoolId,
        int $guardianId
    ): string {
        return sprintf(
            'sp-s%d-t%06d@schoolpass.local',
            $schoolId,
            $guardianId
        );
    }

    private function generateInitialPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $random = '';

        for ($index = 0; $index < 6; $index++) {
            $random .= $alphabet[
                random_int(0, strlen($alphabet) - 1)
            ];
        }

        return 'SP-'.$random;
    }

    private function normalizeStoragePath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'storage/')) {
            return Str::after($path, 'storage/');
        }

        return $path;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function activeCycle(int $schoolId): ?object
    {
        return DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->first();
    }

    private function schoolId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user && $user->school_id, 403);

        return (int) $user->school_id;
    }
}
