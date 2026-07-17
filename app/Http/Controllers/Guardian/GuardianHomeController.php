<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GuardianHomeController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $guardian = DB::table('guardians')
            ->where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $guardian) {
            return view('guardian.home', [
                'guardian' => null,
                'students' => collect(),
                'notifications' => collect(),
            ]);
        }

        $students = DB::table('student_guardians')
            ->join('students', 'students.id', '=', 'student_guardians.student_id')
            ->leftJoin('school_groups', 'school_groups.id', '=', 'students.current_group_id')
            ->leftJoin('academic_levels', 'academic_levels.id', '=', 'school_groups.academic_level_id')
            ->where('student_guardians.guardian_id', $guardian->id)
            ->where('student_guardians.status', 'active')
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
                'student_guardians.can_authorize_exit',
                'student_guardians.can_view_attendance'
            )
            ->orderBy('students.first_name')
            ->get()
            ->map(function ($student) use ($user) {
                $lastAttendance = DB::table('daily_attendance')
                    ->where('school_id', $user->school_id)
                    ->where('student_id', $student->id)
                    ->orderByDesc('date')
                    ->first();

                $student->last_attendance = $lastAttendance;

                return $student;
            });

        $notifications = DB::table('notifications')
            ->leftJoin('students', 'students.id', '=', 'notifications.student_id')
            ->where('notifications.school_id', $user->school_id)
            ->where('notifications.guardian_id', $guardian->id)
            ->select(
                'notifications.*',
                DB::raw("CONCAT(students.first_name, ' ', students.last_name) as student_name")
            )
            ->orderByDesc('notifications.id')
            ->limit(10)
            ->get();

        return view('guardian.home', compact('guardian', 'students', 'notifications'));
    }
}