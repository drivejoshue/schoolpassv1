<?php

namespace App\Http\Requests\Sysadmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreSchoolAdministratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'superadmin'
            && $this->user()?->school_id === null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => [
                'required',
                'email',
                'max:180',
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => [
                'required',
                Rule::in(['director', 'school_admin']),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ];
    }
}
