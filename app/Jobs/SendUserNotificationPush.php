<?php

namespace App\Jobs;

use App\Models\UserDeviceToken;
use App\Services\Firebase\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendUserNotificationPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;
    public int $timeout = 45;

    public function __construct(public readonly int $notificationId)
    {
        $this->onQueue('notifications');
    }

    /** @return array<int> */
    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(FcmService $fcm): void
    {
        $notification = DB::table('notifications')
            ->where('id', $this->notificationId)
            ->first();

        if (! $notification) {
            return;
        }

        DB::table('notifications')
            ->where('id', $notification->id)
            ->update([
                'push_status' => 'processing',
                'push_attempted_at' => now(),
                'push_error_code' => null,
                'push_error_message' => null,
                'updated_at' => now(),
            ]);

        $userId = $notification->user_id
            ? (int) $notification->user_id
            : null;

        if (! $userId && $notification->guardian_id) {
            $userId = DB::table('guardians')
                ->where('id', $notification->guardian_id)
                ->where('school_id', $notification->school_id)
                ->value('user_id');

            $userId = $userId ? (int) $userId : null;
        }

        if (! $userId) {
            $this->finishNotification(
                notificationId: (int) $notification->id,
                status: 'no_user',
                errorCode: 'GUARDIAN_WITHOUT_USER',
                errorMessage: 'El tutor no tiene una cuenta de usuario vinculada.'
            );

            return;
        }

        $devices = UserDeviceToken::query()
            ->where('school_id', $notification->school_id)
            ->where('user_id', $userId)
            ->where('app_key', 'schoolpass_family')
            ->where('is_active', true)
            ->where('notifications_enabled', true)
            ->get();

        if ($devices->isEmpty()) {
            $this->finishNotification(
                notificationId: (int) $notification->id,
                status: 'no_devices',
                errorCode: 'NO_ACTIVE_DEVICES',
                errorMessage: 'El usuario no tiene dispositivos activos registrados.'
            );

            return;
        }

        $successCount = 0;
        $lastErrorCode = null;
        $lastErrorMessage = null;

        foreach ($devices as $device) {
            $result = $fcm->sendToToken(
                token: $device->fcm_token,
                title: (string) $notification->title,
                body: (string) $notification->body,
                data: [
                    'type' => (string) $notification->type,
                    'notification_id' => (string) $notification->id,
                    'school_id' => (string) $notification->school_id,
                    'student_id' => $notification->student_id
                        ? (string) $notification->student_id
                        : '',
                    'route' => $this->routeFor((string) $notification->type),
                    'reference_type' => (string) ($notification->reference_type ?? ''),
                    'reference_id' => $notification->reference_id ? (string) $notification->reference_id : '',
                    'notice_id' => (($notification->reference_type ?? null) === 'school_notice' && $notification->reference_id)
                        ? (string) $notification->reference_id
                        : '',
                    'occurred_at' => (string) ($notification->created_at ?? now()->toIso8601String()),
                ],
                channelId: $this->channelFor((string) $notification->type)
            );

            if ($result['ok']) {
                $successCount++;

                $device->forceFill([
                    'last_success_at' => now(),
                    'last_error_at' => null,
                    'last_error_code' => null,
                    'last_seen_at' => now(),
                ])->save();

                continue;
            }

            $lastErrorCode = $result['error_code'];
            $lastErrorMessage = $result['error_message'];

            $updates = [
                'last_error_at' => now(),
                'last_error_code' => $lastErrorCode,
                'last_seen_at' => now(),
            ];

            if ($result['invalid_token']) {
                $updates['is_active'] = false;
                $updates['revoked_at'] = now();
            }

            $device->forceFill($updates)->save();
        }

        if ($successCount > 0) {
            $this->finishNotification(
                notificationId: (int) $notification->id,
                status: 'sent',
                sent: true
            );

            return;
        }

        $this->finishNotification(
            notificationId: (int) $notification->id,
            status: 'failed',
            errorCode: $lastErrorCode ?: 'FCM_SEND_FAILED',
            errorMessage: $lastErrorMessage ?: 'No se pudo entregar la notificación.'
        );
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('notifications')
            ->where('id', $this->notificationId)
            ->update([
                'push_status' => 'failed',
                'push_attempted_at' => now(),
                'push_error_code' => $exception ? class_basename($exception) : 'JOB_FAILED',
                'push_error_message' => $exception?->getMessage(),
                'updated_at' => now(),
            ]);
    }

    private function channelFor(string $type): string
    {
        return match ($type) {
            'entry', 'exit', 'late', 'absence', 'early_exit' => 'schoolpass_attendance',
            'school_notice' => 'schoolpass_notices',
            default => 'schoolpass_general_v2',
        };
    }


    private function routeFor(string $type): string
    {
        return match ($type) {
            'school_notice' => 'notices',
            default => 'activity',
        };
    }

    private function finishNotification(
        int $notificationId,
        string $status,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        bool $sent = false
    ): void {
        DB::table('notifications')
            ->where('id', $notificationId)
            ->update([
                'push_status' => $status,
                'push_sent_at' => $sent ? now() : null,
                'push_error_code' => $errorCode,
                'push_error_message' => $errorMessage,
                'updated_at' => now(),
            ]);
    }
}
