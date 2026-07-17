<?php

namespace App\Services\Firebase;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class FcmService
{
    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private Client $client;
    private string $projectId;

    public function __construct()
    {
        $this->projectId = trim((string) config('fcm.project_id'));

        if ($this->projectId === '') {
            throw new RuntimeException('FCM_PROJECT_ID no está configurado.');
        }

        $credentialsPath = trim((string) config('fcm.credentials'));

        if ($credentialsPath === '') {
            throw new RuntimeException('GOOGLE_APPLICATION_CREDENTIALS no está configurado.');
        }

        $resolvedCredentialsPath = $this->resolveCredentialsPath($credentialsPath);

        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $resolvedCredentialsPath);
        $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] = $resolvedCredentialsPath;
        $_SERVER['GOOGLE_APPLICATION_CREDENTIALS'] = $resolvedCredentialsPath;

        $middleware = ApplicationDefaultCredentials::getMiddleware([
            self::MESSAGING_SCOPE,
        ]);

        $stack = HandlerStack::create();
        $stack->push($middleware);

        $this->client = new Client([
            'handler' => $stack,
            'auth' => 'google_auth',
            'base_uri' => 'https://fcm.googleapis.com',
            'timeout' => (float) config('fcm.timeout_seconds', 12),
            'connect_timeout' => (float) config('fcm.connect_timeout_seconds', 5),
            'http_errors' => true,
        ]);
    }

    /**
     * @param array<string, scalar|null> $data
     * @return array{ok: bool, message_id: ?string, error_code: ?string, error_message: ?string, invalid_token: bool}
     */
    public function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data = [],
        string $channelId = 'schoolpass_general_v2'
    ): array {
        $token = trim($token);

        if ($token === '') {
            throw new InvalidArgumentException('El token FCM está vacío.');
        }

        $normalizedData = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalizedData[(string) $key] = is_bool($value)
                ? ($value ? '1' : '0')
                : (string) $value;
        }

     $payload = [
    'message' => [
        'token' => $token,

        /*
         * Este bloque permite que Google Play Services muestre
         * la notificación cuando la app está en segundo plano.
         */
        'notification' => [
            'title' => $title,
            'body' => $body,
        ],

        /*
         * Todas las claves deben ser string.
         * MainActivity las recibe al tocar la notificación.
         */
        'data' => $normalizedData,

        'android' => [
            /*
             * Prioridad de ENTREGA.
             * FCM intentará despertar el dispositivo cuando sea necesario.
             */
            'priority' => 'HIGH',

            /*
             * Conserva la notificación hasta 24 horas si el teléfono
             * está temporalmente sin conexión.
             */
            'ttl' => '86400s',

            'notification' => [
                /*
                 * Debe coincidir exactamente con el canal creado
                 * en NotificationChannels.kt.
                 */
                'channel_id' => $channelId,

                /*
                 * Nombre del recurso drawable, sin extensión.
                 */
                'icon' => 'ic_notification_schoolpass',

                'sound' => 'default',
                'default_vibrate_timings' => true,

                /*
                 * Prioridad VISUAL de la notificación.
                 */
                'notification_priority' => 'PRIORITY_HIGH',

                /*
                 * Permite mostrar el contenido en la pantalla bloqueada.
                 */
                'visibility' => 'PUBLIC',
            ],
        ],
    ],
];

        try {
            $response = $this->client->post(
                '/v1/projects/' . rawurlencode($this->projectId) . '/messages:send',
                ['json' => $payload]
            );

            $decoded = json_decode((string) $response->getBody(), true);

            return [
                'ok' => true,
                'message_id' => is_array($decoded) ? ($decoded['name'] ?? null) : null,
                'error_code' => null,
                'error_message' => null,
                'invalid_token' => false,
            ];
        } catch (RequestException $e) {
            return $this->requestFailure($e);
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message_id' => null,
                'error_code' => class_basename($e),
                'error_message' => $e->getMessage(),
                'invalid_token' => false,
            ];
        }
    }

    /**
     * @return array{ok: bool, message_id: ?string, error_code: ?string, error_message: ?string, invalid_token: bool}
     */
    private function requestFailure(RequestException $e): array
    {
        $response = $e->getResponse();
        $decoded = null;

        if ($response) {
            $decoded = json_decode((string) $response->getBody(), true);
        }

        $status = is_array($decoded)
            ? data_get($decoded, 'error.status')
            : null;

        $detailCode = is_array($decoded)
            ? data_get($decoded, 'error.details.0.errorCode')
            : null;

        $errorCode = (string) ($detailCode ?: $status ?: ($response?->getStatusCode() ?? 'FCM_REQUEST_FAILED'));
        $errorMessage = is_array($decoded)
            ? (string) data_get($decoded, 'error.message', $e->getMessage())
            : $e->getMessage();

        $invalidToken = in_array($errorCode, [
            'UNREGISTERED',
            'INVALID_ARGUMENT',
            'SENDER_ID_MISMATCH',
        ], true);

        return [
            'ok' => false,
            'message_id' => null,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'invalid_token' => $invalidToken,
        ];
    }

    private function resolveCredentialsPath(string $configuredPath): string
    {
        $candidate = $configuredPath;

        if (! str_starts_with($candidate, '/')
            && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $candidate)) {
            $candidate = base_path($candidate);
        }

        $realPath = realpath($candidate);

        if ($realPath === false || ! is_file($realPath)) {
            throw new RuntimeException(
                'No se encontró el archivo de credenciales Firebase en: ' . $candidate
            );
        }

        return $realPath;
    }
}
