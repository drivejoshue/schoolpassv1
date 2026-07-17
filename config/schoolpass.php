<?php

return [

    'license' => [
        'grace_days' => (int) env(
            'SCHOOLPASS_LICENSE_GRACE_DAYS',
            7
        ),

        'info_warning_days' => (int) env(
            'SCHOOLPASS_LICENSE_INFO_DAYS',
            30
        ),

        'modal_warning_days' => (int) env(
            'SCHOOLPASS_LICENSE_MODAL_DAYS',
            15
        ),

        'critical_warning_days' => (int) env(
            'SCHOOLPASS_LICENSE_CRITICAL_DAYS',
            7
        ),

        'support_email' => env(
            'SCHOOLPASS_LICENSE_SUPPORT_EMAIL',
            'soporte@schoolpass.local'
        ),

        'support_phone' => env(
            'SCHOOLPASS_LICENSE_SUPPORT_PHONE'
        ),

        'support_whatsapp' => env(
            'SCHOOLPASS_LICENSE_SUPPORT_WHATSAPP'
        ),
    ],

    'support' => [
        /*
         * Duración máxima de una sesión de soporte.
         */
        'impersonation_minutes' => (int) env(
            'SCHOOLPASS_SUPPORT_SESSION_MINUTES',
            60
        ),
    ],

];