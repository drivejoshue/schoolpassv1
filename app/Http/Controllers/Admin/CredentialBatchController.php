<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CredentialBatchController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $filters = [
            'group_id' => $request->query('group_id'),
            'credential_status' => $request->query('credential_status'),
            'search' => trim((string) $request->query('search', '')),
        ];

        $students = DB::table('students')
            ->leftJoin('school_groups', 'school_groups.id', '=', 'students.current_group_id')
            ->leftJoin('academic_levels', 'academic_levels.id', '=', 'school_groups.academic_level_id')
            ->leftJoin('student_credentials', function ($join) {
                $join->on('student_credentials.student_id', '=', 'students.id')
                    ->where('student_credentials.status', '=', 'active');
            })
            ->where('students.school_id', $schoolId)
            ->where('students.status', 'active')
            ->when($filters['group_id'], function ($query, $groupId) {
                $query->where('students.current_group_id', $groupId);
            })
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($q) use ($search) {
                    $q->where('students.first_name', 'like', "%{$search}%")
                        ->orWhere('students.last_name', 'like', "%{$search}%")
                        ->orWhere('students.student_code', 'like', "%{$search}%")
                        ->orWhere('student_credentials.public_code', 'like', "%{$search}%");
                });
            })
            ->select(
                'students.id',
                'students.student_code',
                'students.first_name',
                'students.last_name',
                'students.photo_url',
                'school_groups.name as group_name',
                'academic_levels.name as level_name',
                'student_credentials.id as credential_id',
                'student_credentials.public_code',
                'student_credentials.issued_at'
            )
            ->orderBy('academic_levels.sort_order')
            ->orderBy('school_groups.name')
            ->orderBy('students.first_name')
            ->get();

        if ($filters['credential_status'] === 'with') {
            $students = $students->filter(fn ($student) => ! empty($student->credential_id))->values();
        }

        if ($filters['credential_status'] === 'without') {
            $students = $students->filter(fn ($student) => empty($student->credential_id))->values();
        }

        return view('admin.credentials.index', [
            'students' => $students,
            'groups' => $this->groups($schoolId),
            'filters' => $filters,
        ]);
    }

    public function generateMissing(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $data = $request->validate([
            'group_id' => ['nullable', 'integer'],
        ]);

        $query = DB::table('students')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('student_credentials')
                    ->whereColumn('student_credentials.student_id', 'students.id')
                    ->where('student_credentials.status', 'active');
            });

        if (! empty($data['group_id'])) {
            $query->where('current_group_id', $data['group_id']);
        }

        $students = $query->select('id', 'student_code')->get();

        $created = 0;

        DB::transaction(function () use ($students, $schoolId, &$created) {
            foreach ($students as $student) {
                $token = 'QR-' . $student->student_code . '-' . Str::upper(Str::random(8));

                DB::table('student_credentials')->insert([
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'type' => 'qr',
                    'token_hash' => hash('sha256', $token),
                    'public_code' => $token,
                    'status' => 'active',
                    'issued_at' => now(),
                    'revoked_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;
            }
        });

        return back()->with('success', "Credenciales generadas: {$created}.");
    }

   public function print(Request $request): View
{
    $user = auth()->user();
    $schoolId = (int) $user->school_id;

    $groupId = $request->query('group_id');
    $studentId = $request->query('student_id');

    $students = DB::table('students')
        ->join('school_groups', 'school_groups.id', '=', 'students.current_group_id')
        ->leftJoin('academic_levels', 'academic_levels.id', '=', 'school_groups.academic_level_id')
        ->join('student_credentials', function ($join) {
            $join->on('student_credentials.student_id', '=', 'students.id')
                ->where('student_credentials.status', '=', 'active');
        })
        ->where('students.school_id', $schoolId)
        ->where('students.status', 'active')
        ->when($groupId, function ($query, $groupId) {
            $query->where('students.current_group_id', $groupId);
        })
        ->when($studentId, function ($query, $studentId) {
            $query->where('students.id', $studentId);
        })
        ->select(
            'students.id',
            'students.student_code',
            'students.first_name',
            'students.last_name',
            'students.photo_url',
            'school_groups.name as group_name',
            'academic_levels.name as level_name',
            'student_credentials.public_code'
        )
        ->orderBy('academic_levels.sort_order')
        ->orderBy('school_groups.name')
        ->orderBy('students.first_name')
        ->get();

    $groupRow = null;

    if ($groupId) {
        $groupRow = DB::table('school_groups')
            ->where('school_id', $schoolId)
            ->where('id', $groupId)
            ->first();
    }

    return view('admin.credentials.print', [
        'students' => $students,
        'groupRow' => $groupRow,
        'isIndividual' => $studentId !== null,
    ]);
}

    private function groups(int $schoolId)
    {
        return DB::table('school_groups')
            ->leftJoin('academic_levels', 'academic_levels.id', '=', 'school_groups.academic_level_id')
            ->where('school_groups.school_id', $schoolId)
            ->where('school_groups.status', 'active')
            ->select(
                'school_groups.id',
                'school_groups.name',
                'academic_levels.name as level_name'
            )
            ->orderBy('academic_levels.sort_order')
            ->orderBy('school_groups.name')
            ->get();
    }
}