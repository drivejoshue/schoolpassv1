<?php

return [
    'project_id' => env('FCM_PROJECT_ID'),

    // Preferir una ruta absoluta fuera de public/ y fuera del repositorio.
    'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),

    'timeout_seconds' => (int) env('FCM_TIMEOUT_SECONDS', 12),
    'connect_timeout_seconds' => (int) env('FCM_CONNECT_TIMEOUT_SECONDS', 5),

    'default_app_key' => env('FCM_DEFAULT_APP_KEY', 'schoolpass_family'),
];
