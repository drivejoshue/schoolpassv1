<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $areas = DB::table('areas')
            ->leftJoin('campuses', 'campuses.id', '=', 'areas.campus_id')
            ->where('areas.school_id', $schoolId)
            ->select(
                'areas.*',
                'campuses.name as campus_name',
                DB::raw("(select count(*) from access_devices where access_devices.area_id = areas.id and access_devices.status = 'active') as active_devices_count")
            )
            ->orderBy('areas.name')
            ->get();

        return view('admin.areas.index', compact('areas'));
    }

    public function create(): View
    {
        $campuses = $this->campuses();

        return view('admin.areas.create', compact('campuses'));
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
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('areas', 'code')->where('school_id', $schoolId),
            ],
            'type' => ['required', Rule::in(['entrance', 'restricted', 'lab', 'storage', 'library', 'classroom', 'other'])],
            'affects_attendance' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $code = $data['code'] ?: Str::slug($data['name']);

        DB::table('areas')->insert([
            'school_id' => $schoolId,
            'campus_id' => $data['campus_id'],
            'name' => $data['name'],
            'code' => $code,
            'type' => $data['type'],
            'affects_attendance' => $request->boolean('affects_attendance'),
            'status' => $data['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.areas.index')
            ->with('success', 'Área registrada correctamente.');
    }

    public function edit(int $area): View
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $areaRow = DB::table('areas')
            ->where('school_id', $schoolId)
            ->where('id', $area)
            ->firstOrFail();

        $campuses = $this->campuses();

        return view('admin.areas.edit', compact('areaRow', 'campuses'));
    }

    public function update(Request $request, int $area): RedirectResponse
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $areaRow = DB::table('areas')
            ->where('school_id', $schoolId)
            ->where('id', $area)
            ->firstOrFail();

        $data = $request->validate([
            'campus_id' => [
                'required',
                'integer',
                Rule::exists('campuses', 'id')->where('school_id', $schoolId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('areas', 'code')
                    ->where('school_id', $schoolId)
                    ->ignore($areaRow->id),
            ],
            'type' => ['required', Rule::in(['entrance', 'restricted', 'lab', 'storage', 'library', 'classroom', 'other'])],
            'affects_attendance' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $code = $data['code'] ?: Str::slug($data['name']);

        DB::table('areas')
            ->where('id', $areaRow->id)
            ->where('school_id', $schoolId)
            ->update([
                'campus_id' => $data['campus_id'],
                'name' => $data['name'],
                'code' => $code,
                'type' => $data['type'],
                'affects_attendance' => $request->boolean('affects_attendance'),
                'status' => $data['status'],
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.areas.index')
            ->with('success', 'Área actualizada correctamente.');
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
}