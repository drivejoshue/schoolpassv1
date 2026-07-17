<?php

namespace App\Http\Requests\SysAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolAppConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         * La autorización principal debe permanecer en el
         * middleware del grupo sysadmin.
         */
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $booleanFields = [
            'theme_allow_user_override',

            'remove_logo',
            'remove_welcome_image',

            'check_in_enabled',
            'check_out_enabled',
            'early_exit_enabled',
            'early_exit_requires_authorization',
            'observations_enabled',
            'temporary_passes_enabled',

            'qr_enabled',
            'nfc_enabled',
            'printed_credential_enabled',

            'notify_entry',
            'notify_exit',
            'notify_late',
            'notify_absence',
            'notify_early_exit',
            'notify_denied_access',

            'staff_qr_scan_enabled',
            'staff_manual_search_enabled',
            'staff_recent_access_enabled',
            'staff_show_student_photo',
            'staff_sound_enabled',
            'staff_vibration_enabled',

            'show_notices',
            'show_attendance_history',
            'show_digital_credential',
            'show_authorizations',
            'show_payments',
            'show_grades',
        ];

        $normalized = [];

        foreach ($booleanFields as $field) {
            $normalized[$field] =
                $this->boolean($field);
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $hexColor = [
            'required',
            'string',
            'regex:/^#[0-9A-Fa-f]{6}$/',
        ];

        return [
            /*
             * Identidad y branding.
             */
            'app_name' => [
                'required',
                'string',
                'max:80',
            ],

            'short_name' => [
                'required',
                'string',
                'max:30',
            ],

            'welcome_message' => [
                'nullable',
                'string',
                'max:180',
            ],

            'primary_color' => $hexColor,

            'secondary_color' => $hexColor,

            'accent_color' => $hexColor,

            'theme_default_mode' => [
                'required',
                'string',
                'in:system,light,dark',
            ],

            'theme_allow_user_override' => [
                'required',
                'boolean',
            ],

            'light_background_color' =>
                $hexColor,

            'light_surface_color' =>
                $hexColor,

            'light_on_surface_color' =>
                $hexColor,

            'dark_background_color' =>
                $hexColor,

            'dark_surface_color' =>
                $hexColor,

            'dark_on_surface_color' =>
                $hexColor,

            'logo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:4096',
            ],

            'remove_logo' => [
                'required',
                'boolean',
            ],

            'welcome_image' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:6144',
            ],

            'remove_welcome_image' => [
                'required',
                'boolean',
            ],

            /*
             * Soporte.
             */
            'support_email' => [
                'nullable',
                'email:rfc',
                'max:255',
            ],

            'support_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'support_whatsapp' => [
                'nullable',
                'string',
                'max:30',
            ],

            /*
             * Asistencia.
             */
            'check_in_enabled' => [
                'required',
                'boolean',
            ],

            'check_out_enabled' => [
                'required',
                'boolean',
            ],

            'late_tolerance_minutes' => [
                'required',
                'integer',
                'min:0',
                'max:180',
            ],

            'early_exit_enabled' => [
                'required',
                'boolean',
            ],

            'early_exit_requires_authorization' => [
                'required',
                'boolean',
            ],

            'observations_enabled' => [
                'required',
                'boolean',
            ],

            'temporary_passes_enabled' => [
                'required',
                'boolean',
            ],

            /*
             * Credenciales.
             */
            'qr_enabled' => [
                'required',
                'boolean',
            ],

            'qr_mode' => [
                'required',
                'string',
                'in:fixed,dynamic',
            ],

            'nfc_enabled' => [
                'required',
                'boolean',
            ],

            'printed_credential_enabled' => [
                'required',
                'boolean',
            ],

            'temporary_pass_minutes' => [
                'required',
                'integer',
                'min:5',
                'max:1440',
            ],

            /*
             * Notificaciones.
             */
            'notify_entry' => [
                'required',
                'boolean',
            ],

            'notify_exit' => [
                'required',
                'boolean',
            ],

            'notify_late' => [
                'required',
                'boolean',
            ],

            'notify_absence' => [
                'required',
                'boolean',
            ],

            'notify_early_exit' => [
                'required',
                'boolean',
            ],

            'notify_denied_access' => [
                'required',
                'boolean',
            ],

            /*
             * SchoolPass Staff.
             */
            'staff_qr_scan_enabled' => [
                'required',
                'boolean',
            ],

            'staff_manual_search_enabled' => [
                'required',
                'boolean',
            ],

            'staff_recent_access_enabled' => [
                'required',
                'boolean',
            ],

            'staff_show_student_photo' => [
                'required',
                'boolean',
            ],

            'staff_sound_enabled' => [
                'required',
                'boolean',
            ],

            'staff_vibration_enabled' => [
                'required',
                'boolean',
            ],

            'staff_auto_reset_seconds' => [
                'required',
                'integer',
                'min:1',
                'max:30',
            ],

            'staff_default_event_type' => [
                'required',
                'string',
                'in:entry,exit',
            ],

            'staff_camera_facing' => [
                'required',
                'string',
                'in:back,front',
            ],

            /*
             * Navegación.
             */
            'show_notices' => [
                'required',
                'boolean',
            ],

            'show_attendance_history' => [
                'required',
                'boolean',
            ],

            'show_digital_credential' => [
                'required',
                'boolean',
            ],

            'show_authorizations' => [
                'required',
                'boolean',
            ],

            'show_payments' => [
                'required',
                'boolean',
            ],

            'show_grades' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' =>
                'Este campo es obligatorio.',

            '*.boolean' =>
                'El valor enviado no es válido.',

            '*.integer' =>
                'Debe ingresar un número entero.',

            '*.regex' => (
                'El color debe tener formato hexadecimal, '
                .'por ejemplo #2563EB.'
            ),

            'app_name.max' =>
                'El nombre no puede exceder 80 caracteres.',

            'short_name.max' =>
                'El nombre corto no puede exceder 30 caracteres.',

            'support_email.email' =>
                'Ingrese un correo electrónico válido.',

            'logo.image' =>
                'El logotipo debe ser una imagen válida.',

            'logo.mimes' => (
                'El logotipo debe ser PNG, JPG, JPEG '
                .'o WebP.'
            ),

            'logo.max' =>
                'El logotipo no puede exceder 4 MB.',

            'welcome_image.image' => (
                'La imagen de bienvenida debe ser '
                .'una imagen válida.'
            ),

            'welcome_image.mimes' => (
                'La imagen de bienvenida debe ser PNG, '
                .'JPG, JPEG o WebP.'
            ),

            'welcome_image.max' => (
                'La imagen de bienvenida no puede '
                .'exceder 6 MB.'
            ),
        ];
    }

    public function attributes(): array
    {
        return [
            'app_name' =>
                'nombre mostrado',

            'short_name' =>
                'nombre corto',

            'welcome_message' =>
                'mensaje de bienvenida',

            'primary_color' =>
                'color principal',

            'secondary_color' =>
                'color secundario',

            'accent_color' =>
                'color de acento',

            'late_tolerance_minutes' =>
                'tolerancia de retardo',

            'temporary_pass_minutes' =>
                'duración del pase temporal',

            'staff_auto_reset_seconds' =>
                'tiempo de reinicio',
        ];
    }
}