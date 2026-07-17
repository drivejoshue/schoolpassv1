<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FamilyController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $guardian = $this->guardianOrFail($request);

        $school = DB::table('schools')
            ->where('id', $user->school_id)
            ->first();

            $guardianCredential = DB::table('guardian_credentials')
    ->where('school_id', $user->school_id)
    ->where('guardian_id', $guardian->id)
    ->where('status', 'active')
    ->latest('id')
    ->first();

        return response()->json([
            'ok' => true,
           'user' => [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'phone' => $user->phone,
    'role' => $user->role,
    'status' => $user->status,

    'security' => [
        'must_change_password' => (bool) $user->must_change_password,
        'password_changed_at' => $user->password_changed_at,
    ],
],
           'guardian' => [
    'id' => $guardian->id,

    'name' => trim(
        $guardian->first_name.' '.$guardian->last_name
    ),

    'phone' => $guardian->phone,

    'email' => $guardian->email,

    'status' => $guardian->status,

    'photo_url' => $this->fullUrl(
        $guardian->photo_url
    ),

    'has_photo' => ! empty(
        $guardian->photo_url
    ),

    'qr_available' =>
        ! empty($guardian->photo_url)
        && $guardianCredential !== null,
],
            'school' => [
                'id' => $school?->id,
                'name' => $school?->name,
                'slug' => $school?->slug,
                'logo_url' => null,
                'primary_color' => '#0D6EFD',
                'secondary_color' => '#00B8D9',
            ],
        ]);
    }



    public function uploadPhoto(Request $request): JsonResponse
{
    $user = $request->user();
    $guardian = $this->guardianOrFail($request);

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

    try {
        $result = DB::transaction(function () use (
            $uploadedPhoto,
            $guardian,
            $user
        ): array {
            $currentGuardian = DB::table('guardians')
                ->where('id', $guardian->id)
                ->where('school_id', $user->school_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $currentGuardian) {
                throw new AuthorizationException(
                    'No existe un perfil de tutor activo.'
                );
            }

            $extension = strtolower(
                $uploadedPhoto->getClientOriginalExtension()
            );

            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $extension = 'jpg';
            }

            $fileName = sprintf(
                'guardian_%d_%s.%s',
                $currentGuardian->id,
                Str::lower(Str::random(20)),
                $extension
            );

            $directory = sprintf(
                'guardians/school_%d/guardian_%d',
                $user->school_id,
                $currentGuardian->id
            );

            $storedPath = $uploadedPhoto->storeAs(
                $directory,
                $fileName,
                'public'
            );

            if (! $storedPath) {
                throw new \RuntimeException(
                    'No se pudo guardar la fotografía.'
                );
            }

            $previousPhotoPath = $this->normalizeStoragePath(
                $currentGuardian->photo_url
            );

            DB::table('guardians')
                ->where('id', $currentGuardian->id)
                ->where('school_id', $user->school_id)
                ->update([
                    'photo_url' => '/storage/'.$storedPath,
                    'updated_at' => now(),
                ]);

            if (
                $previousPhotoPath !== null
                && $previousPhotoPath !== $storedPath
                && Storage::disk('public')->exists($previousPhotoPath)
            ) {
                Storage::disk('public')->delete($previousPhotoPath);
            }

            $credential = DB::table('guardian_credentials')
                ->where('school_id', $user->school_id)
                ->where('guardian_id', $currentGuardian->id)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if (! $credential) {
              $rawToken = Str::random(64);

                $credentialId = DB::table('guardian_credentials')
                    ->insertGetId([
                        'school_id' => $user->school_id,
                        'guardian_id' => $currentGuardian->id,
                        'type' => 'qr',
                        'token_hash' => hash('sha256', $rawToken),
                        'public_code' => $rawToken,
                        'status' => 'active',
                        'issued_at' => now(),
                        'expires_at' => null,
                        'revoked_at' => null,
                        'revoked_reason' => null,
                        'created_by' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $credential = DB::table('guardian_credentials')
                    ->where('id', $credentialId)
                    ->first();
            }

            return [
                'photo_path' => '/storage/'.$storedPath,
                'credential' => $credential,
            ];
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Fotografía registrada correctamente.',
            'guardian' => [
                'name' => trim(
                    $guardian->first_name.' '.$guardian->last_name
                ),
                'photo_url' => $this->fullUrl(
                    $result['photo_path']
                ),
                'has_photo' => true,
                'qr_available' => $result['credential'] !== null,
            ],
        ]);
    } catch (AuthorizationException $exception) {
        throw $exception;
    } catch (\Throwable $exception) {
        report($exception);

        return response()->json([
            'ok' => false,
            'message' => 'No se pudo guardar la fotografía del tutor.',
            'debug' => app()->environment('local')
                ? $exception->getMessage()
                : null,
        ], 500);
    }
}

public function guardianCredential(Request $request): JsonResponse
{
    $user = $request->user();
    $guardian = $this->guardianOrFail($request);

    if (empty($guardian->photo_url)) {
        return response()->json([
            'ok' => false,
            'status' => 'guardian_photo_required',
            'message' => 'Agrega una fotografía antes de utilizar tu credencial.',
            'guardian' => [
                'name' => trim(
                    $guardian->first_name.' '.$guardian->last_name
                ),
                'photo_url' => null,
                'has_photo' => false,
                'qr_available' => false,
            ],
        ], 403);
    }

    $credential = DB::table('guardian_credentials')
        ->where('school_id', $user->school_id)
        ->where('guardian_id', $guardian->id)
        ->where('type', 'qr')
        ->where('status', 'active')
        ->where(function ($query): void {
            $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })
        ->latest('id')
        ->first();

    if (! $credential) {
        return response()->json([
            'ok' => false,
            'status' => 'guardian_credential_not_found',
            'message' => 'No existe una credencial activa para el tutor.',
            'guardian' => [
                'name' => trim(
                    $guardian->first_name.' '.$guardian->last_name
                ),
                'photo_url' => $this->fullUrl(
                    $guardian->photo_url
                ),
                'has_photo' => true,
                'qr_available' => false,
            ],
        ], 404);
    }

    return response()->json([
        'ok' => true,
        'guardian' => [
            'name' => trim(
                $guardian->first_name.' '.$guardian->last_name
            ),
            'photo_url' => $this->fullUrl(
                $guardian->photo_url
            ),
            'status' => $guardian->status,
            'has_photo' => true,
            'qr_available' => true,
        ],
        'credential' => [
            'type' => $credential->type,
            'status' => $credential->status,
            'qr_payload' => $credential->public_code,
            'issued_at' => $credential->issued_at,
            'expires_at' => $credential->expires_at,
        ],
        'school' => [
            'id' => $user->school_id,
        ],
    ]);
}




    public function students(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);
        $today = Carbon::now(config('app.timezone'))->toDateString();

        $students = DB::table('student_guardians as sg')
            ->join('students as s', 's.id', '=', 'sg.student_id')
            ->leftJoin('school_groups as g', 'g.id', '=', 's.current_group_id')
            ->leftJoin('daily_attendance as da', function ($join) use ($today) {
                $join->on('da.student_id', '=', 's.id')
                    ->where('da.date', '=', $today);
            })
            ->where('sg.guardian_id', $guardian->id)
            ->where('sg.status', 'active')
            ->where('s.school_id', $guardian->school_id)
            ->where('s.status', 'active')
            ->select([
                's.id',
                's.student_code',
                's.first_name',
                's.last_name',
                's.photo_url',
                'g.name as group_name',
                'sg.relationship',
                'sg.is_primary',
                'sg.can_view_attendance',
                'sg.can_receive_notifications',
                'sg.can_authorize_exit',
                'da.attendance_status',
                'da.entry_at',
                'da.exit_at',
                'da.minutes_late',
            ])
            ->orderByDesc('sg.is_primary')
            ->orderBy('s.first_name')
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                    'student_code' => $student->student_code,
                    'group' => $student->group_name,
                    'photo_url' => $this->fullUrl($student->photo_url),
                    'relationship' => $student->relationship,
                    'permissions' => [
                        'is_primary' => (bool) $student->is_primary,
                        'can_view_attendance' => (bool) $student->can_view_attendance,
                        'can_receive_notifications' => (bool) $student->can_receive_notifications,
                        'can_authorize_exit' => (bool) $student->can_authorize_exit,
                    ],
                    'today' => [
                        'status' => $student->attendance_status ?? 'no_record',
                        'status_label' => $this->attendanceStatusLabel($student->attendance_status),
                        'entry_at' => $this->formatTime($student->entry_at),
                        'exit_at' => $this->formatTime($student->exit_at),
                        'minutes_late' => (int) ($student->minutes_late ?? 0),
                    ],
                ];
            });

        return response()->json([
            'ok' => true,
            'count' => $students->count(),
            'items' => $students,
        ]);
    }

    public function attendance(Request $request, int $student): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);
        $this->authorizeStudentForGuardian($guardian, $student, true);

        $range = $request->query('range', 'month');

        [$from, $to] = $this->resolveDateRange($range);

        $query = DB::table('daily_attendance as da')
            ->join('students as s', 's.id', '=', 'da.student_id')
            ->leftJoin('school_groups as g', 'g.id', '=', 'da.group_id')
            ->where('da.school_id', $guardian->school_id)
            ->where('da.student_id', $student)
            ->select([
                'da.id',
                'da.date',
                'da.attendance_status',
                'da.entry_at',
                'da.exit_at',
                'da.minutes_late',
                'g.name as group_name',
            ])
            ->orderByDesc('da.date');

        if ($from !== null) {
            $query->whereDate('da.date', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('da.date', '<=', $to);
        }

        $items = $query
            ->limit($range === 'all' ? 100 : 60)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'date' => $row->date,
                    'group' => $row->group_name,
                    'status' => $row->attendance_status,
                    'status_label' => $this->attendanceStatusLabel($row->attendance_status),
                    'entry_at' => $this->formatTime($row->entry_at),
                    'exit_at' => $this->formatTime($row->exit_at),
                    'minutes_late' => (int) ($row->minutes_late ?? 0),
                ];
            });

        return response()->json([
            'ok' => true,
            'student_id' => $student,
            'range' => $range,
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    public function credential(Request $request, int $student): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);
        $this->authorizeStudentForGuardian($guardian, $student, false);

        $studentRow = DB::table('students as s')
            ->leftJoin('school_groups as g', 'g.id', '=', 's.current_group_id')
            ->join('schools as school', 'school.id', '=', 's.school_id')
            ->where('s.id', $student)
            ->where('s.school_id', $guardian->school_id)
            ->select([
                's.id',
                's.student_code',
                's.first_name',
                's.last_name',
                's.photo_url',
                'g.name as group_name',
                'school.name as school_name',
            ])
            ->first();

        if (! $studentRow) {
            throw new AuthorizationException('Alumno no disponible.');
        }

        $credential = DB::table('student_credentials')
            ->where('student_id', $student)
            ->where('school_id', $guardian->school_id)
            ->where('type', 'qr')
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (! $credential) {
            return response()->json([
                'ok' => false,
                'message' => 'El alumno no tiene credencial activa.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'student' => [
                'id' => $studentRow->id,
                'name' => trim($studentRow->first_name . ' ' . $studentRow->last_name),
                'student_code' => $studentRow->student_code,
                'group' => $studentRow->group_name,
                'photo_url' => $this->fullUrl($studentRow->photo_url),
                'school_name' => $studentRow->school_name,
            ],
            'credential' => [
                'type' => $credential->type,
                'status' => $credential->status,
                'qr_payload' => $credential->public_code,
                'issued_at' => $credential->issued_at,
                'expires_at' => $credential->expires_at,
            ],
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);

        $items = DB::table('notifications')
            ->where('school_id', $guardian->school_id)
            ->where(function ($query) use ($guardian) {
                $query->where('guardian_id', $guardian->id)
                    ->orWhere('user_id', $guardian->user_id);
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'status' => $notification->status,
                    'sent_at' => $notification->sent_at,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'is_read' => $notification->read_at !== null || $notification->status === 'read',
                ];
            });

        return response()->json([
            'ok' => true,
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    public function markNotificationAsRead(Request $request, int $notification): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);

        $row = DB::table('notifications')
            ->where('id', $notification)
            ->where('school_id', $guardian->school_id)
            ->where(function ($query) use ($guardian) {
                $query->where('guardian_id', $guardian->id)
                    ->orWhere('user_id', $guardian->user_id);
            })
            ->first();

        if (! $row) {
            throw new AuthorizationException('Notificación no disponible.');
        }

        DB::table('notifications')
            ->where('id', $notification)
            ->update([
                'status' => 'read',
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'message' => 'Notificación marcada como leída.',
            'notification_id' => $notification,
        ]);
    }

    private function guardianOrFail(Request $request): object
    {
        $user = $request->user();

        if (! $user || $user->role !== 'guardian') {
            throw new AuthorizationException('Usuario no autorizado para Family.');
        }

        $guardian = DB::table('guardians')
            ->where('user_id', $user->id)
            ->where('school_id', $user->school_id)
            ->where('status', 'active')
            ->first();

        if (! $guardian) {
            throw new AuthorizationException('No existe perfil de tutor activo.');
        }

        return $guardian;
    }

    private function authorizeStudentForGuardian(object $guardian, int $studentId, bool $requiresAttendancePermission): void
    {
        $query = DB::table('student_guardians as sg')
            ->join('students as s', 's.id', '=', 'sg.student_id')
            ->where('sg.guardian_id', $guardian->id)
            ->where('sg.student_id', $studentId)
            ->where('sg.status', 'active')
            ->where('s.status', 'active')
            ->where('s.school_id', $guardian->school_id);

        if ($requiresAttendancePermission) {
            $query->where('sg.can_view_attendance', 1);
        }

        if (! $query->exists()) {
            throw new AuthorizationException('Alumno no autorizado para este tutor.');
        }
    }

    private function resolveDateRange(string $range): array
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        return match ($range) {
            'today' => [
                $today->toDateString(),
                $today->toDateString(),
            ],
            'yesterday' => [
                $today->copy()->subDay()->toDateString(),
                $today->copy()->subDay()->toDateString(),
            ],
            'week' => [
                $today->copy()->startOfWeek()->toDateString(),
                $today->copy()->endOfWeek()->toDateString(),
            ],
            'month' => [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ],
            'all' => [
                null,
                null,
            ],
            default => [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ],
        };
    }

    private function attendanceStatusLabel(?string $status): string
    {
        return match ($status) {
            'present' => 'Presente',
            'on_time' => 'Puntual',
            'late' => 'Retardo',
            'very_late' => 'Extemporáneo',
            'absent' => 'Falta',
            'early_exit' => 'Salida anticipada',
            'pending' => 'Pendiente',
            'no_record', null => 'Sin registro',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function formatTime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->format('H:i');
    }


    private function normalizeStoragePath(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (
        str_starts_with($value, 'http://')
        || str_starts_with($value, 'https://')
    ) {
        $path = parse_url($value, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $value = $path;
    }

    $value = ltrim($value, '/');

    if (str_starts_with($value, 'storage/')) {
        $value = substr($value, strlen('storage/'));
    }

    return $value !== '' ? $value : null;
}




private function fullUrl(?string $path): ?string
{
    $path = trim((string) $path);

    if ($path === '') {
        return null;
    }

    if (
        str_starts_with($path, 'http://')
        || str_starts_with($path, 'https://')
    ) {
        if (
            request()->isSecure()
            || request()->header('x-forwarded-proto') === 'https'
        ) {
            return preg_replace(
                '/^http:\/\//i',
                'https://',
                $path
            );
        }

        return $path;
    }

    $url = url($path);

    if (
        request()->isSecure()
        || request()->header('x-forwarded-proto') === 'https'
    ) {
        return preg_replace(
            '/^http:\/\//i',
            'https://',
            $url
        );
    }

    return $url;
}



}