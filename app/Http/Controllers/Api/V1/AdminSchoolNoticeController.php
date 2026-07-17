<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Notifications\SchoolNoticePushService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminSchoolNoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->adminUserOrFail($request);

        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $query = DB::table('school_notices')
            ->where('school_id', $user->school_id)
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $items = $query
            ->limit(100)
            ->get()
            ->map(fn ($notice) => $this->mapAdminNotice($notice));

        return response()->json([
            'ok' => true,
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    public function show(Request $request, int $notice): JsonResponse
    {
        $user = $this->adminUserOrFail($request);

        $row = DB::table('school_notices')
            ->where('id', $notice)
            ->where('school_id', $user->school_id)
            ->first();

        if (! $row) {
            throw new AuthorizationException('Aviso no disponible.');
        }

        $targets = DB::table('school_notice_targets')
            ->where('school_notice_id', $notice)
            ->orderBy('target_type')
            ->get()
            ->map(fn ($target) => [
                'id' => $target->id,
                'type' => $target->target_type,
                'id_value' => $target->target_id,
            ]);

        return response()->json([
            'ok' => true,
            'notice' => $this->mapAdminNotice($row),
            'targets' => $targets,
        ]);
    }

    public function store(Request $request, SchoolNoticePushService $pushService): JsonResponse
    {
        $user = $this->adminUserOrFail($request);

        $data = $this->validateNotice($request);

        $bannerPath = null;

        if ($request->hasFile('banner')) {
            $bannerPath = $request->file('banner')->store(
                'school_notices/school_' . $user->school_id,
                'public'
            );
            $bannerPath = '/storage/' . $bannerPath;
        }

        $noticeId = DB::transaction(function () use ($data, $user, $bannerPath) {
            $noticeId = DB::table('school_notices')->insertGetId([
                'school_id' => $user->school_id,
                'created_by_user_id' => $user->id,

                'title' => $data['title'],
                'subtitle' => $data['subtitle'] ?? null,
                'header' => $data['header'] ?? null,
                'body' => $data['body'],
                'footer' => $data['footer'] ?? null,

                'banner_path' => $bannerPath,
                'banner_alt' => $data['banner_alt'] ?? null,

                'priority' => $data['priority'] ?? 'normal',
                'show_as_modal' => (bool) ($data['show_as_modal'] ?? false),
                'requires_ack' => (bool) ($data['requires_ack'] ?? false),

                'cta_label' => $data['cta_label'] ?? null,
                'cta_url' => $data['cta_url'] ?? null,

                'publish_at' => $data['publish_at'] ?? now(),
                'expires_at' => $data['expires_at'] ?? null,
                'status' => $data['status'] ?? 'draft',

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncTargets(
                schoolId: $user->school_id,
                noticeId: $noticeId,
                targets: $data['targets'] ?? [
                    ['type' => 'all_school', 'id' => null],
                ]
            );

            return $noticeId;
        });

        $pushResult = null;

        if (($data['status'] ?? 'draft') === 'published') {
            $pushResult = $pushService->publishAndQueue((int) $user->school_id, $noticeId);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Aviso creado correctamente.',
            'notice_id' => $noticeId,
            'push' => $pushResult,
        ], 201);
    }

    public function update(Request $request, int $notice, SchoolNoticePushService $pushService): JsonResponse
    {
        $user = $this->adminUserOrFail($request);

        $row = DB::table('school_notices')
            ->where('id', $notice)
            ->where('school_id', $user->school_id)
            ->first();

        if (! $row) {
            throw new AuthorizationException('Aviso no disponible.');
        }

        $data = $this->validateNotice($request, partial: true);

        $updates = [];

        foreach ([
            'title',
            'subtitle',
            'header',
            'body',
            'footer',
            'banner_alt',
            'priority',
            'cta_label',
            'cta_url',
            'publish_at',
            'expires_at',
            'status',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('show_as_modal', $data)) {
            $updates['show_as_modal'] = (bool) $data['show_as_modal'];
        }

        if (array_key_exists('requires_ack', $data)) {
            $updates['requires_ack'] = (bool) $data['requires_ack'];
        }

        if ($request->hasFile('banner')) {
            $bannerPath = $request->file('banner')->store(
                'school_notices/school_' . $user->school_id,
                'public'
            );

            $updates['banner_path'] = '/storage/' . $bannerPath;
        }

        $updates['updated_at'] = now();

        DB::transaction(function () use ($notice, $user, $data, $updates) {
            DB::table('school_notices')
                ->where('id', $notice)
                ->where('school_id', $user->school_id)
                ->update($updates);

            if (array_key_exists('targets', $data)) {
                DB::table('school_notice_targets')
                    ->where('school_notice_id', $notice)
                    ->delete();

                $this->syncTargets(
                    schoolId: $user->school_id,
                    noticeId: $notice,
                    targets: $data['targets']
                );
            }
        });

        $pushResult = null;

        if (($data['status'] ?? null) === 'published') {
            $pushResult = $pushService->publishAndQueue((int) $user->school_id, $notice);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Aviso actualizado correctamente.',
            'notice_id' => $notice,
            'push' => $pushResult,
        ]);
    }

    public function publish(
        Request $request,
        int $notice,
        SchoolNoticePushService $pushService
    ): JsonResponse {
        $user = $this->adminUserOrFail($request);

        $pushResult = $pushService->publishAndQueue(
            schoolId: (int) $user->school_id,
            noticeId: $notice
        );

        return response()->json([
            'ok' => true,
            'message' => $pushResult['already_dispatched']
                ? 'El aviso ya estaba publicado y su push ya fue procesado.'
                : 'Aviso publicado y notificaciones encoladas.',
            'push' => $pushResult,
        ]);
    }

    public function archive(Request $request, int $notice): JsonResponse
    {
        $user = $this->adminUserOrFail($request);

        DB::table('school_notices')
            ->where('id', $notice)
            ->where('school_id', $user->school_id)
            ->update([
                'status' => 'archived',
                'archived_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'message' => 'Aviso archivado.',
        ]);
    }

    private function validateNotice(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:220'],
            'header' => ['nullable', 'string', 'max:180'],
            'body' => [$required, 'string'],
            'footer' => ['nullable', 'string', 'max:220'],

            'banner' => ['nullable', 'image', 'max:4096'],
            'banner_alt' => ['nullable', 'string', 'max:160'],

            'priority' => ['nullable', Rule::in(['normal', 'important', 'urgent'])],
            'show_as_modal' => ['nullable', 'boolean'],
            'requires_ack' => ['nullable', 'boolean'],

            'cta_label' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'string', 'max:255'],

            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:publish_at'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],

            'targets' => ['nullable', 'array'],
            'targets.*.type' => [
                'required_with:targets',
                Rule::in(['all_school', 'group', 'student', 'guardian', 'user']),
            ],
            'targets.*.id' => ['nullable', 'integer'],
        ]);
    }

    private function syncTargets(int $schoolId, int $noticeId, array $targets): void
    {
        if (empty($targets)) {
            $targets = [
                ['type' => 'all_school', 'id' => null],
            ];
        }

        foreach ($targets as $target) {
            $type = $target['type'] ?? 'all_school';
            $id = $target['id'] ?? null;

            if ($type === 'all_school') {
                $id = null;
            }

            DB::table('school_notice_targets')->insert([
                'school_id' => $schoolId,
                'school_notice_id' => $noticeId,
                'target_type' => $type,
                'target_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function mapAdminNotice(object $notice): array
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
            'status' => $notice->status,
            'created_at' => $notice->created_at,
            'updated_at' => $notice->updated_at,
        ];
    }

    private function adminUserOrFail(Request $request): object
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['superadmin', 'school_admin', 'director'], true)) {
            throw new AuthorizationException('Usuario no autorizado para administrar avisos.');
        }

        if (! $user->school_id) {
            throw new AuthorizationException('Usuario sin institución.');
        }

        return $user;
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