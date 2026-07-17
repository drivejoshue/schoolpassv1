<?php

namespace App\Http\Controllers\Sysadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(
        Request $request,
    ): View {
        $filters = $request->validate([
            'q' => [
                'nullable',
                'string',
                'max:150',
            ],

            'school_id' => [
                'nullable',
                'integer',
                'exists:schools,id',
            ],

            'action' => [
                'nullable',
                'string',
                'max:120',
            ],

            'actor_type' => [
                'nullable',
                'string',
                'max:40',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],
        ]);

        $query = AuditLog::query()
            ->with([
                'school:id,name',
                'actor:id,name,email',
            ]);

        if (! empty($filters['q'])) {
            $search = trim($filters['q']);

            $query->where(
                function ($builder) use (
                    $search
                ): void {
                    $builder
                        ->where(
                            'action',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'entity_type',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'actor',
                            function ($actor) use (
                                $search
                            ): void {
                                $actor
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'email',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        )
                        ->orWhereHas(
                            'school',
                            function ($school) use (
                                $search
                            ): void {
                                $school->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                );
                            }
                        );
                }
            );
        }

        if (! empty($filters['school_id'])) {
            $query->where(
                'school_id',
                (int) $filters['school_id']
            );
        }

        if (! empty($filters['action'])) {
            $query->where(
                'action',
                $filters['action']
            );
        }

        if (! empty($filters['actor_type'])) {
            $query->where(
                'actor_type',
                $filters['actor_type']
            );
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate(
                'created_at',
                '>=',
                $filters['date_from']
            );
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate(
                'created_at',
                '<=',
                $filters['date_to']
            );
        }

        $logs = $query
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $schools = School::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $actorTypes = AuditLog::query()
            ->select('actor_type')
            ->distinct()
            ->orderBy('actor_type')
            ->pluck('actor_type');

        return view(
            'sysadmin.audit-logs.index',
            compact(
                'logs',
                'schools',
                'actions',
                'actorTypes',
                'filters'
            )
        );
    }

    public function show(
        AuditLog $auditLog,
    ): View {
        $auditLog->load([
            'school:id,name',
            'actor:id,name,email',
        ]);

        return view(
            'sysadmin.audit-logs.show',
            compact('auditLog')
        );
    }
}