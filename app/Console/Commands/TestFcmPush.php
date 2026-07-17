<?php

namespace App\Console\Commands;

use App\Models\UserDeviceToken;
use App\Services\Firebase\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestFcmPush extends Command
{
    protected $signature = 'schoolpass:fcm:test
        {user : ID o correo del usuario}
        {--title=SchoolPass listo}
        {--body=Notificación de prueba enviada desde Laravel}';

    protected $description = 'Envía una notificación FCM de prueba a los dispositivos activos de un usuario.';

    public function handle(FcmService $fcm): int
    {
        $userInput = (string) $this->argument('user');

        $user = DB::table('users')
            ->when(
                ctype_digit($userInput),
                fn ($query) => $query->where('id', (int) $userInput),
                fn ($query) => $query->where('email', $userInput)
            )
            ->first();

        if (! $user) {
            $this->error('Usuario no encontrado.');
            return self::FAILURE;
        }

        $devices = UserDeviceToken::query()
            ->where('user_id', $user->id)
            ->where('app_key', 'schoolpass_family')
            ->where('is_active', true)
            ->where('notifications_enabled', true)
            ->get();

        if ($devices->isEmpty()) {
            $this->error('El usuario no tiene dispositivos Family activos registrados.');
            return self::FAILURE;
        }

        $success = 0;

        foreach ($devices as $device) {
            $result = $fcm->sendToToken(
                token: $device->fcm_token,
                title: (string) $this->option('title'),
                body: (string) $this->option('body'),
                data: [
                    'type' => 'test',
                    'route' => 'home',
                    'user_id' => (string) $user->id,
                ],
                channelId: 'schoolpass_general_v2'
            );

            if ($result['ok']) {
                $success++;
                $this->info("OK device #{$device->id}: {$result['message_id']}");
                continue;
            }

            $this->error(
                "ERROR device #{$device->id}: {$result['error_code']} - {$result['error_message']}"
            );
        }

        return $success > 0 ? self::SUCCESS : self::FAILURE;
    }
}
