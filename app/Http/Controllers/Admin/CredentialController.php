<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CredentialController extends Controller
{
    public function store(int $student): RedirectResponse
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $studentRow = DB::table('students')
            ->where('school_id', $schoolId)
            ->where('id', $student)
            ->firstOrFail();

        $token = 'QR-' . strtoupper($studentRow->student_code) . '-' . strtoupper(Str::random(8));

        DB::table('student_credentials')->insert([
            'school_id' => $schoolId,
            'student_id' => $student,
            'type' => 'qr',
            'token_hash' => hash('sha256', $token),
            'public_code' => $token,
            'status' => 'active',
            'issued_at' => now(),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', 'Credencial QR generada correctamente.');
    }

    public function revoke(int $credential): RedirectResponse
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $credentialRow = DB::table('student_credentials')
            ->where('school_id', $schoolId)
            ->where('id', $credential)
            ->firstOrFail();

        DB::table('student_credentials')
            ->where('id', $credential)
            ->where('school_id', $schoolId)
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_reason' => 'Revocada desde panel administrativo',
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.students.show', $credentialRow->student_id)
            ->with('success', 'Credencial revocada correctamente.');
    }
}