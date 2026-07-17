<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Notifications\SchoolNoticePushService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolNoticeController extends Controller
{
    public function index(Request $request): View
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
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%");
            });
        }

        $notices = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => DB::table('school_notices')->where('school_id', $user->school_id)->count(),
            'draft' => DB::table('school_notices')->where('school_id', $user->school_id)->where('status', 'draft')->count(),
            'published' => DB::table('school_notices')->where('school_id', $user->school_id)->where('status', 'published')->count(),
            'archived' => DB::table('school_notices')->where('school_id', $user->school_id)->where('status', 'archived')->count(),
        ];

        return view('admin.notices.index', [
            'notices' => $notices,
            'stats' => $stats,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $this->adminUserOrFail($request);

        return view('admin.notices.create', [
            'notice' => null,
            'targets' => collect(),
            'groups' => $this->groups($user->school_id),
            'students' => $this->students($user->school_id),
            'guardians' => $this->guardians($user->school_id),
        ]);
    }

    public function store(
        Request $request,
        SchoolNoticePushService $pushService
    ): RedirectResponse {
        $user = $this->adminUserOrFail($request);
        $data = $this->validateNotice($request);

        $bannerPath = null;

        if ($request->hasFile('banner')) {
            $stored = $request->file('banner')->store(
                'school_notices/school_' . $user->school_id,
                'public'
            );

            $bannerPath = '/storage/' . $stored;
        }

        $noticeId = DB::transaction(function () use ($request, $user, $data, $bannerPath) {
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

                'publish_at' => $data['publish_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'status' => $data['status'] ?? 'draft',

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncTargetsFromRequest(
                request: $request,
                schoolId: (int) $user->school_id,
                noticeId: $noticeId
            );

            return $noticeId;
        });

        $message = 'Aviso creado correctamente.';

        if (($data['status'] ?? 'draft') === 'published') {
            $pushResult = $pushService->publishAndQueue(
                schoolId: (int) $user->school_id,
                noticeId: $noticeId
            );

            $message .= $pushResult['already_dispatched']
                ? ' El push ya había sido procesado.'
                : ' Se encolaron ' . $pushResult['queued'] . ' notificaciones.';
        }

        return redirect()
            ->route('admin.notices.edit', $noticeId)
            ->with('success', $message);
    }

    public function edit(Request $request, int $notice): View
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
            ->get();

        return view('admin.notices.edit', [
            'notice' => $row,
            'targets' => $targets,
            'groups' => $this->groups($user->school_id),
            'students' => $this->students($user->school_id),
            'guardians' => $this->guardians($user->school_id),
        ]);
    }

    public function update(
        Request $request,
        int $notice,
        SchoolNoticePushService $pushService
    ): RedirectResponse {
        $user = $this->adminUserOrFail($request);

        $row = DB::table('school_notices')
            ->where('id', $notice)
            ->where('school_id', $user->school_id)
            ->first();

        if (! $row) {
            throw new AuthorizationException('Aviso no disponible.');
        }

        $data = $this->validateNotice($request);

        $updates = [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'header' => $data['header'] ?? null,
            'body' => $data['body'],
            'footer' => $data['footer'] ?? null,

            'banner_alt' => $data['banner_alt'] ?? null,

            'priority' => $data['priority'] ?? 'normal',
            'show_as_modal' => (bool) ($data['show_as_modal'] ?? false),
            'requires_ack' => (bool) ($data['requires_ack'] ?? false),

            'cta_label' => $data['cta_label'] ?? null,
            'cta_url' => $data['cta_url'] ?? null,

            'publish_at' => $data['publish_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'status' => $data['status'] ?? 'draft',

            'updated_at' => now(),
        ];

        if ($request->hasFile('banner')) {
            $stored = $request->file('banner')->store(
                'school_notices/school_' . $user->school_id,
                'public'
            );

            $updates['banner_path'] = '/storage/' . $stored;
        }

        DB::transaction(function () use ($request, $user, $notice, $updates) {
            DB::table('school_notices')
                ->where('id', $notice)
                ->where('school_id', $user->school_id)
                ->update($updates);

            DB::table('school_notice_targets')
                ->where('school_notice_id', $notice)
                ->delete();

            $this->syncTargetsFromRequest(
                request: $request,
                schoolId: (int) $user->school_id,
                noticeId: $notice
            );
        });

        $message = 'Aviso actualizado correctamente.';

        if (($data['status'] ?? 'draft') === 'published') {
            $pushResult = $pushService->publishAndQueue(
                schoolId: (int) $user->school_id,
                noticeId: $notice
            );

            $message .= $pushResult['already_dispatched']
                ? ' El push ya había sido procesado.'
                : ' Se encolaron ' . $pushResult['queued'] . ' notificaciones.';
        }

        return redirect()
            ->route('admin.notices.edit', $notice)
            ->with('success', $message);
    }

    public function publish(
        Request $request,
        int $notice,
        SchoolNoticePushService $pushService
    ): RedirectResponse {
        $user = $this->adminUserOrFail($request);

        $pushResult = $pushService->publishAndQueue(
            schoolId: (int) $user->school_id,
            noticeId: $notice
        );

        $message = $pushResult['already_dispatched']
            ? 'El aviso ya estaba publicado y su push ya fue procesado.'
            : 'Aviso publicado. Se encolaron '
                . $pushResult['queued']
                . ' notificaciones.';

        return back()->with('success', $message);
    }

    public function archive(Request $request, int $notice): RedirectResponse
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

        return back()->with('success', 'Aviso archivado.');
    }

    private function validateNotice(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:220'],
            'header' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string'],
            'footer' => ['nullable', 'string', 'max:220'],

            'banner' => ['nullable', 'image', 'max:4096'],
            'banner_alt' => ['nullable', 'string', 'max:160'],

            'priority' => ['required', Rule::in(['normal', 'important', 'urgent'])],
            'show_as_modal' => ['nullable', 'boolean'],
            'requires_ack' => ['nullable', 'boolean'],

            'cta_label' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'string', 'max:255'],

            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:publish_at'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],

            'target_scope' => ['required', Rule::in(['all_school', 'groups', 'students', 'guardians'])],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
            'guardian_ids' => ['nullable', 'array'],
            'guardian_ids.*' => ['integer'],
        ]);
    }

    private function syncTargetsFromRequest(Request $request, int $schoolId, int $noticeId): void
    {
        $scope = $request->input('target_scope', 'all_school');

        if ($scope === 'all_school') {
            $this->insertTarget($schoolId, $noticeId, 'all_school', null);
            return;
        }

        if ($scope === 'groups') {
            foreach ((array) $request->input('group_ids', []) as $id) {
                $this->insertTarget($schoolId, $noticeId, 'group', (int) $id);
            }
            return;
        }

        if ($scope === 'students') {
            foreach ((array) $request->input('student_ids', []) as $id) {
                $this->insertTarget($schoolId, $noticeId, 'student', (int) $id);
            }
            return;
        }

        if ($scope === 'guardians') {
            foreach ((array) $request->input('guardian_ids', []) as $id) {
                $this->insertTarget($schoolId, $noticeId, 'guardian', (int) $id);
            }
            return;
        }

        $this->insertTarget($schoolId, $noticeId, 'all_school', null);
    }

    private function insertTarget(int $schoolId, int $noticeId, string $type, ?int $id): void
    {
        DB::table('school_notice_targets')->insert([
            'school_id' => $schoolId,
            'school_notice_id' => $noticeId,
            'target_type' => $type,
            'target_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function groups(int $schoolId)
    {
        return DB::table('school_groups')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function students(int $schoolId)
    {
        return DB::table('students as s')
            ->leftJoin('school_groups as g', 'g.id', '=', 's.current_group_id')
            ->where('s.school_id', $schoolId)
            ->where('s.status', 'active')
            ->select([
                's.id',
                's.student_code',
                's.first_name',
                's.last_name',
                'g.name as group_name',
            ])
            ->orderBy('s.first_name')
            ->get();
    }

    private function guardians(int $schoolId)
    {
        return DB::table('guardians')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();
    }

    private function adminUserOrFail(Request $request): object
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['superadmin', 'school_admin', 'director'], true)) {
            throw new AuthorizationException('Usuario no autorizado.');
        }

        if (! $user->school_id) {
            throw new AuthorizationException('Usuario sin institución.');
        }

        return $user;
    }
}