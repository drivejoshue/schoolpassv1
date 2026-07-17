<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolCalendarController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $activeCycle = $this->activeCycle($schoolId);

        $filters = [
            'from' => $request->query('from', $activeCycle?->starts_on ?? now()->startOfMonth()->toDateString()),
            'to' => $request->query('to', $activeCycle?->ends_on ?? now()->endOfMonth()->toDateString()),
            'type' => $request->query('type'),
            'academic_cycle_id' => $request->query('academic_cycle_id', $activeCycle?->id),
        ];

        $days = DB::table('school_calendar_days')
            ->leftJoin('academic_cycles', 'academic_cycles.id', '=', 'school_calendar_days.academic_cycle_id')
            ->where('school_calendar_days.school_id', $schoolId)
            ->whereBetween('school_calendar_days.date', [$filters['from'], $filters['to']])
            ->when($filters['type'], function ($query, $type) {
                $query->where('school_calendar_days.type', $type);
            })
            ->when($filters['academic_cycle_id'], function ($query, $cycleId) {
                $query->where('school_calendar_days.academic_cycle_id', $cycleId);
            })
            ->select(
                'school_calendar_days.*',
                'academic_cycles.name as cycle_name'
            )
            ->orderBy('school_calendar_days.date')
            ->get();

        return view('admin.calendar.index', [
            'days' => $days,
            'filters' => $filters,
            'types' => $this->types(),
            'noClassTypes' => $this->noClassTypes(),
            'cycles' => $this->cycles($schoolId),
            'activeCycle' => $activeCycle,
        ]);
    }

    public function create(): View
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        return view('admin.calendar.create', [
            'dayRow' => null,
            'cycles' => $this->cycles($schoolId),
            'activeCycle' => $this->activeCycle($schoolId),
            'types' => $this->types(),
            'noClassTypes' => $this->noClassTypes(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $data = $this->validatedPeriodData($request, $schoolId);

        $from = Carbon::parse($data['date_from'])->startOfDay();
        $to = Carbon::parse($data['date_to'] ?: $data['date_from'])->startOfDay();

        if ($from->diffInDays($to) > 370) {
            return back()
                ->withErrors(['date_to' => 'El periodo no puede ser mayor a 370 días.'])
                ->withInput();
        }

        $createdOrUpdated = 0;

        DB::transaction(function () use ($schoolId, $data, $from, $to, &$createdOrUpdated) {
            $cursor = $from->copy();

            while ($cursor->lte($to)) {
                DB::table('school_calendar_days')->updateOrInsert(
                    [
                        'school_id' => $schoolId,
                        'date' => $cursor->toDateString(),
                    ],
                    [
                        'academic_cycle_id' => $data['academic_cycle_id'] ?: null,
                        'type' => $data['type'],
                        'title' => $data['title'],
                        'notes' => $data['notes'] ?? null,
                        'status' => $data['status'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $createdOrUpdated++;
                $cursor->addDay();
            }
        });

        return redirect()
            ->route('admin.calendar.index', [
                'academic_cycle_id' => $data['academic_cycle_id'],
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ])
            ->with('success', "Fechas registradas o actualizadas: {$createdOrUpdated}.");
    }

    public function edit(int $day): View
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $dayRow = DB::table('school_calendar_days')
            ->where('school_id', $schoolId)
            ->where('id', $day)
            ->firstOrFail();

        return view('admin.calendar.edit', [
            'dayRow' => $dayRow,
            'cycles' => $this->cycles($schoolId),
            'activeCycle' => $this->activeCycle($schoolId),
            'types' => $this->types(),
            'noClassTypes' => $this->noClassTypes(),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, int $day): RedirectResponse
    {
        $user = auth()->user();
        $schoolId = (int) $user->school_id;

        $dayRow = DB::table('school_calendar_days')
            ->where('school_id', $schoolId)
            ->where('id', $day)
            ->firstOrFail();

        $data = $this->validatedSingleData($request, $schoolId);

        $exists = DB::table('school_calendar_days')
            ->where('school_id', $schoolId)
            ->where('date', $data['date'])
            ->where('id', '!=', $dayRow->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['date' => 'Ya existe otro registro para esa fecha.'])
                ->withInput();
        }

        DB::table('school_calendar_days')
            ->where('id', $dayRow->id)
            ->where('school_id', $schoolId)
            ->update([
                'academic_cycle_id' => $data['academic_cycle_id'] ?: null,
                'date' => $data['date'],
                'type' => $data['type'],
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'],
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.calendar.index', [
                'academic_cycle_id' => $data['academic_cycle_id'],
                'from' => $data['date'],
                'to' => $data['date'],
            ])
            ->with('success', 'Fecha especial actualizada correctamente.');
    }

    private function validatedPeriodData(Request $request, int $schoolId): array
    {
        return $request->validate([
            'academic_cycle_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_cycles', 'id')->where('school_id', $schoolId),
            ],
            'date_from' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['required', Rule::in(array_keys($this->types()))],
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function validatedSingleData(Request $request, int $schoolId): array
    {
        return $request->validate([
            'academic_cycle_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_cycles', 'id')->where('school_id', $schoolId),
            ],
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys($this->types()))],
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function cycles(int $schoolId)
    {
        return DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->orderByDesc('is_active')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();
    }

    private function activeCycle(int $schoolId): ?object
    {
        return DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('status', 'active')
            ->first();
    }

    private function types(): array
    {
        return [
            'class_day' => 'Día de clase',
            'holiday' => 'Día festivo',
            'vacation' => 'Vacaciones',
            'suspension' => 'Suspensión',
            'technical_council' => 'Consejo técnico',
            'exam' => 'Examen',
            'event' => 'Evento escolar',
        ];
    }

    private function noClassTypes(): array
    {
        return [
            'holiday',
            'vacation',
            'suspension',
            'technical_council',
        ];
    }
}