<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyNoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);

        $studentIds = $this->studentIdsForGuardian($guardian);

        $noticeIds = $this->accessibleNoticeIds($guardian, $studentIds);

        $items = DB::table('school_notices as n')
            ->leftJoin('school_notice_reads as r', function ($join) use ($guardian) {
                $join->on('r.school_notice_id', '=', 'n.id')
                    ->where('r.user_id', '=', $guardian->user_id);
            })
            ->whereIn('n.id', $noticeIds)
            ->where('n.school_id', $guardian->school_id)
            ->where('n.status', 'published')
            ->where(function ($query) {
                $query->whereNull('n.publish_at')
                    ->orWhere('n.publish_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('n.expires_at')
                    ->orWhere('n.expires_at', '>=', now());
            })
            ->orderByRaw("FIELD(n.priority, 'urgent', 'important', 'normal')")
            ->orderByDesc('n.publish_at')
            ->orderByDesc('n.created_at')
            ->select([
                'n.*',
                'r.read_at',
                'r.acknowledged_at',
            ])
            ->limit(100)
            ->get()
            ->map(fn ($notice) => $this->mapNotice($notice));

        return response()->json([
            'ok' => true,
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    public function show(Request $request, int $notice): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);
        $studentIds = $this->studentIdsForGuardian($guardian);
        $noticeIds = $this->accessibleNoticeIds($guardian, $studentIds);

        if (! in_array($notice, $noticeIds, true)) {
            throw new AuthorizationException('Aviso no disponible.');
        }

        $row = DB::table('school_notices as n')
            ->leftJoin('school_notice_reads as r', function ($join) use ($guardian) {
                $join->on('r.school_notice_id', '=', 'n.id')
                    ->where('r.user_id', '=', $guardian->user_id);
            })
            ->where('n.id', $notice)
            ->where('n.school_id', $guardian->school_id)
            ->select([
                'n.*',
                'r.read_at',
                'r.acknowledged_at',
            ])
            ->first();

        if (! $row) {
            throw new AuthorizationException('Aviso no disponible.');
        }

        return response()->json([
            'ok' => true,
            'notice' => $this->mapNotice($row),
        ]);
    }

    public function modal(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);
        $studentIds = $this->studentIdsForGuardian($guardian);
        $noticeIds = $this->accessibleNoticeIds($guardian, $studentIds);

        $row = DB::table('school_notices as n')
            ->leftJoin('school_notice_reads as r', function ($join) use ($guardian) {
                $join->on('r.school_notice_id', '=', 'n.id')
                    ->where('r.user_id', '=', $guardian->user_id);
            })
            ->whereIn('n.id', $noticeIds)
            ->where('n.school_id', $guardian->school_id)
            ->where('n.status', 'published')
            ->where('n.show_as_modal', 1)
            ->whereNull('r.read_at')
            ->where(function ($query) {
                $query->whereNull('n.publish_at')
                    ->orWhere('n.publish_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('n.expires_at')
                    ->orWhere('n.expires_at', '>=', now());
            })
            ->orderByRaw("FIELD(n.priority, 'urgent', 'important', 'normal')")
            ->orderByDesc('n.publish_at')
            ->orderByDesc('n.created_at')
            ->select([
                'n.*',
                'r.read_at',
                'r.acknowledged_at',
            ])
            ->first();

        return response()->json([
            'ok' => true,
            'notice' => $row ? $this->mapNotice($row) : null,
        ]);
    }

    public function markAsRead(Request $request, int $notice): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);
        $this->authorizeNotice($guardian, $notice);

        $this->upsertRead(
            guardian: $guardian,
            noticeId: $notice,
            read: true,
            acknowledged: false
        );

        return response()->json([
            'ok' => true,
            'message' => 'Aviso marcado como leído.',
        ]);
    }

    public function acknowledge(Request $request, int $notice): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);
        $this->authorizeNotice($guardian, $notice);

        $this->upsertRead(
            guardian: $guardian,
            noticeId: $notice,
            read: true,
            acknowledged: true
        );

        return response()->json([
            'ok' => true,
            'message' => 'Aviso confirmado.',
        ]);
    }

    private function upsertRead(object $guardian, int $noticeId, bool $read, bool $acknowledged): void
    {
        $existing = DB::table('school_notice_reads')
            ->where('school_notice_id', $noticeId)
            ->where('user_id', $guardian->user_id)
            ->first();

        $payload = [
            'school_id' => $guardian->school_id,
            'school_notice_id' => $noticeId,
            'user_id' => $guardian->user_id,
            'guardian_id' => $guardian->id,
            'updated_at' => now(),
        ];

        if ($read) {
            $payload['read_at'] = now();
        }

        if ($acknowledged) {
            $payload['acknowledged_at'] = now();
        }

        if ($existing) {
            DB::table('school_notice_reads')
                ->where('id', $existing->id)
                ->update($payload);
        } else {
            $payload['created_at'] = now();

            DB::table('school_notice_reads')->insert($payload);
        }
    }

    private function authorizeNotice(object $guardian, int $noticeId): void
    {
        $studentIds = $this->studentIdsForGuardian($guardian);
        $noticeIds = $this->accessibleNoticeIds($guardian, $studentIds);

        if (! in_array($noticeId, $noticeIds, true)) {
            throw new AuthorizationException('Aviso no disponible.');
        }
    }

    private function accessibleNoticeIds(object $guardian, array $studentIds): array
    {
        $groupIds = DB::table('students')
            ->whereIn('id', $studentIds)
            ->where('school_id', $guardian->school_id)
            ->pluck('current_group_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return DB::table('school_notice_targets')
            ->where('school_id', $guardian->school_id)
            ->where(function ($query) use ($guardian, $studentIds, $groupIds) {
                $query->where('target_type', 'all_school')
                    ->orWhere(function ($q) use ($groupIds) {
                        $q->where('target_type', 'group')
                            ->whereIn('target_id', $groupIds);
                    })
                    ->orWhere(function ($q) use ($studentIds) {
                        $q->where('target_type', 'student')
                            ->whereIn('target_id', $studentIds);
                    })
                    ->orWhere(function ($q) use ($guardian) {
                        $q->where('target_type', 'guardian')
                            ->where('target_id', $guardian->id);
                    })
                    ->orWhere(function ($q) use ($guardian) {
                        $q->where('target_type', 'user')
                            ->where('target_id', $guardian->user_id);
                    });
            })
            ->pluck('school_notice_id')
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function studentIdsForGuardian(object $guardian): array
    {
        return DB::table('student_guardians')
            ->where('guardian_id', $guardian->id)
            ->where('status', 'active')
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

    private function mapNotice(object $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'subtitle' => $notice->subtitle,
            'header' => $notice->header,
            'body' => $notice->body,
            'footer' => $notice->footer,

            'banner_url' => $this->fullUrl($notice->banner_path),
            'banner_alt' => $notice->banner_alt,

            'priority' => $notice->priority,
            'show_as_modal' => (bool) $notice->show_as_modal,
            'requires_ack' => (bool) $notice->requires_ack,

            'cta_label' => $notice->cta_label,
            'cta_url' => $notice->cta_url,

            'publish_at' => $notice->publish_at,
            'expires_at' => $notice->expires_at,
            'created_at' => $notice->created_at,

            'read_at' => $notice->read_at,
            'acknowledged_at' => $notice->acknowledged_at,
            'is_read' => $notice->read_at !== null,
            'is_acknowledged' => $notice->acknowledged_at !== null,
        ];
    }

    private function fullUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url($path);
    }
}