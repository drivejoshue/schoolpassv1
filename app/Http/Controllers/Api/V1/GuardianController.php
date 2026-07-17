<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuardianController extends Controller
{   

    public function markNotificationAsRead(Request $request, int $notification): JsonResponse
{
    $user = $request->user();

    $guardian = $this->guardianForUser($user);

    if (! $guardian) {
        return response()->json([
            'ok' => false,
            'message' => 'No hay tutor vinculado a esta cuenta.',
        ], 404);
    }

    $notificationRow = DB::table('notifications')
        ->where('school_id', $user->school_id)
        ->where('guardian_id', $guardian->id)
        ->where('id', $notification)
        ->first();

    if (! $notificationRow) {
        return response()->json([
            'ok' => false,
            'message' => 'Notificación no encontrada.',
        ], 404);
    }

    DB::table('notifications')
        ->where('id', $notification)
        ->where('guardian_id', $guardian->id)
        ->update([
            'status' => 'read',
            'read_at' => now(),
            'updated_at' => now(),
        ]);

    return response()->json([
        'ok' => true,
        'message' => 'Notificación marcada como leída.',
    ]);
}


    public function students(Request $request): JsonResponse
    {
        $user = $request->user();

        $guardian = $this->guardianForUser($user);

        if (! $guardian) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay tutor vinculado a esta cuenta.',
            ], 404);
        }

        $students = DB::table('student_guardians')
            ->join('students', 'students.id', '=', 'student_guardians.student_id')
            ->leftJoin('school_groups', 'school_groups.id', '=', 'students.current_group_id')
            ->leftJoin('academic_levels', 'academic_levels.id', '=', 'school_groups.academic_level_id')
            ->where('student_guardians.guardian_id', $guardian->id)
            ->where('student_guardians.status', 'active')
            ->where('student_guardians.can_view_attendance', true)
            ->select(
                'students.id',
                'students.student_code',
                'students.first_name',
                'students.last_name',
                'students.status',
                'students.photo_url',
                'school_groups.name as group_name',
                'academic_levels.name as level_name',
                'student_guardians.relationship',
                'student_guardians.can_receive_notifications',
                'student_guardians.can_authorize_exit'
            )
            ->orderBy('students.first_name')
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'student_code' => $student->student_code,
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'status' => $student->status,
                   'photo_url' => $student->photo_url ? asset($student->photo_url) : null,
                    'level' => $student->level_name,
                    'group' => $student->group_name,
                    'relationship' => $student->relationship,
                    'permissions' => [
                        'can_receive_notifications' => (bool) $student->can_receive_notifications,
                        'can_authorize_exit' => (bool) $student->can_authorize_exit,
                    ],
                ];
            });

        return response()->json([
            'ok' => true,
            'students' => $students,
        ]);
    }

    public function attendance(Request $request, int $student): JsonResponse
    {
        $user = $request->user();

        $guardian = $this->guardianForUser($user);

        if (! $guardian || ! $this->canAccessStudent($guardian->id, $student)) {
            return response()->json([
                'ok' => false,
                'message' => 'No tienes permiso para consultar este alumno.',
            ], 403);
        }

        $attendance = DB::table('daily_attendance')
            ->where('school_id', $user->school_id)
            ->where('student_id', $student)
            ->orderByDesc('date')
            ->limit(30)
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->date,
                    'status' => $row->attendance_status,
                    'entry_at' => $row->entry_at,
                    'exit_at' => $row->exit_at,
                    'minutes_late' => $row->minutes_late,
                ];
            });

        return response()->json([
            'ok' => true,
            'student_id' => $student,
            'attendance' => $attendance,
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $guardian = $this->guardianForUser($user);

        if (! $guardian) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay tutor vinculado a esta cuenta.',
            ], 404);
        }

        $notifications = DB::table('notifications')
            ->leftJoin('students', 'students.id', '=', 'notifications.student_id')
            ->where('notifications.school_id', $user->school_id)
            ->where('notifications.guardian_id', $guardian->id)
            ->select(
                'notifications.id',
                'notifications.type',
                'notifications.title',
                'notifications.body',
                'notifications.status',
                'notifications.sent_at',
                'notifications.read_at',
                'notifications.created_at',
                DB::raw("CONCAT(students.first_name, ' ', students.last_name) as student_name")
            )
            ->orderByDesc('notifications.id')
            ->limit(50)
            ->get();

        return response()->json([
            'ok' => true,
            'notifications' => $notifications,
        ]);
    }

    private function guardianForUser(object $user): ?object
    {
        return DB::table('guardians')
            ->where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    private function canAccessStudent(int $guardianId, int $studentId): bool
    {
        return DB::table('student_guardians')
            ->where('guardian_id', $guardianId)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->where('can_view_attendance', true)
            ->exists();
    }
}