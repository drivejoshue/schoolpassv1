<?php

namespace App\Services\Notifications;

use App\Jobs\SendUserNotificationPush;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchoolNoticePushService
{
    /**
     * Publica el aviso y crea una notificación persistente por destinatario.
     * Es idempotente: un aviso con push_dispatched_at no se vuelve a enviar.
     *
     * @return array{queued: int, already_dispatched: bool}
     */
    public function publishAndQueue(int $schoolId, int $noticeId): array
    {
        return DB::transaction(function () use ($schoolId, $noticeId) {
            $notice = DB::table('school_notices')
                ->where('id', $noticeId)
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->first();

            if (! $notice) {
                throw new AuthorizationException('Aviso no disponible.');
            }

            DB::table('school_notices')
                ->where('id', $noticeId)
                ->where('school_id', $schoolId)
                ->update([
                    'status' => 'published',
                    'publish_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($notice->push_dispatched_at) {
                return [
                    'queued' => (int) ($notice->push_recipient_count ?? 0),
                    'already_dispatched' => true,
                ];
            }

            $recipients = $this->resolveRecipients($schoolId, $noticeId);
            $body = $this->notificationBody($notice);
            $queued = 0;

            foreach ($recipients as $recipient) {
                $notificationId = DB::table('notifications')->insertGetId([
                    'school_id' => $schoolId,
                    'guardian_id' => $recipient['guardian_id'],
                    'student_id' => null,
                    'user_id' => $recipient['user_id'],
                    'type' => 'school_notice',
                    'title' => (string) $notice->title,
                    'body' => $body,
                    'status' => 'pending',
                    'push_status' => 'pending',
                    'reference_type' => 'school_notice',
                    'reference_id' => $noticeId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                SendUserNotificationPush::dispatch($notificationId)->afterCommit();
                $queued++;
            }

            DB::table('school_notices')
                ->where('id', $noticeId)
                ->where('school_id', $schoolId)
                ->update([
                    'push_dispatched_at' => now(),
                    'push_recipient_count' => $queued,
                    'updated_at' => now(),
                ]);

            return [
                'queued' => $queued,
                'already_dispatched' => false,
            ];
        });
    }

    /**
     * @return Collection<int, array{user_id: int, guardian_id: ?int}>
     */
    private function resolveRecipients(int $schoolId, int $noticeId): Collection
    {
        $targets = DB::table('school_notice_targets')
            ->where('school_id', $schoolId)
            ->where('school_notice_id', $noticeId)
            ->get();

        if ($targets->isEmpty()) {
            $targets = collect([(object) [
                'target_type' => 'all_school',
                'target_id' => null,
            ]]);
        }

        $allSchool = $targets->contains(
            fn (object $target) => $target->target_type === 'all_school'
        );

        $groupIds = $targets
            ->where('target_type', 'group')
            ->pluck('target_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $studentIds = $targets
            ->where('target_type', 'student')
            ->pluck('target_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $guardianIds = $targets
            ->where('target_type', 'guardian')
            ->pluck('target_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $userIds = $targets
            ->where('target_type', 'user')
            ->pluck('target_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $recipients = collect();

        $guardianQuery = DB::table('guardians as g')
            ->join('student_guardians as sg', 'sg.guardian_id', '=', 'g.id')
            ->join('students as s', 's.id', '=', 'sg.student_id')
            ->join('users as u', 'u.id', '=', 'g.user_id')
            ->where('g.school_id', $schoolId)
            ->where('g.status', 'active')
            ->where('sg.status', 'active')
            ->where('sg.can_receive_notifications', true)
            ->where('s.school_id', $schoolId)
            ->where('s.status', 'active')
            ->where('u.school_id', $schoolId)
            ->where('u.status', 'active')
            ->where('u.role', 'guardian');

        if (! $allSchool) {
            $guardianQuery->where(function ($query) use (
                $groupIds,
                $studentIds,
                $guardianIds,
                $userIds
            ) {
                $hasCondition = false;

                if ($groupIds->isNotEmpty()) {
                    $query->whereIn('s.current_group_id', $groupIds->all());
                    $hasCondition = true;
                }

                if ($studentIds->isNotEmpty()) {
                    $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('s.id', $studentIds->all());
                    $hasCondition = true;
                }

                if ($guardianIds->isNotEmpty()) {
                    $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('g.id', $guardianIds->all());
                    $hasCondition = true;
                }

                if ($userIds->isNotEmpty()) {
                    $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('u.id', $userIds->all());
                    $hasCondition = true;
                }

                if (! $hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            });
        }

        $guardianQuery
            ->select(['u.id as user_id', 'g.id as guardian_id'])
            ->distinct()
            ->get()
            ->each(function (object $row) use ($recipients) {
                $recipients->push([
                    'user_id' => (int) $row->user_id,
                    'guardian_id' => (int) $row->guardian_id,
                ]);
            });

        // Alumnos con cuenta propia también pueden usar SchoolPass Family.
        $studentUserQuery = DB::table('students as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.school_id', $schoolId)
            ->where('s.status', 'active')
            ->where('u.school_id', $schoolId)
            ->where('u.status', 'active')
            ->where('u.role', 'student');

        if (! $allSchool) {
            $studentUserQuery->where(function ($query) use ($groupIds, $studentIds, $userIds) {
                $hasCondition = false;

                if ($groupIds->isNotEmpty()) {
                    $query->whereIn('s.current_group_id', $groupIds->all());
                    $hasCondition = true;
                }

                if ($studentIds->isNotEmpty()) {
                    $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('s.id', $studentIds->all());
                    $hasCondition = true;
                }

                if ($userIds->isNotEmpty()) {
                    $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('u.id', $userIds->all());
                    $hasCondition = true;
                }

                if (! $hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            });
        }

        $studentUserQuery
            ->select('u.id as user_id')
            ->distinct()
            ->get()
            ->each(function (object $row) use ($recipients) {
                $recipients->push([
                    'user_id' => (int) $row->user_id,
                    'guardian_id' => null,
                ]);
            });

        return $recipients
            ->filter(fn (array $recipient) => $recipient['user_id'] > 0)
            ->unique('user_id')
            ->values();
    }

    private function notificationBody(object $notice): string
    {
        $source = trim((string) ($notice->subtitle ?: $notice->body));
        $plain = preg_replace('/\s+/', ' ', strip_tags($source)) ?: 'Tienes un nuevo aviso escolar.';

        return Str::limit($plain, 180);
    }
}
