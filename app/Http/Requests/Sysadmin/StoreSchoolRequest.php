<?php

namespace App\Http\Requests\Sysadmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'superadmin'
            && $this->user()?->school_id === null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('schools', 'slug'),
            ],
            'status' => [
                'required',
                Rule::in(['active', 'suspended', 'cancelled']),
            ],
            'timezone' => ['required', 'timezone'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'primary_color' => [
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'secondary_color' => [
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:180'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'support_email' => ['nullable', 'email', 'max:180'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],

            'admin_name' => ['required', 'string', 'max:160'],
            'admin_email' => [
                'required',
                'email',
                'max:180',
                Rule::unique('users', 'email'),
            ],
            'admin_phone' => ['nullable', 'string', 'max:30'],
            'admin_role' => [
                'required',
                Rule::in(['director', 'school_admin']),
            ],
            'admin_password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ];
    }
}
