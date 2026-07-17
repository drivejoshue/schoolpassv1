<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AreaAccessRuleController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $rules = DB::table('area_access_rules')
            ->join('areas', 'areas.id', '=', 'area_access_rules.area_id')
            ->where('area_access_rules.school_id', $schoolId)
            ->select(
                'area_access_rules.*',
                'areas.name as area_name',
                'areas.type as area_type'
            )
            ->orderBy('areas.name')
            ->orderBy('area_access_rules.applies_to_type')
            ->get()
            ->map(function ($rule) {
                $rule->target_name = $this->targetName($rule);
                return $rule;
            });

        return view('admin.area_rules.index', compact('rules'));
    }

    public function create(): View
    {
        return view('admin.area_rules.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $data = $this->validatedData($request, $schoolId);

        DB::table('area_access_rules')->insert([
            'school_id' => $schoolId,
            'area_id' => $data['area_id'],
            'applies_to_type' => $data['applies_to_type'],
            'applies_to_id' => $data['applies_to_id'],
            'role_name' => null,
            'weekday' => $data['weekday'] ?: null,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
            'status' => $data['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.area-rules.index')
            ->with('success', 'Regla de acceso registrada correctamente.');
    }

    public function edit(int $rule): View
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $ruleRow = DB::table('area_access_rules')
            ->where('school_id', $schoolId)
            ->where('id', $rule)
            ->firstOrFail();

        return view('admin.area_rules.edit', array_merge(
            $this->formData(),
            compact('ruleRow')
        ));
    }

    public function update(Request $request, int $rule): RedirectResponse
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $ruleRow = DB::table('area_access_rules')
            ->where('school_id', $schoolId)
            ->where('id', $rule)
            ->firstOrFail();

        $data = $this->validatedData($request, $schoolId);

        DB::table('area_access_rules')
            ->where('id', $ruleRow->id)
            ->where('school_id', $schoolId)
            ->update([
                'area_id' => $data['area_id'],
                'applies_to_type' => $data['applies_to_type'],
                'applies_to_id' => $data['applies_to_id'],
                'role_name' => null,
                'weekday' => $data['weekday'] ?: null,
                'starts_at' => $data['starts_at'] ?: null,
                'ends_at' => $data['ends_at'] ?: null,
                'status' => $data['status'],
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.area-rules.index')
            ->with('success', 'Regla de acceso actualizada correctamente.');
    }

    private function validatedData(Request $request, int $schoolId): array
    {
        $data = $request->validate([
            'area_id' => [
                'required',
                'integer',
                Rule::exists('areas', 'id')->where('school_id', $schoolId),
            ],
            'applies_to_type' => ['required', Rule::in(['group', 'student'])],
            'applies_to_id' => ['required', 'integer'],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($data['applies_to_type'] === 'group') {
            $exists = DB::table('school_groups')
                ->where('school_id', $schoolId)
                ->where('id', $data['applies_to_id'])
                ->exists();

            if (! $exists) {
                abort(422, 'El grupo seleccionado no pertenece a la institución.');
            }
        }

        if ($data['applies_to_type'] === 'student') {
            $exists = DB::table('students')
                ->where('school_id', $schoolId)
                ->where('id', $data['applies_to_id'])
                ->exists();

            if (! $exists) {
                abort(422, 'El alumno seleccionado no pertenece a la institución.');
            }
        }

        if (($data['starts_at'] && ! $data['ends_at']) || (! $data['starts_at'] && $data['ends_at'])) {
            abort(422, 'Si defines horario, debes capturar inicio y fin.');
        }

        if ($data['starts_at'] && $data['ends_at'] && $data['starts_at'] > $data['ends_at']) {
            abort(422, 'La hora de inicio no puede ser mayor a la hora de fin.');
        }

        return $data;
    }

    private function formData(): array
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $areas = DB::table('areas')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $groups = DB::table('school_groups')
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

        $students = DB::table('students')
            ->leftJoin('school_groups', 'school_groups.id', '=', 'students.current_group_id')
            ->where('students.school_id', $schoolId)
            ->where('students.status', 'active')
            ->select(
                'students.id',
                'students.first_name',
                'students.last_name',
                'students.student_code',
                'school_groups.name as group_name'
            )
            ->orderBy('students.first_name')
            ->get();

        $weekdays = [
            '' => 'Todos los días',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        return compact('areas', 'groups', 'students', 'weekdays');
    }

    private function targetName(object $rule): string
    {
        if ($rule->applies_to_type === 'group') {
            $group = DB::table('school_groups')
                ->leftJoin('academic_levels', 'academic_levels.id', '=', 'school_groups.academic_level_id')
                ->where('school_groups.id', $rule->applies_to_id)
                ->select('school_groups.name', 'academic_levels.name as level_name')
                ->first();

            return $group
                ? trim(($group->level_name ? $group->level_name . ' · ' : '') . $group->name)
                : 'Grupo no encontrado';
        }

        if ($rule->applies_to_type === 'student') {
            $student = DB::table('students')
                ->where('id', $rule->applies_to_id)
                ->first();

            return $student
                ? trim($student->first_name . ' ' . $student->last_name . ' · ' . $student->student_code)
                : 'Alumno no encontrado';
        }

        return 'Destino no válido';
    }
}